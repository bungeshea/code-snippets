<?php

namespace Code_Snippets;

use WP_Error;
use function Code_Snippets\Settings\get_setting;
use function Code_Snippets\Settings\update_setting;

/**
 * Handles license activation and automatic updates.
 * @package Code_Snippets
 */
class Licensing {

	/**
	 * URL to Easy Digital Downloads store.
	 */
	const EDD_STORE_URL = 'https://codesnippets.pro';

	/**
	 * The download name for the product in Easy Digital Downloads.
	 */
	const EDD_ITEM_NAME = 'Code Snippets Pro Beta';

	/**
	 * EDD plugin updater class.
	 *
	 * @var EDD_SL_Plugin_Updater
	 */
	private $edd_updater;

	/**
	 * Current license key
	 * @var string
	 */
	private $license_key = null;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'init' ], 1 );
		add_action( 'admin_init', [ $this, 'handle_form_submit' ] );
	}

	/**
	 * Initialise the plugin updater.
	 */
	public function init() {
		$plugin = code_snippets();

		// setup the updater
		$this->edd_updater = new EDD_SL_Plugin_Updater( self::EDD_STORE_URL, $plugin->file, [
			'version'   => $plugin->version,
			'license'   => $this->get_license_key(),
			'item_name' => self::EDD_ITEM_NAME,
			'author'    => __( 'Code Snippets Pro', 'code-snippets' ),
			'beta'      => false,
		] );
	}

	/**
	 * Retrieve the current saved license key
	 * @return string
	 */
	public function get_license_key() {
		if ( is_null( $this->license_key ) ) {
			$this->license_key = trim( get_setting( 'license', 'key' ) );
		}

		return $this->license_key;
	}

	/**
	 * Render the license status settings field.
	 */
	public function render_license_status() {
		if ( ! $this->get_license_key() ) {
			esc_html_e( 'You will need to provide a valid key before activating the license.', 'code-snippets' );
			return;
		}

		$status = Settings\get_setting( 'license', 'status' );
		$valid = 'valid' === $status;

		if ( $valid ) {
			echo '<span class="license-active-status">', esc_html__( 'active', 'code-snippets' ), '</span>';
		}

		wp_nonce_field( 'code_snippets_pro_license', 'code_snippets_pro_license_nonce' );

		submit_button(
			$valid ? __( 'Deactivate License', 'code-snippets' ) : __( 'Activate License', 'code-snippets' ),
			'secondary',
			'code_snippets_pro_license_' . ( $valid ? 'deactivate' : 'activate' ),
			false
		);
	}

	/**
	 * Send a request to the remote licensing server.
	 *
	 * @param string $action Action to perform.
	 *
	 * @return array|WP_Error Request response.
	 */
	private function do_remote_request( $action ) {
		$api_params = array(
			'edd_action' => $action,
			'license'    => $this->get_license_key(),
			'item_name'  => urlencode( self::EDD_ITEM_NAME ),
			'url'        => home_url(),
		);

		return wp_remote_post( self::EDD_STORE_URL, [ 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ] );
	}

	/**
	 * Translate a request error code into an error message.
	 *
	 * @param Object $license_data
	 *
	 * @return string
	 */
	public function translate_license_error( $license_data ) {
		switch ( $license_data->error ) {
			case 'expired':
				/* translators: %s: expiry date */
				return sprintf( __( 'Your license key expired on %s.', 'code-snippets' ),
					date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
				);

			case 'disabled':
			case 'revoked':
				return __( 'Your license key has been disabled.', 'code-snippets' );

			case 'missing':
			case 'key_mismatch':
			case 'item_name_mismatch' :
				return __( 'Invalid license.', 'code-snippets' );

			case 'invalid' :
			case 'site_inactive' :
				return __( 'Your license is not active for this URL.', 'code-snippets' );

			case 'no_activations_left':
				return __( 'Your license key has reached its activation limit.', 'code-snippets' );

			default:
				return __( 'An error occurred activating the license.', 'code-snippets' );
		}
	}

	/**
	 * Handle requests to activate or deactivate the license.
	 */
	public function handle_form_submit() {

		// only continue if we are activating or deactivating the license
		if ( ! isset( $_POST['code_snippets_pro_license_activate'] ) && ! isset( $_POST['code_snippets_pro_license_deactivate'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'code_snippets_pro_license', 'code_snippets_pro_license_nonce' ) ) {
			return;
		}

		// send the request to the license server
		$activating = isset( $_POST['code_snippets_pro_license_activate'] );
		$response = $this->do_remote_request( $activating ? 'activate_license' : 'deactivate_license' );

		// check whether the request succeeded
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {

			// fetch the license data from the request
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			$message = '';

			// clear the license status if it was deactivated
			if ( $activating ) {
				if ( false === $license_data->success ) {
					$message = $this->translate_license_error( $license_data );
				} else {
					update_setting( 'license', 'status', $license_data->license );
				}
			} else if ( 'deactivated' === $license_data->license ) {
				update_setting( 'license', 'status', false );
			}

		} else {
			$message = is_wp_error( $response ) ? $response->get_error_message() :
				__( 'An error occurred, please try again.', 'code-snippets' );
		}

		if ( ! empty( $message ) ) {
			add_settings_error( 'code-snippets-settings-notices', 'activation_error', $message );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
	}
}
