<?php
/**
 * Care tips.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;
?>
<section aria-labelledby="care-h" class="vt-sec vt-care-sec">
	<div class="vt-ice vt-reflect-soft vt-care">
		<div class="vt-sec-intro" data-tdl-reveal>
			<h2 id="care-h" class="vt-h2 vt-h2-lg"><?php echo esc_html( $s['title'] ); ?></h2>
			<?php if ( $s['desc'] ) : ?><p><?php echo esc_html( $s['desc'] ); ?></p><?php endif; ?>
		</div>
		<div class="vt-care-grid">
			<?php foreach ( (array) $s['items'] as $i => $c ) : ?>
			<div class="vt-glass vt-lift vt-care-item" data-tdl-reveal style="--i:<?php echo (int) $i; ?>">
				<span class="vt-raised vt-bob vt-care-ic" style="animation-delay:<?php echo esc_attr( -0.6 * $i ); ?>s"><?php echo tdl_icon( $c['icon'], 22 ); // phpcs:ignore ?></span>
				<h3><?php echo esc_html( $c['t'] ); ?></h3>
				<p><?php echo esc_html( $c['d'] ); ?></p>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
