<?php

namespace Code_Snippets;

use Code_Snippets\Cloud\Cloud_Snippet;
use function Code_Snippets\Settings\get_setting;
use WP_List_Table;

/**
 * Contains the class for handling the snippets table
 *
 * @package Code_Snippets
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 */

/* The WP_List_Table base class is not included by default, so we need to load it */
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * This class handles the table for the manage snippets menu
 *
 * @since   1.5
 * @package Code_Snippets
 */
class List_Table extends WP_List_Table {

	/**
	 * Whether the current screen is in the network admin
	 *
	 * @var bool
	 */
	public $is_network;

	/**
	 * A list of statuses (views)
	 *
	 * @var array<string>
	 */
	public $statuses = array( 'all', 'active', 'inactive', 'recently_activated' );

	/**
	 * Column name to use when ordering the snippets list.
	 *
	 * @var string
	 */
	protected $order_by;

	/**
	 * Direction to use when ordering the snippets list. Either 'asc' or 'desc'.
	 *
	 * @var string
	 */
	protected $order_dir;

	/**
	 * Array of local snippets synced to the cloud using the cloud link transient
	 *
	 * @var array
	 */
	protected $cloud_synced_snippets;

	/**
	 * The constructor function for our class.
	 * Registers hooks, initializes variables, setups class.
	 *
	 * @phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
	 */
	public function __construct() {
		global $status, $page;
		$this->is_network = is_network_admin();

		/* Determine the status */
		$status = apply_filters( 'code_snippets/list_table/default_view', 'all' );
		if ( isset( $_REQUEST['status'] ) && in_array( sanitize_key( $_REQUEST['status'] ), $this->statuses, true ) ) {
			$status = sanitize_key( $_REQUEST['status'] );
		}

		/* Add the search query to the URL */
		if ( isset( $_REQUEST['s'] ) ) {
			$_SERVER['REQUEST_URI'] = add_query_arg( 's', sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) );
		}

