<?php
/**
 * Related categories.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $s['items'] ) ) {
	return;
}
?>
<section aria-labelledby="rel-h" class="vt-sec vt-rel-sec">
	<div class="vt-wrap">
		<h2 id="rel-h" class="vt-h2" data-tdl-reveal><?php echo esc_html( $s['title'] ); ?></h2>
		<div class="vt-rel">
			<?php foreach ( $s['items'] as $i => $r ) : ?>
			<a href="<?php echo esc_url( tdl_link( $r['term'], $r['url'] ) ); ?>" class="vt-ice vt-lift vt-reflect vt-rel-card" data-tdl-reveal style="--i:<?php echo (int) $i; ?>">
				<?php echo tdl_img( $r['image'], array( 'alt' => $r['alt'] ? $r['alt'] : $r['name'], 'class' => 'vt-pimg', 'sizes' => '(max-width: 700px) 46vw, 300px', 'size' => 'medium_large' ) ); // phpcs:ignore ?>
				<span class="vt-glass vt-rel-name"><?php echo esc_html( $r['name'] ); ?><?php echo tdl_glyph( 'chev-left', 16 ); // phpcs:ignore ?></span>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
