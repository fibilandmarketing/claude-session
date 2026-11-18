<?php
/**
 * Tennis jewelry types and stone shapes.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;
?>
<section aria-labelledby="types-h" class="vt-sec vt-types-sec">
	<div class="vt-wrap">
		<div class="vt-sec-intro" data-tdl-reveal>
			<h2 id="types-h" class="vt-h2 vt-h2-lg"><?php echo esc_html( $s['title'] ); ?></h2>
			<?php if ( $s['desc'] ) : ?><p><?php echo esc_html( $s['desc'] ); ?></p><?php endif; ?>
		</div>
		<div class="vt-types">
			<?php foreach ( (array) $s['items'] as $i => $t ) : ?>
				<?php
				$chip = TDL_Render::chip_for_term( $t['term'] );
				$href = tdl_link( $t['term'], $t['url'] );
				?>
				<a href="<?php echo esc_url( $href ); ?>" class="vt-glass vt-lift vt-reflect vt-type" data-tdl-reveal style="--i:<?php echo (int) $i; ?>">
					<div class="vt-well vt-type-img"><?php echo tdl_img( $t['image'], array( 'alt' => $t['name'], 'class' => 'vt-pimg', 'sizes' => '92px', 'size' => 'thumbnail' ) ); // phpcs:ignore ?></div>
					<div class="vt-type-tx">
						<h3><?php echo esc_html( $t['name'] ); ?></h3>
						<p><?php echo esc_html( $t['desc'] ); ?></p>
						<span class="vt-type-more"><?php echo esc_html( $s['link_text'] ); ?></span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $s['shapes'] ) ) : ?>
		<div class="vt-glass vt-shapes" data-tdl-reveal>
			<span class="vt-shapes-t"><?php echo esc_html( $s['shapes_title'] ); ?></span>
			<?php foreach ( $s['shapes'] as $sh ) : ?>
				<?php if ( empty( $sh['label'] ) ) { continue; } ?>
				<?php $chip = TDL_Render::chip_for_term( $sh['term'] ); ?>
				<a href="<?php echo esc_url( $chip ? '#grid' : tdl_link( $sh['term'], $sh['url'] ) ); ?>" class="vt-chip"<?php echo $chip ? ' data-tdl-filter="' . esc_attr( $chip ) . '"' : ''; ?>><?php echo esc_html( $sh['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</section>