		/* Add a snippets per page screen option */
		$page = $this->get_pagenum();

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Snippets per page', 'code-snippets' ),
				'default' => 999,
				'option'  => 'snippets_per_page',
			)
		);

		add_filter( 'default_hidden_columns', array( $this, 'default_hidden_columns' ) );

		/* Strip the result query arg from the URL */
		$_SERVER['REQUEST_URI'] = remove_query_arg( 'result' );

		/* Add filters to format the snippet description in the same way the post content is formatted */
		$filters = [ 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'shortcode_unautop', 'capital_P_dangit', [ $this, 'wp_kses_desc' ] ];
		foreach ( $filters as $filter ) {
			add_filter( 'code_snippets/list_table/column_description', $filter );
		}

		/* Set up the class */
		parent::__construct(
			array(
				'ajax'     => true,
				'plural'   => 'snippets',
				'singular' => 'snippet',
			)
		);
	}

	/**
	 * Apply a more permissive version of wp_kses_post() to the snippet description.
	 *
	 * @param string $data Description content to filter.
	 *
	 * @return string Filtered description content with allowed HTML tags and attributes intact.
	 */
	public function wp_kses_desc( $data ) {
		$safe_style_filter = function ( $styles ) {
			$styles[] = 'display';
			return $styles;
		};

		add_filter( 'safe_style_css', $safe_style_filter );
		$data = wp_kses_post( $data );
		remove_filter( 'safe_style_css', $safe_style_filter );

		return $data;
	}

	/**
	 * Set the 'id' column as hidden by default.
	 *
	 * @param array<string> $hidden List of hidden columns.
	 *
	 * @return array<string> Modified list of hidden columns.
	 */
	public function default_hidden_columns( $hidden ) {
		array_push( $hidden, 'id', 'code', 'cloud_id', 'revision' );
		return $hidden;
	}

	/**
	 * Set the 'name' column as the primary column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

	/**
	 * Define the output of all columns that have no callback function
	 *
	 * @param Snippet $item        The snippet used for the current row.
	 * @param string  $column_name The name of the column being printed.
	 *
	 * @return string The content of the column to output.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return $item->id;

			case 'description':
				return apply_filters( 'code_snippets/list_table/column_description', $item->desc );

			case 'type':
				$type = $item->type;
				return sprintf(
					'<a class="snippet-type-badge" href="%s" data-type="%s">%s</a>',
					esc_url( add_query_arg( 'type', $type ) ),
					esc_attr( $type ),
					esc_html( $type )
				);

			case 'date':
				return $item->modified ? $item->format_modified() : '&#8212;';

			default:
				return apply_filters( "code_snippets/list_table/column_$column_name", '&#8212;', $item );
		}
	}

	/**
	 * Retrieve a URL to perform an action on a snippet
	 *
	 * @param string  $action  Name of action to produce a link for.
	 * @param Snippet $snippet Snippet object to produce link for.
	 *
	 * @return string URL to perform action.
	 */
	public function get_action_link( $action, $snippet ) {

		// Redirect actions to the network dashboard for shared network snippets.
		$local_actions = array( 'activate', 'activate-shared', 'run-once', 'run-once-shared' );
		$network_redirect = $snippet->shared_network && ! $this->is_network && ! in_array( $action, $local_actions, true );

		// Edit links go to a different menu.
		if ( 'edit' === $action ) {
			return code_snippets()->get_snippet_edit_url( $snippet->id, $network_redirect ? 'network' : 'self' );
		}

		$query_args = array(
			'action' => $action,
			'id'     => $snippet->id,
			'scope'  => $snippet->scope,
		);

		$url = $network_redirect ?
			add_query_arg( $query_args, code_snippets()->get_menu_url( 'manage', 'network' ) ) :
			add_query_arg( $query_args );

		// Add a nonce to the URL for security purposes.
		return wp_nonce_url( $url, 'code_snippets_manage_snippet_' . $snippet->id );
	}

	/**
	 * Build a list of action links for individual snippets
	 *
	 * @param Snippet $snippet The current snippet.
	 *
	 * @return array<string, string> The action links HTML.
	 */
	private function get_snippet_action_links( Snippet $snippet ) {
		$actions = array();

		if ( ! $this->is_network && $snippet->network && ! $snippet->shared_network ) {
			// Display special links if on a subsite and dealing with a network-active snippet.
			if ( $snippet->active ) {
				$actions['network_active'] = esc_html__( 'Network Active', 'code-snippets' );
			} else {
				$actions['network_only'] = esc_html__( 'Network Only', 'code-snippets' );
			}
		} elseif ( ! $snippet->shared_network || current_user_can( code_snippets()->get_network_cap_name() ) ) {

			// If the snippet is a shared network snippet, only display extra actions if the user has network permissions.
			$simple_actions = array(
				'edit'   => esc_html__( 'Edit', 'code-snippets' ),
				'clone'  => esc_html__( 'Clone', 'code-snippets' ),
				'export' => esc_html__( 'Export', 'code-snippets' ),
				'cloud'  => esc_html__( 'Sync to Codevault', 'code-snippets' ),
			);

			foreach ( $simple_actions as $action => $label ) {
				$actions[ $action ] = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_action_link( $action, $snippet ) ), $label );
			}

			//Check there is a valid cloud connection or show a link to set it up
			if( $this->is_cloud_link_valid() ){
				//Get the cloud link object
				$cloud_link = code_snippets()->cloud_api->get_cloud_link( $snippet->id, 'local' );
				//Check this snippet is linked or orignated from the cloud
				if ( $cloud_link ) {
					//Check if the snippet is in the users codevault 
					if ( $cloud_link->in_codevault) {
						$actions['cloud'] = sprintf( '<a>%s</a>', 'Synced' );
						//Check if an update is available only in users codevault
						if ( $cloud_link->update_available ) {
							$actions['cloud_update'] = sprintf(
								'<a href="%s#updated-code">%s</a>',
								esc_url( $this->get_action_link( 'edit', $snippet ) ),
								esc_html__( 'Cloud Update', 'code-snippets' )
							);
						}
					}	
				}
			}else{
				$actions['cloud'] = sprintf( '<a href="%s">Set up Cloud</a>', esc_url( add_query_arg( 'section', 'cloud', code_snippets()->get_menu_url( 'settings' ) ) ));
			}

			$actions['delete'] = sprintf(
				'<a href="%2$s" class="delete" onclick="%3$s">%1$s</a>',
				esc_html__( 'Delete', 'code-snippets' ),
				esc_url( $this->get_action_link( 'delete', $snippet ) ),
				esc_js(
					sprintf(
						'return confirm("%s");',
						esc_html__( 'You are about to permanently delete the selected item.', 'code-snippets' ) . "\n" .
						esc_html__( "'Cancel' to stop, 'OK' to delete.", 'code-snippets' )
					)
				)
			);
		}

		return apply_filters( 'code_snippets/list_table/row_actions', $actions, $snippet );
	}

	/**
	 * Retrieve the code for a snippet activation switch
	 *
	 * @param Snippet $snippet Snippet object.
	 *
	 * @return string Output for activation switch.
	 */
	protected function column_activate( $snippet ) {

		if ( $this->is_network && $snippet->shared_network || ( ! $this->is_network && $snippet->network && ! $snippet ) ) {
			return '';
		}

		if ( 'single-use' === $snippet->scope ) {
			$class = 'snippet-execution-button';
			$action = 'run-once';
			$label = esc_html__( 'Run Once', 'code-snippets' );
		} else {
			$class = 'snippet-activation-switch';
			$action = $snippet->active ? 'deactivate' : 'activate';
			$label = $snippet->network && ! $snippet->shared_network ?
				( $snippet->active ? __( 'Network Deactivate', 'code-snippets' ) : __( 'Network Activate', 'code-snippets' ) ) :
				( $snippet->active ? __( 'Deactivate', 'code-snippets' ) : __( 'Activate', 'code-snippets' ) );
		}

		if ( $snippet->shared_network ) {
			$action .= '-shared';
		}

		return sprintf(
			'<a class="%s" href="%s" title="%s">&nbsp;</a> ',
			esc_attr( $class ),
			esc_url( $this->get_action_link( $action, $snippet ) ),
			esc_attr( $label )
		);
	}

	/**
	 * Build the content of the snippet name column
	 *
	 * @param Snippet $snippet The snippet being used for the current row.
	 *
	 * @return string The content of the column to output.
	 */
	protected function column_name( $snippet ) {
		$row_actions = $this->row_actions(
			$this->get_snippet_action_links( $snippet ),
			apply_filters( 'code_snippets/list_table/row_actions_always_visible', true )
		);

		$out = esc_html( $snippet->display_name );

		if ( 'global' !== $snippet->scope ) {
			$out .= sprintf( ' <span class="dashicons dashicons-%s"></span>', $snippet->scope_icon );
		}

		// Add a link to the snippet if it isn't an unreadable network-only snippet.
		if ( $this->is_network || ! $snippet->network || current_user_can( code_snippets()->get_network_cap_name() ) ) {
			$out = sprintf(
				'<a href="%s" class="snippet-name">%s</a>',
				esc_attr( code_snippets()->get_snippet_edit_url( $snippet->id, $snippet->network ? 'network' : 'admin' ) ),
				$out
			);
		}

		if ( $snippet->shared_network ) {
			$out .= ' <span class="badge">' . esc_html__( 'Shared on Network', 'code-snippets' ) . '</span>';
		}

		if( $this->is_cloud_link_valid() ){
			$cloud_link = code_snippets()->cloud_api->get_cloud_link( $snippet->id, 'local' );
			if ( $cloud_link ) {
				//If update available make cloud icon orange?
				if ( $cloud_link->update_available ) {
					$out = '<span class="dashicons dashicons-cloud cloud-icon cloud-update"></span>' . $out;
				}elseif ( $cloud_link->in_codevault) { 
					//If snippet in codevaule and no update available make cloud icon blue
					$out = '<span class="dashicons dashicons-cloud cloud-icon cloud-synced"></span>' . $out;
				}else{
					//Make cloud icon grey to show its from the cloud
					$out = '<span class="dashicons dashicons-cloud cloud-icon cloud-downloaded"></span>' . $out;
				}
			}
		}

		$out = apply_filters( 'code_snippets/list_table/column_name', $out, $snippet );
		return $out . $row_actions;
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @param Snippet $item The snippet being used for the current row.
	 *
	 * @return string The column content to be printed.
	 */
	protected function column_cb( $item ) {

		if ( $item instanceof Cloud_Snippet ) {
			$checkbox_name = 'cloud_ids';
		} else {
			$checkbox_name = $item->shared_network ? 'shared_ids' : 'ids';
		}
		$out = sprintf(
			'<input type="checkbox" name="%s[]" value="%s">',
			$checkbox_name,
			$item->id
		);

		return apply_filters( 'code_snippets/list_table/column_cb', $out, $item );
	}

	/**
	 * Handles the tags column output.
	 *
	 * @param Snippet $snippet The snippet being used for the current row.
	 *
	 * @return string The column output.
	 */
	protected function column_tags( $snippet ) {

		// Return now if there are no tags.
		if ( empty( $snippet->tags ) ) {
			return '';
		}

		$out = array();

		/* Loop through the tags and create a link for each one */
		foreach ( $snippet->tags as $tag ) {
			$out[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( 'tag', esc_attr( $tag ) ) ),
				esc_html( $tag )
			);
		}

		return join( ', ', $out );
	}

	/**
	 * Handles the priority column output.
	 *
	 * @param Snippet $snippet The snippet being used for the current row.
	 *
	 * @return string The column output.
	 */
	protected function column_priority( $snippet ) {
		return sprintf( '<input type="number" class="snippet-priority" value="%d" step="1" disabled>', $snippet->priority );
	}

	/**
	 * Define the column headers for the table
	 *
	 * @return array<string, string> The column headers, ID paired with label
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox">',
			'activate'    => '',
			'name'        => __( 'Name', 'code-snippets' ),
			'type'        => __( 'Type', 'code-snippets' ),
			'description' => __( 'Description', 'code-snippets' ),
			'tags'        => __( 'Tags', 'code-snippets' ),
			'date'        => __( 'Modified', 'code-snippets' ),
			'priority'    => __( 'Priority', 'code-snippets' ),
			'id'          => __( 'ID', 'code-snippets' ),
		);

		if ( isset( $_GET['type'] ) && 'all' !== $_GET['type'] ) {
			unset( $columns['type'] );
		}

		if ( ! get_setting( 'general', 'enable_description' ) ) {
			unset( $columns['description'] );
		}

		if ( ! get_setting( 'general', 'enable_tags' ) ) {
			unset( $columns['tags'] );
		}

		return apply_filters( 'code_snippets/list_table/columns', $columns );
	}

	/**
	 * Define the columns that can be sorted. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending.
	 *
	 * @return array<string, string|array<string|bool>> The IDs of the columns that can be sorted
	 */
	public function get_sortable_columns() {

		$sortable_columns = array(
			'id'       => array( 'id', true ),
			'name'     => 'name',
			'type'     => array( 'type', true ),
			'date'     => array( 'modified', true ),
			'priority' => array( 'priority', true ),
		);

		return apply_filters( 'code_snippets/list_table/sortable_columns', $sortable_columns );
	}

	/**
	 * Define the bulk actions to include in the drop-down menus
	 *
	 * @return array<string, string> An array of menu items with the ID paired to the label
	 */
	public function get_bulk_actions() {
		$actions = array(
			'activate-selected'   => $this->is_network ? __( 'Network Activate', 'code-snippets' ) : __( 'Activate', 'code-snippets' ),
			'deactivate-selected' => $this->is_network ? __( 'Network Deactivate', 'code-snippets' ) : __( 'Deactivate', 'code-snippets' ),
			'clone-selected'      => __( 'Clone', 'code-snippets' ),
			'download-selected'   => __( 'Download', 'code-snippets' ),
			'export-selected'     => __( 'Export', 'code-snippets' ),
			'delete-selected'     => __( 'Delete', 'code-snippets' ),
			'sync-selected'       => __( 'Cloud Sync', 'code-snippets' ),
		);

		return apply_filters( 'code_snippets/list_table/bulk_actions', $actions );
	}

	/**
	 * Retrieve the classes for the table
	 *
	 * We override this in order to add 'snippets' as a class for custom styling
	 *
	 * @return array<string> The classes to include on the table element
	 */
	public function get_table_classes() {
		$classes = array( 'widefat', $this->_args['plural'] );

		return apply_filters( 'code_snippets/list_table/table_classes', $classes );
	}

	/**
	 * Retrieve the 'views' of the table
	 *
	 * Example: active, inactive, recently active
	 *
	 * @return array<string, string> A list of the view labels linked to the view
	 */
	public function get_views() {
		global $totals, $status;
		$status_links = array();

		/* Loop through the view counts */
		foreach ( $totals as $type => $count ) {

			/* Don't show the view if there is no count */
			if ( ! $count ) {
				continue;
			}

			/* Define the labels for each view */
			$labels = array();

			/* translators: %s: total number of snippets */
			$labels['all'] = _n(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$count,
				'code-snippets'
			);

			/* translators: %s: total number of active snippets */
			$labels['active'] = _n(
				'Active <span class="count">(%s)</span>',
				'Active <span class="count">(%s)</span>',
				$count,
				'code-snippets'
			);

			/* translators: %s: total number of inactive snippets */
			$labels['inactive'] = _n(
				'Inactive <span class="count">(%s)</span>',
				'Inactive <span class="count">(%s)</span>',
				$count,
				'code-snippets'
			);

			/* translators: %s: total number of recently activated snippets */
			$labels['recently_activated'] = _n(
				'Recently Active <span class="count">(%s)</span>',
				'Recently Active <span class="count">(%s)</span>',
				$count,
				'code-snippets'
			);

			/* The page URL with the status parameter */
			$url = esc_url( add_query_arg( 'status', $type ) );

			/* Add a class if this view is currently being viewed */
			$class = $type === $status ? ' class="current"' : '';

			/* Add the view count to the label */
			$text = sprintf( $labels[ $type ], number_format_i18n( $count ) );

			/* Construct the link */
			$status_links[ $type ] = sprintf( '<a href="%s"%s>%s</a>', $url, $class, $text );
		}

		/* Filter and return the list of views */

		return apply_filters( 'code_snippets/list_table/views', $status_links );
	}

	/**
	 * Gets the tags of the snippets currently being viewed in the table
	 *
	 * @since 2.0
	 */
	public function get_current_tags() {
		global $snippets, $status;

		/* If we're not viewing a snippets table, get all used tags instead */
		if ( ! isset( $snippets, $status ) ) {
			$tags = get_all_snippet_tags();
		} else {
			$tags = array();

			/* Merge all tags into a single array */
			foreach ( $snippets[ $status ] as $snippet ) {
				$tags = array_merge( $snippet->tags, $tags );
			}

			/* Remove duplicate tags */
			$tags = array_unique( $tags );
		}

		sort( $tags );

		return $tags;
	}

	/**
	 * Add filters and extra actions above and below the table
	 *
	 * @param string $which Whether the actions are displayed on the before (true) or after (false) the table.
	 */
	public function extra_tablenav( $which ) {
		/**
		 * Status global.
		 *
		 * @var string $status
		 */
		global $status;

		if ( 'top' === $which ) {

			/* Tags dropdown filter */
			$tags = $this->get_current_tags();

			if ( count( $tags ) ) {
				$query = isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( $_GET['tag'] ) ) : '';

				echo '<div class="alignleft actions">';
				echo '<select name="tag">';

				printf(
					"<option %s value=''>%s</option>\n",
					selected( $query, '', false ),
					esc_html__( 'Show all tags', 'code-snippets' )
				);

				foreach ( $tags as $tag ) {

					printf(
						"<option %s value='%s'>%s</option>\n",
						selected( $query, $tag, false ),
						esc_attr( $tag ),
						esc_html( $tag )
					);
				}

				echo '</select>';

				submit_button( __( 'Filter', 'code-snippets' ), 'button', 'filter_action', false );
				echo '</div>';
			}
		}

		echo '<div class="alignleft actions">';

		if ( 'recently_activated' === $status ) {
			submit_button( __( 'Clear List', 'code-snippets' ), 'secondary', 'clear-recent-list', false );
		}

		do_action( 'code_snippets/list_table/actions', $which );

		echo '</div>';
	}

	/**
	 * Output form fields needed to preserve important
	 * query vars over form submissions
	 *
	 * @param string $context The context in which the fields are being outputted.
	 */
	public static function required_form_fields( $context = 'main' ) {

		$vars = apply_filters(
			'code_snippets/list_table/required_form_fields',
			array( 'page', 's', 'status', 'paged', 'tag' ),
			$context
		);

		if ( 'search_box' === $context ) {
			/* Remove the 's' var if we're doing this for the search box */
			$vars = array_diff( $vars, array( 's' ) );
		}

		foreach ( $vars as $var ) {
			if ( ! empty( $_REQUEST[ $var ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_REQUEST[ $var ] ) );
				printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $var ), esc_attr( $value ) );
				print "\n";
			}
		}

		do_action( 'code_snippets/list_table/print_required_form_fields', $context );
	}

	/**
	 * Perform an action on a single snippet.
	 *
	 * @param int    $id     Snippet ID.
	 * @param string $action Action to perform.
	 * @param string $scope  Snippet scope; used for cache busting CSS and JS snippets.
	 *
	 * @return bool|string Result of performing action
	 * @uses activate_snippet() to activate snippets
	 * @uses deactivate_snippet() to deactivate snippets
	 * @uses delete_snippet() to delete snippets
	 */
	private function perform_action( $id, $action, $scope = '' ) {

		if ( in_array( $action, array( 'activate', 'deactivate', 'activate-shared', 'deactivate-shared', 'delete' ), true ) &&
		     ( '-js' === substr( $scope, -3 ) || '-css' === substr( $scope, -4 ) ) ) {
			code_snippets()->active_snippets->increment_rev( $scope, $this->is_network );
		}

		switch ( $action ) {

			case 'activate':
				activate_snippet( $id, $this->is_network );
				return 'activated';

			case 'deactivate':
				deactivate_snippet( $id, $this->is_network );
				return 'deactivated';

			case 'run-once':
				$this->perform_action( $id, 'activate' );
				return 'executed';

			case 'run-once-shared':
				$this->perform_action( $id, 'activate-shared' );
				return 'executed';

			case 'activate-shared':
				$active_shared_snippets = get_option( 'active_shared_network_snippets', array() );

				if ( ! in_array( $id, $active_shared_snippets, true ) ) {
					$active_shared_snippets[] = $id;
					update_option( 'active_shared_network_snippets', $active_shared_snippets );
					clean_active_snippets_cache( code_snippets()->db->ms_table );
				}

				return 'activated';

			case 'deactivate-shared':
				$active_shared_snippets = get_option( 'active_shared_network_snippets', array() );
				update_option( 'active_shared_network_snippets', array_diff( $active_shared_snippets, array( $id ) ) );
				clean_active_snippets_cache( code_snippets()->db->ms_table );
				return 'deactivated';

			case 'clone':
				$this->clone_snippets( array( $id ) );
				return 'cloned';

			case 'delete':
				delete_snippet( $id, $this->is_network );
				return 'deleted';

			case 'export':
				$export = new Export_Attachment( $id );
				$export->download_snippets_json();
				break;

			case 'download':
				$export = new Export_Attachment( $id );
				$export->download_snippets_code();
				break;

			case 'cloud':
				$this->sync_to_cloud( array( $id ) );
				return 'synced';
		}

		return false;
	}

	/**
	 * Processes actions requested by the user.
	 */
	public function process_requested_actions() {

		/* Clear the recent snippets list if requested to do so */
		if ( isset( $_POST['clear-recent-list'] ) ) {
			check_admin_referer( 'bulk-' . $this->_args['plural'] );

			if ( $this->is_network ) {
				update_site_option( 'recently_activated_snippets', array() );
			} else {
				update_option( 'recently_activated_snippets', array() );
			}
		}

		/* Check if there are any single snippet actions to perform */
		if ( isset( $_GET['action'], $_GET['id'] ) ) {
			$id = absint( $_GET['id'] );
			$scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';

			/* Verify they were sent from a trusted source */
			$nonce_action = 'code_snippets_manage_snippet_' . $id;
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
				wp_nonce_ays( $nonce_action );
			}

			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'action', 'id', 'scope', '_wpnonce' ) );

			/* If so, then perform the requested action and inform the user of the result */
			$result = $this->perform_action( $id, sanitize_key( $_GET['action'] ), $scope );

			if ( $result ) {
				wp_safe_redirect( esc_url_raw( add_query_arg( 'result', $result ) ) );
				exit;
			}
		}

		/* Only continue from this point if there are bulk actions to process */
		if ( ! isset( $_POST['ids'] ) && ! isset( $_POST['shared_ids'] ) ) {
			return;
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
		$_SERVER['REQUEST_URI'] = remove_query_arg( 'action' );

		switch ( $this->current_action() ) {

			case 'activate-selected':
				activate_snippets( $ids );

				/* Process the shared network snippets */
				if ( isset( $_POST['shared_ids'] ) && is_multisite() && ! $this->is_network ) {
					$active_shared_snippets = get_option( 'active_shared_network_snippets', array() );

					foreach ( array_map( 'intval', $_POST['shared_ids'] ) as $id ) {
						if ( ! in_array( $id, $active_shared_snippets, true ) ) {
							$active_shared_snippets[] = $id;
						}
					}

					update_option( 'active_shared_network_snippets', $active_shared_snippets );
					clean_active_snippets_cache( code_snippets()->db->ms_table );
				}

				$result = 'activated-multi';
				break;

			case 'deactivate-selected':
				foreach ( $ids as $id ) {
					deactivate_snippet( $id, $this->is_network );
				}

				/* Process the shared network snippets */
				if ( isset( $_POST['shared_ids'] ) && is_multisite() && ! $this->is_network ) {
					$active_shared_snippets = get_option( 'active_shared_network_snippets', array() );
					$active_shared_snippets = ( '' === $active_shared_snippets ) ? array() : $active_shared_snippets;
					$active_shared_snippets = array_diff( $active_shared_snippets, array_map( 'intval', $_POST['shared_ids'] ) );
					update_option( 'active_shared_network_snippets', $active_shared_snippets );
					clean_active_snippets_cache( code_snippets()->db->ms_table );
				}

				$result = 'deactivated-multi';
				break;

			case 'export-selected':
				$export = new Export_Attachment( $ids );
				$export->download_snippets_json();
				break;

			case 'download-selected':
				$export = new Export_Attachment( $ids );
				$export->download_snippets_code();
				break;

			case 'clone-selected':
				$this->clone_snippets( $ids );
				$result = 'cloned-multi';
				break;

			case 'delete-selected':
				foreach ( $ids as $id ) {
					delete_snippet( $id, $this->is_network );
				}
				$result = 'deleted-multi';
				break;
			
			case 'sync-selected':
				$this->sync_to_cloud( $ids );
				$result = 'synced-multi';
				break;
		}

		if ( isset( $result ) ) {

			// We have no way of knowing whether CSS/JS snippets were modified or not, so always increment the asset revisions.
			if ( in_array( $result, array( 'activated-multi', 'deactivated-multi', 'deleted-multi' ), true ) ) {
				code_snippets()->active_snippets->increment_rev( 'all', $this->is_network );
			}

			wp_safe_redirect( esc_url_raw( add_query_arg( 'result', $result ) ) );
			exit;
		}
	}

	/**
	 * Message to display if no snippets are found
	 */
	public function no_items() {

		if ( ! empty( $GLOBALS['s'] ) || ! empty( $_GET['tag'] ) ) {
			esc_html_e( 'No snippets were found matching the current search query. Please enter a new query or use the "Clear Filters" button above.', 'code-snippets' );

		} else {
			$add_url = code_snippets()->get_menu_url( 'add' );

			if ( empty( $_GET['type'] ) ) {
				esc_html_e( "It looks like you don't have any snippets.", 'code-snippets' );
			} else {
				esc_html_e( "It looks like you don't have any snippets of this type.", 'code-snippets' );
				$add_url = add_query_arg( 'type', sanitize_key( wp_unslash( $_GET['type'] ) ), $add_url );
			}

			printf(
				' <a href="%s">%s</a>',
				esc_url( $add_url ),
				esc_html__( 'Perhaps you would like to add a new one?', 'code-snippets' )
			);
		}

	}

	/**
	 * Fetch all shared network snippets for the current site
	 */
	private function fetch_shared_network_snippets() {
		global $snippets;
		$ids = get_site_option( 'shared_network_snippets' );

		if ( ! is_multisite() || ! $ids ) {
			return;
		}

		if ( $this->is_network ) {
			$limit = count( $snippets['all'] );

			for ( $i = 0; $i < $limit; $i++ ) {
				/** Snippet @var Snippet $snippet */
				$snippet = &$snippets['all'][ $i ];

				if ( in_array( $snippet->id, $ids, true ) ) {
					$snippet->shared_network = true;
					$snippet->tags = array_merge( $snippet->tags, array( 'shared on network' ) );
					$snippet->active = false;
				}
			}
		} else {
			$active_shared_snippets = get_option( 'active_shared_network_snippets', array() );
			$shared_snippets = get_snippets( $ids, true );

			foreach ( $shared_snippets as $snippet ) {
				$snippet->shared_network = true;
				$snippet->tags = array_merge( $snippet->tags, array( 'shared on network' ) );
				$snippet->active = in_array( $snippet->id, $active_shared_snippets, true );
			}

			$snippets['all'] = array_merge( $snippets['all'], $shared_snippets );
		}
	}

	/**
	 * Prepares the items to later display in the table.
	 * Should run before any headers are sent.
	 *
	 * @phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	 */
	public function prepare_items() {
		global $status, $snippets, $totals, $s;

		wp_reset_vars( array( 'orderby', 'order', 's' ) );

		/* Redirect tag filter from POST to GET */
		if ( isset( $_POST['filter_action'] ) ) {
			$location = empty( $_POST['tag'] ) ?
				remove_query_arg( 'tag' ) :
				add_query_arg( 'tag', sanitize_text_field( wp_unslash( $_POST['tag'] ) ) );
			wp_safe_redirect( esc_url_raw( $location ) );
			exit;
		}

		/* First, lets process the submitted actions */
		$this->process_requested_actions();

		/* Initialize the $snippets array */
		$snippets = array_fill_keys( $this->statuses, array() );

		/* Fetch all snippets */
		$snippets['all'] = apply_filters( 'code_snippets/list_table/get_snippets', get_snippets() );
		$this->fetch_shared_network_snippets();

		/* Filter snippets by type */
		if ( isset( $_GET['type'] ) && 'all' !== $_GET['type'] ) {
			$snippets['all'] = array_filter(
				$snippets['all'],
				function ( Snippet $snippet ) {
					return $_GET['type'] === $snippet->type;
				}
			);

		}

		/* Add scope tags */
		/** Snippet @var Snippet $snippet */
		foreach ( $snippets['all'] as $snippet ) {
			if ( 'global' !== $snippet->scope ) {
				$snippet->add_tag( $snippet->scope );
			}
		}

		/* Filter snippets by tag */
		if ( ! empty( $_GET['tag'] ) ) {
			$snippets['all'] = array_filter( $snippets['all'], array( $this, 'tags_filter_callback' ) );
		}

		/* Filter snippets based on search query */
		if ( $s ) {
			$snippets['all'] = array_filter( $snippets['all'], array( $this, 'search_by_line_callback' ) );
		}
		
		/* Clear recently activated snippets older than a week */
		$recently_activated = $this->is_network ?
			get_site_option( 'recently_activated_snippets', array() ) :
			get_option( 'recently_activated_snippets', array() );

		foreach ( $recently_activated as $key => $time ) {

			if ( $time + WEEK_IN_SECONDS < time() ) {
				unset( $recently_activated[ $key ] );
			}
		}

		$this->is_network ?
			update_site_option( 'recently_activated_snippets', $recently_activated ) :
			update_option( 'recently_activated_snippets', $recently_activated );

		/**
		 * Filter snippets into individual sections
		 *
		 * @var Snippet $snippet
		 */
		foreach ( $snippets['all'] as $snippet ) {

			if ( $snippet->active ) {
				$snippets['active'][] = $snippet;
			} else {
				$snippets['inactive'][] = $snippet;

				/* Was the snippet recently deactivated? */
				if ( isset( $recently_activated[ $snippet->id ] ) ) {
					$snippets['recently_activated'][] = $snippet;
				}
			}
		}

		/* Count the totals for each section */
		$totals = array();
		foreach ( $snippets as $type => $list ) {
			$totals[ $type ] = count( $list );
		}

		/* If the current status is empty, default tp all */
		if ( empty( $snippets[ $status ] ) ) {
			$status = 'all';
		}

		/* Get the current data */
		$data = $snippets[ $status ];

		/* Decide how many records per page to show by getting the user's setting in the Screen Options panel */
		$sort_by = $this->screen->get_option( 'per_page', 'option' );
		$per_page = get_user_meta( get_current_user_id(), $sort_by, true );

		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = $this->screen->get_option( 'per_page', 'default' );
		}

		$per_page = (int) $per_page;

		$this->set_order_vars();
		usort( $data, array( $this, 'usort_reorder_callback' ) );

		/* Determine what page the user is currently looking at */
		$current_page = $this->get_pagenum();

		/* Check how many items are in the data array */
		$total_items = count( $data );

		/* The WP_List_Table class does not handle pagination for us, so we need to ensure that the data is trimmed to only the current page. */
		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		/* Now we can add our *sorted* data to the 'items' property, where it can be used by the rest of the class. */
		$this->items = $data;

		/* We register our pagination options and calculations */
		$this->set_pagination_args(
			array(
				'total_items' => $total_items, // Calculate the total number of items.
				'per_page'    => $per_page, // Determine how many items to show on a page.
				'total_pages' => ceil( $total_items / $per_page ), // Calculate the total number of pages.
			)
		);
	}

	/**
	 * Determine the sort ordering for two pieces of data.
	 *
	 * @param string $a_data First piece of data.
	 * @param string $b_data Second piece of data.
	 *
	 * @return int Returns -1 if $a_data is less than $b_data; 0 if they are equal; 1 otherwise
	 * @ignore
	 */
	private function get_sort_direction( $a_data, $b_data ) {

		// If the data is numeric, then calculate the ordering directly.
		if ( is_numeric( $a_data ) ) {
			return $a_data - $b_data;
		}

		// If only one of the data points is empty, then place it before the one which is not.
		if ( '' === $a_data xor '' === $b_data ) {
			return '' === $a_data ? 1 : -1;
		}

		// Sort using the default string sort order if possible.
		if ( is_string( $a_data ) ) {
			return strcasecmp( $a_data, $b_data );
		}

		// Otherwise, use basic comparison operators.
		return $a_data === $b_data ? 0 : ( $a_data < $b_data ? -1 : 1 );
	}

	/**
	 * Set the $order_by and $order_dir class variables.
	 */
	private function set_order_vars() {
		$order = Settings\get_setting( 'general', 'list_order' );

		// set the order by based on the query variable, if set.
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$this->order_by = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) );
		} else {
			// otherwise, fetch the order from the setting, ensuring it is valid.
			$valid_fields = [ 'id', 'name', 'type', 'modified', 'priority' ];
			$order_parts = explode( '-', $order, 2 );

			$this->order_by = in_array( $order_parts[0], $valid_fields, true ) ? $order_parts[0] :
				apply_filters( 'code_snippets/list_table/default_orderby', 'priority' );
		}

		// set the order dir based on the query variable, if set.
		if ( ! empty( $_REQUEST['order'] ) ) {
			$this->order_dir = sanitize_key( wp_unslash( $_REQUEST['order'] ) );
		} elseif ( '-desc' === substr( $order, -5 ) ) {
			$this->order_dir = 'desc';
		} elseif ( '-asc' === substr( $order, -4 ) ) {
			$this->order_dir = 'asc';
		} else {
			$this->order_dir = apply_filters( 'code_snippets/list_table/default_order', 'asc' );
		}
	}

	/**
	 * Callback for usort() used to sort snippets
	 *
	 * @param Snippet $a The first snippet to compare.
	 * @param Snippet $b The second snippet to compare.
	 *
	 * @return int The sort order.
	 * @ignore
	 */
	private function usort_reorder_callback( $a, $b ) {
		$orderby = $this->order_by;
		$result = $this->get_sort_direction( $a->$orderby, $b->$orderby );

		if ( 0 === $result && 'id' !== $orderby ) {
			$result = $this->get_sort_direction( $a->id, $b->id );
		}

		// Apply the sort direction to the calculated order.
		return ( 'asc' === $this->order_dir ) ? $result : -$result;
	}

	/**
	 * Callback for search function
	 *
	 * @param Snippet $snippet The snippet being filtered.
	 *
	 * @return bool The result of the filter
	 * @ignore
	 */
	private function search_callback( $snippet ) {
		global $s;

		$fields = array( 'name', 'desc', 'code', 'tags_list' );

		foreach ( $fields as $field ) {
			if ( false !== stripos( $snippet->$field, $s ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Callback for search function
	 *
	 * @param Snippet $snippet The snippet being filtered.
	 *
	 * @return bool The result of the filter
	 * @ignore
	 */
	private function search_by_line_callback( $snippet ) {
		global $s;
		static $line_num;

		if ( is_null( $line_num ) ) {

			if ( preg_match( '/@line:(?P<line>\d+)/', $s, $matches ) ) {
				$s = trim( str_replace( $matches[0], '', $s ) );
				$line_num = (int) $matches['line'] - 1;
			} else {
				$line_num = -1;
			}
		}

		if ( $line_num < 0 ) {
			return $this->search_callback( $snippet );
		}

		$code_lines = explode( "\n", $snippet->code );

		return isset( $code_lines[ $line_num ] ) && false !== stripos( $code_lines[ $line_num ], $s );
	}

	/**
	 * Callback for filtering snippets by tag.
	 *
	 * @param Snippet $snippet The snippet being filtered.
	 *
	 * @return bool The result of the filter.
	 * @ignore
	 */
	private function tags_filter_callback( $snippet ) {
		$tags = isset( $_GET['tag'] ) ?
			explode( ',', sanitize_text_field( wp_unslash( $_GET['tag'] ) ) ) :
			array();

		foreach ( $tags as $tag ) {
			if ( in_array( $tag, $snippet->tags, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Display a notice showing the current search terms
	 *
	 * @since 1.7
	 */
	public function search_notice() {
		if ( ! empty( $_REQUEST['s'] ) || ! empty( $_GET['tag'] ) || ! empty( $_GET['cloud_search'] ) ) {

			echo '<span class="subtitle">' . esc_html__( 'Search results', 'code-snippets' );

			if ( ! empty( $_REQUEST['s'] ) || ! empty( $_REQUEST['cloud_search'] ) ) {
				$s = empty( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cloud_search'] ) ) : sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
				

				if ( preg_match( '/@line:(?P<line>\d+)/', $s, $matches ) ) {

					/* translators: 1: search query, 2: line number */
					$text = __( ' for &ldquo;%1$s&rdquo; on line %2$d', 'code-snippets' );
					printf(
						esc_html( $text ),
						esc_html( trim( str_replace( $matches[0], '', $s ) ) ),
						intval( $matches['line'] )
					);

				} else {
					/* translators: %s: search query */
					echo esc_html( sprintf( __( ' for &ldquo;%s&rdquo;', 'code-snippets' ), $s ) );
				}
			}

			if ( ! empty( $_GET['tag'] ) ) {
				$tag = sanitize_text_field( wp_unslash( $_GET['tag'] ) );
				/* translators: %s: tag name */
				echo esc_html( sprintf( __( ' in tag &ldquo;%s&rdquo;', 'code-snippets' ), $tag ) );
			}

			echo '</span>';

			/* translators: 1: link URL, 2: link text */
			printf(
				'&nbsp;<a class="button clear-filters" href="%s">%s</a>',
				esc_url( remove_query_arg( array( 's', 'tag', 'cloud_search' ) ) ),
				esc_html__( 'Clear Filters', 'code-snippets' )
			);
		}
	}

	/**
	 * Outputs content for a single row of the table
	 *
	 * @param Snippet $item The snippet being used for the current row.
	 */
	public function single_row( $item ) {
		$status = $item->active ? 'active' : 'inactive';

		$row_class = "snippet $status-snippet $item->type-snippet $item->scope-scope";

		if ( $item->shared_network ) {
			$row_class .= ' shared-network-snippet';
		}

		printf( '<tr class="%s" data-snippet-scope="%s">', esc_attr( $row_class ), esc_attr( $item->scope ) );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Clone a selection of snippets
	 *
	 * @param array<string> $ids List of snippet IDs.
	 */
	private function clone_snippets( $ids ) {
		$snippets = get_snippets( $ids, $this->is_network );

		foreach ( $snippets as $snippet ) {
			// Copy all data from the previous snippet aside from the ID and active status.
			$snippet->id = 0;
			$snippet->active = false;

			/* translators: %s: snippet title */
			$snippet->name = sprintf( __( '%s [CLONE]', 'code-snippets' ), $snippet->name );
			$snippet = apply_filters( 'code_snippets/list_table/clone_snippet', $snippet );

			save_snippet( $snippet );
		}
	}

	/**
	 * Check if cloud link is valid
	 *
	 * @return bool
	 */
	private function is_cloud_link_valid() {
		//
		return code_snippets()->cloud_api->cloud_key_is_verified;
	}

	/**
	 * Sync snippets to cloud
	 *
	 * @param array<integer> $ids List of snippet IDs.
	 */
	private function sync_to_cloud( $ids ) {

		$snippets = get_snippets( $ids, $this->is_network );
		code_snippets()->cloud_api->store_snippets_in_cloud( $snippets );
	}

}
