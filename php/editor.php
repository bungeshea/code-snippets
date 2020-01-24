<?php
/**
 * Functions for using the built-in code editor library
 *
 * @package Code_Snippets
 */

namespace Code_Snippets;

use function Code_Snippets\Settings\get_setting;

/**
 * Register and load the CodeMirror library.
 *
 * @param string $type       Type of code editor – either 'php', 'css', 'js', or 'html'.
 * @param array  $extra_atts Pass an array of attributes to override the saved ones.
 */
function enqueue_code_editor( $type, $extra_atts = [] ) {

	$modes = [
		'css'  => 'text/css',
		'php'  => 'php-snippet',
		'js'   => 'javascript',
		'html' => 'application/x-httpd-php',
	];

	if ( ! isset( $modes[ $type ] ) ) {
		$type = 'php';
	}

	$default_atts = [
		'mode'           => $modes[ $type ],
		'matchBrackets'  => true,
		'extraKeys'      => [ 'Alt-F' => 'findPersistent', 'Ctrl-Space' => 'autocomplete' ],
		'gutters'        => [ 'CodeMirror-lint-markers' ],
		'lint'           => true,
		'viewportMargin' => 'Infinity',
	];

	// add relevant saved setting values to the default attributes
	$plugin_settings = Settings\get_settings_values();
	$setting_fields = get_settings_fields();

	foreach ( $setting_fields['editor'] as $field_id => $field ) {
		// the 'codemirror' setting field specifies the name of the attribute
		$default_atts[ $field['codemirror'] ] = $plugin_settings['editor'][ $field_id ];
	}

	// merge the default attributes with the ones passed into the function
	$atts = wp_parse_args( $default_atts, $extra_atts );
	$atts = apply_filters( 'code_snippets_codemirror_atts', $atts );

	// ensure number values are not formatted as strings
	foreach ( [ 'indentUnit', 'tabSize' ] as $number_att ) {
		$atts[ $number_att ] = intval( $atts[ $number_att ] );
	}

	wp_enqueue_code_editor( [
		'type'       => $modes[ $type ],
		'codemirror' => $atts,
	] );

	// CodeMirror Theme
	$theme = get_setting( 'editor', 'theme' );

	if ( 'default' !== $theme ) {

		wp_enqueue_style(
			'code-snippets-editor-theme-' . $theme,
			plugins_url( "css/min/editor-themes/$theme.css", PLUGIN_FILE ),
			[ 'code-editor' ], code_snippets()->version
		);
	}
}

/**
 * Retrieve a list of the available CodeMirror themes.
 *
 * @return array The available themes.
 */
function get_editor_themes() {
	static $themes = null;

	if ( ! is_null( $themes ) ) {
		return $themes;
	}

	$themes = array( 'default' );
	$themes_dir = plugin_dir_path( PLUGIN_FILE ) . 'css/min/editor-themes/';

	$theme_files = glob( $themes_dir . '*.css' );

	foreach ( $theme_files as $i => $theme ) {
		$theme = str_replace( $themes_dir, '', $theme );
		$theme = str_replace( '.css', '', $theme );
		$themes[] = $theme;
	}

	return $themes;
}
