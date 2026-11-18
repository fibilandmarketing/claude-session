<?php
/**
 * All products grid with filter chips and load more.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$res = TDL_Products::ids(
	array(
		'filter'   => 'all',
		'page'     => 1,
		'per_page' => (int) $s['per_page'],
		'orderby'  => $s['orderby'],
	)
);

$chips = array();
$woo   = tdl_opt( 'woo' );
foreach ( (array) $s['chips'] as $chip ) {
	$f = isset( $chip['filter'] ) ? $chip['filter'] : '';
	if ( '' === $f || '' === trim( (string) $chip['label'] ) ) {
		continue;
	}
	if ( ( 'eco' === $f && ! $woo['eco_cat'] ) || ( 'master' === $f && ! $woo['master_cat'] ) ) {
		continue;
	}
	$chips[] = $chip;
}
?>
<section id="grid" aria-labelledby="grid-h" class="vt-sec vt-grid-sec" data-tdl-grid data-per-page="<?php echo (int) $s['per_page']; ?>">
	<div class="vt-wrap">
		<div class="vt-sec-head" data-tdl-reveal>
			<h2 id="grid-h" class="vt-h2"><?php echo esc_html( $s['title'] ); ?></h2>
			<span class="vt-sub" data-tdl-count aria-live="polite"><?php echo esc_html( tdl_fa( $res['total'] ) . ' محصول' ); ?></span>
		</div>
		<?php if ( count( $chips ) > 1 ) : ?>
		<div class="vt-scroll vt-chips" role="group" aria-label="فیلتر دسته‌بندی" data-tdl-drag>
			<?php foreach ( $chips as $i => $chip ) : ?>
				<button type="button" class="vt-chip<?php echo 0 === $i ? ' is-on' : ''; ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>" data-tdl-chip="<?php echo esc_attr( $chip['filter'] ); ?>"><?php echo esc_html( $chip['label'] ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<div class="vt-grid" data-tdl-items>
			<?php
			foreach ( TDL_Products::load( $res['ids'] ) as $i => $product ) {
				echo TDL_Render::card( $product, 'grid', $i ); // phpcs:ignore
			}
			?>
		</div>
		<p class="vt-empty" data-tdl-empty<?php echo $res['total'] ? ' hidden' : ''; ?>><?php echo esc_html( $s['empty_text'] ); ?></p>
		<div class="vt-more-wrap">
			<button type="button" class="vt-btn-ice vt-more" data-tdl-more<?php echo $res['pages'] > 1 ? '' : ' hidden'; ?>><?php echo esc_html( $s['more_text'] ); ?></button>
		</div>
	</div>
</section>
