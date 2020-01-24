<?php

namespace Code_Snippets;

/**
 * This class handles the settings admin menu
 *
 * @since 2.4.0
 * @package Code_Snippets
 */
class Settings_Menu extends Admin_Menu {

	/**
	 * Settings page name as registered with the Settings API.
	 */
	const SETTINGS_PAGE = 'code-snippets';

	/**
	 * Constructor
	 */
	public function __construct() {

		parent::__construct( 'settings',
			_x( 'Settings', 'menu label', 'code-snippets' ),
			__( 'Snippets Settings', 'code-snippets' )
		);
	}

	/**
	 * Executed when the admin page is loaded
	 */
	public function load() {
		parent::load();

		if ( isset( $_GET['reset_settings'] ) && $_GET['reset_settings'] ) {

			if ( Settings\are_settings_unified() ) {
				delete_site_option( 'code_snippets_settings' );
			} else {
				delete_option( 'code_snippets_settings' );
			}

			add_settings_error(
				'code-snippets-settings-notices',
				'settings_reset',
				__( 'All settings have been reset to their defaults.', 'code-snippets' ),
				'updated'
			);

			set_transient( 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( esc_url_raw( add_query_arg( 'settings-updated', true, remove_query_arg( 'reset_settings' ) ) ) );
			exit;
		}

		if ( is_network_admin() ) {

			if ( Settings\are_settings_unified() ) {
				$this->update_network_options();
			} else {
				wp_redirect( code_snippets()->get_menu_url( 'settings', 'admin' ) );
				exit;
			}
		}
	}

	/**
	 * Enqueue the stylesheet for the settings menu
	 */
	public function enqueue_assets() {
		$plugin = code_snippets();

		Settings\enqueue_editor_preview_assets();

		wp_enqueue_style(
			'code-snippets-edit',
			plugins_url( 'css/min/settings.css', $plugin->file ),
			[], $plugin->version
		);
	}

	/**
	 * Retrieve the name of the settings section currently being viewed.
	 *
	 * @param string $default Name of the default tab displayed.
	 *
	 * @return string
	 */
	public function get_current_section( $default = 'general' ) {
		global $wp_settings_sections;

		if ( ! isset( $wp_settings_sections[ self::SETTINGS_PAGE ] ) ) {
			return $default;
		}

		$sections = (array) $wp_settings_sections[ self::SETTINGS_PAGE ];
		$active_tab = isset( $_REQUEST['section'] ) ? sanitize_text_field( $_REQUEST['section'] ) : $default;
		return isset( $sections[ $active_tab ] ) ? $active_tab : $default;
	}

	/**
	 * Render the admin screen
	 */
	public function render() {
		$update_url = is_network_admin() ? add_query_arg( 'update_site_option', true ) : admin_url( 'options.php' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'code-snippets' );

				if ( code_snippets()->admin->is_compact_menu() ) {
					$actions = [
						_x( 'Manage', 'snippets', 'code-snippets' ) => code_snippets()->get_menu_url(),
						_x( 'Add New', 'snippet', 'code-snippets' ) => code_snippets()->get_menu_url( 'add' ),
						_X( 'Import', 'snippets', 'code-snippets' ) => code_snippets()->get_menu_url( 'import' ),
					];

					foreach ( $actions as $label => $url ) {
						printf( '<a href="%s" class="page-title-action">%s</a>',
							esc_url( $url ), esc_html( $label )
						);
					}
				}

				?></h1>

			<?php settings_errors( 'code-snippets-settings-notices' ); ?>

			<form action="<?php echo esc_url( $update_url ); ?>" method="post">
				<input type="hidden" name="section" value="<?php echo esc_attr( $this->get_current_section() ); ?>">
				<?php

				settings_fields( 'code-snippets' );
				$this->do_settings_tabs();

				?>
				<p class="submit">
					<?php submit_button( null, 'primary', 'submit', false ); ?>

					<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'reset_settings', true ) ); ?>"><?php
						esc_html_e( 'Reset to Default', 'code-snippets' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Output snippet settings in tabs
	 */
	protected function do_settings_tabs() {
		global $wp_settings_sections;

		if ( ! isset( $wp_settings_sections[ self::SETTINGS_PAGE ] ) ) {
			return;
		}

		$sections = (array) $wp_settings_sections[ self::SETTINGS_PAGE ];
		$active_tab = $this->get_current_section();

		echo '<h2 class="nav-tab-wrapper" id="settings-sections-tabs">';

		foreach ( $sections as $section ) {
			printf(
				'<a class="nav-tab%s" data-section="%s">%s</a>',
				( $active_tab === $section['id'] ) ? ' nav-tab-active' : '',
				esc_attr( $section['id'] ), esc_html( $section['title'] )
			);
		}

		echo '</h2>';

		foreach ( $sections as $section ) {

			if ( $section['title'] ) {
				printf( '<h2 id="%s-settings" class="settings-section-title">%s</h2>' . "\n",
					esc_attr( $section['id'] ), esc_html( $section['title'] )
				);
			}

			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}

			printf(
				'<table class="form-table settings-section %s-settings" style="display: %s;">',
				esc_attr( $section['id'] ), $active_tab === $section['id'] ? 'block' : 'none'
			);

			do_settings_fields( self::SETTINGS_PAGE, $section['id'] );
			echo '</table>';
		}
	}

	/**
	 * Fill in for the Settings API in the Network Admin
	 */
	public function update_network_options() {

		/* Ensure the settings have been saved */
		if ( ! isset( $_GET['update_site_option'], $_POST['code_snippets_settings'] ) || ! $_GET['update_site_option'] ) {
			return;
		}

		check_admin_referer( 'code-snippets-options' );

		/* Retrieve the saved options and save them to the database */
		$value = wp_unslash( $_POST['code_snippets_settings'] );
		update_site_option( 'code_snippets_settings', $value );

		/* Add an updated notice */
		if ( ! count( get_settings_errors() ) ) {
			add_settings_error( 'general', 'settings_updated', __( 'Settings saved.', 'code-snippets' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		/* Redirect back to the settings menu */
		$redirect = add_query_arg( 'settings-updated', 'true', remove_query_arg( 'update_site_option', wp_get_referer() ) );
		wp_redirect( esc_url_raw( $redirect ) );
		exit;
	}
}
