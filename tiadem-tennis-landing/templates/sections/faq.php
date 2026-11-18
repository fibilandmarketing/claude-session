<?php
/**
 * FAQ accordion.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $s['items'] ) ) {
	return;
}
?>
<section id="faq" aria-labelledby="faq-h" class="vt-sec vt-faq-sec">
	<div class="vt-faq">
		<h2 id="faq-h" class="vt-h2 vt-h2-lg vt-center" data-tdl-reveal><?php echo esc_html( $s['title'] ); ?></h2>
		<div class="vt-faq-list">
			<?php foreach ( $s['items'] as $i => $f ) : ?>
				<?php if ( empty( $f['q'] ) ) { continue; } ?>
				<details class="vt-details vt-glass" data-tdl-reveal style="--i:<?php echo (int) $i; ?>"<?php echo ! empty( $f['open'] ) ? ' open' : ''; ?>>
					<summary><span><?php echo esc_html( $f['q'] ); ?></span><span class="vt-plus vt-raised" aria-hidden="true"><?php echo tdl_glyph( 'plus', 16 ); // phpcs:ignore ?></span></summary>
					<div class="vt-details-body"><?php echo wp_kses_post( wpautop( $f['a'] ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
