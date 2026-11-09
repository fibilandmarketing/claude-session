// End-to-end: edit in the admin panel, save, verify on the front end (desktop + mobile), then reset.
// Covers the review items: S350 card link, hero button links, consultation links of the heavy /
// refrigerated sections, footer trust-seal code + removable label, highlighted [parts] of titles.
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8899';
function check(name, ok, extra) { console.log((ok ? 'PASS ' : 'FAIL ') + name + (extra ? ' — ' + extra : '')); if (!ok) process.exitCode = 1; }
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  p.on('dialog', d => d.accept());
  await p.goto(BASE + '/wp-login.php');
  await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  await p.goto(BASE + '/wp-admin/admin.php?page=neuron-landing&tab=hero');

  // set an input of a panel, found by its design value (opens its box first)
  async function setIn(tab, def, val, nth = 0, linkOf = false) {
    await p.click('.nrn-tab[data-tab="' + tab + '"]');
    const key = await p.evaluate(([tab, def, nth, linkOf]) => {
      const ins = [...document.querySelectorAll('.nrn-panel[data-panel="' + tab + '"] .nrn-in')].filter(i => i.dataset.default === def && !i.closest('.nrn-link-in'));
      const el = ins[nth]; if (!el) return null;
      el.closest('details').open = true;
      const target = linkOf ? el.closest('.nrn-fr-in').querySelector('.nrn-link-in .nrn-in') : el;
      return target ? target.dataset.key : null;
    }, [tab, def, nth, linkOf]);
    if (!key) throw new Error('field not found: ' + tab + ' / ' + def);
    await p.fill('.nrn-in[data-key="' + key + '"]', val);
    return key;
  }

  await setIn('hero', 'خودرویت را\n[روی نقشه ببین]', 'ماشینت را\n[روی نقشه ببین]');
  await setIn('hero', 'مشاهده ردیاب‌ها', 'https://example.com/trackers/', 0, true);         // item 4: hero button link
  await setIn('choose', 'مشاهده و خرید', 'https://example.com/s350/', 0, true);            // item 1: S350 card link
  await setIn('choose', 'از [۱۲٬۲۴۰٬۰۰۰] تومان', 'از [۱۳٬۰۰۰٬۰۰۰] تومان');
  await setIn('heavy', 'مشاورهٔ سازمانی', 'https://example.com/b2b/', 0, true);            // item 5
  await setIn('cold', 'مشاورهٔ سازمانی', 'tel:02100000000', 0, true);                       // item 5
  await setIn('hero', 'مشاهده دزدگیرها', '#heavy', 0, true);                                // in-page anchor (mobile: #m-heavy)
  // item 3: footer seal code + an empty label + image link
  await p.click('.nrn-tab[data-tab="footer"]');
  await p.evaluate(() => {
    const rows = document.querySelectorAll('.nrn-list[data-list=badges] .nrn-row');
    rows[0].querySelector('[data-f=code]').value = "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=1&Code=x'><img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=1&Code=x' alt='' style='cursor:pointer' code='x'></a>";
    rows[1].querySelector('[data-f=label]').value = '';
    rows[2].querySelector('[data-f=link]').value = 'https://example.com/knowledge/';
  });
  await Promise.all([p.waitForNavigation(), p.click('.nrn-save')]);
  check('saved', await p.locator('.notice-success').count() > 0);
  check('stays on the last tab', p.url().includes('tab=footer'));
  check('edited rows stay marked after reload', await p.evaluate(() => document.querySelectorAll('.nrn-panel[data-panel=choose] .nrn-fr.is-changed').length === 2));

  const f = await ctx.newPage();
  f.on('pageerror', e => errs.push('front: ' + e.message));
  await f.goto(BASE + '/', { waitUntil: 'networkidle' });
  const r = await f.evaluate(() => {
    const d = document.querySelector('.nrn-d'), m = document.querySelector('.nrn-m');
    const a = (root, text) => [...root.querySelectorAll('a')].filter(x => x.textContent.trim().startsWith(text));
    return {
      heroHtml: d.querySelector('.hero-big').innerHTML, heroM: m.querySelector('.hero-big').innerHTML,
      heroBtn: a(d, 'مشاهده ردیاب‌ها')[0].getAttribute('href'), heroBtnM: a(m, 'مشاهده ردیاب‌ها')[0].getAttribute('href'),
      anchorD: a(d, 'مشاهده دزدگیرها')[0].getAttribute('href'), anchorM: a(m, 'مشاهده دزدگیرها')[0].getAttribute('href'),
      s350: a(d.querySelector('#choose'), 'مشاهده و خرید')[0].getAttribute('href'), s350m: a(m.querySelector('#m-choose'), 'مشاهده و خرید')[0].getAttribute('href'),
      s600: a(d.querySelector('#choose'), 'مشاهده و خرید')[1].getAttribute('href'),
      price: d.querySelector('#choose').textContent.includes('۱۳٬۰۰۰٬۰۰۰'), priceB: !!d.querySelector('#choose b, #choose span') && [...d.querySelectorAll('#choose p, #choose div')].some(e => e.innerHTML.includes('۱۳٬۰۰۰٬۰۰۰</')),
      heavy: a(d.querySelector('#heavy'), 'مشاورهٔ سازمانی')[0].getAttribute('href'), cold: a(d.querySelector('#cold'), 'مشاورهٔ سازمانی')[0].getAttribute('href'),
      heavyM: a(m.querySelector('#m-heavy'), 'مشاورهٔ سازمانی').map(x => x.getAttribute('href')).join(),
      seal: !!d.querySelector('footer .nrn-seal a[href*="trustseal.enamad.ir"] img[referrerpolicy][code]'), sealM: !!m.querySelector('footer .nrn-seal img'),
      labels: [...d.querySelectorAll('footer span')].map(s => s.textContent.trim()).filter(t => t === 'ساماندهی' || t === 'دانش‌بنیان'),
      badgeLink: !!d.querySelector('footer a[href="https://example.com/knowledge/"] img'),
    };
  });
  check('title with highlighted part (desktop)', r.heroHtml.startsWith('ماشینت را<br><span>روی نقشه ببین</span>'), r.heroHtml);
  check('title with highlighted part (mobile, shared field)', r.heroM.startsWith('ماشینت را<br><span>روی نقشه ببین</span>'), r.heroM);
  check('item 4: hero button link (desktop + mobile)', r.heroBtn === 'https://example.com/trackers/' && r.heroBtnM === 'https://example.com/trackers/');
  check('in-page anchor maps to the mobile section id', r.anchorD === '#heavy' && r.anchorM === '#m-heavy', r.anchorD + ' / ' + r.anchorM);
  check('item 1: S350 card link (desktop + mobile), S600 untouched', r.s350 === 'https://example.com/s350/' && r.s350m === 'https://example.com/s350/' && r.s600 === '#', [r.s350, r.s350m, r.s600].join(' '));
  check('price keeps its bold style', r.price && r.priceB);
  check('item 5: consultation links (heavy, refrigerated)', r.heavy === 'https://example.com/b2b/' && r.cold === 'tel:02100000000', r.heavy + ' / ' + r.cold + ' / mobile: ' + r.heavyM);
  check('item 3: eNamad code printed as is (desktop + mobile)', r.seal && r.sealM);
  check('item 3: badge label removable + image link', r.labels.join() === 'دانش‌بنیان' && r.badgeLink, JSON.stringify(r.labels));

  await p.goto(BASE + '/wp-admin/admin.php?page=neuron-landing&tab=settings');
  await Promise.all([p.waitForNavigation(), p.click('.nrn-reset-all')]);
  await f.goto(BASE + '/', { waitUntil: 'networkidle' });
  const back = await f.evaluate(() => ({ hero: document.querySelector('.nrn-d .hero-big').innerHTML, seal: !!document.querySelector('.nrn-seal'), btn: [...document.querySelectorAll('.nrn-d .hero a')].find(x => x.textContent.trim().startsWith('مشاهده ردیاب‌ها')).getAttribute('href') }));
  check('reset all restores the design', back.hero.startsWith('خودرویت را<br><span>روی نقشه ببین</span>') && !back.seal && back.btn === '#choose', JSON.stringify(back));
  check('no JS errors', errs.length === 0, errs.join(' | '));
  await b.close();
})();
