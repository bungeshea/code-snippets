<?php

namespace Code_Snippets;

/**
 * Functions used to manage the database tables
 *
 * @package Code_Snippets
 */
class DB {

	/**
	 * Unprefixed site-wide table name
	 */
	const TABLE_NAME = 'snippets';

	/**
	 * Unprefixed network-wide table name
	 */
	const MS_TABLE_NAME = 'ms_snippets';

	/**
	 * Side-wide table name
	 *
	 * @var string
	 */
	public $table;

	/**
	 * Network-wide table name
	 *
	 * @var string
	 */
	public $ms_table;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->set_table_vars();
	}

	/**
	 * Register the snippet table names with WordPress
	 *
	 * @since 2.0
	 */
	public function set_table_vars() {
		global $wpdb;

		$this->table = $wpdb->prefix . self::TABLE_NAME;
		$this->ms_table = $wpdb->base_prefix . self::MS_TABLE_NAME;

		/* Register the snippet table names with WordPress */
		$wpdb->snippets = $this->table;
		$wpdb->ms_snippets = $this->ms_table;

		$wpdb->tables[] = self::TABLE_NAME;
		$wpdb->ms_global_tables[] = self::MS_TABLE_NAME;
	}

	/**
	 * Validate the multisite parameter of the get_table_name() function
	 *
	 * @param bool|null $network Value of multisite parameter – true for multisite, false for single-site.
	 *
	 * @return bool Validated value of multisite parameter.
	 */
	public static function validate_network_param( $network ) {

		/* If multisite is not active, then the parameter should always be false */
		if ( ! is_multisite() ) {
			return false;
		}

		/* If $multisite is null, try to base it on the current admin page */
		if ( is_null( $network ) && function_exists( 'is_network_admin' ) ) {
			$network = is_network_admin();
		}

		return $network;
	}

	/**
	 * Return the appropriate snippet table name
	 *
	 * @param string|bool|null $multisite Whether retrieve the multisite table name (true) or the site table name (false).
	 *
	 * @return string The snippet table name
	 * @since 2.0
	 */
	public function get_table_name( $multisite = null ) {

		/* If the first parameter is a string, assume it is a table name */
		if ( is_string( $multisite ) ) {
			return $multisite;
		}

		/* Validate the multisite parameter */
		$multisite = $this->validate_network_param( $multisite );

		/* return the correct table name depending on the value of $multisite */
		return $multisite ? $this->ms_table : $this->table;
	}

	/**
	 * Determine whether a database table exists.
	 *
	 * @param string $table_name Name of database table to check.
	 *
	 * @return bool Whether the database table exists.
	 */
	public static function table_exists( $table_name ) {
		global $wpdb;
		return $wpdb->get_var( sprintf( "SHOW TABLES LIKE '%s'", $table_name ) ) === $table_name; // cache pass
	}

	/**
	 * Create the snippet tables if they do not already exist
	 */
	public function create_missing_tables() {

		/* Create the network snippets table if it doesn't exist */
		if ( is_multisite() && ! self::table_exists( $this->ms_table ) ) {
			$this->create_table( $this->ms_table );
		}

		/* Create the table if it doesn't exist */
		if ( ! self::table_exists( $this->table ) ) {
			$this->create_table( $this->table );
		}
	}

	/**
	 * Create the snippet tables, or upgrade them if they already exist
	 */
	public function create_or_upgrade_tables() {
		if ( is_multisite() ) {
			$this->create_table( $this->ms_table );
		}

		$this->create_table( $this->table );
	}

	/**
	 * Create a snippet table if it does not already exist
	 *
	 * @param string $table_name Name of database table.
	 */
	public static function create_missing_table( $table_name ) {

		if ( self::table_exists( $table_name ) ) {
			return;
		}

		self::create_table( $table_name );
	}

	/**
	 * Create a single snippet table.
	 *
	 * @param string $table_name The name of the table to create.
	 *
	 * @return bool Whether the table creation was successful.
	 * @since 1.6
	 * @uses  dbDelta() to apply the SQL code
	 */
	public static function create_table( $table_name ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		/* Create the database table */
		$sql = "CREATE TABLE $table_name (
				id          BIGINT(20)  NOT NULL AUTO_INCREMENT,
				name        TINYTEXT    NOT NULL DEFAULT '',
				description TEXT        NOT NULL DEFAULT '',
				code        LONGTEXT    NOT NULL DEFAULT '',
				tags        LONGTEXT    NOT NULL DEFAULT '',
				scope       VARCHAR(15) NOT NULL DEFAULT 'global',
				priority    SMALLINT    NOT NULL DEFAULT 10,
				active      TINYINT(1)  NOT NULL DEFAULT 0,
				modified    DATETIME    NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY scope (scope),
				KEY active (active)
			) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$success = empty( $wpdb->last_error );

		if ( $success ) {
			do_action( 'code_snippets/create_table', $table_name );
		}

		return $success;
	}

	/**
	 * Build a list of formatting placeholders for an array of data.
	 *
	 * @param int    $count       Length of data.
	 * @param string $placeholder Placeholder to use. Defaults to string placeholder.
	 *
	 * @return string List of placeholders, ready for inclusion in query.
	 */
	private static function build_format_list( $count, $placeholder = '%s' ) {
		return implode( ',', array_fill( 0, $count, $placeholder ) );
	}

	/**
	 * Fetch a list of active snippets from a database table.
	 *
	 * @param string $table_name     Name of table to fetch snippets from.
	 * @param array  $scopes         List of scopes to include in query.
	 * @param array  $additional_ids List of any additional inactive snippets to include in query.
	 *
	 * @return array|false|object|\stdClass[]
	 */
	private static function fetch_active_snippets_from_table( $table_name, $scopes, $additional_ids = array() ) {
		global $wpdb;

		$cache_key = sprintf( 'active_snippets_%s_%s', sanitize_key( '_' . join( $scopes ) ), $table_name );
		$cached_snippets = wp_cache_get( $cache_key );

		if ( is_array( $cached_snippets ) ) {
			return $cached_snippets;
		}

		if ( ! self::table_exists( $table_name ) ) {
			return false;
		}

		$scopes_format = self::build_format_list( count( $scopes ) );
		$select = "SELECT id, code, scope FROM";
		$where = "WHERE scope IN ($scopes_format)";
		$order = 'ORDER BY priority ASC, id ASC';

		if ( is_array( $additional_ids ) && count( $additional_ids ) ) {
			$ids_format = self::build_format_list( count( $additional_ids ), '%d' );

			$query = $wpdb->prepare(
				"$select $table_name $where AND (active=1 OR id IN ($ids_format)) $order",
				array_merge( $scopes, $additional_ids )
			);
		} else {
			$query = $wpdb->prepare( "$select $table_name $where AND active=1 $order", $scopes );
		}

		$snippets = $wpdb->get_results( $query, 'ARRAY_A' );

		if ( is_array( $snippets ) ) {
			wp_cache_set( $cache_key, $snippets, CACHE_GROUP );
			return $snippets;
		}

		return false;
	}

	/**
	 * Generate the SQL for fetching active snippets from the database.
	 *
	 * @param array|string $scopes List of scopes to retrieve in.
	 *
	 * @return array List of active snippets, indexed by table.
	 */
	public function fetch_active_snippets( $scopes ) {
		$active_snippets = array();

		// Ensure that the list of scopes is an array.
		if ( ! is_array( $scopes ) ) {
			$scopes = array( $scopes );
		}

		// Fetch the active snippets for the current site, if there are any.
		$snippets = $this->fetch_active_snippets_from_table( $this->table, $scopes );
		if ( $snippets ) {
			$active_snippets[ $this->table ] = $snippets;
		}

		// If multisite is enabled, fetch active snippets from the network table, including active network shared snippets.
		if ( is_multisite() ) {
			$active_shared_ids = get_option( 'active_shared_network_snippets', array() );
			$snippets = $this->fetch_active_snippets_from_table( $this->ms_table, $scopes, $active_shared_ids );

			if ( $snippets ) {
				$active_snippets[ $this->ms_table ] = $snippets;
			}
		}

		return $active_snippets;
	}
}
