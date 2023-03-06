<?php


namespace Code_Snippets;

use function Code_Snippets\Settings\get_setting;

/**
 * Functions used to manage cloud synchronisation.
 *
 * @package Code_Snippets
 */
class CS_Cloud {

	/**
	 * Base URL for cloud API.
	 *
	 * @var String
	 */
	const CLOUD_API_URL = 'https://codesnippets.cloud/api/v1/';


	/**
	 * Base URL to cloud platform.
	 *
	 * @var String
	 */
	const CLOUD_URL = 'https://codesnippets.cloud/';

	/**
	 * Days to store for cloud snippets.
	 *
	 * @var integer
	 */
	const DAYS_TO_STORE_CS = 1;


	/**
	 * Cloud API key.
	 *
	 * @var string
	 */
	private $cloud_key = '';

	/**
	 * Verification status of cloud API key.
	 *
	 * @var boolean
	 */
	private $cloud_key_is_verified = false;

	/**
	 * Cloud Snippets Object
	 *
	 * @var array
	 */
	public $cloud_snippets;

	/**
	 * Codevault Snippets Object
	 *
	 * @var array
	 */
	public $codevault_snippets;

	/**
	 * Local to Cloud Snippets Map Object
	 *
	 * @var array
	 */
	public $local_to_cloud_map;


	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->cloud_key = get_setting( 'cloud', 'cloud_token' );
		$this->cloud_key_is_verified = get_setting( 'cloud', 'token_verified' );
		$this->codevault_snippets = get_transient( 'cs_codevault_snippets' );
		$this->local_to_cloud_map = get_transient( 'cs_local_to_cloud_map' );
		$this->init();
	}

	/**
	 * Initialise class functions.
	 *
	 * @return void
	 */
	public function init() {
		//Enqueue Prism Files
		$this->enqueue_all_prism_themes();
		//If no codevault snippets transient object then grab from api and store as transient
		if ( empty( $this->codevault_snippets ) ) {
			$this->codevault_snippets = $this->get_codevault_snippets();
			set_transient( 'cs_codevault_snippets', $this->codevault_snippets, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );
		}
		//If no local to cloud map transient object then generate this map and store as transient
		if ( empty( $this->local_to_cloud_map ) ) {
			$this->local_to_cloud_map = $this->generate_local_to_cloud_map();
		}

		$this->process_refresh_synced_data_request();
	}

	/**
	 * Check if the API key is set and verified.
	 *
	 * @return boolean
	 */
	public function is_cloud_connection_available() {
		return $this->cloud_key && $this->cloud_key_is_verified && $this->cloud_key_is_verified !== 'false';
	}

	/**
	 * Display Cloud Key Notice
	 *
	 * @return void
	 */
	public function display_cloud_key_notice() {
		$message = sprintf(
			__( 'Please enter a valid Cloud API Token in the <a href="%s">Cloud Settings</a> to enable Cloud Sync.', 'code-snippets' ),
			esc_url( add_query_arg( 'section', 'cloud', code_snippets()->get_menu_url( 'settings ' ) ) )
		);

		printf(
			'<div class="notice notice-error is-dismissible""><p>%s</p></div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Get cloud snippets
	 * Sends a request to the cloud api to get all snippets
	 *
	 * @return array<object>|bool
	 */
	public function get_codevault_snippets() {
		$url = self::CLOUD_API_URL . 'private/allsnippets';
		$cloud_api_key = get_setting( 'cloud', 'cloud_token' );
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $cloud_api_key,
			),
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$snippets = json_decode( $body, true );
		return $snippets['snippets'];
	}


	/**
	 * Create Local to Cloud Map to keep track of local snippets
	 * that have been synced to the cloud
	 *
	 * @return void
	 */
	public function generate_local_to_cloud_map() {
		$snippet_revision_array = []; //e.g. cloud_id => revision -> [163_1 => 2 ]
		$local_to_cloud_map = []; //e.g. local_id, cloud_id, downloaded, update_available
		$codevault_snippet_ids = [];

		//wp_die(var_dump($this->codevault_snippets));
		foreach ( $this->codevault_snippets as $codevault_snippet ) {
			//Get snippet revision and id and store in array
			$snippet_revision_array[ $codevault_snippet['cloud_id'] ] = $codevault_snippet['revision'];
			$codevault_snippet_ids[] = $codevault_snippet['cloud_id'];
		}

		//Get all local snippets stored in db
		$local_snippets = get_snippets( array() );
		//Loop through local snippets
		foreach ( $local_snippets as $local_snippet ) {
			//check if cloud id is null and if so skip this item
			if ( $local_snippet->cloud_id == null ) {
				continue;
			}
			//Check if snippet is a synced codevault snippet
			if ( in_array( $local_snippet->cloud_id, $codevault_snippet_ids ) ) {
				$in_codevault = true;
				//Check if local revision is less than cloud revision
				if ( intval( $local_snippet->revision ) < intval( $snippet_revision_array[ $local_snippet->cloud_id ] ) ) {
					$update_available = true;
				} else {
					$update_available = false;
				}
			} else {
				$cloud_snippet_revision = $this->get_cloud_snippet_revision( $local_snippet->cloud_id );
				$in_codevault = false;
				if ( intval( $local_snippet->revision ) < intval( $cloud_snippet_revision ) ) {
					$update_available = true;
				} else {
					$update_available = false;
				}
			}

			$local_to_cloud_map[] = [
				'local_id'         => $local_snippet->id,
				'cloud_id'         => $local_snippet->cloud_id,
				'in_codevault'     => $in_codevault,
				'update_available' => $update_available,
			];
		}
		set_transient( 'cs_local_to_cloud_map', $local_to_cloud_map, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );
	}

	/**
	 * Enqueue all available Prism themes.
	 *
	 * @return void
	 */
	public function enqueue_all_prism_themes() {
		Frontend::register_prism_assets();

		foreach ( Frontend::get_prism_themes() as $theme => $label ) {
			wp_enqueue_style( Frontend::get_prism_theme_style_handle( $theme ) );
		}

		wp_enqueue_style( Frontend::PRISM_HANDLE );
		wp_enqueue_script( Frontend::PRISM_HANDLE );
	}

	/**
	 * Process a request to refresh all synced data.
	 *
	 * @return array<string, bool|string>
	 */
	public function process_refresh_synced_data_request() {
		return isset( $_GET['refresh'] ) && $_GET['refresh'] ? [
			'success' => true,
			'message' => __( 'Synced data refreshed successfully', 'code-snippets' ),
		] : [];
	}

	/**
	 * Refresh all transient data.
	 *
	 * @return boolean
	 */
	public function refresh_synced_data() {
		//Delete local to cloud map transient
		delete_transient( 'cs_local_to_cloud_map' );
		//Delete cloud snippets transient
		delete_transient( 'cs_codevault_snippets' );

		//Get cloud snippets and store in transient
		$this->codevault_snippets = $this->get_codevault_snippets();
		set_transient( 'cs_codevault_snippets', $this->codevault_snippets, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );

		//Get local to cloud map and store in transient
		$this->local_to_cloud_map = $this->generate_local_to_cloud_map();

		return true;
	}

	/** Static Methods **/

	/**
	 * Store Snippets in Cloud - Static Function
	 *
	 * @param Snippet $snippets json data
	 *
	 */
	public static function store_snippets_to_cloud( $snippets ) {
		$cloud_api_key = get_setting( 'cloud', 'cloud_token' );
		/** Snippet @var Snippet $snippet */
		foreach ( $snippets as $snippet ) {
			//send post request to cs store api with snippet data
			$cs_stre_api_response = wp_remote_post( self::CLOUD_API_URL . 'private/storesnippet', array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'Bearer ' . $cloud_api_key,
				),
				'body'    => array(
					'name'     => $snippet->name,
					'desc'     => $snippet->desc,
					'code'     => $snippet->code,
					'scope'    => $snippet->scope,
					'revision' => $snippet->revision,
				),
			) );
			//get response body
			$body = wp_remote_retrieve_body( $cs_stre_api_response );
			//decode json response
			$cloud_snippet = json_decode( $body, true );
			//update snippet fields
			$update = update_snippet_fields( $snippet->id,
				array(
					'cloud_id' => $cloud_snippet['cloud_id'],
					'revision' => $snippet->revision ? $snippet->revision : $cloud_snippet['revision'],
				)
			);
			//update local to cloud map transient
			$local_to_cloud_map = get_transient( 'cs_local_to_cloud_map' );
			$local_to_cloud_map[] = array(
				'local_id'         => $snippet->id,
				'cloud_id'         => $cloud_snippet['cloud_id'],
				'in_codevault'     => true,
				'update_available' => false,
			);
			set_transient( 'cs_local_to_cloud_map', $local_to_cloud_map, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );

			//Update codevault snippet transient
			delete_transient( 'cs_codevault_snippets' );
			$cloud_snippets = self::get_codevault_snippets();
			set_transient( 'cs_codevault_snippets', $cloud_snippets, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );

		}
	}

	/**
	 * Update Snippet in the Cloud - Static Function
	 *
	 * @param array of Snippets to update
	 *
	 */
	public static function update_snippet_in_cloud( $snippets_to_update ) {
		//wp_die(var_dump($snippets_to_update) );
		$cloud_api_key = get_setting( 'cloud', 'cloud_token' );
		/** Snippet @var Snippet $snippet */
		foreach ( $snippets_to_update as $snippet ) {
			$cloud_id = explode( '_', $snippet->cloud_id );
			//send post request to cs store api with snippet data
			$cs_update_response = wp_remote_post( self::CLOUD_API_URL . 'private/updatesnippet', array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'Bearer ' . $cloud_api_key,
				),
				'body'    => array(
					'name'     => $snippet->name,
					'desc'     => $snippet->desc,
					'code'     => $snippet->code,
					'revision' => $snippet->revision,
					'cloud_id' => $cloud_id[0],
					'local_id' => $snippet->id,
				),
			) );
			//get response body
			$body = wp_remote_retrieve_body( $cs_update_response );
			//decode json response
			$updated = json_decode( $body, true );
			//Check if success in  response is true or false
			if ( $updated['success'] == true ) {
				//Update codevault snippet transient
				delete_transient( 'cs_codevault_snippets' );
				$cloud_snippets = self::get_codevault_snippets();
				set_transient( 'cs_codevault_snippets', $cloud_snippets, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );
				//Update local to cloud map transient
				$local_to_cloud_map = get_transient( 'cs_local_to_cloud_map' );
				//find the key of the snippet in the map using the local id from response
				$key = array_search( $updated['local_id'], array_column( $local_to_cloud_map, 'local_id' ) );
				//update the revision in the map
				$local_to_cloud_map[ $key ]['update_available'] = false;
				set_transient( 'cs_local_to_cloud_map', $local_to_cloud_map, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );
			}
		}
	}

	/**
	 * Delete a snippet from local-to-cloud map.
	 *
	 * @param integer $snippet_id Local snippet ID.
	 *
	 * @return void
	 */
	public static function delete_snippet_from_transient_data( $snippet_id ) {
		$local_to_cloud_map = get_transient( 'cs_local_to_cloud_map' );

		foreach ( $local_to_cloud_map as $key => $value ) {
			if ( $value['local_id'] === $snippet_id ) {
				unset( $local_to_cloud_map[ $key ] );
			}
		}

		set_transient( 'cs_local_to_cloud_map', $local_to_cloud_map, ( DAY_IN_SECONDS * self::DAYS_TO_STORE_CS ) );
	}

	/**
	 * Search Code Snippets Cloud -> Static Function
	 *
	 * @param string  $search Search query.
	 * @param integer $page   Search result page to retrieve. Defaults to '0'.
	 *
	 * @return object|null Result of search query.
	 */
	public static function fetch_search_results( $search, $page = 0 ) {
		$api_url = self::CLOUD_API_URL . 'public/search';

		// Send a GET request to request url with search query.
		$body = wp_remote_retrieve_body(
			wp_remote_get( add_query_arg( [
				's'    => $search,
				'page' => $page,
			], $api_url ) )
		);

		return $body ? json_decode( $body, true ) : null;
	}

	/**
	 * Get Single Cloud Snippets -> Static Function
	 *
	 * @param String $cloud_id
	 *
	 * @return Object $cloud_snippets
	 */
	public static function get_single_cloud_snippet( $cloud_id ) {
		//construct api endpoint request url
		$api_url = self::CLOUD_API_URL . 'public/getsnippet/' . $cloud_id;
		$site_token = get_setting( 'cloud', 'local_token' );
		//Get site host name
		$site_host = parse_url( get_site_url(), PHP_URL_HOST );
		//Send GET request to request url with search query
		$response = wp_remote_get( $api_url . '?site_host=' . $site_host . '&site_token=' . $site_token );
		//get response body
		$body = wp_remote_retrieve_body( $response );
		//decode json response
		$cloud_snippet = json_decode( $body, true );
		//return cloud snippets
		return $cloud_snippet;
	}

	/**
	 * Get the current revision of a single cloud snippet.
	 *
	 * @param string $cloud_id Cloud snippet ID.
	 *
	 * @return string|null Revision number on success, null otherwise.
	 */
	public static function get_cloud_snippet_revision( $cloud_id ) {
		$api_url = sprintf( "%s/public/getsnippetrevision/%s", untrailingslashit( self::CLOUD_API_URL ), $cloud_id );
		$body = wp_remote_retrieve_body( wp_remote_get( $api_url ) );

		if ( ! $body ) {
			return null;
		}

		$cloud_snippet_revision = json_decode( $body, true );
		return isset( $cloud_snippet_revision['snippet_revision'] ) ? $cloud_snippet_revision['snippet_revision'] : null;
	}

	/**
	 * Download a snippet from the cloud TODO: ***MOVE TO CLOUD CLASS**
	 *
	 * @param string $cloud_id The Cloud ID of the snippet to download
	 * @param string $source   The source table of the snippet - codevault or search
	 * @param string $action   The action to be performed - download or update
	 *
	 * @return bool|string True if the snippet was downloaded successfully, or an error message
	 */
	public static function download_or_update_snippet( $cloud_id, $source, $action ) {
		//Check source and get the snippet to be downloaded
		if ( 'codevault' == $source ) {
			//Get Snippets currently store in transient object
			$codevault_snippets = get_transient( 'cs_codevault_snippets' );
			//Filter the cloud snippet array to get the snippet that is to be saved to the database
			$snippet_to_store = array_filter( $codevault_snippets, function ( $var ) use ( $cloud_id ) {
				return ( $var['cloud_id'] == $cloud_id );
			} );
			$in_codevault = true;
		}
		if ( 'search' == $source ) {
			//Get snippet from cloud using id
			$snippet_to_store = reset( CS_Cloud::get_single_cloud_snippet( $cloud_id ) );
			$in_codevault = false;
		}
		$local_to_cloud_map = get_transient( 'cs_local_to_cloud_map' );
		//Check if action was download or update
		if ( 'download' == $action ) {
			//Create a new snippet object
			$snippet = new Snippet();
			//Set the fields of the snippet object
			$snippet->set_fields( $snippet_to_store );
			//Set the snippet id to 0 to ensure that the snippet is saved as a new snippet
			$snippet->id = 0;
			$snippet->active = 0;
			///Save the snippet to the database
			$new_snippet_id = save_snippet( $snippet );

			//Add the snippet to the local to cloud map
			$local_to_cloud_map[] = array(
				'local_id'         => $new_snippet_id,
				'cloud_id'         => $snippet_to_store['cloud_id'],
				'in_codevault'     => $in_codevault,
				'update_available' => false,
			);
			set_transient( 'cs_local_to_cloud_map', $local_to_cloud_map );

			return [
				'success' => true,
				'action'  => 'Downloaded',
			];
		}

		if ( 'update' == $action ) {
			$local_snippet = get_snippet_by_cloud_id( sanitize_key( $cloud_id ) );
			$updated_snippet_data = reset( $snippet_to_store );
			//Set fields to update: Just update the code, revision number and deactive the snippet by default
			$fields = [
				'code'     => $updated_snippet_data['code'],
				'active'   => false,
				'revision' => $updated_snippet_data['revision'],
			];
			$update = update_snippet_fields( $local_snippet->id, $fields );
			$snippet_id = $local_snippet->id;
			//Update the local to cloud map
			$local_to_cloud_map = array_map( function ( $local_to_cloud_map_item ) use ( $snippet_id ) {
				if ( $local_to_cloud_map_item['local_id'] == $snippet_id ) {
					$local_to_cloud_map_item['update_available'] = false;
				}
				return $local_to_cloud_map_item;
			}, $local_to_cloud_map );
			//update the transient object
			set_transient( 'cs_local_to_cloud_map', $local_to_cloud_map );
			//Update the snippet in the cs codevault transient object
			$codevault_snippets = get_transient( 'cs_codevault_snippets' );
			$codevault_snippets = array_map( function ( $codevault_snippet ) use ( $snippet_id, $updated_snippet_data ) {
				if ( $codevault_snippet['id'] == $snippet_id ) {
					$codevault_snippet['code'] = $updated_snippet_data['code'];
					$codevault_snippet['revision'] = $updated_snippet_data['revision'];
				}
				return $codevault_snippet;
			}, $codevault_snippets );
			//update the transient object
			set_transient( 'cs_codevault_snippets', $codevault_snippets );

			return [
				'success' => true,
				'action'  => 'Updated',
			];
		}
	}

}
