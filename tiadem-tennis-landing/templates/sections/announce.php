<?php
/**
 * Announcement bar.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$tag   = $s['link'] ? 'a' : 'div';
$href  = $s['link'] ? ' href="' . esc_url( $s['link'] ) . '"' : '';
?>
<<?php echo $tag; // phpcs:ignore ?> class="vt-annc"<?php echo $href; // phpcs:ignore ?>>
	<span class="vt-hide-sm vt-annc-d"><?php echo esc_html( $s['text'] ); ?></span>
	<span class="vt-only-sm vt-annc-m"><span class="vt-spark vt-spark-inline" aria-hidden="true"></span><?php echo esc_html( $s['text_mobile'] ? $s['text_mobile'] : $s['text'] ); ?></span>
</<?php echo $tag; // phpcs:ignore ?>>
