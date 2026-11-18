<?php
/**
 * Sticky glass header.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$g     = tdl_opt( 'general' );
$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
?>
<div class="vt-hdr-wrap" id="tdl-hdr">
	<header class="vt-glass vt-hdr">
		<button type="button" class="vt-btn-ice vt-only-sm vt-burger" aria-label="باز کردن منو" aria-expanded="false" aria-controls="tdl-drawer" data-tdl-open="drawer"><span></span><span></span><span></span></button>
		<a href="#top" class="vt-hdr-logo" aria-label="<?php echo esc_attr( $g['logo_alt'] ); ?>، بالای صفحه">
			<?php echo tdl_img( $g['logo'], array( 'alt' => $g['logo_alt'], 'loading' => 'eager', 'size' => 'medium', 'sizes' => '180px' ) ); // phpcs:ignore ?>
		</a>
		<nav class="vt-hide-sm vt-nav" aria-label="منوی اصلی">
			<?php foreach ( (array) $s['nav'] as $link ) : ?>
				<?php if ( empty( $link['t'] ) ) { continue; } ?>
				<a href="<?php echo esc_url( $link['href'] ); ?>" class="vt-link"><?php echo esc_html( $link['t'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="vt-hdr-actions">
			<?php if ( ! empty( $s['show_search'] ) ) : ?>
			<div class="vt-search-wrap vt-hide-sm">
				<button type="button" aria-label="جستجو" aria-expanded="false" aria-controls="tdl-search" class="vt-btn-ice vt-icon-btn" data-tdl-search><?php echo tdl_glyph( 'search', 19 ); // phpcs:ignore ?></button>
				<form id="tdl-search" class="vt-glass vt-search-pop" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="tdl-search-in">جستجو در محصولات</label>
					<input id="tdl-search-in" type="search" name="s" placeholder="<?php echo esc_attr( $s['search_placeholder'] ); ?>">
					<input type="hidden" name="post_type" value="product">
					<button type="submit" class="vt-btn" aria-label="جستجو"><?php echo tdl_glyph( 'search', 17 ); // phpcs:ignore ?></button>
				</form>
			</div>
			<?php endif; ?>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="vt-btn vt-cart" aria-label="سبد خرید" data-tdl-cart>
				<?php echo tdl_glyph( 'bag', 19 ); // phpcs:ignore ?>
				<span class="vt-cart-txt"><?php echo esc_html( $s['cart_label'] ); ?></span>
				<span class="tdl-cart-count" data-count="<?php echo (int) $count; ?>"><?php echo esc_html( tdl_fa( $count ) ); ?></span>
			</a>
		</div>
	</header>
</div>
