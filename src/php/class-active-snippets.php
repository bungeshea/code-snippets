<?php
namespace Code_Snippets;

/**
 * Class for loading active snippets of various types.
 *
 * @package Code_Snippets
 */
class Active_Snippets {

	/**
	 * Cached list of active snippets.
	 *
	 * @var Snippet[]
	 */
	private array $active_snippets = [];

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialise class functions.
	 */
	public function init() {
		add_action( 'wp_head', [ $this, 'load_head_content' ] );
		add_action( 'wp_footer', [ $this, 'load_footer_content' ] );
	}

	/**
	 * Fetch active snippets for a given scope, and cache the data in this class.
	 *
	 * @param string|string[] $scope Snippet scope.
	 *
	 * @return array[][]
	 */
	protected function fetch_active_snippets( $scope ) {
		$scope_key = is_array( $scope ) ? implode( '|', $scope ) : $scope;

		if ( ! isset( $this->active_snippets[ $scope_key ] ) ) {
			$this->active_snippets[ $scope_key ] = code_snippets()->db->fetch_active_snippets( $scope );
		}

		return $this->active_snippets[ $scope_key ];
	}

	/**
	 * Print snippet code fetched from the database from a certain scope.
	 *
	 * @param string $scope Name of scope to print.
	 */
	private function print_content_snippets( string $scope ) {
		$snippets_list = $this->fetch_active_snippets( [ 'head-content', 'footer-content' ] );

		foreach ( $snippets_list as $snippets ) {
			foreach ( $snippets as $snippet ) {
				if ( $scope === $snippet['scope'] ) {
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "\n", $snippet['code'], "\n";
				}
			}
		}
	}

	/**
	 * Print head content snippets.
	 */
	public function load_head_content() {
		$this->print_content_snippets( 'head-content' );
	}

	/**
	 * Print footer content snippets.
	 */
	public function load_footer_content() {
		$this->print_content_snippets( 'footer-content' );
	}
}
