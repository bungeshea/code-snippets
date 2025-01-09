<?php

namespace Code_Snippets\Cloud;

use Code_Snippets\Snippet;
use WP_Error;
use function Code_Snippets\code_snippets;
use function Code_Snippets\get_snippet_by_cloud_id;
use function Code_Snippets\get_snippets;
use function Code_Snippets\save_snippet;
use function Code_Snippets\update_snippet_fields;

/**
 * Functions used to manage cloud synchronisation.
 *
 * @package Code_Snippets
 */
class Cloud_API {

	/**
	 * Key used to access the local-to-cloud map transient data.
	 */
	private const CLOUD_MAP_TRANSIENT_KEY = 'cs_local_to_cloud_map';

	/**
	 * Key used to access the codevault snippets transient data.
	 */
	private const CODEVAULT_SNIPPETS_TRANSIENT_KEY = 'cs_codevault_snippets';

	/**
	 * Action name for the nonce required to create a cloud connection.
	 */
	private const CLOUD_ACTION_NONCE = 'connect_code_snippets_cloud';

	/**
	 * Days to cache data retrieved from API.
	 */
	private const DAYS_TO_STORE_CS = 1;

	/**
	 * Name of option used to store cloud settings.
	 */
	private const CLOUD_SETTINGS_OPTION = 'code_snippets_cloud_settings';

	/**
	 * Name of key used to cache cloud settings.
	 *
	 * @var string
	 */
	private const CLOUD_SETTINGS_CACHE_KEY = 'code_snippets_cloud_settings';

	/**
	 * Cached list of cloud snippets.
	 *
	 * @var Cloud_Snippets|null
	 */
	private ?Cloud_Snippets $cached_codevault_snippets = null;

	/**
	 * Cached list of cloud links.
	 *
	 * @var Cloud_Link[]|null
	 */
	private ?array $cached_cloud_links = null;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( ! defined( 'CS_CLOUD_URL' ) ) {
			define( 'CS_CLOUD_URL', 'https://codesnippets.cloud/' );
		}

		if ( ! defined( 'CS_CLOUD_API_URL' ) ) {
			define( 'CS_CLOUD_API_URL', CS_CLOUD_URL . 'api/v1/' );
		}

