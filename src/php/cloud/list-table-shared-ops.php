<?php
/**
 * Functions to perform snippet operations
 *
 * @package Code_Snippets
 */

namespace Code_Snippets\Cloud;

use function Code_Snippets\code_snippets;

/**
 * Display a hidden input field for a certain column and snippet value.
 *
 * @param string        $column_name Column name.
 * @param Cloud_Snippet $snippet     Column item.
 *
 * @return void
 */
function cloud_lts_display_column_hidden_input( string $column_name, Cloud_Snippet $snippet ) {
	printf(
		'<input id="cloud-snippet-%s-%s" class="cloud-snippet-item" type="hidden" name="%s" value="%s" />',
		esc_attr( $column_name ),
		esc_attr( $snippet->id ),
		esc_attr( $column_name ),
		esc_attr( $snippet->$column_name )
	);
}

/**
 * Display a hidden input field for a certain column and snippet value.
 *
 * @param string        $column_name Column name.
 * @param Cloud_Snippet $snippet     Column item.
 *
 * @return string HTML
 */
function cloud_lts_build_column_hidden_input( string $column_name, Cloud_Snippet $snippet ): string {
	return sprintf(
		'<input id="cloud-snippet-%s-%s" class="cloud-snippet-item" type="hidden" name="%s" value="%s" />',
		esc_attr( $column_name ),
		esc_attr( $snippet->id ),
		esc_attr( $column_name ),
		esc_attr( $snippet->$column_name )
	);
}

/**
 * Process the download snippet action
 *
 * @param string $action         Action - 'download' or 'update'.
 * @param string $source         Source - 'search' or 'cloud'.
 * @param string $snippet        Snippet ID.
 * @param int    $codevault_page The current page of the codevault.
 *
 * @return void
 */
function cloud_lts_process_download_action( string $action, string $source, string $snippet, int $codevault_page = 0 ) {
	if ( 'download' === $action || 'update' === $action ) {
		$result = code_snippets()->cloud_api->download_or_update_snippet( $snippet, $source, $action, $codevault_page );

		if ( $result['success'] ) {
			$redirect_uri = $result['snippet_id'] ?
				code_snippets()->get_snippet_edit_url( (int) $result['snippet_id'] ) :
				add_query_arg( 'result', $result['action'] );

			wp_safe_redirect( esc_url_raw( $redirect_uri ) );
			exit;
		}
	}
}

/**
 * Build action links for snippet.
 *
 * @param Cloud_Snippet $cloud_snippet Snippet/Column item.
 * @param string        $source        Source - 'search' or 'codevault'.
 *
 * @return string
 */
function cloud_lts_build_action_links( Cloud_Snippet $cloud_snippet, string $source ): string {
	$lang = Cloud_API::get_type_from_scope( $cloud_snippet->scope );
	$link = code_snippets()->cloud_api->get_link_for_cloud_snippet( $cloud_snippet );
	$additional_classes = 'search' === $source ? 'action-button-link' : '';
	$is_licensed = code_snippets()->licensing->is_licensed();
	$download = true;
	$action_link = '';

	if ( ! $is_licensed && in_array( $lang, [ 'css', 'js' ], true ) ) {
		$download = false;
	}

	if ( $link ) {
		if ( $is_licensed && $link->update_available ) {
			$download = false;
			$update_url = add_query_arg(
				[
					'action'  => 'update',
					'snippet' => $cloud_snippet->id,
					'source'  => $source,
				]
			);
			$action_link = sprintf(
				'<a class="cloud-snippet-update %s" href="%s">%s</a>',
				$additional_classes,
				esc_url( $update_url ),
				esc_html__( 'Update Available', 'code-snippets' )
			);
		} else {
			return sprintf(
				'<a href="%s" class="cloud-snippet-downloaded %s">%s</a>',
				esc_url( code_snippets()->get_snippet_edit_url( $link->local_id ) ),
				$additional_classes,
				esc_html__( 'View', 'code-snippets' )
			);
		}
	}

	if ( $download ) {
		$download_url = add_query_arg(
			[
				'action'  => 'download',
				'snippet' => $cloud_snippet->id,
				'source'  => $source,
			]
		);

		$action_link = $is_licensed ?
			sprintf(
				'<a class="cloud-snippet-download %s" href="%s">%s</a>',
				$additional_classes,
				esc_url( $download_url ),
				esc_html__( 'Download', 'code-snippets' )
			) :
			sprintf(
				'<a class="cloud-snippet-download %s" href="%s" target="_blank"><span class="go-pro-badge">%s</span>%s</a>',
				$additional_classes,
				'https://codesnippets.pro/pricing/',
				esc_html_x( 'Pro', 'pro only', 'code-snippets' ),
				esc_html_x( ' Only', 'pro only', 'code-snippets' )
			);
	}

	$thickbox_url = '#TB_inline?&width=700&height=500&inlineId=show-code-preview';

	$thickbox_link = sprintf(
		'<a href="%s" title="%s" class="cloud-snippet-preview cloud-snippet-preview-style thickbox %s" data-snippet="%s" data-lang="%s">%s</a>',
		esc_url( $thickbox_url ),
		esc_attr( $cloud_snippet->name ),
		$additional_classes,
		esc_attr( $cloud_snippet->id ),
		esc_attr( $lang ),
		esc_html__( 'Preview', 'code-snippets' )
	);

	return $action_link . $thickbox_link;
}

