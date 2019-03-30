<?php

namespace Code_Snippets;

/**
 * This class manages the shortcodes included with the plugin
 *
 * @package Code_Snippets
 */
class Shortcodes {

	/**
	 * Name of the shortcode tag for rendering the code source
	 */
	const SOURCE_TAG = 'code_snippet_source';

	/**
	 * Name of the shortcode tag for rendering content snippets
	 */
	const CONTENT_TAG = 'code_snippet';

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_shortcode( self::CONTENT_TAG, [ $this, 'render_content_shortcode' ] );
		add_shortcode( self::SOURCE_TAG, [ $this, 'render_source_shortcode' ] );
		add_action( 'the_posts', [ $this, 'enqueue_highlighting' ] );
		add_action( 'init', [ $this, 'setup_mce_plugin' ] );
	}

	/**
	 * Perform the necessary actions to add a button to the TinyMCE editor
	 */
	public function setup_mce_plugin() {
		if ( ! code_snippets()->current_user_can() ) {
			return;
		}

		/* Register the TinyMCE plugin */
		add_filter( 'mce_external_plugins', function ( $plugins ) {
			$plugins['code_snippets'] = plugins_url( 'js/min/mce.js', PLUGIN_FILE );
			return $plugins;
		} );

		/* Add the button to the editor toolbar */
		add_filter( 'mce_buttons', function ( $buttons ) {
			$buttons[] = 'code_snippets';
			return $buttons;
		} );

		/* Add the translation strings to the TinyMCE editor */
		add_filter( 'mce_external_languages', function ( $languages ) {
			$languages['code_snippets'] = __DIR__ . '/strings/mce.php';
			return $languages;
		} );
	}

	/**
	 * Enqueue the syntax highlighting assets if they are required for the current posts
	 *
	 * @param array $posts List of currently visible posts.
	 *
	 * @return array Unchanged list of posts.
	 */
	public function enqueue_highlighting( $posts ) {

		if ( empty( $posts ) || Settings\get_setting( 'general', 'disable_prism' ) ) {
			return $posts;
		}

		$found = false;

		foreach ( $posts as $post ) {

			if ( false !== stripos( $post->post_content, '[' . self::SOURCE_TAG ) ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return $posts;
		}

		$plugin = code_snippets();

		wp_enqueue_style(
			'code-snippets-front-end',
			plugins_url( 'css/min/front-end.css', $plugin->file ),
			array(), $plugin->version
		);

		wp_enqueue_script(
			'code-snippets-front-end',
			plugins_url( 'js/min/front-end.js', $plugin->file ),
			array(), $plugin->version, true
		);

		return $posts;
	}

	/**
	 * Render the value of a content shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Shortcode content.
	 */
	public function render_content_shortcode( $atts ) {

		$atts = shortcode_atts(
			array(
				'id'         => 0,
				'network'    => false,
				'php'        => false,
				'format'     => false,
				'shortcodes' => false,
			),
			$atts, self::CONTENT_TAG
		);

		if ( ! $id = intval( $atts['id'] ) ) {
			return '';
		}

		$snippet = get_snippet( $id, $atts['network'] ? true : false );

		// render the source code if this is not a shortcode snippet
		if ( 'content' !== $snippet->scope ) {
			return $snippet->id ? $this->render_snippet_source( $snippet ) : '';
		}

		$content = $snippet->code;

		if ( $atts['php'] ) {
			ob_start();
			eval( "?>\n\n" . $snippet->code . "\n\n<?php" );
			$content = ob_get_clean();
		}

		if ( $atts['format'] ) {
			$functions = [ 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'capital_P_dangit' ];
			foreach ( $functions as $function ) {
				$content = call_user_func( $function, $content );
			}
		}

		if ( $atts['shortcodes'] ) {

			// remove this shortcode from the list to prevent recursion
			remove_shortcode( self::CONTENT_TAG );

			// evaluate shortcodes
			if ( $atts['format'] ) {
				$content = shortcode_unautop( $content );
			}
			$content = do_shortcode( $content );

			// add this shortcode back to the list
			add_shortcode( self::CONTENT_TAG, [ $this, 'render_content_shortcode' ] );
		}

		return $content;
	}

	/**
	 * Render the source code of a given snippet
	 *
	 * @param Snippet $snippet Snippet object.
	 * @param array   $atts    Shortcode attributes.
	 *
	 * @return string Shortcode content.
	 */
	private function render_snippet_source( Snippet $snippet, $atts = [] ) {
		$atts = array_merge( [ 'line_numbers' => false ], $atts );

		if ( ! trim( $snippet->code ) ) {
			return '';
		}

		$class = 'language-' . $snippet->type;

		if ( $atts['line_numbers'] ) {
			$class .= ' line-numbers';
		}

		return sprintf(
			'<pre><code class="%s">%s</code></pre>',
			$class, esc_html( $snippet->code )
		);
	}

	/**
	 * Render the value of a source shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Shortcode content.
	 */
	public function render_source_shortcode( $atts ) {

		$atts = shortcode_atts(
			array(
				'id'           => 0,
				'network'      => false,
				'line_numbers' => false,
			),
			$atts, self::SOURCE_TAG
		);

		if ( ! $id = intval( $atts['id'] ) ) {
			return '';
		}

		$snippet = get_snippet( $id, $atts['network'] ? true : false );

		return $this->render_snippet_source( $snippet, $atts );
	}
}

