<?php

namespace Code_Snippets;

use Code_Snippets\Cloud\Cloud_API;
use Code_Snippets\REST_API\Snippets_REST_Controller;

/**
 * The main plugin class
 *
 * @package Code_Snippets
 */
class Plugin {

	/**
	 * Current plugin version number
	 *
	 * @var string
	 */
	public string $version;

	/**
	 * Filesystem path to the main plugin file
	 *
	 * @var string
	 */
	public string $file;

	/**
	 * Database class
	 *
	 * @var DB
	 */
	public DB $db;

	/**
	 * Administration area class
	 *
	 * @var Admin
	 */
	public Admin $admin;

	/**
	 * Front-end functionality class
	 *
	 * @var Front_End
	 */
	public Front_End $front_end;

	/**
	 * Class for managing cloud API actions.
	 *
	 * @var Cloud_API
	 */
	public Cloud_API $cloud_api;

	/**
	 * Class for managing active snippets
	 *
	 * @var Active_Snippets
	 */
	public Active_Snippets $active_snippets;

	/**
	 * Class constructor
	 *
	 * @param string $version Current plugin version.
	 * @param string $file    Path to main plugin file.
	 */
	public function __construct( string $version, string $file ) {
		$this->version = $version;
		$this->file = $file;

		wp_cache_add_global_groups( CACHE_GROUP );

		add_filter( 'code_snippets/execute_snippets', array( $this, 'disable_snippet_execution' ), 5 );

		if ( isset( $_REQUEST['snippets-safe-mode'] ) ) {
			add_filter( 'home_url', array( $this, 'add_safe_mode_query_var' ) );
			add_filter( 'admin_url', array( $this, 'add_safe_mode_query_var' ) );
		}

		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'allowed_redirect_hosts', [ $this, 'allow_code_snippets_redirect' ] );
	}

	/**
	 * Initialise classes and include files
	 */
	public function load_plugin() {
		$includes_path = __DIR__;

		// Database operation functions.
		$this->db = new DB();

		// Snippet operation functions.
		require_once $includes_path . '/snippet-ops.php';

		// CodeMirror editor functions.
		require_once $includes_path . '/editor.php';

		// General Administration functions.
		if ( is_admin() ) {
			$this->admin = new Admin();
		}

		// Settings component.
		require_once $includes_path . '/settings/settings-fields.php';
		require_once $includes_path . '/settings/editor-preview.php';
		require_once $includes_path . '/settings/settings.php';

		// Cloud List Table shared functions.
		require_once $includes_path . '/cloud/list-table-shared-ops.php';

		$this->active_snippets = new Active_Snippets();
		$this->front_end = new Front_End();
		$this->cloud_api = new Cloud_API();

		$upgrade = new Upgrade( $this->version, $this->db );
		add_action( 'plugins_loaded', array( $upgrade, 'run' ), 0 );
	}

	/**
	 * Register custom REST API controllers.
	 *
	 * @return void
	 */
	public function init_rest_api() {
		$snippets_controller = new Snippets_REST_Controller();
		$snippets_controller->register_routes();
	}

	/**
	 * Disable snippet execution if the necessary query var is set.
	 *
	 * @param bool $execute_snippets Current filter value.
	 *
	 * @return bool New filter value.
	 */
	public function disable_snippet_execution( bool $execute_snippets ): bool {
		return ! empty( $_REQUEST['snippets-safe-mode'] ) && $this->current_user_can() ? false : $execute_snippets;
	}

	/**
	 * Determine whether the menu is full or compact.
	 *
	 * @return bool
	 */
	public function is_compact_menu(): bool {
		return ! is_network_admin() && apply_filters( 'code_snippets_compact_menu', false );
	}

	/**
	 * Fetch the admin menu slug for a menu.
	 *
	 * @param string $menu Name of menu to retrieve the slug for.
	 *
	 * @return string The menu's slug.
	 */
	public function get_menu_slug( string $menu = '' ): string {
		$add = array( 'single', 'add', 'add-new', 'add-snippet', 'new-snippet', 'add-new-snippet' );
		$edit = array( 'edit', 'edit-snippet' );
		$import = array( 'import', 'import-snippets', 'import-code-snippets' );
		$settings = array( 'settings', 'snippets-settings' );
		$cloud = array( 'cloud', 'cloud-snippets' );
		$welcome = array( 'welcome', 'getting-started', 'code-snippets' );

		if ( in_array( $menu, $edit, true ) ) {
			return 'edit-snippet';
		} elseif ( in_array( $menu, $add, true ) ) {
			return 'add-snippet';
		} elseif ( in_array( $menu, $import, true ) ) {
			return 'import-code-snippets';
		} elseif ( in_array( $menu, $settings, true ) ) {
			return 'snippets-settings';
		} elseif ( in_array( $menu, $cloud, true ) ) {
			return 'snippets&type=cloud';
		} elseif ( in_array( $menu, $welcome, true ) ) {
			return 'code-snippets-welcome';
		} else {
			return 'snippets';
		}
	}

	/**
	 * Fetch the URL to a snippets admin menu.
	 *
	 * @param string $menu    Name of menu to retrieve the URL to.
	 * @param string $context URL scheme to use.
	 *
	 * @return string The menu's URL.
	 */
	public function get_menu_url( string $menu = '', string $context = 'self' ): string {
		$slug = $this->get_menu_slug( $menu );

		if ( $this->is_compact_menu() && 'network' !== $context ) {
			$base_slug = $this->get_menu_slug();
			$url = 'tools.php?page=' . $base_slug;

			if ( $slug !== $base_slug ) {
				$url .= '&sub=' . $slug;
			}
		} else {
			$url = 'admin.php?page=' . $slug;
		}

		if ( 'network' === $context || 'snippets-settings' === $slug ) {
			return network_admin_url( $url );
		} elseif ( 'admin' === $context ) {
			return admin_url( $url );
		} else {
			return self_admin_url( $url );
		}
	}

	/**
	 * Fetch the admin menu slug for a snippets admin menu.
	 *
	 * @param integer $snippet_id Snippet ID.
	 * @param string  $context    URL scheme to use.
	 *
	 * @return string The URL to the edit snippet page for that snippet.
	 */
	public function get_snippet_edit_url( int $snippet_id, string $context = 'self' ): string {
		return add_query_arg(
			'id',
			absint( $snippet_id ),
			$this->get_menu_url( 'edit', $context )
		);
	}

	/**
	 * Allow redirecting to the Code Snippets site.
	 *
	 * @param array<string> $hosts Allowed hosts.
	 *
	 * @return array Modified allowed hosts.
	 */
	public function allow_code_snippets_redirect( array $hosts ): array {
		$hosts[] = 'codesnippets.pro';
		$hosts[] = 'snipco.de';
		return $hosts;
	}

	/**
	 * Determine whether the current user can perform actions on snippets.
	 *
	 * @return boolean Whether the current user has the required capability.
	 *
	 * @since 2.8.6
	 */
	public function current_user_can(): bool {
		return current_user_can( $this->get_cap() );
	}

	/**
	 * Retrieve the name of the capability required to manage sub-site snippets.
	 *
	 * @return string
	 */
	public function get_cap_name(): string {
		return apply_filters( 'code_snippets_cap', 'manage_options' );
	}

	/**
	 * Retrieve the name of the capability required to manage network snippets.
	 *
	 * @return string
	 */
	public function get_network_cap_name(): string {
		return apply_filters( 'code_snippets_network_cap', 'manage_network_options' );
	}

	/**
	 * Get the required capability to perform a certain action on snippets.
	 * Does not check if the user has this capability or not.
	 *
	 * If multisite, checks if *Enable Administration Menus: Snippets* is active
	 * under the *Settings > Network Settings* network admin menu
	 *
	 * @return string The capability required to manage snippets.
	 *
	 * @since 2.0
	 */
	public function get_cap(): string {
		if ( is_multisite() ) {
			$menu_perms = get_site_option( 'menu_items', array() );

			// If multisite is enabled and the snippet menu is not activated, restrict snippet operations to super admins only.
			if ( empty( $menu_perms['snippets'] ) ) {
				return $this->get_network_cap_name();
			}
		}

		return $this->get_cap_name();
	}

	/**
	 * Inject the safe mode query var into URLs
	 *
	 * @param string $url Original URL.
	 *
	 * @return string Modified URL.
	 */
	public function add_safe_mode_query_var( string $url ): string {
		return isset( $_REQUEST['snippets-safe-mode'] ) ?
			add_query_arg( 'snippets-safe-mode', (bool) $_REQUEST['snippets-safe-mode'], $url ) :
			$url;
	}

	/**
	 * Retrieve a list of available snippet types and their labels.
	 *
	 * @return array<string, string> Snippet types.
	 */
	public static function get_types(): array {
		return apply_filters(
			'code_snippets_types',
			array(
				'php'          => __( 'Functions', 'code-snippets' ),
				'html'         => __( 'Content', 'code-snippets' ),
				'css'          => __( 'Styles', 'code-snippets' ),
				'js'           => __( 'Scripts', 'code-snippets' ),
				'cloud'        => __( 'Codevault', 'code-snippets' ),
				'cloud_search' => __( 'Cloud Search', 'code-snippets' ),
				'bundles'      => __( 'Bundles', 'code-snippets' ),
			)
		);
	}

	/**
	 * Determine whether a snippet type is Pro-only.
	 *
	 * @param string $type Snippet type name.
	 *
	 * @return bool
	 */
	public static function is_pro_type( string $type ): bool {
		return 'css' === $type || 'js' === $type || 'cloud' === $type || 'bundles' === $type;
	}

	/**
	 * Localise a plugin script to provide the CODE_SNIPPETS object.
	 *
	 * @param string $handle Script handle.
	 *
	 * @return void
	 */
	public function localize_script( string $handle ) {
		wp_localize_script(
			$handle,
			'CODE_SNIPPETS',
			[
				'isLicensed'       => $this->licensing->is_licensed(),
				'isCloudConnected' => Cloud_API::is_cloud_connection_available(),
				'restAPI'          => [
					'base'       => esc_url_raw( rest_url() ),
					'snippets'   => esc_url_raw( rest_url( Snippets_REST_Controller::get_base_route() ) ),
					'cloud'      => esc_url_raw( rest_url( Cloud_REST_API::get_base_route() ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'localToken' => $this->cloud_api->get_local_token(),
				],
				'urls'             => [
					'plugin'       => esc_url_raw( plugins_url( '', PLUGIN_FILE ) ),
					'manage'       => esc_url_raw( $this->get_menu_url() ),
					'edit'         => esc_url_raw( $this->get_menu_url( 'edit' ) ),
					'addNew'       => esc_url_raw( $this->get_menu_url( 'add' ) ),
					'connectCloud' => esc_url_raw( Cloud_API::get_connect_cloud_url() ),
				],
			]
		);
	}
}
