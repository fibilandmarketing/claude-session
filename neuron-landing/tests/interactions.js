// Front-end interactions of the landing (run against WordPress with the plugin on).
const { chromium } = require('playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8899/';
function check(name, ok, extra) { console.log((ok ? 'PASS ' : 'FAIL ') + name + (extra ? ' — ' + extra : '')); if (!ok) process.exitCode = 1; }
(async () => {
  const b = await chromium.launch();
  const errs = [];
  // ---------------- desktop
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  p.on('pageerror', e => errs.push(e.message));
  await p.goto(BASE, { waitUntil: 'networkidle' });
  const c0 = await p.evaluate(() => document.querySelector('.nrn-d .car').style.transform);
  await p.waitForTimeout(1200);
  const c1 = await p.evaluate(() => document.querySelector('.nrn-d .car').style.transform);
  await p.waitForTimeout(2200);
  const c2 = await p.evaluate(() => document.querySelector('.nrn-d .car').style.transform);
  check('intro: car drives in on load', c0 !== c1 && c1 !== c2, [c0, c1, c2].map(s => s.slice(12, 40)).join(' → '));
  check('mobile layout hidden on desktop', await p.evaluate(() => getComputedStyle(document.querySelector('.nrn-m')).display === 'none'));
  // marquee moves
  const m0 = await p.evaluate(() => getComputedStyle(document.querySelector('.nrn-d .mtrack')).transform);
  await p.waitForTimeout(700);
  const m1 = await p.evaluate(() => getComputedStyle(document.querySelector('.nrn-d .mtrack')).transform);
  check('logo marquee is moving', m0 !== m1);
  check('marquee has two identical copies', await p.evaluate(() => { const o = document.querySelectorAll('.nrn-d .mtrack .org'); return o.length === 20 && document.querySelectorAll('.nrn-d .mtrack .org[aria-hidden=true]').length === 10; }));
  check('no horizontal scrollbar', await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  // map hover / click / select / keyboard
  await p.locator('.nrn-d [data-province=fars]').scrollIntoViewIfNeeded();
  await p.hover('.nrn-d [data-province=fars]', { force: true });
  let info = await p.evaluate(() => [document.querySelector('.nrn-d [data-mname]').textContent, document.querySelector('.nrn-d [data-mcount]').textContent, document.querySelector('.nrn-d [data-mhov]').classList.contains('on')]);
  check('map hover shows province', info[0] === 'فارس' && info[1] === '۸ نمایندگی فعال' && info[2], info.join(' | '));
  await p.click('.nrn-d [data-province=qom]', { force: true });
  await p.mouse.move(5, 5);
  info = await p.evaluate(() => [document.querySelector('.nrn-d [data-mname]').textContent, document.querySelector('.nrn-d [data-mcount]').textContent, document.querySelector('.nrn-d [data-mcta]').textContent, document.querySelector('.nrn-d [data-mcta]').getAttribute('href'), document.querySelector('.nrn-d #prov').value, document.querySelector('.nrn-d [data-province=qom]').classList.contains('sel')]);
  check('map click selects province (no agents → call CTA)', info[0] === 'قم' && info[2] === 'تماس با نورون' && info[3] === 'tel:05152220010' && info[4] === 'qom' && info[5], info.join(' | '));
  await p.selectOption('.nrn-d #prov', 'gilan');
  info = await p.evaluate(() => [document.querySelector('.nrn-d [data-mname]').textContent, document.querySelector('.nrn-d [data-mcta]').getAttribute('href'), document.querySelector('.nrn-d [data-msel]').getAttribute('d') === document.querySelector('.nrn-d [data-province=gilan]').getAttribute('d')]);
  check('dropdown selects province on map', info[0] === 'گیلان' && info[1] === 'https://noroncar.ir/namayandegi/' && info[2], info.join(' | '));
  await p.focus('.nrn-d [data-province=yazd]');
  await p.keyboard.press('Enter');
  check('keyboard Enter selects province', await p.evaluate(() => document.querySelector('.nrn-d #prov').value === 'yazd'));
  // FAQ
  const faq = await p.evaluate(() => { const d = document.querySelectorAll('.nrn-d details'); return [d[0].open, d[1].open]; });
  await p.click('.nrn-d details:nth-of-type(2) summary');
  const faq2 = await p.evaluate(() => document.querySelectorAll('.nrn-d details')[1].open);
  check('FAQ: first open, others toggle', faq[0] && !faq[1] && faq2);
  // section visited glow + stops
  await p.evaluate(() => window.scrollTo(0, 3000)); await p.waitForTimeout(1800);
  check('passed sections get the "visited" state', await p.evaluate(() => document.querySelectorAll('.nrn-d [data-stop].visited').length >= 2));
  // the car drives under boxes: they turn see-through ("tunnel") while it passes, then back
  let maxT = 0, seen = new Set();
  for (let y = 0; y < 9000; y += 150) {
    await p.evaluate(y => window.scrollTo(0, y), y); await p.waitForTimeout(120);
    const t = await p.evaluate(() => [...document.querySelectorAll('.nrn-d .tunnel')].map(e => e.className.split(' ')[0] + '@' + Math.round(e.getBoundingClientRect().top + scrollY)));
    maxT = Math.max(maxT, t.length); t.forEach(x => seen.add(x));
  }
  await p.waitForTimeout(1500);
  const tNow = await p.evaluate(() => document.querySelectorAll('.nrn-d .tunnel').length);
  check('boxes turn to glass while the car passes under them', maxT > 0 && seen.size >= 3, 'max at once=' + maxT + ', distinct boxes=' + seen.size + ', now=' + tNow);
  // breakpoint switch at runtime
  await p.setViewportSize({ width: 390, height: 844 });
  await p.waitForTimeout(1500);
  const mob = await p.evaluate(() => ({ d: getComputedStyle(document.querySelector('.nrn-d')).display, m: getComputedStyle(document.querySelector('.nrn-m')).display, car: document.querySelector('.nrn-m .car').style.transform, stops: document.querySelectorAll('.nrn-m .stop').length }));
  check('resize to phone switches to the mobile design and starts its car', mob.d === 'none' && mob.m === 'block' && mob.car.includes('translate3d') && mob.stops === 6, JSON.stringify(mob));
  await p.close();

  // ---------------- mobile (fresh, touch)
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
  const q = await ctx.newPage();
  q.on('pageerror', e => errs.push('mobile: ' + e.message));
  await q.goto(BASE, { waitUntil: 'networkidle' });
  await q.waitForTimeout(500);
  check('mobile: no horizontal scroll', await q.evaluate(() => document.documentElement.scrollWidth <= innerWidth), String(await q.evaluate(() => document.documentElement.scrollWidth)));
  await q.tap('.nrn-m [data-menu-open]');
  await q.waitForTimeout(500);
  let dr = await q.evaluate(() => { const d = document.querySelector('.nrn-m [data-drawer]'); return [d.classList.contains('open'), d.getAttribute('aria-hidden'), document.activeElement && document.activeElement.className]; });
  check('mobile menu opens (focus moves to close button)', dr[0] && dr[1] === 'false' && /md-x/.test(dr[2]), dr.join(' | '));
  await q.screenshot({ path: process.argv[3] ? process.argv[3] + '/drawer.png' : '/dev/null' });
  await q.keyboard.press('Escape');
  await q.waitForTimeout(300);
  check('Escape closes menu', await q.evaluate(() => !document.querySelector('.nrn-m [data-drawer]').classList.contains('open')));
  await q.tap('.nrn-m [data-menu-open]'); await q.waitForTimeout(400);
  await q.tap('.nrn-m .md-link[href="#m-compare"]'); await q.waitForTimeout(900);
  dr = await q.evaluate(() => [document.querySelector('.nrn-m [data-drawer]').classList.contains('open'), Math.round(document.querySelector('#m-compare').getBoundingClientRect().top)]);
  check('menu link closes menu and jumps to section', !dr[0] && Math.abs(dr[1]) < 40, dr.join(' | '));
  await ctx.close();
  check('no JS errors', errs.length === 0, errs.join(' | '));
  await b.close();
})();