		add_action( 'plugins_loaded', [ $this, 'init_oauth_sync' ] );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_cloud_redirects' ] );
	}

	/**
	 * Retrieve the Cloud URL from wp-config or fallback to default.
	 *
	 * @return string
	 */
	public static function get_cloud_url(): string {
		return defined( 'CS_CLOUD_URL' ) ? CS_CLOUD_URL : 'https://codesnippets.cloud/';
	}

	/**
	 * Retrieve the Cloud API URL from wp-config or fallback to default.
	 *
	 * @return string
	 */
	public static function get_cloud_api_url(): string {
		return defined( 'CS_CLOUD_API_URL' ) ? CS_CLOUD_API_URL : 'https://codesnippets.cloud/api/v1/';
	}

	/**
	 * Retrieve the value of a cloud setting, if it exists.
	 *
	 * @param string $setting Setting name.
	 *
	 * @return mixed|null Setting value, or null if the setting is unrecognised.
	 */
	private static function get_cloud_setting( string $setting ) {
		$settings = self::get_cloud_settings();
		return $settings[ $setting ] ?? null;
	}

	/**
	 * Retrieve cloud settings.
	 *
	 * @return array
	 */
	private static function get_cloud_settings(): array {
		static $settings = null;

		if ( ! is_null( $settings ) ) {
			return $settings;
		}

		$settings = wp_cache_get( self::CLOUD_SETTINGS_CACHE_KEY );
		if ( $settings ) {
			return $settings;
		}

		$settings = get_option( self::CLOUD_SETTINGS_CACHE_KEY );

		// Check if the settings exist in the database if not create defaults.
		if ( false === $settings ) {
			$settings = [
				'cloud_token'    => '',
				'local_token'    => '',
				'token_verified' => false,
				'code_verifier'  => '',
				'code_challenge' => '',
				'state'          => '',
			];

			update_option( self::CLOUD_SETTINGS_CACHE_KEY, $settings );
		}

		wp_cache_set( self::CLOUD_SETTINGS_CACHE_KEY, $settings );
		return $settings;
	}

	/**
	 * Retrieve the current cloud connection state.
	 *
	 * @return string
	 */
	public static function get_current_state(): string {
		return self::get_cloud_setting( 'state' ) ?? '';
	}

	/**
	 * Retrieve the cloud local token.
	 *
	 * @return string
	 */
	public static function get_local_token(): string {
		return self::get_cloud_setting( 'local_token' ) ?? '';
	}

	/**
	 * Retrieve the cloud API key.
	 *
	 * @return string
	 */
	public static function get_cloud_key(): string {
		return self::get_cloud_setting( 'cloud_token' ) ?? '';
	}

	/**
	 * Check that the cloud key is valid and verified.
	 *
	 * @return boolean
	 */
	public static function is_cloud_key_verified(): bool {
		return boolval( self::get_cloud_setting( 'token_verified' ) );
	}

	/**
	 * Check if the API key is set and verified.
	 *
	 * @return boolean
	 */
	public static function is_cloud_connection_available(): bool {
		return self::get_cloud_key() && self::is_cloud_key_verified();
	}

	/**
	 * Update multiple cloud settings
	 *
	 * @param array<string, mixed> $settings to update in cloud settings with key value pairs 'setting' => 'value'.
	 *
	 * @return void
	 */
	private function update_cloud_settings( array $settings ) {
		$existing_settings = self::get_cloud_settings();

		foreach ( $settings as $setting => $value ) {
			$existing_settings[ $setting ] = $value;
		}

		update_option( self::CLOUD_SETTINGS_OPTION, $existing_settings );
		wp_cache_set( self::CLOUD_SETTINGS_CACHE_KEY, $existing_settings );
	}

	/**
	 * Generate a URL for initiating a new cloud connection.
	 *
	 * @return string
	 */
	public static function get_connect_cloud_url(): string {
		return add_query_arg(
			[
				'connect-authorise-cloud' => true,
				'_wpnonce'                => wp_create_nonce( self::CLOUD_ACTION_NONCE ),
			],
			code_snippets()->get_menu_url( 'settings' )
		);
	}

	/**
	 * Generate a URL for resetting the current cloud connection.
	 *
	 * @return string
	 */
	public static function get_reset_cloud_url(): string {
		return add_query_arg(
			[
				'reset-cloud' => true,
				'_wpnonce'    => wp_create_nonce( self::CLOUD_ACTION_NONCE ),
			],
			code_snippets()->get_menu_url( 'settings' )
		);
	}

	/**
	 * Normalise a generated password by removing special characters
	 *
	 * @param string $password Original generated password.
	 *
	 * @return string Sanitised password.
	 */
	private function sanitise_generated_password( string $password ): string {
		return str_replace( '=', '', strtr( $password, '+/', '-_' ) );
	}

	/**
	 * Initialise data for OAuth Cloud Connect.
	 *
	 * @return void
	 *
	 * @uses wp_generate_password() – must be loaded after pluggable functions.
	 */
	public function init_oauth_sync() {
		// Bail early if the cloud key is already verified or if code verifier is already set.
		if ( $this->is_cloud_key_verified() || $this->get_cloud_setting( 'code_verifier' ) ) {
			return;
		}

		$code_verifier = $this->sanitise_generated_password(
			wp_generate_password( 128, false )
		);

		$code_challenge = $this->sanitise_generated_password(
			base64_encode( hash( 'sha256', $code_verifier, true ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);

		$state = wp_generate_password( 15, false );
		$local_token = wp_generate_password( 30, false );

		$this->update_cloud_settings(
			[
				'code_verifier'  => $code_verifier,
				'code_challenge' => $code_challenge,
				'state'          => $state,
				'local_token'    => $local_token,
			]
		);
	}

	/**
	 * Create local-to-cloud map to keep track of local snippets that have been synced to the cloud.
	 *
	 * @return Cloud_Link[]
	 */
	private function get_cloud_links(): ?array {
		// Return the cached data if available.
		if ( is_array( $this->cached_cloud_links ) ) {
			return $this->cached_cloud_links;
		}

		// Fetch data from the stored transient, if available.
		$transient_data = get_transient( self::CLOUD_MAP_TRANSIENT_KEY );
		if ( is_array( $transient_data ) ) {
			$this->cached_cloud_links = $transient_data;
			return $this->cached_cloud_links;
		}

		// Otherwise, regenerate the local-to-cloud-map.
		$this->cached_cloud_links = [];
		$codevault_snippets = $this->get_codevault_snippets();

		if ( ! $codevault_snippets ) {
			return $this->cached_cloud_links;
		}

		$cloud_id_rev = $codevault_snippets->cloud_id_rev;

		// Fetch and iterate through all local snippets to create the map.
		foreach ( get_snippets() as $local_snippet ) {
			// Skip snippets that are only stored locally.
			if ( ! $local_snippet->cloud_id ) {
				continue;
			}

			$link = new Cloud_Link();
			$cloud_id_owner = $this->get_cloud_id_and_ownership( $local_snippet->cloud_id );
			$cloud_id_int = intval( $cloud_id_owner['cloud_id'] );
			$link->local_id = $local_snippet->id;
			$link->cloud_id = $cloud_id_int;
			$link->is_owner = $cloud_id_owner['is_owner'];
			// Check if cloud id exists in cloud_id_rev array - this shows if the snippet is in the codevault.
			$link->in_codevault = $cloud_id_rev[ $cloud_id_int ] ?? false;

			// Get the cloud snippet revision if in codevault get from cloud_id_rev array otherwise get from cloud.
			if ( $link->in_codevault ) {
				$cloud_snippet_revision =
					$cloud_id_rev[ $cloud_id_int ] ? $cloud_id_rev[ $cloud_id_int ] :
						$this->get_cloud_snippet_revision( $local_snippet->cloud_id );

				$link->update_available = $local_snippet->revision < $cloud_snippet_revision;
			}

			$this->cached_cloud_links[] = $link;
		}

		set_transient(
			self::CLOUD_MAP_TRANSIENT_KEY,
			$this->cached_cloud_links,
			DAY_IN_SECONDS * self::DAYS_TO_STORE_CS
		);

		return $this->cached_cloud_links;
	}

	/**
	 * Filter the list of allowed redirect hosts to include the Cloud site.
	 *
	 * @param string[] $allowed_hosts List of allowed redirect hosts.
	 *
	 * @return string[] Modified list of allowed redirect hosts.
	 */
	public function allow_cloud_redirects( array $allowed_hosts ): array {
		$api_url = wp_parse_url( self::get_cloud_url() );
		$allowed_hosts[] = $api_url['host'];
		return $allowed_hosts;
	}

	/**
	 * Check Cloud Connection is Available or Establish New Connection
	 *
	 * @return array
	 */
	public function ensure_cloud_connection_available(): array {
		// Check if cloud connection is already available.
		if ( $this->is_cloud_connection_available() ) {
			return [
				'success'       => true,
				'redirect-slug' => 'success',
			];
		}

		if ( ! $this->is_cloud_key_verified() ) {
			return [
				'success'       => false,
				'redirect-slug' => 'not-connected',
			];
		}

		// Establish new cloud connection.
		$cloud_connection = $this->establish_new_cloud_connection();

		if ( 'no_codevault' === $cloud_connection['message'] ) {
			return [
				'success'       => false,
				'redirect-slug' => 'no-codevault',
			];
		}

		// Check if the connection was successful.
		if ( ! $cloud_connection['success'] ) {
			// If not successful return the error message.
			return [
				'success'       => false,
				'redirect-slug' => 'invalid',
				'message'       => $cloud_connection['message'],
			];
		}

		$this->update_cloud_settings( [ 'token_verified' => true ] );

		return [
			'success'       => true,
			'redirect-slug' => 'success',
		];
	}

	/**
	 * Get ownership and Cloud ID of a snippet.
	 *
	 * @param string $cloud_id Cloud ID.
	 *
	 * @return array<string, mixed>
	 */
	public function get_cloud_id_and_ownership( string $cloud_id ): array {
		$cloud_id_owner = explode( '_', $cloud_id );

		return [
			'cloud_id'        => (int) $cloud_id_owner[0] ?? '',
			'is_owner'        => isset( $cloud_id_owner[1] ) && $cloud_id_owner[1],
			'is_owner_string' => isset( $cloud_id_owner[1] ) && $cloud_id_owner[1] ? '1' : '0',
		];
	}

	/**
	 * Build a list of headers required for an authenticated request.
	 *
	 * @return array<string, string>
	 */
	public static function build_request_headers(): array {
		return [
			'Authorization' => 'Bearer ' . self::get_cloud_key(),
			'Local-Token'   => self::get_local_token(),
		];
	}

	/**
	 * Unpack JSON data from a request response.
	 *
	 * @param array|WP_Error $response Response from wp_request_*.
	 *
	 * @return array<string, mixed>|null Associative array of JSON data on success, null on failure.
	 */
	private static function unpack_request_json( $response ): ?array {
		$body = wp_remote_retrieve_body( $response );
		return $body ? json_decode( $body, true ) : null;
	}

	/**
	 * Establish new connection to the cloud platform.
	 *
	 * @return array - success, message,
	 */
	public function establish_new_cloud_connection(): array {
		$local_token = $this->get_local_token();
		$cloud_key = $this->get_cloud_key();

		// Send POST request to CLOUD_API_URL . 'private/syncandverify' with site_token and site_host as form data.
		$response = wp_remote_post(
			self::get_cloud_api_url() . 'private/syncandverify',
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization'               => 'Bearer ' . $cloud_key,
					'Local-Token'                 => $local_token,
					'Access-Control-Allow-Origin' => '*',
					'Accept'                      => 'application/json',
				],
				'body'    => [
					'site_token' => $local_token,
					'site_host'  => wp_parse_url( get_site_url(), PHP_URL_HOST ),
				],
			]
		);

		// Check the response codes and return accordingly.
		if ( 401 === wp_remote_retrieve_response_code( $response ) ) {
			return [
				'success' => false,
				'message' => 'That token is invalid - please check and try again.',
			];
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return [
				'success' => false,
				'message' => 'There was an error connecting to the cloud platform. Please try again later.',
			];
		}

		$data = self::unpack_request_json( $response );

		if ( isset( $data['sync_status'] ) ) {
			if ( 'error' === $data['sync_status'] ) {
				return [
					'success' => false,
					'message' => strpos( $data['message'], 'No Codevault!' ) !== false ?
						'no_codevault' :
						$data['message'],
				];
			} elseif ( 'success' === $data['sync_status'] ) {
				return [
					'success' => true,
					'message' => $data['message'],
				];
			}
		}

		return [
			'success' => false,
			'message' => 'There was an unknown error, please try again later.',
		];
	}

	/**
	 * Generate the client ID from the current local token and site URL.
	 *
	 * @return string Client ID.
	 */
	private function get_client_id(): string {
		$local_token = $this->get_cloud_setting( 'local_token' );
		$site_host = wp_parse_url( get_site_url(), PHP_URL_HOST );
		return "$site_host-$local_token";
	}

	/**
	 * Verify that a request to connect to or disconnect from cloud is genuine.
	 *
	 * @param string|null $nonce_value Value of nonce to verify, or null if nonce has already been verified.
	 *
	 * @return bool
	 */
	public function verify_action_nonce( string $nonce_value ): bool {
		return wp_verify_nonce( $nonce_value, self::CLOUD_ACTION_NONCE );
	}

	/**
	 * Initialise the process for connecting to Cloud.
	 *
	 * Use verify_connection_nonce() or similar validation before calling this function.
	 *
	 * @return void
	 */
	public function init_cloud_connection() {
		$callback_url = add_query_arg(
			[ 'confirm-authorise-cloud' => true ],
			code_snippets()->get_menu_url( 'settings' )
		);

		$url = add_query_arg(
			[
				'response_type'  => 'code',
				'client_id'      => $this->get_client_id(),
				'code_challenge' => $this->get_cloud_setting( 'code_challenge' ),
				'state'          => $this->get_current_state(),
				'callback_url'   => esc_url_raw( $callback_url ),
			],
			self::get_cloud_url() . 'oauth/login'
		);

		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Exchange the auth code for a bearer token.
	 *
	 * @param string $state     Received state.
	 * @param string $auth_code Authorisation code.
	 *
	 * @return WP_Error|null Error on failure, null on success.
	 */
	public function decode_auth_code( string $state, string $auth_code ): ?WP_Error {
		if ( self::get_current_state() !== $state ) {
			return new WP_Error(
				'snippets_cloud_invalid_state',
				__( 'Did not receive a valid state from Code Snippets Cloud. Please try again.', 'code-snippets' )
			);
		}

		$response = wp_remote_post(
			self::get_cloud_api_url() . 'auth/token',
			[
				'method'  => 'POST',
				'headers' => [
					'Accept'                      => 'application/json',
					'Local-Token'                 => $this->get_cloud_setting( 'local_token' ),
					'Access-Control-Allow-Origin' => '*',
				],
				'body'    => [
					'code'          => $auth_code,
					'client_id'     => $this->get_client_id(),
					'grant_type'    => 'authorization_code',
					'code_verifier' => $this->get_cloud_setting( 'code_verifier' ),
				],
			]
		);

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check the response codes and return accordingly.
		if ( 401 === wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'snippets_cloud_invalid_token',
				esc_html__( 'That token is invalid – please check and try again.', 'code-snippets' )
			);
		}

		if ( empty( $data['token'] ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'snippets_cloud_connection_error',
				esc_html__( 'There was an error connecting to the cloud platform. Please try again later.', 'code-snippets' )
			);
		}

		// Save the token in code snippets cloud settings.
		$this->update_cloud_settings(
			[
				'cloud_token'    => $data['token'],
				'token_verified' => true,
			]
		);

		return null;
	}

	/**
	 * Retrieves a list of all snippets from the cloud API.
	 *
	 * @param integer $page Page of data to retrieve.
	 *
	 * @return Cloud_Snippets|null
	 */
	public function get_codevault_snippets( int $page = 0 ): ?Cloud_Snippets {
		// Return the cached data if available.
		if ( $this->cached_codevault_snippets ) {
			return $this->cached_codevault_snippets;
		}

		// Fetch data from the stored transient, if available.
		$transient_data = get_transient( self::CODEVAULT_SNIPPETS_TRANSIENT_KEY );

		if ( $transient_data instanceof Cloud_Snippets ) {
			$this->cached_codevault_snippets = $transient_data;

			if ( $page === $this->cached_codevault_snippets->page ) {
				return $this->cached_codevault_snippets;
			}
		}

		// Otherwise, fetch from API and store.
		$response = wp_remote_get(
			self::get_cloud_api_url() . 'private/allsnippets?page=' . $page,
			[ 'headers' => $this->build_request_headers() ]
		);

		$data = $this->unpack_request_json( $response );

		if ( ! $data || ! isset( $data['snippets'] ) ) {
			return null;
		}

		foreach ( $data['snippets'] as $key => $snippet ) {
			$data['snippets'][ $key ]['cloud_id'] = $snippet['id'];
		}

		$data['page'] = $page;
		$this->cached_codevault_snippets = new Cloud_Snippets( $data );

		set_transient(
			self::CODEVAULT_SNIPPETS_TRANSIENT_KEY,
			$this->cached_codevault_snippets,
			DAY_IN_SECONDS * self::DAYS_TO_STORE_CS
		);

		return $this->cached_codevault_snippets;
	}

	/**
	 * Search Code Snippets Cloud -> Static Function
	 *
	 * @param string  $search_method Search by name of codevault or keyword(s).
	 * @param string  $search        Search query.
	 * @param integer $page          Search result page to retrieve. Defaults to '0'.
	 *
	 * @return Cloud_Snippets Result of search query.
	 */
	public static function fetch_search_results( string $search_method, string $search, int $page = 0 ): Cloud_Snippets {
		$api_url = add_query_arg(
			[
				's_method'   => $search_method,
				's'          => $search,
				'page'       => $page,
				'site_token' => self::get_local_token(),
				'site_host'  => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			],
			self::get_cloud_api_url() . 'public/search'
		);

		$results = self::unpack_request_json( wp_remote_get( $api_url ) );

		$results = new Cloud_Snippets( $results );
		$results->page = $page;

		return $results;
	}

	/**
	 * Add a new link item to the local-to-cloud map.
	 *
	 * @param Cloud_Link $link Link to add.
	 *
	 * @return void
	 */
	public function add_cloud_link( Cloud_Link $link ) {
		$local_to_cloud_map = get_transient( self::CLOUD_MAP_TRANSIENT_KEY );
		$local_to_cloud_map[] = $link;

		set_transient(
			self::CLOUD_MAP_TRANSIENT_KEY,
			$local_to_cloud_map,
			DAY_IN_SECONDS * self::DAYS_TO_STORE_CS
		);
	}

	/**
	 * Upload a series of local snippets to the cloud platform.
	 *
	 * @param Snippet[] $snippets List of code snippets to store.
	 */
	public function store_snippets_in_cloud( array $snippets ) {
		foreach ( $snippets as $snippet ) {
			$snippet->desc = wp_strip_all_tags( $snippet->desc );

			// Send post request to cs store api with snippet data.
			$response = wp_remote_post(
				self::get_cloud_api_url() . 'private/storesnippet',
				[
					'method'  => 'POST',
					'headers' => $this->build_request_headers(),
					'body'    => [
						'name'     => $snippet->name,
						'desc'     => $snippet->desc,
						'code'     => $snippet->code,
						'scope'    => $snippet->scope,
						'revision' => $snippet->revision,
					],
				]
			);

			$data = $this->unpack_request_json( $response );
			$cloud_id = (string) $data['cloud_id'];
			$revision = (int) $data['revision'];

			// Update the stored local snippet information.
			update_snippet_fields(
				$snippet->id,
				array(
					'cloud_id' => $cloud_id,
					'revision' => $revision,
				)
			);

			$this->clear_caches();
		}
	}

	/**
	 * Update the already-existing remote data for a series of snippets.
	 *
	 * @param Snippet[] $snippets_to_update List of snippets to update.
	 *
	 * @return void
	 */
	public function update_snippets_in_cloud( array $snippets_to_update ) {
		foreach ( $snippets_to_update as $snippet ) {
			$cloud_id_owner = $this->get_cloud_id_and_ownership( $snippet->cloud_id );
			$cloud_id = (int) $cloud_id_owner['cloud_id'];

			// Send post request to cs store api with snippet data.
			$response = wp_remote_post(
				self::get_cloud_api_url() . 'private/updatesnippet/' . $cloud_id,
				[
					'method'  => 'POST',
					'headers' => $this->build_request_headers(),
					'body'    => [
						'name'     => $snippet->name,
						'desc'     => $snippet->desc,
						'code'     => $snippet->code,
						'revision' => $snippet->revision,
						'local_id' => $snippet->id,
					],
				]
			);

			$updated = $this->unpack_request_json( $response );

			if ( $updated && $updated['success'] ) {
				$this->clear_caches();
			}
		}
	}

	/**
	 * Delete a snippet from local-to-cloud map.
	 *
	 * @param int $snippet_id Local snippet ID.
	 *
	 * @return void
	 */
	public function delete_snippet_from_transient_data( int $snippet_id ) {
		if ( ! $this->cached_cloud_links ) {
			$this->get_cloud_links();
		}

		foreach ( $this->cached_cloud_links as $link ) {
			if ( $link->local_id === $snippet_id ) {
				// Remove the link from the local_to_cloud_map.
				$index = array_search( $link, $this->cached_cloud_links, true );
				unset( $this->cached_cloud_links[ $index ] );

				// Update the transient data.
				set_transient(
					self::CLOUD_MAP_TRANSIENT_KEY,
					$this->cached_cloud_links,
					DAY_IN_SECONDS * self::DAYS_TO_STORE_CS
				);
			}
		}
	}

	/**
	 * Retrieve a single cloud snippet from the API.
	 *
	 * @param int $cloud_id Remote cloud snippet ID.
	 *
	 * @return Cloud_Snippet Retrieved snippet.
	 */
	public static function get_single_snippet_from_cloud( int $cloud_id ): Cloud_Snippet {
		$url = self::get_cloud_api_url() . sprintf( 'public/getsnippet/%s', $cloud_id );
		$response = wp_remote_get( $url );
		$cloud_snippet = self::unpack_request_json( $response );

		return new Cloud_Snippet( $cloud_snippet['snippet'] );
	}

	/**
	 * Get the current revision of a single cloud snippet.
	 *
	 * @param string $cloud_id Cloud snippet ID.
	 *
	 * @return string|null Revision number on success, null otherwise.
	 */
	public static function get_cloud_snippet_revision( string $cloud_id ): ?string {
		$api_url = self::get_cloud_api_url() . sprintf( 'public/getsnippetrevision/%s', $cloud_id );
		$body = wp_remote_retrieve_body( wp_remote_get( $api_url ) );

		if ( ! $body ) {
			return null;
		}

		$cloud_snippet_revision = json_decode( $body, true );
		return $cloud_snippet_revision['snippet_revision'] ?? null;
	}

	/**
	 * Get list of all bundles from the cloud API.
	 *
	 * @return array|null Bundle name and id, null otherwise.
	 */
	public static function get_bundles(): ?array {
		$response = wp_remote_get(
			self::get_cloud_api_url() . 'private/bundles',
			[ 'headers' => self::build_request_headers() ]
		);
		return self::unpack_request_json( $response );
	}

	/**
	 * Get List of Snippets from a Bundle from the cloud API.
	 *
	 * @param int $bundle_id Bundle ID.
	 *
	 * @return Cloud_Snippets
	 */
	public function get_snippets_from_bundle( int $bundle_id ): Cloud_Snippets {
		$api_url = self::get_cloud_api_url() . sprintf( 'private/getbundle/%s', $bundle_id );
		$response = wp_remote_post(
			$api_url,
			[
				'method'  => 'POST',
				'headers' => $this->build_request_headers(),
			]
		);

		$results = self::unpack_request_json( $response );
		$results = new Cloud_Snippets( $results );
		$results->page = 1;

		return $results;
	}

	/**
	 * Get List of Snippets from a Shared Bundle from the cloud API.
	 *
	 * @param string $bundle_share_name Bundle share name.
	 *
	 * @return Cloud_Snippets
	 */
	public function get_snippets_from_shared_bundle( string $bundle_share_name ): Cloud_Snippets {
		$api_url = self::get_cloud_api_url() . sprintf( 'private/getsharedbundle?share_name=%s', $bundle_share_name );
		$response = wp_remote_post(
			$api_url,
			[
				'method'  => 'POST',
				'headers' => $this->build_request_headers(),
			]
		);

		$results = self::unpack_request_json( $response );
		$results = new Cloud_Snippets( $results );
		$results->page = 1;

		return $results;
	}

	/**
	 * Download a snippet from the cloud.
	 *
	 * @param int|string $cloud_id       The cloud ID of the snippet as string from query args.
	 * @param string     $source         The source table of the snippet: 'codevault' or 'search'.
	 * @param string     $action         The action to be performed: 'download' or 'update'.
	 * @param int        $codevault_page The current page of the codevault.
	 *
	 * @return array<string, string|bool> Result of operation: an array with `success` and `error_message` keys.
	 */
	public function download_or_update_snippet( int $cloud_id, string $source, string $action, int $codevault_page ): array {
		$cloud_id = intval( $cloud_id );

		switch ( $source ) {
			case 'codevault':
				$in_codevault = true;
				$snippet_to_store = $this->get_single_snippet_from_codevault( $cloud_id, $codevault_page );
				$snippet_to_store = reset( $snippet_to_store );
				break;
			case 'search':
				$in_codevault = false;
				$snippet_to_store = $this->get_single_snippet_from_cloud( $cloud_id );
				break;
			default:
				return [
					'success' => false,
					'error'   => 'Invalid source.',
				];
		}

		// Get all cloud_id from the Cloud Link Map and send this as a json array to the cloud.
		$cloud_snippets_synced_to_local_site = array_map(
			function ( $link ) {
				// Check if the cloud_id is not empty, null or 0.
				return ! $link->cloud_id || 0 !== $link->cloud_id ? $link->cloud_id : null;
			},
			$this->get_cloud_links()
		);

		// Send the cloud_ids to the cloud.
		wp_remote_post(
			self::get_cloud_api_url() . 'private/setsyncedsnippetlist',
			[
				'method'  => 'POST',
				'headers' => $this->build_request_headers(),
				'body'    => [
					'cloud_id_array' => wp_json_encode( $cloud_snippets_synced_to_local_site ),
				],
			]
		);

		switch ( $action ) {
			case 'download':
				// Convert snippet to store to an array.
				$snippet_to_store = [ $snippet_to_store ];
				return $this->store_snippets_from_cloud_to_local( $snippet_to_store, $in_codevault );
			case 'update':
				return $this->update_snippet_from_cloud( $snippet_to_store );
			default:
				return [
					'success' => false,
					'error'   => __( 'Invalid action.', 'code-snippets' ),
				];
		}
	}

	/**
	 * Get a single snippet from the codevault.
	 *
	 * @param int $actual_cloud_id The cloud ID of the snippet.
	 * @param int $current_page    The current page of the codevault.
	 *
	 * @return Cloud_Snippet[]|null The snippet object on success, null otherwise.
	 */
	public function get_single_snippet_from_codevault( int $actual_cloud_id, int $current_page ): ?array {
		$snippets = $this->get_codevault_snippets( $current_page );
		// Filter the cloud snippet array to get the snippet that is to be saved to the database.
		return array_filter(
			$snippets->snippets,
			function ( $snippet ) use ( $actual_cloud_id ) {
				return $snippet->id === $actual_cloud_id;
			}
		);
	}

	/**
	 * Download a snippet from the cloud.
	 *
	 * @param Cloud_Snippet $snippet_to_store The snippet to be downloaded.
	 * @param bool          $in_codevault     Whether the snippet is in the codevault or not.
	 *
	 * @return array The result of the download.
	 */
	public function store_single_snippet_from_cloud_to_local( Cloud_Snippet $snippet_to_store, bool $in_codevault ): array {
		$snippet = new Snippet( $snippet_to_store );

		// Set the snippet id to 0 to ensure that the snippet is saved as a new snippet.
		$ownership = $snippet_to_store->is_owner ? '1' : '0';
		$snippet->id = 0;
		$snippet->active = 0;
		$snippet->cloud_id = $snippet_to_store->id . '_' . $ownership;
		$snippet->desc = $snippet_to_store->description ? $snippet_to_store->description : '';

		// Save the snippet to the database.
		$new_snippet = save_snippet( $snippet );

		$link = new Cloud_Link();
		$link->local_id = $new_snippet->id;
		$link->cloud_id = $snippet_to_store->id;
		$link->is_owner = $snippet_to_store->is_owner;
		$link->in_codevault = $in_codevault;
		$link->update_available = false;

		$this->add_cloud_link( $link );

		return [
			'success'    => true,
			'action'     => 'Single Downloaded',
			'snippet_id' => $new_snippet->id,
			'link_id'    => $link->cloud_id,
		];
	}

	/**
	 * Download snippets from the cloud.
	 *
	 * @param Cloud_Snippet[] $snippets_to_store The snippet to be downloaded.
	 * @param bool            $in_codevault      Whether the snippet is in the codevault or not.
	 *
	 * @return array The result of the download.
	 */
	public function store_snippets_from_cloud_to_local( array $snippets_to_store, bool $in_codevault ): array {
		if ( 1 === count( $snippets_to_store ) ) {
			return $this->store_single_snippet_from_cloud_to_local( $snippets_to_store[0], $in_codevault );
		}

		foreach ( $snippets_to_store as $snippet_to_store ) {
			$this->store_single_snippet_from_cloud_to_local( $snippet_to_store, $in_codevault );
		}

		return count( $snippets_to_store ) > 1 ?
			[
				'success' => true,
				'action'  => __( 'Downloaded', 'code-snippets' ),
			] :
			[
				'success' => false,
				'error'   => __( 'There was a problem saving or no snippets found to download.', 'code-snippets' ),
			];
	}

	/**
	 * Update a snippet from the cloud.
	 *
	 * @param Cloud_Snippet $snippet_to_store Snippet to be updated.
	 *
	 * @return array The result of the update.
	 */
	public function update_snippet_from_cloud( Cloud_Snippet $snippet_to_store ): array {
		$cloud_id = $snippet_to_store->id . '_' . ( $snippet_to_store->is_owner ? '1' : '0' );

		$local_snippet = get_snippet_by_cloud_id( sanitize_key( $cloud_id ) );

		// Only update the code, active and revision fields.
		$fields = [
			'code'     => $snippet_to_store->code,
			'active'   => false,
			'revision' => $snippet_to_store->revision,
		];

		update_snippet_fields( $local_snippet->id, $fields );
		$this->clear_caches();

		return [
			'success' => true,
			'action'  => __( 'Updated', 'code-snippets' ),
		];
	}

	/**
	 * Find the cloud link for a given local snippet identifier.
	 *
	 * @param Snippet $snippet Local snippet.
	 *
	 * @return Cloud_Link|null
	 */
	public function get_link_for_snippet( Snippet $snippet ): ?Cloud_Link {
		$cloud_links = $this->get_cloud_links();

		if ( $cloud_links ) {
			foreach ( $cloud_links as $cloud_link ) {
				if ( $cloud_link->local_id === $snippet->id ) {
					return $cloud_link;
				}
			}
		}

		return null;
	}

	/**
	 * Find the cloud link for a given cloud snippet identifier.
	 *
	 * @param int $cloud_id Cloud ID.
	 *
	 * @return Cloud_Link|null
	 */
	public function get_link_for_cloud_id( int $cloud_id ): ?Cloud_Link {
		$cloud_links = $this->get_cloud_links();

		if ( $cloud_links ) {
			foreach ( $cloud_links as $cloud_link ) {
				if ( $cloud_link->cloud_id === $cloud_id ) {
					return $cloud_link;
				}
			}
		}

		return null;
	}


	/**
	 * Find the cloud link for a given cloud snippet.
	 *
	 * @param Cloud_Snippet $cloud_snippet Cloud snippet.
	 *
	 * @return Cloud_Link|null
	 */
	public function get_link_for_cloud_snippet( Cloud_Snippet $cloud_snippet ): ?Cloud_Link {
		return $this->get_link_for_cloud_id( $cloud_snippet->id );
	}

	/**
	 * Translate a snippet scope to a type.
	 *
	 * @param string $scope The scope of the snippet.
	 *
	 * @return string The type of the snippet.
	 */
	public static function get_type_from_scope( string $scope ): string {
		switch ( $scope ) {
			case 'global':
				return 'php';
			case 'site-css':
				return 'css';
			case 'site-footer-js':
				return 'js';
			case 'content':
				return 'html';
			default:
				return '';
		}
	}

	/**
	 * Translate a snippet status to a status-name.
	 *
	 * @param int $status The scope of the snippet.
	 *
	 * @return string The style to be used for the stats badge.
	 */
	public static function get_status_name_from_status( int $status ): string {
		switch ( $status ) {
			case 3:
				return __( 'Private', 'code-snippets' );
			case 4:
				return __( 'Public', 'code-snippets' );
			case 5:
				return __( 'Unverified', 'code-snippets' );
			case 6:
				return __( 'AI Verified', 'code-snippets' );
			case 8:
				return __( 'Pro Verified', 'code-snippets' );
			default:
				return '';
		}
	}

	/**
	 * Renders the html for the preview thickbox popup.
	 *
	 * @return void
	 */
	public static function render_cloud_snippet_thickbox() {
		add_thickbox();
		?>
		<div id="show-code-preview" style="display: none;">
			<h3 id="snippet-name-thickbox"></h3>
			<h4><?php esc_html_e( 'Snippet Code:', 'code-snippets' ); ?></h4>
			<pre class="thickbox-code-viewer">
				<code id="snippet-code-thickbox"></code>
			</pre>
		</div>
		<?php
	}

	/**
	 * Remove cloud connection and clear synced data.
	 */
	public function remove_sync() {
		$this->update_cloud_settings(
			[
				'cloud_token'    => '',
				'token_verified' => false,
				'local_token'    => '',
				'code_challenge' => '',
				'code_verifier'  => '',
			]
		);

		$this->clear_caches();
	}

	/**
	 * Refresh the cached synced data.
	 *
	 * @return void
	 */
	public function clear_caches() {
		$this->cached_cloud_links = null;
		$this->cached_codevault_snippets = null;

		delete_transient( self::CLOUD_MAP_TRANSIENT_KEY );
		delete_transient( self::CODEVAULT_SNIPPETS_TRANSIENT_KEY );
	}

	/**
	 * Unsync local snippets from the cloud
	 *
	 * @param Snippet[] $snippets List of code snippets to remove sync.
	 */
	public function remove_snippets_from_cloud( array $snippets ) {
		foreach ( $snippets as $snippet ) {
			update_snippet_fields( $snippet->id, [ 'cloud_id' => null ], $snippet->network );

			$this->delete_snippet_from_transient_data( $snippet->id );
		}
	}
}
