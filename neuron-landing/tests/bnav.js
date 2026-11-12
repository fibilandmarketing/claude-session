// Mobile bottom bar: shown on phones only, links, raised main button, live cart count (works with the page cache).
const { chromium, devices } = require('playwright');
const BASE = 'http://127.0.0.1:8899';
function check(name, ok, extra) { console.log((ok ? 'PASS ' : 'FAIL ') + name + (extra ? ' — ' + extra : '')); if (!ok) process.exitCode = 1; }
(async () => {
  const b = await chromium.launch();
  const errs = [];
  const d = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await d.goto(BASE + '/', { waitUntil: 'networkidle' });
  check('hidden on desktop', await d.evaluate(() => { const n = document.querySelector('.nrn-bnav'); return !n || n.getBoundingClientRect().width === 0; }));
  await d.close();

  const ctx = await b.newContext({ ...devices['Pixel 7'] });
  const p = await ctx.newPage();
  p.on('pageerror', e => errs.push(e.message));
  await p.goto(BASE + '/', { waitUntil: 'networkidle' });
  const info = await p.evaluate(() => {
    const n = document.querySelector('.nrn-bnav'), r = n.getBoundingClientRect();
    const items = [...n.querySelectorAll('.bn-item')].map(a => ({ t: a.textContent.trim(), href: a.getAttribute('href'), main: a.classList.contains('bn-main') }));
    const main = n.querySelector('.bn-main .bn-ic').getBoundingClientRect();
    return { fixedBottom: Math.round(innerHeight - r.bottom), w: Math.round(r.width), vw: innerWidth, items, mainRaised: main.top < r.top, badgeHidden: n.querySelector('[data-cart-count]').hidden };
  });
  check('fixed at the bottom of the screen', info.fixedBottom >= 8 && info.fixedBottom <= 40 && info.w >= info.vw - 24, JSON.stringify([info.fixedBottom, info.w]));
  check('5 items, the main one raised', info.items.length === 5 && info.items.filter(i => i.main).length === 1 && info.mainRaised, info.items.map(i => i.t + (i.main ? '*' : '')).join(' | '));
  check('cart item links to the WooCommerce cart', /\/cart\/?$/.test(info.items.find(i => i.t === 'سبد خرید').href), info.items.find(i => i.t === 'سبد خرید').href);
  check('no cart badge with an empty cart', info.badgeHidden);
  // stays on screen while scrolling
  await p.evaluate(() => scrollTo(0, 5000)); await p.waitForTimeout(500);
  check('still on screen after scrolling', await p.evaluate(() => { const r = document.querySelector('.nrn-bnav').getBoundingClientRect(); return r.bottom <= innerHeight && r.top > innerHeight - 120; }));
  // footer is not hidden behind the bar
  await p.evaluate(() => scrollTo(0, document.documentElement.scrollHeight)); await p.waitForTimeout(500);
  check('end of the page reachable above the bar', await p.evaluate(() => { const a = document.querySelector('.nrn-m [data-ref=root]').getBoundingClientRect(); const n = document.querySelector('.nrn-bnav').getBoundingClientRect(); return a.bottom <= n.top + 2; }));
  // hides while the drawer menu is open
  await p.evaluate(() => scrollTo(0, 0)); await p.waitForTimeout(300);
  await p.tap('.nrn-m header [data-menu-open]'); await p.waitForTimeout(600);
  check('moves away while the drawer menu is open', await p.evaluate(() => getComputedStyle(document.querySelector('.nrn-bnav')).opacity === '0'));
  await p.keyboard.press('Escape'); await p.waitForTimeout(600);
  check('comes back when the menu closes', await p.evaluate(() => getComputedStyle(document.querySelector('.nrn-bnav')).opacity === '1'));

  // add a product to the cart, then the (cached) homepage shows the count
  const pid = await p.evaluate(async () => { const r = await fetch('/wp-json/wc/store/v1/products?per_page=1'); return (await r.json())[0].id; });
  await p.goto(BASE + '/?add-to-cart=' + pid, { waitUntil: 'networkidle' });
  const r = await p.goto(BASE + '/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1200);
  const badge = await p.evaluate(() => { const b = document.querySelector('.nrn-bnav [data-cart-count]'); return [b.hidden, b.textContent]; });
  check('cart count on the bar (from WooCommerce, page cache ' + (r.headers()['x-nrn-cache'] || 'off') + ')', !badge[0] && badge[1] === '۱', JSON.stringify(badge));
  check('no JS errors', errs.length === 0, errs.join(' | '));
  await b.close();
})();
