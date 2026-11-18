<?php
/**
 * Mobile drawer menu.
 *
 * @package TiademTennisLanding
 * @var array $s Header settings.
 */

defined( 'ABSPATH' ) || exit;

$g    = tdl_opt( 'general' );
$cats = tdl_opt( 'cats', 'items', array() );
?>
<div class="vt-scrim" data-tdl-close="drawer" hidden></div>
<nav id="tdl-drawer" class="vt-drawer vt-glass" aria-label="منوی موبایل" hidden>
	<div class="vt-drawer-top">
		<?php echo tdl_img( $g['logo'], array( 'alt' => $g['logo_alt'], 'class' => 'vt-drawer-logo', 'sizes' => '160px', 'size' => 'medium' ) ); // phpcs:ignore ?>
		<button type="button" class="vt-btn-ice vt-icon-btn" aria-label="بستن منو" data-tdl-close="drawer"><?php echo tdl_glyph( 'close', 18 ); // phpcs:ignore ?></button>
	</div>
	<form class="vt-well vt-dl vt-drawer-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="--d:.05s">
		<?php echo tdl_glyph( 'search', 18 ); // phpcs:ignore ?>
		<label class="screen-reader-text" for="tdl-drawer-s">جستجو در محصولات تنیسی</label>
		<input id="tdl-drawer-s" type="search" name="s" placeholder="<?php echo esc_attr( $s['search_placeholder'] ); ?>">
		<input type="hidden" name="post_type" value="product">
	</form>
	<?php if ( $cats ) : ?>
	<div class="vt-dl vt-drawer-cats" style="--d:.1s">
		<span class="vt-drawer-cap"><?php echo esc_html( $s['drawer_cats_title'] ); ?></span>
		<div class="vt-drawer-cat-grid">
			<?php foreach ( $cats as $c ) : ?>
				<?php
				$chip = TDL_Render::chip_for_term( $c['term'] );
				$href = $chip ? '#grid' : tdl_link( $c['term'], $c['url'] );
				?>
				<a href="<?php echo esc_url( $href ); ?>" class="vt-drawer-cat"<?php echo $chip ? ' data-tdl-filter="' . esc_attr( $chip ) . '"' : ''; ?> data-tdl-close="drawer">
					<span class="vt-raised"><?php echo tdl_img( $c['image'], array( 'alt' => '', 'sizes' => '64px', 'size' => 'thumbnail' ) ); // phpcs:ignore ?></span>
					<span><?php echo esc_html( $c['name'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
	<div class="vt-drawer-links">
		<?php foreach ( (array) $s['drawer_links'] as $i => $m ) : ?>
			<?php if ( empty( $m['t'] ) ) { continue; } ?>
			<a href="<?php echo esc_url( $m['href'] ); ?>" class="vt-raised vt-dl vt-drawer-link" style="--d:<?php echo esc_attr( 0.18 + 0.06 * $i ); ?>s" data-tdl-close="drawer">
				<span class="vt-drawer-ic"><?php echo tdl_icon( $m['icon'], 17, 1.8 ); // phpcs:ignore ?></span>
				<span class="vt-drawer-t"><?php echo esc_html( $m['t'] ); ?></span>
				<?php if ( ! empty( $m['badge'] ) ) : ?><span class="vt-badge"><?php echo esc_html( $m['badge'] ); ?></span><?php endif; ?>
				<?php echo tdl_glyph( 'chev-left', 15 ); // phpcs:ignore ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( $g['phone'] || $g['instagram'] || $g['telegram'] || $g['bale'] ) : ?>
	<div class="vt-glass vt-dl vt-drawer-contact" style="--d:.5s">
		<?php if ( $g['phone'] ) : ?>
		<div class="vt-drawer-row">
			<div class="vt-drawer-col"><span class="vt-muted-s"><?php echo esc_html( $g['phone_label'] ); ?></span><span class="vt-phone"><?php echo esc_html( tdl_fa( $g['phone'] ) ); ?></span></div>
			<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $g['phone'] ) ); ?>" aria-label="تماس تلفنی" class="vt-btn vt-round-btn"><?php echo tdl_glyph( 'phone', 19 ); // phpcs:ignore ?></a>
		</div>
		<?php endif; ?>
		<?php if ( $g['instagram'] || $g['telegram'] || $g['bale'] ) : ?>
		<div class="vt-drawer-row vt-drawer-social">
			<span class="vt-muted-s"><?php echo esc_html( $g['social_label'] ); ?></span>
			<div class="vt-social-list">
				<?php foreach ( array( 'instagram' => 'اینستاگرام', 'telegram' => 'تلگرام', 'bale' => 'بله' ) as $net => $label ) : ?>
					<?php if ( $g[ $net ] ) : ?>
					<a href="<?php echo esc_url( $g[ $net ] ); ?>" aria-label="<?php echo esc_attr( $label . ' ' . $g['logo_alt'] ); ?>" class="vt-raised vt-social" target="_blank" rel="noopener"><?php echo tdl_glyph( $net ); // phpcs:ignore ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</nav>
