<?php
/**
 * HTML for the welcome page.
 *
 * @package    Code_Snippets
 * @subpackage Views
 */

namespace Code_Snippets;

/**
 * Loaded from the Welcome_Menu class.
 *
 * @var Welcome_Menu $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$hero = $this->api->get_hero_item();

$changelog_sections = [
	'Added'    => [
		'title' => __( 'New features', 'code-snippets' ),
		'icon'  => 'lightbulb',
	],
	'Improved' => [
		'title' => __( 'Improvements', 'code-snippets' ),
		'icon'  => 'chart-line',
	],
	'Fixed'    => [
		'title' => __( 'Bug fixes', 'code-snippets' ),
		'icon'  => 'buddicons-replies',
	],
	'Other'    => [
		'title' => __( 'Other', 'code-snippets' ),
		'icon'  => 'open-folder',
	],
];

$plugin_types = [
	'core' => _x( 'Core', 'badge label', 'code-snippets' ),
	'pro'  => _x( 'Pro', 'badge label', 'code-snippets' ),
];

?>

<div class="csp-welcome-wrap">
	<div class="csp-welcome-header">
		<header class="csp-welcome-logo">
			<img width="50px"
			     src="<?php echo esc_url( plugins_url( 'assets/icon.svg', PLUGIN_FILE ) ); ?>"
			     alt="<?php esc_attr_e( 'Code Snippets Logo', 'code-snippets' ); ?>">
			<h1>
				<?php echo wp_kses( __( "Resources and <span>What's New</span>", 'code-snippets' ), [ 'span' => [] ] ); ?>
			</h1>
		</header>
		<ul class="csp-welcome-nav">
			<?php foreach ( $this->get_header_links() as $link_name => $link_info ) { ?>
				<li>
					<a href="<?php echo esc_url( $link_info['url'] ); ?>" target="_blank"
					   class="csp-link-<?php echo esc_attr( $link_name ); ?>">
						<span><?php echo esc_html( $link_info['label'] ); ?></span>

						<?php if ( 'discord' === $link_info['icon'] ) { ?>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36">
								<path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z" />
							</svg>
						<?php } else { ?>
							<span class="dashicons dashicons-<?php echo esc_attr( $link_info['icon'] ); ?>"></span>
						<?php } ?>
					</a>
				</li>
			<?php } ?>
		</ul>
	</div>

	<section class="csp-section-changes">
		<h1>📰 <?php esc_html_e( 'Latest news', 'code-snippets' ); ?></h1>
		<div class="csp-cards">
			<a class="csp-card" href="<?php echo esc_url( $hero['follow_url'] ); ?>" target="_blank"
			   title="<?php esc_html_e( 'Read more', 'code-snippets' ); ?>">
				<header>
					<span class="dashicons dashicons-external"></span>
					<h2><?php echo esc_html( $hero['name'] ); ?></h2>
				</header>
				<figure>
					<div id="csp-loading-spinner" class="csp-loading-spinner"></div>
					<img id="csp-changes-img"
					     onload="hideLoadingAnimation()"
					     src="<?php echo esc_url( $hero['image_url'] ); ?>"
					     alt="<?php esc_attr_e( 'Latest news image', 'code-snippets' ); ?>);">
				</figure>
			</a>

			<a class="csp-card" href="https://wordpress.org/plugins/code-snippets/changelog" target="_blank"
			   title="<?php esc_html_e( 'Read the full changelog', 'code-snippets' ); ?>">
				<header>
					<span class="dashicons dashicons-external"></span>
					<h2><?php esc_html_e( 'Latest changes', 'code-snippets' ); ?></h2>
				</header>
				<div class="csp-section-changelog">
					<?php foreach ( $this->api->get_changelog() as $version => $version_changes ) { ?>
						<h3><?php echo esc_html( $version ); ?></h3>
						<article>
							<?php
							foreach ( $changelog_sections as $section_key => $section ) {
								if ( empty( $version_changes[ $section_key ] ) ) {
									continue;
								}
								?>
								<h4>
									<span class="dashicons dashicons-<?php echo esc_attr( $section['icon'] ); ?>"></span>
									<?php echo esc_html( $section['title'] ); ?>
								</h4>
								<ul>
									<?php
									foreach ( $plugin_types as $plugin_type => $type_label ) {
										if ( empty( $version_changes[ $section_key ][ $plugin_type ] ) ) {
											continue;
										}

										foreach ( $version_changes[ $section_key ][ $plugin_type ] as $change ) {
											?>
											<li>
												<span class="badge <?php echo esc_attr( $plugin_type ); ?>-badge">
													<?php echo esc_html( $type_label ); ?>
												</span>
												<span><?php echo esc_html( $change ); ?></span>
											</li>
											<?php
										}
									}
									?>
								</ul>
							<?php } ?>
						</article>
					<?php } ?>
				</div>
			</a>
		</div>
	</section>

	<section class="csp-section-articles csp-section-links">
		<h1>🛟 <?php esc_html_e( 'Helpful articles', 'code-snippets' ); ?></h1>
		<div class="csp-cards">
			<?php foreach ( $this->api->get_features() as $feature ) { ?>
				<a class="csp-card"
				   href="<?php echo esc_url( $feature['follow_url'] ); ?>" target="_blank"
				   title="<?php esc_html_e( 'Read more', 'code-snippets' ); ?>">
					<figure>
						<img src="<?php echo esc_url( $feature['image_url'] ); ?>"
						     alt="<?php esc_attr_e( 'Feature image', 'code-snippets' ); ?>">
					</figure>
					<header>
						<h2><?php echo esc_html( $feature['title'] ); ?></h2>
						<p class="csp-card-item-description"><?php echo esc_html( $feature['description'] ); ?></p>
					</header>
					<footer>
						<p class="csp-card-item-category"><?php echo esc_html( $feature['category'] ); ?></p>
						<span class="dashicons dashicons-external"></span>
					</footer>
				</a>
			<?php } ?>
		</div>
	</section>

	<section class="csp-section-links csp-section-partners">
		<h1>🚀 <?php esc_html_e( 'Partners and apps', 'code-snippets' ); ?></h1>
		<div class="csp-cards">
			<?php foreach ( $this->api->get_partners() as $partner ) { ?>
				<a class="csp-card"
				   href="<?php echo esc_url( $partner['follow_url'] ); ?>" target="_blank"
				   title="<?php esc_attr_e( 'Go to Partner', 'code-snippets' ); ?>">
					<figure>
						<img src="<?php echo esc_url( $partner['image_url'] ); ?>"
						     alt="<?php esc_attr_e( 'Partner image', 'code-snippets' ); ?>">
					</figure>
					<header>
						<span class="dashicons dashicons-external"></span>
						<h2><?php echo esc_html( $partner['title'] ); ?></h2>
					</header>
				</a>
			<?php } ?>
		</div>
	</section>
</div>

<script type="text/javascript">
	function hideLoadingAnimation() {
		const spinner = document.getElementById<HTMLDivElement>('csp-loading-spinner')
		const image = document.getElementById<HTMLDivElement>('csp-changes-img')

		spinner.style.display = 'none'
		image.style.display = 'block'
	}
</script>
