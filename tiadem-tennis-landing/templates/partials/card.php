<?php
/**
 * Product card.
 *
 * @package TiademTennisLanding
 * @var array  $c     Card data (TDL_Products::card).
 * @var string $ctx   fest|fest-nt|eco|master|grid.
 * @var int    $index Position.
 */

defined( 'ABSPATH' ) || exit;

$is_fest = 0 === strpos( $ctx, 'fest' );
$is_grid = 'grid' === $ctx;
$img     = $c['image_id']
	? wp_get_attachment_image(
		$c['image_id'],
		'woocommerce_thumbnail',
		false,
		array(
			'class'    => 'vt-pimg',
			'alt'      => $c['name'],
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => '(max-width: 700px) 46vw, 250px',
		)
	)
	: wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'vt-pimg' ) );

switch ( $c['mode'] ) {
	case 'popup':
		$btn_label = 'انتخاب و افزودن';
		break;
	case 'link':
		$btn_label = 'مشاهده محصول';
		break;
	case 'none':
		$btn_label = 'ناموجود';
		break;
	default:
		$btn_label = 'افزودن به سبد';
}
$classes = 'vt-card vt-glass vt-lift' . ( $is_grid ? ' vt-card-grid' : ' vt-card-row' ) . ( $c['in_stock'] ? '' : ' is-out' );
?>
<article class="<?php echo esc_attr( $classes ); ?>" data-tdl-reveal data-id="<?php echo (int) $c['id']; ?>" style="--i:<?php echo (int) ( $index % 8 ); ?>">
	<div class="vt-well vt-card-media">
		<a href="<?php echo esc_url( $c['url'] ); ?>" class="vt-card-img" tabindex="-1" aria-hidden="true"><?php echo $img; // phpcs:ignore ?></a>
		<?php if ( $is_fest && 'fest' === $ctx && $c['sale_end'] ) : ?>
			<span class="vt-card-timer" data-tdl-countdown="<?php echo (int) $c['sale_end'] * 1000; ?>" data-format="hms" dir="ltr">--:--:--</span>
		<?php elseif ( $is_grid && $c['q_label'] ) : ?>
			<span class="vt-glass vt-card-tag"><?php echo esc_html( $c['q_label'] ); ?></span>
		<?php elseif ( ! $is_fest && $c['tag'] ) : ?>
			<span class="vt-card-tag <?php echo 'master' === $ctx ? 'vt-card-tag-dark' : 'vt-glass'; ?>"><?php echo esc_html( $c['tag'] ); ?></span>
		<?php endif; ?>
		<button type="button" class="vt-heart" aria-label="افزودن «<?php echo esc_attr( $c['name'] ); ?>» به علاقه‌مندی‌ها" aria-pressed="false" data-tdl-wish="<?php echo (int) $c['id']; ?>"><?php echo tdl_glyph( 'heart', 18 ); // phpcs:ignore ?></button>
		<?php if ( $is_grid && $c['off'] ) : ?>
			<span class="vt-off vt-off-abs"><?php echo esc_html( $c['off_text'] ); ?></span>
		<?php endif; ?>
	</div>
	<div class="vt-card-body">
		<?php if ( $is_grid ) : ?>
			<div class="vt-card-meta"><span><?php echo esc_html( $c['tag'] ); ?></span><?php if ( $c['rating'] ) : ?><span class="vt-rate"><?php echo tdl_glyph( 'star', 13 ); // phpcs:ignore ?><?php echo esc_html( $c['rating'] ); ?></span><?php endif; ?></div>
		<?php endif; ?>
		<h3 class="vt-pname"><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['name'] ); ?></a></h3>
		<?php if ( $is_fest ) : ?>
			<div class="vt-bar" aria-hidden="true"><span style="width:<?php echo (int) $c['sold']; ?>%"></span></div>
			<span class="vt-stock-a"><?php echo esc_html( $c['stock_text'] ); ?></span>
			<div class="vt-card-offrow">
				<?php if ( $c['off'] ) : ?><span class="vt-off"><?php echo esc_html( $c['off_text'] ); ?></span><?php endif; ?>
				<?php if ( $c['old'] ) : ?><del class="vt-old"><?php echo esc_html( $c['old'] ); ?></del><?php endif; ?>
			</div>
		<?php elseif ( ! $is_grid ) : ?>
			<div class="vt-card-meta"><span><?php echo esc_html( $c['stock_text'] ); ?></span><?php if ( $c['rating'] ) : ?><span class="vt-rate"><?php echo tdl_glyph( 'star', 13 ); // phpcs:ignore ?><?php echo esc_html( $c['rating'] ); ?></span><?php endif; ?></div>
		<?php endif; ?>
		<div class="vt-price-row">
			<span class="vt-price"><?php echo esc_html( $c['from'] . $c['price'] ); ?> <small><?php echo esc_html( tdl_currency() ); ?></small></span>
			<?php if ( ! $is_fest && $c['old'] ) : ?><del class="vt-old"><?php echo esc_html( $c['old'] ); ?></del><?php endif; ?>
		</div>
		<?php if ( 'link' === $c['mode'] ) : ?>
			<a href="<?php echo esc_url( $c['url'] ); ?>" class="vt-btn vt-add"><?php echo esc_html( $btn_label ); ?></a>
		<?php else : ?>
			<button type="button" class="vt-btn vt-add" data-tdl-add="<?php echo (int) $c['id']; ?>" data-mode="<?php echo esc_attr( $c['mode'] ); ?>"<?php echo 'none' === $c['mode'] ? ' disabled' : ''; ?>><span class="vt-add-l"><?php echo esc_html( $btn_label ); ?></span></button>
		<?php endif; ?>
	</div>
</article>
