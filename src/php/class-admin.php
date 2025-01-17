<?php

namespace Code_Snippets;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Functions specific to the administration interface
 *
 * @package Code_Snippets
 */
class Admin {

	/**
	 * Admin_Menu class instances
	 *
	 * @var array<string, Admin_Menu>
	 */
	public array $menus = array();

	/**
	 * Welcome_API class instance.
	 *
	 * @var Welcome_API
	 */
	public Welcome_API $welcome_api;

	/**
	 * Class constructor
	 */
	public function __construct() {
		if ( is_admin() ) {
			$this->welcome_api = new Welcome_API();
			$this->run();
		}
	}

	/**
	 * Initialise classes
	 */
	public function load_classes() {
		$this->menus['manage'] = new Manage_Menu();
		$this->menus['edit'] = new Edit_Menu();
		$this->menus['import'] = new Import_Menu();

		if ( is_network_admin() === Settings\are_settings_unified() ) {
			$this->menus['settings'] = new Settings_Menu();
		}

		$this->menus['welcome'] = new Welcome_Menu( $this->welcome_api );

		foreach ( $this->menus as $menu ) {
			$menu->run();
		}
	}

	/**
	 * Register action and filter hooks
	 */
	public function run() {
		add_action( 'init', array( $this, 'load_classes' ), 11 );

		add_filter( 'mu_menu_items', array( $this, 'mu_menu_items' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
		add_action( 'code_snippets/admin/manage', array( $this, 'print_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue general admin assets.
	 *
	 * @param string|null $hook_name Current plugin page hook name.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( ?string $hook_name ) {
		if ( 'plugins.php' === $hook_name ) {
			wp_enqueue_style(
				'code-snippets-plugins-css',
				plugins_url( 'dist/plugins.css', PLUGIN_FILE ),
				[],
				PLUGIN_VERSION
			);
		}
	}

	/**
	 * Allow super admins to control site admin access to
	 * snippet admin menus
	 *
	 * Adds a checkbox to the *Settings > Network Settings*
	 * network admin menu
	 *
	 * @param array<string, string> $menu_items Current mu menu items.
	 *
	 * @return array<string, string> The modified mu menu items
	 *
	 * @since 1.7.1
	 */
	public function mu_menu_items( array $menu_items ): array {
		$menu_items['snippets'] = __( 'Snippets', 'code-snippets' );
		$menu_items['snippets_settings'] = __( 'Snippets &raquo; Settings', 'code-snippets' );

		return $menu_items;
	}

	/**
	 * Modify the action links for this plugin.
	 *
	 * @param array<string> $actions     Existing plugin action links.
	 * @param string        $plugin_file The plugin the links are for.
	 *
	 * @return array<string> Modified plugin action links.
	 * @since 2.0.0
	 */
	public function plugin_action_links( array $actions, string $plugin_file ): array {
		if ( plugin_basename( PLUGIN_FILE ) !== $plugin_file ) {
			return $actions;
		}

		$format = '<a href="%1$s" title="%2$s">%3$s</a>';

		$actions = array_merge(
			[
				sprintf(
					$format,
					esc_url( code_snippets()->get_menu_url( 'settings' ) ),
					esc_html__( 'Change plugin settings', 'code-snippets' ),
					esc_html__( 'Settings', 'code-snippets' )
				),
				sprintf(
					$format,
					esc_url( code_snippets()->get_menu_url() ),
					esc_html__( 'Manage your existing snippets', 'code-snippets' ),
					esc_html__( 'Snippets', 'code-snippets' )
				),
			],
			$actions
		);

		if ( ! code_snippets()->licensing->is_licensed() ) {
			$actions[] = sprintf(
				'<a href="%1$s" title="%2$s" style="color: #d46f4d; font-weight: bold;" target="_blank">%3$s</a>',
				'https://snipco.de/JE2i',
				esc_attr__( 'Upgrade to Code Snippets Pro', 'code-snippets' ),
				esc_html__( 'Go Pro', 'code-snippets' )
			);
		}
		return $actions;
	}

	/**
	 * Adds extra links related to the plugin
	 *
	 * @param array<string> $plugin_meta Existing plugin info links.
	 * @param string        $plugin_file The plugin the links are for.
	 *
	 * @return array<string> Modified plugin info links.
	 * @since 2.0.0
	 */
	public function plugin_row_meta( array $plugin_meta, string $plugin_file ): array {
		if ( plugin_basename( PLUGIN_FILE ) !== $plugin_file ) {
			return $plugin_meta;
		}

		$format = '<a href="%1$s" title="%2$s" target="_blank">%3$s</a>';

		return array_merge(
			$plugin_meta,
			array(
				sprintf(
					$format,
					'https://help.codesnippets.pro/',
					esc_attr__( 'Find out how to get support with Code Snippets', 'code-snippets' ),
					esc_html__( 'Docs and Support', 'code-snippets' )
				),
				sprintf(
					$format,
					'https://www.facebook.com/groups/codesnippetsplugin/',
					esc_attr__( 'Join our community on Facebook', 'code-snippets' ),
					esc_html__( 'Community', 'code-snippets' )
				),
			)
		);
	}

	/**
	 * Add Code Snippets information to Site Health information.
	 *
	 * @param array<string, array<string, mixed>> $info Current Site Health information.
	 *
	 * @return array<string, array<string, mixed>> Updated Site Health information.
	 * @author sc0ttkclark
	 */
	public function debug_information( array $info ): array {
		$fields = array();

		// build the debug information from snippet data.
		foreach ( get_snippets() as $snippet ) {
			$values = [ $snippet->scope_name ];
			$debug = [];

			if ( ! $snippet->active ) {
				continue;
			}

			if ( $snippet->name ) {
				$debug[] = 'name: ' . $snippet->name;
			}

			$debug[] = 'scope: ' . $snippet->scope;

			if ( $snippet->modified ) {
				/* translators: %s: formatted last modified date */
				$values[] = sprintf( __( 'Last modified %s', 'code-snippets' ), $snippet->format_modified( false ) );
				$debug[] = 'modified: ' . $snippet->modified;
			}

			if ( $snippet->tags ) {
				$values[] = $snippet->tags_list;
				$debug[] = 'tags: [' . $snippet->tags_list . ']';
			}

			$fields[ 'snippet-' . $snippet->id ] = [
				'label' => $snippet->display_name,
				'value' => implode( "\n | ", $values ),
				'debug' => implode( ', ', $debug ),
			];
		}

		$snippets_info = array(
			'label'      => __( 'Active Snippets', 'code-snippets' ),
			'show_count' => true,
			'fields'     => $fields,
		);

		// attempt to insert the new section right after the Inactive Plugins section.
		$index = array_search( 'wp-plugins-inactive', array_keys( $info ), true );

		if ( false === $index ) {
			$info['code-snippets'] = $snippets_info;
		} else {
			$info = array_merge(
				array_slice( $info, 0, $index + 1 ),
				[ 'code-snippets' => $snippets_info ],
				array_slice( $info, $index + 1 )
			);
		}

		return $info;
	}

	/**
	 * Print any admin notices that have not been dismissed.
	 *
	 * @return void
	 */
	public function print_notices() {
		global $current_user;

		$meta_key = 'ignore_code_snippets_survey_message';
		$dismissed = get_user_meta( $current_user->ID, $meta_key );

		if ( isset( $_GET[ $meta_key ], $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), $meta_key ) ) {
			add_user_meta( $current_user->ID, $meta_key, sanitize_key( wp_unslash( $_GET[ $meta_key ] ) ) );
			return;
		}

		$welcome = $this->welcome_api->get_banner();

		try {
			$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			$now = $welcome['start_datetime'];
		}

		if ( isset( $welcome['key'] ) && ! in_array( $welcome['key'], $dismissed, true ) &&
		     ( empty( $welcome['start_datetime'] ) || $now >= $welcome['start_datetime'] ) &&
		     ( empty( $welcome['end_datetime'] ) || $now <= $welcome['end_datetime'] ) ) {
			$notice = $welcome['key'];

			$text = $welcome['text_free'];
			$action_url = $welcome['action_url_free'];
			$action_label = $welcome['action_label_free'];

		} elseif ( ! in_array( 'pro', $dismissed, true ) ) {
			$notice = 'pro';
			$action_url = 'https://snipco.de/Mlll';
			$action_label = __( 'Upgrade now', 'code-snippets' );
			$text = __( '<strong>Lifetime plans return!</strong> Enjoy Code Snippets Pro with new pricing choices, including lifetime, monthly and yearly subscriptions.', 'code-snippets' );

		} elseif ( ! in_array( 'survey', $dismissed, true ) && ! in_array( 'true', $dismissed, true ) ) {
			$notice = 'survey';
			$action_url = 'https://codesnippets.pro/survey/';
			$action_label = __( 'Take the survey now', 'code-snippets' );
			$text = __( "<strong>Have feedback on Code Snippets?</strong> Please take the time to answer a short survey on how you use this plugin and what you'd like to see changed or added in the future.", 'code-snippets' );
		} else {
			return;
		}

		printf(
			'<div class="notice notice-info code-snippets-notice code-snippets-%s-notice is-dismissible"><p>',
			esc_attr( sanitize_key( $notice ) )
		);

		echo wp_kses_post( $text );

		printf(
			'<a href="%s" class="button button-secondary" target="_blank" style="margin: auto .5em;">%s</a>',
			esc_url( $action_url ),
			esc_html( $action_label )
		);

		printf(
			'<a href="%s" class="notice-dismiss"><span class="screen-reader-text">%s</span></a>',
			esc_url( wp_nonce_url( add_query_arg( $meta_key, $notice ), $meta_key ) ),
			esc_attr__( 'Dismiss', 'code-snippets' )
		);

		echo '</p></div>';
	}

	/**
	 * Render a nav tab for a snippet type.
	 *
	 * @param string $type_name    Type identifier.
	 * @param string $label        Type label.
	 * @param string $current_type Identifier of currently-selected type.
	 *
	 * @return void
	 */
	public static function render_snippet_type_tab( string $type_name, string $label, string $current_type = '' ) {
		$cloud_tabs = [ 'cloud', 'cloud_search', 'bundles' ];
		$nav_tab_inactive = false;

		if ( $type_name === $current_type ) {
			printf( '<a class="nav-tab nav-tab-active" data-snippet-type="%s">', esc_attr( $type_name ) );

		} elseif ( ! code_snippets()->licensing->is_licensed() && Plugin::is_pro_type( $type_name ) ) {
			printf(
				'<a class="nav-tab nav-tab-inactive" data-snippet-type="%s" title="%s" href="https://codesnippets.pro/pricing/" target="_blank">',
				esc_attr( $type_name ),
				esc_attr__( 'Available in Code Snippets Pro (external link)', 'code-snippets' )
			);

		} else {
			$current_url = remove_query_arg( [ 'cloud_select', 'cloud_search' ] );

			if ( in_array( $type_name, $cloud_tabs, true ) && ! code_snippets()->cloud_api->is_cloud_key_verified() ) {
				$nav_tab_inactive = true;
			}

			printf(
				'<a class="nav-tab %s" href="%s" data-snippet-type="%s">',
				$nav_tab_inactive ? 'nav-tab-inactive' : '',
				esc_url( add_query_arg( 'type', $type_name, $current_url ) ),
				esc_attr( $type_name )
			);
		}

		if ( 'all' === $type_name ) {
			$label_class = 'all-snippets-label';
		} else {
			$label_class = 'snippet-label';
		}

		echo '<span class="' . $label_class . '">', esc_html( $label ), '</span>';

		switch ( $type_name ) {
			case 'all':
				break;
			case 'cloud':
				echo '<span class="cloud-badge dashicons dashicons-cloud cloud-icon cloud-synced"></span>';
				break;
			case 'cloud_search':
				echo '<span class="cloud-badge dashicons dashicons-search cloud-icon cloud-downloaded"></span>';
				break;
			case 'bundles':
				echo '<span class="cloud-badge dashicons dashicons-screenoptions cloud-icon cloud-bundle"></span>';
				break;
			case 'ai':
				echo '<span class="cloud-badge ai-icon">', esc_html__( 'AI', 'code-snippets' ), '</span>';
				break;
			default:
				echo '<span class="badge">' . esc_html( $type_name ) . '</span>';
				break;
		}

		echo '</a>';
	}
}
