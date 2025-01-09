<?php
/**
 * Functions for cleaning data when the plugin is uninstalled.
 *
 * @package Code_Snippets
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 */

namespace Code_Snippets\Uninstall;

/**
 * Determine whether the option for allowing a complete uninstallation is enabled.
 *
 * @return boolean
 */
function complete_uninstall_enabled(): bool {
	$unified = false;

	if ( is_multisite() ) {
		$menu_perms = get_site_option( 'menu_items', array() );
		$unified = empty( $menu_perms['snippets_settings'] );
	}

	$settings = $unified ? get_site_option( 'code_snippets_settings' ) : get_option( 'code_snippets_settings' );

	return isset( $settings['general']['complete_uninstall'] ) && $settings['general']['complete_uninstall'];
}

/**
 * Clean up data created by this plugin for a single site
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */
function uninstall_current_site() {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippets" );

	delete_option( 'code_snippets_version' );
	delete_option( 'recently_activated_snippets' );
	delete_option( 'code_snippets_settings' );

	delete_option( 'code_snippets_cloud_settings' );
	delete_transient( 'cs_codevault_snippets' );
	delete_transient( 'cs_local_to_cloud_map' );
}

/**
 * Clean up data created by this plugin on multisite.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */
function uninstall_multisite() {
	global $wpdb;

	// Loop through sites.
	$blog_ids = get_sites( [ 'fields' => 'ids' ] );

	foreach ( $blog_ids as $site_id ) {
		switch_to_blog( $site_id );
		uninstall_current_site();
	}

	restore_current_blog();

	// Remove network snippets table.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ms_snippets" );

	// Remove saved options.
	delete_site_option( 'code_snippets_version' );
	delete_site_option( 'recently_activated_snippets' );
}

/**
 * Uninstall the Code Snippets plugin.
 *
 * @return void
 */
function uninstall_plugin() {
	if ( complete_uninstall_enabled() ) {

		if ( is_multisite() ) {
			uninstall_multisite();
		} else {
			uninstall_current_site();
		}
	}
}
