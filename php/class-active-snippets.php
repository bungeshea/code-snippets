<?php

namespace Code_Snippets;

use MatthiasMullie\Minify;

/**
 * Class for loading active style snippets
 *
 * @package Code_Snippets
 */
class Active_Snippets {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialise class functions.
	 */
	public function init() {
		if ( ! code_snippets()->licensing->was_licensed() ) {
			return;
		}

		// respond to requests to print out the active CSS snippets/
		if ( isset( $_GET['code-snippets-css'] ) ) {
			$this->print_code( 'css' );
			exit;
		}

		// respond to requests to print the active JavaScript snippets.
		if ( isset( $_GET['code-snippets-js-snippets'] ) && ! is_admin() ) {
			$this->print_code( 'js' );
			exit;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_js' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_css' ), 15 );
	}

	/**
	 * Increment the asset revision for a specified scope
	 *
	 * @param string $scope   Name of snippet scope.
	 * @param bool   $network Whether to increase for the whole network or the current site.
	 */
	public function increment_rev( $scope, $network ) {
		if ( $network && ! is_multisite() ) {
			return;
		}

		$revisions = Settings\get_self_option( $network, 'code_snippets_assets_rev', array() );

		if ( 'all' === $scope ) {
			foreach ( $revisions as $i => $v ) {
				$revisions[ $i ]++;
			}

		} else {
			if ( ! isset( $revisions[ $scope ] ) ) {
				$revisions[ $scope ] = 0;
			}

			$revisions[ $scope ]++;
		}

		Settings\update_self_option( $network, 'code_snippets_assets_rev', $revisions );
	}

	/**
	 * Retrieve the current asset revision number
	 *
	 * @param string $scope Name of snippet scope.
	 *
	 * @return int Current asset revision number.
	 */
	public function get_rev( $scope ) {
		$rev = 0;

		$revisions = get_option( 'code_snippets_assets_rev' );
		if ( isset( $revisions[ $scope ] ) ) {
			$rev += intval( $revisions[ $scope ] );
		}

		if ( is_multisite() ) {
			$revisions = get_site_option( 'code_snippets_assets_rev' );
			if ( isset( $revisions[ $scope ] ) ) {
				$rev += intval( $revisions[ $scope ] );
			}
		}

		return $rev;
	}

	/**
	 * Retrieve the URL to a generated scope asset.
	 *
	 * @param string $scope      Name of the scope to retrieve the asset for.
	 * @param bool   $latest_rev Whether to ensure that the URL is to the latest revision of the asset.
	 *
	 * @return string URL to asset.
	 */
	public function get_asset_url( $scope, $latest_rev = false ) {
		$base = 'admin-css' === $scope ? self_admin_url( '/' ) : home_url( '/' );

		if ( '-css' === substr( $scope, -4 ) ) {
			$url = add_query_arg( 'code-snippets-css', 1, $base );

		} elseif ( '-js' === substr( $scope, -3 ) ) {
			$key = 'site-head-js' === $scope ? 'head' : 'footer';
			$url = add_query_arg( 'code-snippets-js-snippets', $key, $base );

		} else {
			return '';
		}

		if ( $latest_rev && $rev = $this->get_rev( $scope ) ) {
			$url = add_query_arg( 'ver', $rev, $url );
		}

		return $url;
	}

	/**
	 * Enqueue the active style snippets for the current page
	 */
	public function enqueue_css() {
		$scope = is_admin() ? 'admin' : 'site';

		if ( ! $rev = $this->get_rev( "$scope-css" ) ) {
			return;
		}

		$url = $this->get_asset_url( "$scope-css" );
		wp_enqueue_style( "code-snippets-{$scope}-styles", $url, array(), $rev );
	}

	/**
	 * Enqueue the active javascript snippets for the current page
	 */
	public function enqueue_js() {

		if ( $head_rev = $this->get_rev( 'site-head-js' ) ) {
			wp_enqueue_script(
				'code-snippets-site-head-js',
				$this->get_asset_url( 'site-head-js' ),
				array(), $head_rev, false
			);

		}

		if ( $footer_rev = $this->get_rev( 'site-footer-js' ) ) {
			wp_enqueue_script(
				'code-snippets-site-footer-js',
				$this->get_asset_url( 'site-footer-js' ),
				array(), $footer_rev, true
			);
		}
	}

	/**
	 * Set the necessary headers to mark this page as an asset
	 *
	 * @param string $mime_type File MIME type used to set Content-Type header.
	 */
	private static function do_asset_headers( $mime_type ) {
		$expiry = 365 * 24 * 60 * 60; // year in seconds
		header( 'Content-Type: ' . $mime_type, true, 200 );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expiry ) . ' GMT' );
	}

	/**
	 * Fetch and print the active snippets for a given type and the current scope.
	 *
	 * @param string $type Must be either 'css' or 'js'.
	 */
	private function print_code( $type ) {
		if ( 'js' !== $type && 'css' !== $type ) {
			return;
		}

		if ( 'css' === $type ) {
			$this->do_asset_headers( 'text/css' );
			$current_scope = is_admin() ? 'admin-css' : 'site-css';
		} else {
			$this->do_asset_headers( 'text/javascript' );
			$current_scope = 'site-' . ( 'footer' === $_GET['code-snippets-js-snippets'] ? 'footer' : 'head' ) . '-js';
		}

		$active_snippets = code_snippets()->db->fetch_active_snippets( $current_scope, 'code' );

		// concatenate all fetched code together into a single string
		$code = '';
		foreach ( $active_snippets as $snippets ) {
			/** @phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			$code .= implode( "\n\n", array_column( $snippets, 'code' ) );
		}

		// minify the prepared code if the setting has been set
		$setting = Settings\get_setting( 'general', 'minify_output' );
		if ( is_array( $setting ) && in_array( $type, $setting ) ) {
			$minifier = 'css' === $type ? new Minify\CSS( $code ) : new Minify\JS( $code );
			$code = $minifier->minify();
		}

		// output the code and exit
		echo $code;
		exit;
	}
}
