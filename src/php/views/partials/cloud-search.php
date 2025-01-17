<?php
/**
 * HTML for the cloud search tab
 *
 * @package    Code_Snippets
 * @subpackage Views
 */

namespace Code_Snippets;

/**
 * Loaded from manage menu.
 *
 * @var Manage_Menu $this
 */

$search_query = isset( $_REQUEST['cloud_search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cloud_search'] ) ) : '';
$cloud_select = sanitize_key( wp_unslash( $_REQUEST['cloud_select'] ?? '' ) );

?>

<p class="cloud-search-info">
	<?php esc_html_e( 'Use the search bar below to search cloud snippets by entering either the name of a codevault or keywords.', 'code-snippets' ); ?>

	<small>
		<?php esc_html_e( '(Note: codevault name is case and spelling sensitive and only public snippets will be shown)', 'code-snippets' ); ?>
	</small>
</p>

<form method="get" action="" id="cloud-search-form">
	<?php List_Table::required_form_fields( 'search_box' ); ?>
	<label class="screen-reader-text" for="cloud_search">
		<?php esc_html_e( 'Search cloud snippets', 'code-snippets' ); ?>
	</label>
	<?php
	if ( isset( $_REQUEST['type'] ) ) {
		printf( '<input type="hidden" name="type" value="%s">', esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) ) );
	}
	?>
	<div class="heading-box">
		<p class="cloud-search-heading">
			<label for="cloud-select-prepend"><?php esc_html_e( 'Search Cloud', 'code-snippets' ); ?></label>
		</p>
	</div>
	<div class="input-group">
		<select id="cloud-select-prepend" class="select-prepend" name="cloud_select">
			<option value="term"<?php selected( $cloud_select, 'term' ); ?>>
				<?php esc_html_e( 'Search by keyword(s)', 'code-snippets' ); ?>
			</option>
			<option value="codevault"<?php selected( $cloud_select, 'codevault' ); ?>>
				<?php esc_html_e( 'Name of codevault', 'code-snippets' ); ?>
			</option>
		</select>
		<input type="text" id="cloud_search" name="cloud_search" class="cloud_search"
		       value="<?php echo esc_html( $search_query ); ?>"
		       placeholder="<?php esc_html_e( 'e.g. Remove unused javascript…', 'code-snippets' ); ?>">

		<button type="submit" id="cloud-search-submit" class="button">
			<?php esc_html_e( 'Search Cloud', 'code-snippets' ); ?>
			<span class="dashicons dashicons-search cloud-search"></span>
		</button>
	</div>
</form>
<form method="post" action="" id="cloud-search-results">
	<input type="hidden"
	       id="code_snippets_ajax_nonce"
	       value="<?php echo esc_attr( wp_create_nonce( 'code_snippets_manage_ajax' ) ); ?>">
	<?php
	List_Table::required_form_fields();

	if ( $search_query ) {
		$this->cloud_search_list_table->display();
	}

	?>
</form>
