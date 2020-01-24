<?php

namespace Code_Snippets;

use DateTime;
use DateTimeZone;

/**
 * A snippet object
 *
 * @since   2.4.0
 * @package Code_Snippets
 *
 * @property int         $id             The database ID
 * @property string      $name           The display name
 * @property string      $desc           The formatted description
 * @property string      $code           The executable code
 * @property array       $tags           An array of the tags
 * @property string      $scope          The scope name
 * @property int         $priority       Execution priority
 * @property bool        $active         The active status
 * @property bool        $network        true if is multisite-wide snippet, false if site-wide
 * @property bool        $shared_network Whether the snippet is a shared network snippet
 * @property DateTime    $created        The date and time when the snippet was first saved in the database.
 * @property DateTime    $modified       The date and time when the snippet data was most recently saved to the database.
 *
 * @property-read array  $tags_list      The tags in string list format
 * @property-read string $scope_icon     The dashicon used to represent the current scope
 * @property-read string $type           The type of snippet
 * @property-read string $lang           The language that the snippet code is written in
 * @property-read string $created_raw    The date and time when the snippet was first saved in the database in raw format.
 * @property-read string $modified_raw   The date and time when the snippet was most recently saved to the database in raw format.
 */
class Snippet {

	/**
	 * MySQL datetime format (YYYY-MM-DD hh:mm:ss)
	 */
	const DATE_FORMAT = 'Y-m-d H:i:s';

	/**
	 * The snippet metadata fields.
	 * Initialized with default values.
	 *
	 * @var array Two-dimensional array of field names keyed to current values.
	 */
	private $fields = array(
		'id'             => 0,
		'name'           => '',
		'desc'           => '',
		'code'           => '',
		'tags'           => array(),
		'scope'          => 'global',
		'active'         => false,
		'priority'       => 10,
		'network'        => null,
		'shared_network' => null,
		'created'        => null,
		'modified'       => null,
	);

	/**
	 * List of field aliases
	 *
	 * @var array Two-dimensional array of field alias names keyed to actual field names.
	 */
	private static $field_aliases = array(
		'description' => 'desc',
		'language'    => 'lang',
	);

	/**
	 * Constructor function
	 *
	 * @param array|object $fields Initial snippet fields.
	 */
	public function __construct( $fields = null ) {
		$this->set_fields( $fields );
	}

	/**
	 * Set all of the snippet fields from an array or object
	 * Invalid fields will be ignored.
	 *
	 * @param array|object $fields List of fields.
	 */
	public function set_fields( $fields ) {

		/* Only accept arrays or objects */
		if ( ! $fields || is_string( $fields ) ) {
			return;
		}

		/* Convert objects into arrays */
		if ( is_object( $fields ) ) {
			$fields = get_object_vars( $fields );
		}

		/* Loop through the passed fields and set them */
		foreach ( $fields as $field => $value ) {
			$this->set_field( $field, $value );
		}
	}

	/**
	 * Retrieve all snippet fields
	 *
	 * @return array Two-dimensional array of field names keyed to current values
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Internal function for validating the name of a field
	 *
	 * @param string $field A field name.
	 *
	 * @return string The validated field name.
	 */
	private function validate_field_name( $field ) {

		/* If a field alias is set, remap it to the valid field name */
		if ( isset( self::$field_aliases[ $field ] ) ) {
			return self::$field_aliases[ $field ];
		}

		return $field;
	}

	/**
	 * Check if a field is set
	 *
	 * @param string $field The field name.
	 *
	 * @return bool Whether the field is set.
	 */
	public function __isset( $field ) {
		$field = $this->validate_field_name( $field );

		return isset( $this->fields[ $field ] ) || method_exists( $this, 'get_' . $field );
	}

	/**
	 * Retrieve a field's value
	 *
	 * @param string $field The field name.
	 *
	 * @return mixed The field value.
	 */
	public function __get( $field ) {
		$field = $this->validate_field_name( $field );

		if ( method_exists( $this, 'get_' . $field ) ) {
			return call_user_func( array( $this, 'get_' . $field ) );
		}

		return $this->fields[ $field ];
	}

