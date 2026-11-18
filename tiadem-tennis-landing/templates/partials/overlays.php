<?php
/**
 * Quick-select modal and toast containers (filled by JS).
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="tdl-ov" id="tdl-qv" role="dialog" aria-modal="true" aria-labelledby="tdl-qv-title" hidden>
	<div class="tdl-ov-scrim" data-tdl-close="qv"></div>
	<div class="tdl-qv vt-glass" tabindex="-1">
		<button type="button" class="vt-btn-ice vt-icon-btn tdl-qv-x" aria-label="بستن" data-tdl-close="qv"><?php echo tdl_glyph( 'close', 18 ); // phpcs:ignore ?></button>
		<div class="tdl-qv-body" data-tdl-qv-body></div>
	</div>
</div>
<div class="tdl-toast" role="status" aria-live="polite" data-tdl-toast hidden>
	<span class="tdl-toast-ic"><?php echo tdl_glyph( 'check', 16 ); // phpcs:ignore ?></span>
	<span class="tdl-toast-msg" data-tdl-toast-msg></span>
	<a class="tdl-toast-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">مشاهده سبد</a>
</div>
