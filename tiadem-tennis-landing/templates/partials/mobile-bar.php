<?php
/**
 * Floating mobile buy bar.
 *
 * @package TiademTennisLanding
 * @var array $s Footer settings.
 */

defined( 'ABSPATH' ) || exit;

$price = 'auto' === $s['bar_price_mode'] ? TDL_Products::min_price_text() : $s['bar_price_manual'];
if ( '' === $price ) {
	$price = $s['bar_price_manual'];
}
?>
<div class="vt-mobile-bar" data-tdl-bar>
	<div class="vt-glass vt-mobile-bar-in">
		<div class="vt-mobile-bar-tx"><span><?php echo esc_html( $s['bar_label'] ); ?></span><b><?php echo esc_html( $price ); ?></b></div>
		<a href="<?php echo esc_url( $s['bar_link'] ); ?>" class="vt-btn"><?php echo esc_html( $s['bar_btn'] ); ?></a>
	</div>
</div>
