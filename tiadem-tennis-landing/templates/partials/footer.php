<?php
/**
 * Footer.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$g = tdl_opt( 'general' );
?>
<footer class="vt-footer-wrap">
	<div class="vt-glass vt-footer">
		<a href="#top" class="vt-footer-logo" aria-label="<?php echo esc_attr( $g['logo_alt'] ); ?>"><?php echo tdl_img( $g['logo'], array( 'alt' => $g['logo_alt'], 'sizes' => '160px', 'size' => 'medium' ) ); // phpcs:ignore ?></a>
		<nav class="vt-footer-links" aria-label="لینک‌های فوتر">
			<?php foreach ( (array) $s['links'] as $l ) : ?>
				<?php if ( empty( $l['t'] ) ) { continue; } ?>
				<a href="<?php echo esc_url( $l['href'] ); ?>" class="vt-link"><?php echo esc_html( $l['t'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<span class="vt-footer-domain" dir="ltr"><?php echo esc_html( $s['domain'] ); ?></span>
	</div>
</footer>
