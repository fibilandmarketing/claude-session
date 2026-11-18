<?php
/**
 * What is tennis jewelry.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;
?>
<section aria-labelledby="what-h" class="vt-sec vt-what-sec">
	<div class="vt-what">
		<div class="vt-what-tx" data-tdl-reveal>
			<?php if ( $s['en'] ) : ?><span class="vt-serif vt-what-en" lang="en" dir="ltr"><?php echo esc_html( $s['en'] ); ?></span><?php endif; ?>
			<h2 id="what-h" class="vt-h2 vt-h2-lg"><?php echo esc_html( $s['title'] ); ?></h2>
			<div class="vt-prose"><?php echo wp_kses_post( wpautop( $s['body'] ) ); ?></div>
		</div>
		<?php if ( $s['image'] ) : ?>
		<div class="vt-what-vis" data-tdl-reveal style="--i:2">
			<div class="vt-glass vt-reflect vt-arch">
				<div class="vt-arch-in"><?php echo tdl_img( $s['image'], array( 'alt' => $s['alt'], 'sizes' => '(max-width: 700px) 90vw, 440px', 'size' => 'large' ) ); // phpcs:ignore ?></div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>
