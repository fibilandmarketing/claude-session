# Tiadem Tennis Landing — WordPress/WooCommerce plugin (design)

Source design: claude.ai artifact D1nUd5cTjGUgjwdyyhHyr5 (Vitrine desktop + mobile artboards), PDFs of both.

## Decisions (approved by user, 2026-11-18)
- Delivery: standalone plugin `tiadem-tennis-landing`, full-page template "لندینگ تنیسی تیادم" with its own header/footer; theme assets isolated (option to use theme header/footer instead). Shortcode `[tiadem_tennis_landing]` as secondary path.
- Economy / Master quality = WooCommerce product categories (mapped in settings). Clasp type mappable to category or attribute.
- Variable products: glass quick-select popup, AJAX add to cart. Simple products: AJAX add.
- Wishlist: YITH if active, else user meta (logged-in) + localStorage (guests).
- Festival: auto from on-sale products (manual override); main countdown end set in admin with Jalali picker (Asia/Tehran); per-card timer from product sale end date; section hides when expired.
- Reviews: average/count from real WC reviews of scope products; cards auto (>= min rating) or manual.
- Activation creates the landing page with all design copy/images as defaults (images bundled in plugin, not imported).

## Architecture
- Settings: single option `tdl_settings` (non-autoloaded), schema-driven admin (tabs per section, repeaters, media, term/product pickers, icon picker, Jalali datetime), JSON payload save (avoids max_input_vars), per-section reset, import/export.
- Rendering: server-side PHP templates per section (SEO), section order + enable toggles from settings.
- WooCommerce: `wc_get_products` queries, ID lists cached in transients keyed by a cache version bumped on product/stock/sale/settings changes.
- AJAX via `wc-ajax` endpoints: grid filter/pagination, add to cart (simple+variation), quick view data, wishlist toggle/state, cart count (cache-friendly).
- Front-end: one CSS + one vanilla JS (deferred), self-hosted fonts (Vazirmatn var, heading font, Cormorant italic), static hero poster for LCP then animated WebP swap, IntersectionObserver reveals, reduced-motion support.
- SEO: FAQPage + ItemList JSON-LD, title fallback, meta description fallback when no SEO plugin.
- Security: capability `manage_woocommerce`, nonces on admin save and logged-in wishlist, sanitize by field type, escape on output.

## Testing
Local WordPress + WooCommerce (SQLite) with sample categories/products (simple, variable, on-sale with schedule, stock, reviews); PHP lint; Playwright: render desktop/mobile, add to cart (simple + variable popup), grid filters/load more, wishlist, drawer, admin save round-trip; screenshots vs design.
