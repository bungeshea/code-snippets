<?php

namespace Code_Snippets;

use Code_Snippets\Cloud\Cloud_Search_List_Table;
use function Code_Snippets\Settings\get_setting;

/**
 * This class handles the welcome menu.
 *
 * @since   3.7.0
 * @package Code_Snippets
 */
class Welcome_Menu extends Admin_Menu {

	/**
	 * Instance of Welcome_API class.
	 *
	 * @var Welcome_API
	 */
	protected Welcome_API $api;

	/**
	 * Class constructor
	 *
	 * @param Welcome_API $api Instance of API class.
	 */
	public function __construct( $api ) {
		parent::__construct(
			'welcome',
			_x( "What's New", 'menu label', 'code-snippets' ),
			__( 'Welcome to Code Snippets', 'code-snippets' )
		);

		$this->api = $api;
	}

	/**
	 * Enqueue assets necessary for the welcome menu.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'code-snippets-welcome',
			plugins_url( 'dist/welcome.css', PLUGIN_FILE ),
			[],
			PLUGIN_VERSION
		);
	}

	/**
	 * Retrieve a list of links to display in the page header.
	 *
	 * @return array<string, array{url: string, icon: string, label: string}>
	 */
	protected function get_header_links(): array {
		$links = [
			'cloud'     => [
				'url'   => 'https://codesnippets.cloud',
				'icon'  => 'cloud',
				'label' => __( 'Cloud', 'code-snippets' ),
			],
			'resources' => [
				'url'   => 'https://help.codesnippets.pro/',
				'icon'  => 'sos',
				'label' => __( 'Support', 'code-snippets' ),
			],
			'facebook'  => [
				'url'   => 'https://www.facebook.com/groups/282962095661875/',
				'icon'  => 'facebook',
				'label' => __( 'Community', 'code-snippets' ),
			],
			'discord'   => [
				'url'   => 'https://snipco.de/discord',
				'icon'  => 'discord',
				'label' => __( 'Discord', 'code-snippets' ),
			],
		];

		if ( ! code_snippets()->licensing->is_licensed() ) {
			$links['pro'] = [
				'url'   => 'https://codesnippets.pro/pricing/',
				'icon'  => 'cart',
				'label' => __( 'Get Pro', 'code-snippets' ),
			];
		}

		return $links;
	}
}
