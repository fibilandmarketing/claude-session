<?php
/**
 * Trust badges.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $s['items'] ) ) {
	return;
}
?>
<section aria-label="مزایای خرید" class="vt-trust-sec vt-sec">
	<div class="vt-trust">
		<?php foreach ( $s['items'] as $i => $x ) : ?>
		<div class="vt-glass vt-reflect vt-trust-item" data-tdl-reveal style="--i:<?php echo (int) $i; ?>">
			<span class="vt-raised vt-trust-ic"><?php echo tdl_icon( $x['icon'], 22 ); // phpcs:ignore ?></span>
			<div class="vt-trust-tx"><span class="vt-trust-t"><?php echo esc_html( $x['t'] ); ?></span><span class="vt-trust-s"><?php echo esc_html( $x['s'] ); ?></span></div>
		</div>
		<?php endforeach; ?>
	</div>
</section>
