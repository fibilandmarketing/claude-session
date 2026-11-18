<?php
/**
 * Category cards.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $s['items'] ) ) {
	return;
}
?>
<section aria-labelledby="cats-h" class="vt-sec vt-cats-sec">
	<div class="vt-wrap">
		<div class="vt-sec-head vt-sec-head-split" data-tdl-reveal>
			<h2 id="cats-h" class="vt-h2"><?php echo esc_html( $s['title'] ); ?></h2>
			<span class="vt-sub"><?php echo esc_html( $s['subtitle'] ); ?></span>
		</div>
		<div class="vt-scroll vt-cats-row" data-tdl-drag>
			<?php foreach ( $s['items'] as $i => $c ) : ?>
				<?php
				$chip = TDL_Render::chip_for_term( $c['term'] );
				$href = $chip ? '#grid' : tdl_link( $c['term'], $c['url'] );
				?>
				<a href="<?php echo esc_url( $href ); ?>" class="vt-lift vt-cat"<?php echo $chip ? ' data-tdl-filter="' . esc_attr( $chip ) . '"' : ''; ?> data-tdl-reveal style="--i:<?php echo (int) $i; ?>">
					<div class="vt-ice vt-catcard vt-reflect">
						<span class="vt-bigword vt-head vt-hide-sm" aria-hidden="true"><?php echo esc_html( $c['name'] ); ?></span>
						<div class="vt-cat-img"><?php echo tdl_img( $c['image'], array( 'alt' => $c['name'] . ' تنیسی', 'class' => 'vt-pimg', 'sizes' => '(max-width: 700px) 132px, 200px', 'size' => 'medium_large' ) ); // phpcs:ignore ?></div>
						<span class="vt-cat-name vt-head"><?php echo esc_html( $c['name'] ); ?></span>
					</div>
					<span class="vt-cat-chev"><?php echo tdl_glyph( 'chev-down', 16 ); // phpcs:ignore ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
