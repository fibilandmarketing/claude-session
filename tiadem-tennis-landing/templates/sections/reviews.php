<?php
/**
 * Buyer reviews.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$items = array();
if ( 'auto' === $s['source'] ) {
	$items = TDL_Products::reviews( (int) $s['min_rating'], (int) $s['limit'] );
}
if ( ! $items ) {
	$items = (array) $s['items'];
}

$avg   = (float) str_replace( array( '٫', '/' ), '.', (string) $s['avg_manual'] );
$count = (int) $s['count_manual'];
if ( 'auto' === $s['avg_mode'] ) {
	$stats = TDL_Products::review_stats();
	if ( $stats['count'] ) {
		$avg   = (float) $stats['avg'];
		$count = (int) $stats['count'];
	}
}
$avg       = max( 0, min( 5, $avg ) );
$avg_text  = tdl_fa( number_format( $avg, 1 ) );
$full      = (int) round( $avg );
$count_txt = $count ? str_replace( '{count}', tdl_fa( number_format( $count ) ), $s['count_text'] ) : '';
$count_txt = str_replace( ',', '٬', $count_txt );
if ( ! $items && ! $avg ) {
	return;
}
?>
<section aria-labelledby="rev-h" class="vt-sec vt-rev-sec">
	<div class="vt-glass vt-reflect-soft vt-rev" data-tdl-reveal>
		<div class="vt-rev-sum">
			<h2 id="rev-h" class="vt-rev-h"><?php echo esc_html( $s['title'] ); ?></h2>
			<?php if ( $avg ) : ?>
			<div class="vt-rev-avg"><span class="vt-rev-num"><?php echo esc_html( $avg_text ); ?></span><span>از ۵</span></div>
			<div class="vt-stars" role="img" aria-label="<?php echo esc_attr( $avg_text . ' از ۵ ستاره' ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<span class="<?php echo $i <= $full ? 'is-on' : ''; ?>"><?php echo tdl_glyph( 'star', 18 ); // phpcs:ignore ?></span>
				<?php endfor; ?>
			</div>
			<?php endif; ?>
			<?php if ( $count_txt ) : ?><span class="vt-sub-s"><?php echo esc_html( $count_txt ); ?></span><?php endif; ?>
		</div>
		<div class="vt-scroll vt-rev-list" data-tdl-drag>
			<?php foreach ( $items as $r ) : ?>
				<?php
				if ( empty( $r['text'] ) ) {
					continue;
				}
				$initial = function_exists( 'mb_substr' ) ? mb_substr( trim( (string) $r['name'] ), 0, 1 ) : substr( (string) $r['name'], 0, 1 );
				?>
				<figure class="vt-raised vt-rev-card">
					<blockquote><p>«<?php echo esc_html( $r['text'] ); ?>»</p></blockquote>
					<figcaption><span class="vt-well vt-rev-ini" aria-hidden="true"><?php echo esc_html( $initial ); ?></span><span class="vt-rev-who"><b><?php echo esc_html( $r['name'] ); ?></b><span><?php echo esc_html( $r['product'] ); ?></span></span></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
