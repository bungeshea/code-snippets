<?php

namespace Code_Snippets\REST_API;

use Code_Snippets\Export;
use Code_Snippets\Snippet;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function Code_Snippets\activate_snippet;
use function Code_Snippets\code_snippets;
use function Code_Snippets\deactivate_snippet;
use function Code_Snippets\delete_snippet;
use function Code_Snippets\get_snippet;
use function Code_Snippets\get_snippets;
use function Code_Snippets\save_snippet;
use const Code_Snippets\REST_API_NAMESPACE;

/**
 * Allows fetching snippet data through the WordPress REST API.
 *
 * @since   3.4.0
 * @package Code_Snippets
 */
final class Snippets_REST_Controller extends WP_REST_Controller {

	/**
	 * Current API version.
	 */
	const VERSION = 1;

	/**
	 * The base of this controller's route.
	 */
	const BASE_ROUTE = 'snippets';

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = REST_API_NAMESPACE . self::VERSION;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = self::BASE_ROUTE;

	/**
	 * Retrieve this controller's REST API base path, including namespace.
	 *
	 * @return string
	 */
	public static function get_base_route(): string {
		return REST_API_NAMESPACE . self::VERSION . '/' . self::BASE_ROUTE;
	}

	/**
	 * Retrieve the full base route including the REST API prefix.
	 *
	 * @return string
	 */
	public static function get_prefixed_base_route(): string {
		return '/' . rtrim( rest_get_url_prefix(), '/\\' ) . '/' . self::get_base_route();
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		$route = '/' . $this->rest_base;
		$id_route = $route . '/(?P<id>[\d]+)';

		$network_args = array_intersect_key(
			$this->get_endpoint_args_for_item_schema(),
			[ 'network' ]
		);

		register_rest_route(
			$this->namespace,
			$route,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $network_args,
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$id_route,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => $network_args,
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => $network_args,
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$route . '/schema',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_public_item_schema' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			$id_route . '/activate',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'activate_item' ],
				'permission_callback' => [ $this, 'update_item_permissions_check' ],
				'schema'              => [ $this, 'get_item_schema' ],
				'args'                => $network_args,
			]
		);

