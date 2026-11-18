<?php
/**
 * Quality row (economy / master) with side card and product carousel.
 *
 * @package TiademTennisLanding
 * @var array  $s
 * @var string $key
 * @var string $id
 * @var string $theme
 */

defined( 'ABSPATH' ) || exit;

$products = TDL_Products::row( $key );
if ( ! $products ) {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		echo '<div class="vt-admin-note">ردیف «' . esc_html( $s['title'] ) . '»: محصولی پیدا نشد. دسته مربوط را در تب «اتصال ووکامرس» تنظیم کنید (فقط مدیر این پیام را می‌بیند).</div>';
	}
	return;
}
$filter = 'economy' === $key ? 'eco' : 'master';
$ctx    = 'economy' === $key ? 'eco' : 'master';
?>
<section id="<?php echo esc_attr( $id ); ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>-h" class="vt-sec vt-qrow vt-qrow-<?php echo esc_attr( $theme ); ?>">
	<div class="vt-wrap">
		<div class="vt-line-head" data-tdl-reveal>
			<h2 id="<?php echo esc_attr( $id ); ?>-h" class="vt-h2"><?php echo esc_html( $s['title'] ); ?></h2>
			<span class="vt-line" aria-hidden="true"></span>
			<?php if ( $s['view_all'] ) : ?>
			<a href="#grid" class="vt-btn-ice vt-viewall" data-tdl-filter="<?php echo esc_attr( $filter ); ?>"><?php echo esc_html( $s['view_all'] ); ?></a>
			<?php endif; ?>
		</div>
		<div class="vt-qrow-in">
			<div class="vt-side vt-notch-side vt-reflect vt-side-<?php echo esc_attr( $theme ); ?><?php echo 'ice' === $theme ? ' vt-ice' : ''; ?>" data-tdl-reveal>
				<?php
				echo tdl_img( // phpcs:ignore
					$s['side_image'],
					array(
						'alt'   => $s['side_alt'],
						'class' => 'vt-side-img ' . ( 'ice' === $theme ? 'vt-float' : 'vt-float-2' ),
						'sizes' => '(max-width: 700px) 45vw, 320px',
						'size'  => 'large',
					)
				);
				?>
				<span class="vt-bob vt-side-spark" aria-hidden="true"></span>
				<span class="vt-bob vt-side-spark vt-side-spark-2" aria-hidden="true"></span>
				<div class="vt-side-tx">
					<span class="vt-serif" lang="en" dir="ltr"><?php echo esc_html( $s['side_en'] ); ?></span>
					<span class="vt-head vt-side-title"><?php echo esc_html( $s['side_title'] ); ?></span>
					<p class="vt-hide-sm"><?php echo esc_html( $s['side_desc'] ); ?></p>
					<p class="vt-only-sm"><?php echo esc_html( $s['side_desc_mobile'] ? $s['side_desc_mobile'] : $s['side_desc'] ); ?></p>
				</div>
			</div>
			<div class="vt-row-wrap">
				<button type="button" aria-label="محصولات قبلی" class="vt-btn-ice vt-hide-sm vt-arrow" data-tdl-scroll="1" aria-controls="row-<?php echo esc_attr( $id ); ?>"><?php echo tdl_glyph( 'chev-right', 18 ); // phpcs:ignore ?></button>
				<div id="row-<?php echo esc_attr( $id ); ?>" class="vt-scroll vt-row vt-row-reflect" data-tdl-drag>
					<?php
					foreach ( $products as $i => $product ) {
						echo TDL_Render::card( $product, $ctx, $i ); // phpcs:ignore
					}
					?>
				</div>
				<button type="button" aria-label="محصولات بعدی" class="vt-btn-ice vt-hide-sm vt-arrow" data-tdl-scroll="-1" aria-controls="row-<?php echo esc_attr( $id ); ?>"><?php echo tdl_glyph( 'chev-left', 18 ); // phpcs:ignore ?></button>
			</div>
		</div>
	</div>
</section>