	/**
	 * Set the value of a field
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The field value.
	 */
	public function __set( $field, $value ) {
		$field = $this->validate_field_name( $field );

		if ( ! $this->is_allowed_field( $field ) ) {

			if ( WP_DEBUG ) {
				trigger_error( 'Trying to set invalid property on Snippets class: ' . esc_html( $field ), E_WARNING );
			}

			return;
		}

		/* Check if the field value should be filtered */
		if ( method_exists( $this, 'prepare_' . $field ) ) {
			$value = call_user_func( array( $this, 'prepare_' . $field ), $value );
		}

		$this->fields[ $field ] = $value;
	}

	/**
	 * Retrieve the list of fields allowed to be written to
	 *
	 * @return array Single-dimensional array of field names.
	 */
	public function get_allowed_fields() {
		return array_keys( $this->fields ) + array_keys( self::$field_aliases );
	}

	/**
	 * Determine whether a field is allowed to be written to
	 *
	 * @param string $field The field name.
	 *
	 * @return bool true if the is allowed, false if invalid.
	 */
	public function is_allowed_field( $field ) {
		return array_key_exists( $field, $this->fields ) || array_key_exists( $field, self::$field_aliases );
	}

	/**
	 * Safely set the value for a field
	 * If the field name is invalid, false will be returned instead of an error thrown.
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The field value.
	 *
	 * @return bool true if the field was set successfully, false if the field name is invalid.
	 */
	public function set_field( $field, $value ) {
		if ( ! $this->is_allowed_field( $field ) ) {
			return false;
		}

		$this->__set( $field, $value );

		return true;
	}

	/**
	 * Add a new tag
	 *
	 * @param string $tag Tag content to add to list.
	 */
	public function add_tag( $tag ) {
		$this->fields['tags'][] = $tag;
	}

	/**
	 * Prepare the ID by ensuring it is an absolute integer
	 *
	 * @param int $id The field as provided.
	 *
	 * @return int The field in the correct format.
	 */
	private function prepare_id( $id ) {
		return absint( $id );
	}

	/**
	 * Prepare the scope by ensuring that it is a valid choice
	 *
	 * @param int|string $scope The field as provided.
	 *
	 * @return string The field in the correct format.
	 */
	private function prepare_scope( $scope ) {
		$scopes = self::get_all_scopes();

		if ( in_array( $scope, $scopes, true ) ) {
			return $scope;
		}

		if ( is_numeric( $scope ) && isset( $scopes[ $scope ] ) ) {
			return $scopes[ $scope ];
		}

		return $this->fields['scope'];
	}

	/**
	 * Prepare the snippet tags by ensuring they are in the correct format
	 *
	 * @param string|array $tags The field as provided.
	 *
	 * @return array The field in the correct format.
	 */
	private function prepare_tags( $tags ) {
		return code_snippets_build_tags_array( $tags );
	}

	/**
	 * Prepare the active field by ensuring it is the correct type
	 *
	 * @param bool|int $active The field as provided.
	 *
	 * @return bool The field in the correct format.
	 */
	private function prepare_active( $active ) {

		if ( is_bool( $active ) ) {
			return $active;
		}

		return $active ? true : false;
	}

	/**
	 * Prepare the priority field by ensuring it is an integer
	 *
	 * @param int $priority The field as provided.
	 *
	 * @return int The field in the correct format.
	 */
	private function prepare_priority( $priority ) {
		return intval( $priority );
	}

	/**
	 * If $network is anything other than true, set it to false
	 *
	 * @param bool $network The field as provided.
	 *
	 * @return bool The field in the correct format.
	 */
	private function prepare_network( $network ) {

		if ( null === $network && function_exists( 'is_network_admin' ) ) {
			return is_network_admin();
		}

		return true === $network;
	}

	/**
	 * Determine the type of code this snippet is, based on its scope
	 *
	 * @return string The snippet type – will be a filename extension.
	 */
	private function get_type() {
		if ( '-css' === substr( $this->scope, -4 ) ) {
			return 'css';
		}

		if ( '-js' === substr( $this->scope, -3 ) ) {
			return 'js';
		}

		if ( 'content' === $this->scope ) {
			return 'html';
		}

		return 'php';
	}

	/**
	 * Determine the language that the snippet code is written in, based on the scope
	 *
	 * @return string The name of a language filename extension.
	 */
	private function get_lang() {
		return $this->type;
	}