		register_rest_route(
			$this->namespace,
			$id_route . '/deactivate',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'deactivate_item' ],
				'permission_callback' => [ $this, 'update_item_permissions_check' ],
				'schema'              => [ $this, 'get_item_schema' ],
				'args'                => $network_args,
			]
		);

		register_rest_route(
			$this->namespace,
			$id_route . '/export',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'schema'              => [ $this, 'get_item_schema' ],
				'args'                => $network_args,
			]
		);

		register_rest_route(
			$this->namespace,
			$id_route . '/export-code',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_item_code' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'schema'              => [ $this, 'get_item_schema' ],
				'args'                => $network_args,
			]
		);
	}

	/**
	 * Retrieves a collection of snippets.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_items( $request ): WP_REST_Response {
		$snippets = get_snippets();
		$snippets_data = [];

		foreach ( $snippets as $snippet ) {
			$snippet_data = $this->prepare_item_for_response( $snippet, $request );
			$snippets_data[] = $this->prepare_response_for_collection( $snippet_data );
		}

		return rest_ensure_response( $snippets_data );
	}

	/**
	 * Retrieves one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success.
	 */
	public function get_item( $request ) {
		$snippet_id = $request->get_param( 'id' );
		$item = get_snippet( $snippet_id, $request->get_param( 'network' ) );

		if ( ! $item->id && 0 !== $snippet_id && '0' !== $snippet_id ) {
			return new WP_Error(
				'rest_cannot_get',
				__( 'The snippet could not be found.', 'code-snippets' ),
				[ 'status' => 500 ]
			);
		}

		$data = $this->prepare_item_for_response( $item, $request );
		return rest_ensure_response( $data );
	}

	/**
	 * Create one item from the collection
	 *
	 * @param WP_REST_Request|array $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$snippet = $this->prepare_item_for_database( $request );
		$result = $snippet ? save_snippet( $snippet ) : null;

		return $result ?
			$this->prepare_item_for_response( $result, $request ) :
			new WP_Error(
				'rest_cannot_create',
				__( 'The snippet could not be created.', 'code-snippets' ),
				[ 'status' => 500 ]
			);
	}

	/**
	 * Update one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$snippet_id = absint( $request->get_param( 'id' ) );
		$snippet = $snippet_id ? get_snippet( $snippet_id, $request->get_param( 'network' ) ) : null;

		if ( ! $snippet_id || ! $snippet || ! $snippet->id ) {
			return new WP_Error(
				'rest_cannot_update',
				__( 'Cannot update a snippet without a valid ID.', 'code-snippets' ),
				[ 'status' => 400 ]
			);
		}

		$item = $this->prepare_item_for_database( $request, $snippet );
		$result = save_snippet( $item );

		if ( $result ) {
			$request->set_param( 'id', $result->id );
			return $this->get_item( $request );
		}

		return new WP_Error(
			'rest_cannot_update',
			__( 'The snippet could not be updated.', 'code-snippets' ),
			[ 'status' => 500 ]
		);
	}

	/**
	 * Delete one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$item = $this->prepare_item_for_database( $request );
		$result = delete_snippet( $item->id, $item->network );

		return $result ?
			new WP_REST_Response( null, 204 ) :
			new WP_Error(
				'rest_cannot_delete',
				__( 'The snippet could not be deleted.', 'code-snippets' ),
				[ 'status' => 500 ]
			);
	}

	/**
	 * Activate one item in the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function activate_item( WP_REST_Request $request ) {
		$item = $this->prepare_item_for_database( $request );
		$result = activate_snippet( $item->id, $item->network );

		return $result instanceof Snippet ?
			rest_ensure_response( $result ) :
			new WP_Error(
				'rest_cannot_activate',
				$result,
				[ 'status' => 500 ]
			);
	}

	/**
	 * Deactivate one item in the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function deactivate_item( WP_REST_Request $request ) {
		$item = $this->prepare_item_for_database( $request );
		$result = deactivate_snippet( $item->id, $item->network );

		return $result instanceof Snippet ?
			rest_ensure_response( $result ) :
			new WP_Error(
				'rest_cannot_activate',
				__( 'The snippet could not be deactivated.', 'code-snippets' ),
				[ 'status' => 500 ]
			);
	}

	/**
	 * Prepare an instance of the Export class from a request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return Export
	 */
	protected function build_export( WP_REST_Request $request ): Export {
		$item = $this->prepare_item_for_database( $request );
		return new Export( [ $item->id ], $item->network );
	}

	/**
	 * Retrieve one item in the collection in JSON export format.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function export_item( WP_REST_Request $request ) {
		$export = $this->build_export( $request );
		$result = $export->create_export_object();
		return rest_ensure_response( $result );
	}

	/**
	 * Retrieve one item in the collection in the code export format.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function export_item_code( WP_REST_Request $request ) {
		$export = $this->build_export( $request );
		$result = $export->export_snippets_code();

		return rest_ensure_response( $result );
	}

	/**
	 * Prepares one item for create or update operation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param Snippet|null    $item    Existing item to augment.
	 *
	 * @return Snippet The prepared item.
	 */
	protected function prepare_item_for_database( $request, ?Snippet $item = null ): ?Snippet {
		if ( ! $item instanceof Snippet ) {
			$item = new Snippet();
		}

		foreach ( $item->get_allowed_fields() as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$item->set_field( $field, $request[ $field ] );
			}
		}

		return $item;
	}

	/**
	 * Prepare the item for the REST response.
	 *
	 * @param Snippet         $item    Snippet object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$schema = $this->get_item_schema();
		$response = [];

		foreach ( array_keys( $schema['properties'] ) as $property ) {
			$response[ $property ] = $item->$property;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return boolean
	 */
	public function get_items_permissions_check( $request ): bool {
		return code_snippets()->current_user_can();
	}

	/**
	 * Check if a given request has access to get a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return boolean
	 */
	public function get_item_permissions_check( $request ): bool {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return boolean
	 */
	public function create_item_permissions_check( $request ): bool {
		return code_snippets()->current_user_can();
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return boolean
	 */
	public function update_item_permissions_check( $request ): bool {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to delete a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return boolean
	 */
	public function delete_item_permissions_check( $request ): bool {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Get our sample schema for a post.
	 *
	 * @return array<string, mixed> The sample schema for a post
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->schema;
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'snippet',
			'type'       => 'object',
			'properties' => [
				'id'             => [
					'description' => esc_html__( 'Unique identifier for the snippet.', 'code-snippets' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'name'           => [
					'description' => esc_html__( 'Descriptive title for the snippet.', 'code-snippets' ),
					'type'        => 'string',
				],
				'desc'           => [
					'description' => esc_html__( 'Descriptive text associated with snippet.', 'code-snippets' ),
					'type'        => 'string',
				],
				'code'           => [
					'description' => esc_html__( 'Executable snippet code.', 'code-snippets' ),
					'type'        => 'string',
				],
				'tags'           => [
					'description' => esc_html__( 'List of tag categories the snippet belongs to.', 'code-snippets' ),
					'type'        => 'array',
				],
				'scope'          => [
					'description' => esc_html__( 'Context in which the snippet is executable.', 'code-snippets' ),
					'type'        => 'string',
				],
				'active'         => [
					'description' => esc_html__( 'Snippet activation status.', 'code-snippets' ),
					'type'        => 'boolean',
				],
				'priority'       => [
					'description' => esc_html__( 'Relative priority in which the snippet is executed.', 'code-snippets' ),
					'type'        => 'integer',
				],
				'network'        => [
					'description' => esc_html__( 'Whether the snippet is network-wide instead of site-wide.', 'code-snippets' ),
					'type'        => 'boolean',
					'default'     => null,
				],
				'shared_network' => [
					'description' => esc_html__( 'If a network snippet, whether can be activated on discrete sites instead of network-wide.', 'code-snippets' ),
					'type'        => 'boolean',
				],
				'modified'       => [
					'description' => esc_html__( 'Date and time when the snippet was last modified, in ISO format.', 'code-snippets' ),
					'type'        => 'string',
				],
			],
		];

		return $this->schema;
	}
}
