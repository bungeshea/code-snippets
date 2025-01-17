<?php

namespace Code_Snippets;

/**
 * Base class for a plugin admin menu.
 */
abstract class Admin_Menu {

	/**
	 * The snippet page short name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * The label shown in the admin menu.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * The text used for the page title.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * The base slug for the top-level admin menu.
	 *
	 * @var string
	 */
	protected string $base_slug;

	/**
	 * The slug for this admin menu.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Constructor.
	 *
	 * @param string $name  The snippet page short name.
	 * @param string $label The label shown in the admin menu.
	 * @param string $title The text used for the page title.
	 */
	public function __construct( string $name, string $label, string $title ) {
		$this->name = $name;
		$this->label = $label;
		$this->title = $title;

		$this->base_slug = code_snippets()->get_menu_slug();
		$this->slug = code_snippets()->get_menu_slug( $name );
	}

	/**
	 * Register action and filter hooks.
	 *
	 * @return void
	 */
	public function run() {
		if ( ! code_snippets()->is_compact_menu() ) {
			add_action( 'admin_menu', array( $this, 'register' ) );
			add_action( 'network_admin_menu', array( $this, 'register' ) );
		}
	}

	/**
	 * Add a sub-menu to the Snippets menu.
	 *
	 * @param string $slug  Menu slug.
	 * @param string $label Label shown in admin menu.
	 * @param string $title Page title.
	 *
	 * @return void
	 */
	public function add_menu( string $slug, string $label, string $title ) {
		$hook = add_submenu_page(
			$this->base_slug,
			$title,
			$label,
			code_snippets()->get_cap(),
			$slug,
			array( $this, 'render' )
		);

		add_action( 'load-' . $hook, array( $this, 'load' ) );
	}

	/**
	 * Register the admin menu
	 */
	public function register() {
		$this->add_menu( $this->slug, $this->label, $this->title );
	}

	/**
	 * Render the content of a vew template
	 *
	 * @param string $name Name of view template to render.
	 */
	protected function render_view( string $name ) {
		include dirname( PLUGIN_FILE ) . '/php/views/' . $name . '.php';
	}

	/**
	 * Render the menu
	 */
	public function render() {
		$this->render_view( $this->name );
	}

	/**
	 * Print the status and error messages
	 */
	protected function print_messages() {
		// None required by default.
	}

	/**
	 * Executed when the admin page is loaded
	 */
	public function load() {
		// Make sure the user has permission to be here.
		if ( ! current_user_can( code_snippets()->get_cap() ) ) {
			wp_die( esc_html__( 'You are not authorized to access this page.', 'code-snippets' ) );
		}

		// Create the snippet tables if they are missing.
		$db = code_snippets()->db;

		if ( is_multisite() ) {
			$db->create_missing_table( $db->ms_table );
		}
		$db->create_missing_table( $db->table );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue scripts and stylesheets for the admin page, if necessary
	 */
	abstract public function enqueue_assets();

	/**
	 * Generate a list of page title links for passing to React.
	 *
	 * @param array<string> $actions List of actions to convert into links, as array values.
	 *
	 * @return array<string, string> Link labels keyed to link URLs.
	 */
	public function page_title_action_links( array $actions ): array {
		$plugin = code_snippets();
		$links = [];

		foreach ( $actions as $action ) {
			if ( 'settings' === $action && ! isset( $plugin->admin->menus['settings'] ) ) {
				continue;
			}

			$url = $plugin->get_menu_url( $action );

			if ( isset( $_GET['type'] ) && in_array( $_GET['type'], Snippet::get_types(), true ) ) {
				$url = add_query_arg( 'type', sanitize_key( wp_unslash( $_GET['type'] ) ), $url );
			}

			switch ( $action ) {
				case 'manage':
					$label = _x( 'Manage', 'snippets', 'code-snippets' );
					break;
				case 'add':
					$label = _x( 'Add New', 'snippet', 'code-snippets' );
					break;
				case 'import':
					$label = _x( 'Import', 'snippets', 'code-snippets' );
					break;
				case 'settings':
					$label = _x( 'Settings', 'snippets', 'code-snippets' );
					break;
				default:
					$label = '';
			}

			if ( $label && $url ) {
				$links[ $label ] = $url;
			}
		}

		return $links;
	}

	/**
	 * Render a list of links to other pages in the page title
	 *
	 * @param array<string> $actions List of actions to render as links, as array values.
	 */
	public function render_page_title_actions( array $actions ) {
		foreach ( $this->page_title_action_links( $actions ) as $label => $url ) {
			printf( '<a href="%s" class="page-title-action">%s</a>', esc_url( $url ), esc_html( $label ) );
		}
	}
}