	/**
	 * Retrieve the site's timezone. This is only necessary while < WP 3.5 is supported, as
	 * it can be replaced with the wp_timezone() function.
	 *
	 * @return DateTimeZone
	 */
	private function get_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		$timezone_string = get_option( 'timezone_string' );
		if ( $timezone_string ) {
			return new DateTimeZone( $timezone_string );
		}

		$offset = (float) get_option( 'gmt_offset' );
		$hours = (int) $offset;
		$minutes = ( $offset - $hours );

		$sign = ( $offset < 0 ) ? '-' : '+';
		$abs_hour = abs( $hours );
		$abs_mins = abs( $minutes * 60 );
		$timezone = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

		return new DateTimeZone( $timezone );
	}

	/**
	 * Prepare a date and time field by ensuring it is in the correct format.
	 *
	 * @param DateTime|string $datetime
	 * @param string          $field_name
	 *
	 * @return DateTime
	 */
	private function prepare_datetime( $datetime, $field_name ) {

		/* If the supplied datetime is already in the correct format, then we're done here */
		if ( $datetime instanceof DateTime ) {
			return $datetime;
		}

		/* If the supplied datetime is a string, assume it is in MySQL format */
		if ( is_string( $datetime ) ) {
			$datetime_object = DateTime::createFromFormat( self::DATE_FORMAT, $datetime, $this->get_timezone() );
			if ( $datetime_object ) {
				$this->fields[ $field_name . '_raw' ] = $datetime;
			}
			return $datetime_object;
		}

		/* Otherwise, discard the supplied value */
		return null;
	}

	/**
	 * Prepare the created field by ensuring it is in the correct format.
	 *
	 * @param DateTime|string $created
	 *
	 * @return DateTime
	 */
	private function prepare_created( $created ) {
		return $this->prepare_datetime( $created, 'created' );
	}

	/**
	 * Prepare the modified field by ensuring it is in the correct format.
	 *
	 * @param DateTime|string $modified
	 *
	 * @return DateTime
	 */
	private function prepare_modified( $modified ) {
		return $this->prepare_datetime( $modified, 'modified' );
	}

	/**
	 * Retrieve the tags in list format
	 *
	 * @return string The tags separated by a comma and a space.
	 */
	private function get_tags_list() {
		return implode( ', ', $this->fields['tags'] );
	}

	/**
	 * Retrieve a list of all available scopes
	 *
	 * @return array Single-dimensional array of scope names.
	 */
	public static function get_all_scopes() {
		return array(
			'global', 'admin', 'front-end', 'single-use',
			'content',
			'admin-css', 'site-css',
			'site-head-js', 'site-footer-js',
		);
	}

	/**
	 * Retrieve a list of all scope icons
	 *
	 * @return array Two-dimensional array with scope name keyed to the class name of a dashicon.
	 */
	public static function get_scope_icons() {
		return array(
			'global'         => 'admin-site',
			'admin'          => 'admin-tools',
			'front-end'      => 'admin-appearance',
			'single-use'     => 'clock',
			'content'        => 'admin-post',
			'admin-css'      => 'dashboard',
			'site-css'       => 'admin-customizer',
			'site-head-js'   => 'media-code',
			'site-footer-js' => 'media-code',
		);
	}

	/**
	 * Retrieve the string representation of the scope
	 *
	 * @return string The name of the scope.
	 */
	private function get_scope_name() {
		return $this->scope;
	}

	/**
	 * Retrieve the icon used for the current scope
	 *
	 * @return string A dashicon name.
	 */
	private function get_scope_icon() {
		$icons = self::get_scope_icons();

		return $icons[ $this->scope ];
	}

	/**
	 * Determine if the snippet is a shared network snippet
	 *
	 * @return bool Whether the snippet is a shared network snippet.
	 */
	private function get_shared_network() {

		if ( isset( $this->fields['shared_network'] ) ) {
			return $this->fields['shared_network'];
		}

		if ( ! is_multisite() || ! $this->fields['network'] ) {
			$this->fields['shared_network'] = false;
		} else {
			$shared_network_snippets = get_site_option( 'shared_network_snippets', array() );
			$this->fields['shared_network'] = in_array( $this->fields['id'], $shared_network_snippets, true );
		}

		return $this->fields['shared_network'];
	}
}