/**
 * Build the pagination functionality
 *
 * @param string $which       Context where the pagination will be displayed.
 * @param string $source      Source - 'search' or 'cloud'.
 * @param int    $total_items Total number of items.
 * @param int    $total_pages Total number of pages.
 * @param int    $pagenum     Current page number.
 *
 * @return array
 */
function cloud_lts_pagination( string $which, string $source, int $total_items, int $total_pages, int $pagenum ): array {
	/* translators: %s: Number of items. */
	$num = sprintf( _n( '%s item', '%s items', $total_items, 'code-snippets' ), number_format_i18n( $total_items ) );
	$output = '<span class="displaying-num">' . $num . '</span>';

	$current = isset( $_REQUEST['cloud_page'] ) ? (int) $_REQUEST['cloud_page'] : $pagenum;
	$current_url = remove_query_arg( wp_removable_query_args() ) . '#' . $source;

	$page_links = array();

	$html_current_page = '';
	$total_pages_before = '<span class="paging-input">';
	$total_pages_after = '</span></span>';

	$disable_first = false;
	$disable_last = false;
	$disable_prev = false;
	$disable_next = false;

	if ( 1 === $current ) {
		$disable_first = true;
		$disable_prev = true;
	}

	if ( $total_pages === $current ) {
		$disable_last = true;
		$disable_next = true;
	}

	if ( $disable_first ) {
		$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
	} else {
		$page_links[] = sprintf(
			'<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">&laquo;</span></a>',
			esc_url( remove_query_arg( $source . '_page', $current_url ) ),
			esc_html__( 'First page', 'code-snippets' )
		);
	}

	if ( $disable_prev ) {
		$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
	} else {
		$page_links[] = sprintf(
			'<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">&laquo;</span></a>',
			esc_url( add_query_arg( $source . '_page', max( 1, $current - 1 ), $current_url ) ),
			esc_html__( 'Previous page', 'code-snippets' )
		);
	}

	if ( 'bottom' === $which ) {
		$html_current_page = $current;
		$total_pages_before = sprintf( '<span class="screen-reader-text">%s</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">', __( 'Current page', 'code-snippets' ) );
	}

	if ( 'top' === $which ) {
		$html_current_page = sprintf(
			'<label for="current-page-selector" class="screen-reader-text">%s</label><input class="current-page-selector" id="current-page-selector" type="text" name="%s_page" value="%s" size="%d" aria-describedby="table-paging" /><span class="tablenav-paging-text">',
			__( 'Current page', 'code-snippets' ),
			$source,
			$current,
			strlen( $total_pages )
		);
	}

	$html_total_pages = sprintf( '<span class="total-pages">%s</span>', number_format_i18n( $total_pages ) );

	/* translators: 1: Current page, 2: Total pages. */
	$current_html = _x( '%1$s of %2$s', 'paging', 'code-snippets' );
	$page_links[] = $total_pages_before . sprintf( $current_html, $html_current_page, $html_total_pages ) . $total_pages_after;

	if ( $disable_next ) {
		$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
	} else {
		$page_links[] = sprintf(
			'<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
			esc_url( add_query_arg( $source . '_page', min( $total_pages, $current + 1 ), $current_url ) ),
			__( 'Next page' ),
			'&rsaquo;'
		);
	}

	if ( $disable_last ) {
		$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
	} else {
		$page_links[] = sprintf(
			'<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
			esc_url( add_query_arg( $source . '_page', $total_pages, $current_url ) ),
			__( 'Last page', 'code-snippets' ),
			'&raquo;'
		);
	}

	$pagination_links_class = 'pagination-links';
	if ( ! empty( $infinite_scroll ) ) {
		$pagination_links_class .= ' hide-if-js';
	}

	$output .= "\n<span class='$pagination_links_class'>" . implode( "\n", $page_links ) . '</span>';

	return [
		'output'     => $output,
		'page_class' => $total_pages ? ( $total_pages < 2 ? ' one-page' : '' ) : ' no-pages',
	];
}
