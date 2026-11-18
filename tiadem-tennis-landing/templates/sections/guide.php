<?php
/**
 * Buying guide, clasp comparison and table.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;
?>
<section id="guide" aria-labelledby="guide-h" class="vt-sec vt-guide-sec">
	<div class="vt-wrap">
		<div class="vt-sec-intro" data-tdl-reveal>
			<h2 id="guide-h" class="vt-h2 vt-h2-lg"><?php echo esc_html( $s['title'] ); ?></h2>
			<?php if ( $s['desc'] ) : ?><p><?php echo esc_html( $s['desc'] ); ?></p><?php endif; ?>
		</div>
		<ol class="vt-steps">
			<?php foreach ( (array) $s['steps'] as $i => $st ) : ?>
			<li class="vt-glass vt-lift vt-reflect vt-step" data-tdl-reveal style="--i:<?php echo (int) $i; ?>">
				<span class="vt-raised vt-step-n" aria-hidden="true"><?php echo esc_html( tdl_fa( $i + 1 ) ); ?></span>
				<h3><?php echo esc_html( $st['t'] ); ?></h3>
				<p><?php echo esc_html( $st['d'] ); ?></p>
			</li>
			<?php endforeach; ?>
		</ol>

		<div id="clasps" class="vt-clasps">
			<h3 class="vt-h3" data-tdl-reveal><?php echo esc_html( $s['clasp_title'] ); ?></h3>
			<div class="vt-clasp-grid">
				<?php foreach ( array( 'book' => 'vt-btn-ice', 'cuban' => 'vt-btn' ) as $k => $badge_cls ) : ?>
				<div class="vt-glass vt-reflect vt-clasp" data-tdl-reveal style="--i:<?php echo 'book' === $k ? 0 : 1; ?>">
					<div class="vt-well vt-clasp-img"><?php echo tdl_img( $s[ $k . '_image' ], array( 'alt' => $s[ $k . '_alt' ], 'sizes' => '(max-width: 700px) 90vw, 260px', 'size' => 'medium_large' ) ); // phpcs:ignore ?></div>
					<div class="vt-clasp-tx">
						<?php if ( $s[ $k . '_badge' ] ) : ?><span class="<?php echo esc_attr( $badge_cls ); ?> vt-pill"><?php echo esc_html( $s[ $k . '_badge' ] ); ?></span><?php endif; ?>
						<h4><?php echo esc_html( $s[ $k . '_title' ] ); ?></h4>
						<p><?php echo esc_html( $s[ $k . '_desc' ] ); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $s['rows'] ) ) : ?>
			<div class="vt-glass vt-table-wrap" data-tdl-reveal>
				<div class="vt-table-scroll" tabindex="0" role="region" aria-label="<?php echo esc_attr( $s['clasp_title'] ); ?>">
					<table class="vt-table">
						<thead><tr><th scope="col"><?php echo esc_html( $s['table_h1'] ); ?></th><th scope="col"><?php echo esc_html( $s['table_h2'] ); ?></th><th scope="col"><?php echo esc_html( $s['table_h3'] ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $s['rows'] as $row ) : ?>
							<tr><th scope="row"><?php echo esc_html( $row['f'] ); ?></th><td><?php echo esc_html( $row['a'] ); ?></td><td><?php echo esc_html( $row['b'] ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
