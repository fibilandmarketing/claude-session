<?php
/**
 * Festival: countdown + discounted products.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$end = (int) $s['end_ts'];
if ( $end && $s['hide_expired'] && $end <= time() ) {
	return;
}
$products = TDL_Products::festival();
if ( ! $products ) {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		echo '<div class="vt-admin-note">سکشن جشنواره: هیچ محصول تخفیف‌داری پیدا نشد (فقط مدیر این پیام را می‌بیند).</div>';
	}
	return;
}
$max = 0;
foreach ( $products as $p ) {
	$max = max( $max, TDL_Products::discount( $p ) );
}
$desc = str_replace( '{max}', tdl_fa( $max ) . '٪', $s['desc'] );
?>
<section id="festival" aria-labelledby="fest-h" class="vt-sec vt-fest-sec" data-tdl-fest>
	<div class="vt-reflect-soft vt-fest">
		<span class="vt-spark" style="top:10%;right:6%;--z:30px;animation-delay:.3s"></span>
		<span class="vt-spark" style="bottom:14%;right:22%;--z:22px;animation-delay:1.9s"></span>
		<div class="vt-fest-in">
			<div class="vt-fest-head" data-tdl-reveal>
				<span class="vt-glass vt-fest-label"><?php echo esc_html( $s['label'] ); ?></span>
				<h2 id="fest-h" class="vt-fest-h"><?php echo TDL_Render::multiline( $s['title'] ); // phpcs:ignore ?></h2>
				<p class="vt-fest-p"><?php echo esc_html( $desc ); ?></p>
				<?php if ( $end ) : ?>
				<div class="vt-fest-timer" role="timer" aria-live="off" aria-label="زمان باقی‌مانده جشنواره" data-tdl-countdown="<?php echo (int) $end * 1000; ?>">
					<div><span class="vt-tick" data-u="d">۰۰</span><span>روز</span></div><span class="vt-colon">:</span>
					<div><span class="vt-tick" data-u="h">۰۰</span><span>ساعت</span></div><span class="vt-colon">:</span>
					<div><span class="vt-tick" data-u="m">۰۰</span><span>دقیقه</span></div><span class="vt-colon">:</span>
					<div><span class="vt-tick" data-u="s">۰۰</span><span>ثانیه</span></div>
				</div>
				<?php endif; ?>
				<?php if ( $s['btn_text'] ) : ?>
				<a href="#grid" class="vt-btn vt-fest-btn" data-tdl-filter="sale"><?php echo esc_html( $s['btn_text'] ); ?></a>
				<?php endif; ?>
			</div>
			<div class="vt-fest-list">
				<div class="vt-scroll vt-row vt-row-reflect" data-tdl-drag>
					<?php
					foreach ( $products as $i => $product ) {
						echo TDL_Render::card( $product, $s['show_card_timer'] ? 'fest' : 'fest-nt', $i ); // phpcs:ignore
					}
					?>
				</div>
			</div>
		</div>
	</div>
</section>
