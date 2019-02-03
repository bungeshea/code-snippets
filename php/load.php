<?php

namespace Code_Snippets;

const PLUGIN_VERSION = '3.0.0-dev.3';
const PLUGIN_FILE = CODE_SNIPPETS_FILE;

require_once dirname( PLUGIN_FILE ) . '/vendor/autoload.php';

/**
 * Retrieve the instance of the main plugin class
 *
 * @since 2.6.0
 * @return Plugin
 */
function code_snippets() {
	static $plugin;

	if ( is_null( $plugin ) ) {
		$plugin = new Plugin( PLUGIN_VERSION, PLUGIN_FILE );
	}

	return $plugin;
}

code_snippets()->load_plugin();

/* Execute the snippets once the plugins are loaded */
add_action( 'plugins_loaded', __NAMESPACE__ . '\execute_active_snippets', 1 );
