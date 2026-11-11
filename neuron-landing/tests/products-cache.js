// WooCommerce carousel + page cache.
// Admin: hide one product, move another to the top, custom label; front: order, links, prices,
// arrows; click -> single product page; cache HIT for visitors, never for logged-in users.
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8899';
function check(name, ok, extra) { console.log((ok ? 'PASS ' : 'FAIL ') + name + (extra ? ' — ' + extra : '')); if (!ok) process.exitCode = 1; }
(async () => {
  const b = await chromium.launch();
  const admin = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  const p = await admin.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  await p.goto(BASE + '/wp-login.php');
  await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  await p.goto(BASE + '/wp-admin/admin.php?page=neuron-landing&tab=products');
  const names = await p.$$eval('.nrn-woo tr[data-id] td:nth-child(3) a', as => as.map(a => a.textContent.trim()));
  check('admin lists the WooCommerce products', names.length >= 5, names.join(', '));
  await p.selectOption('[data-prod-k="source"]', 'woo');
  // hide "محصول بدون عکس", move S350 to the top, custom label on S600
  await p.evaluate(() => {
    const rows = [...document.querySelectorAll('.nrn-woo tr[data-id]')];
    const by = n => rows.find(r => r.querySelector('td:nth-child(3) a').textContent.trim() === n);
    by('محصول بدون عکس').querySelector('.nrn-w-show').checked = false;
    const tb = rows[0].parentNode; tb.insertBefore(by('S350'), tb.firstChild);
    by('S600').querySelector('.nrn-w-tag').value = 'پرفروش‌ترین';
  });
  await Promise.all([p.waitForNavigation(), p.click('.nrn-save')]);
  check('saved', await p.locator('.notice-success').count() > 0);

  // start from an empty cache (the "clear cache" button of the settings tab)
  await p.goto(BASE + '/wp-admin/admin.php?page=neuron-landing&tab=settings');
  await Promise.all([p.waitForNavigation(), p.click('button[name=nrn_cache_clear]')]);
  check('clear-cache button', await p.locator('.notice-success').count() > 0);

  // ---- visitor (no login): cards, links, cache
  const v = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const f = await v.newPage();
  f.on('pageerror', e => errs.push('front: ' + e.message));
  let resp = await f.goto(BASE + '/', { waitUntil: 'networkidle' });
  const h1 = resp.headers()['x-nrn-cache'];
  resp = await f.goto(BASE + '/', { waitUntil: 'networkidle' });
  const h2 = resp.headers()['x-nrn-cache'];
  check('page cache: first visit builds, second is served from cache', h1 === 'MISS' && h2 === 'HIT', h1 + ' → ' + h2);
  const cards = await f.$$eval('.nrn-d .pcard', cs => cs.map(c => ({ name: c.querySelector('.pc-name').textContent, tag: (c.querySelector('.pc-tag') || {}).textContent || '', price: c.querySelector('.pc-price').textContent, href: c.getAttribute('href'), img: c.querySelector('img').getAttribute('src') })));
  check('first card is S350 (custom order)', cards[0] && cards[0].name === 'S350', cards.map(c => c.name).join(', '));
  check('hidden product is not shown', !cards.some(c => c.name === 'محصول بدون عکس'));
  check('custom label', (cards.find(c => c.name === 'S600') || {}).tag === 'پرفروش‌ترین');
  check('price from WooCommerce in Persian digits + unit', (cards.find(c => c.name === 'S350') || {}).price === '۱۲٬۲۴۰٬۰۰۰تومان', (cards.find(c => c.name === 'S350') || {}).price);
  check('product image from WooCommerce media', /uploads\//.test(cards[0].img), cards[0].img);
  check('mobile carousel has the same cards', (await f.$$eval('.nrn-m .pcard', cs => cs.length)) === cards.length);
  // arrows
  await f.locator('.nrn-d #products').scrollIntoViewIfNeeded(); await f.waitForTimeout(400);
  const before = await f.$eval('.nrn-d [data-ptrack]', t => [t.scrollLeft, t.scrollWidth - t.clientWidth]);
  if (before[1] > 0) {
    await f.click('.nrn-d [data-pnav="-1"]'); await f.waitForTimeout(800);
    const after = await f.$eval('.nrn-d [data-ptrack]', t => t.scrollLeft);
    check('carousel arrow scrolls the cards', after !== before[0], before[0] + ' → ' + after);
  } else {
    check('carousel arrows disabled when everything fits', await f.$eval('.nrn-d [data-pnav="1"]', b => b.disabled));
  }
  // click -> single product page
  await Promise.all([f.waitForNavigation(), f.click('.nrn-d .pcard >> nth=0')]);
  check('card opens the single product page', /\/product\/s350\/?$/.test(decodeURI(f.url())) && (await f.title()).includes('S350'), f.url());

  // ---- logged-in: never cached
  resp = await p.goto(BASE + '/', { waitUntil: 'networkidle' });
  check('logged-in users bypass the cache', !resp.headers()['x-nrn-cache']);
  check('no JS errors', errs.length === 0, errs.join(' | '));
  await b.close();
})();
