// End-to-end: edit in the admin panel, save, verify on the front end, then reset.
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8899';
function check(name, ok, extra) { console.log((ok ? 'PASS ' : 'FAIL ') + name + (extra ? ' — ' + extra : '')); if (!ok) process.exitCode = 1; }
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  await p.goto(BASE + '/wp-login.php');
  await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  await p.goto(BASE + '/wp-admin/admin.php?page=neuron-landing&tab=hero');

  // 1. hero title (shared desktop+mobile field)
  const heroKey = await p.evaluate(() => [...document.querySelectorAll('.nrn-panel[data-panel=hero] .nrn-field')].find(f => f.querySelector('.nrn-v') && f.querySelector('.nrn-v').value === 'خودرویت را').dataset.key);
  await p.fill('[data-key="' + heroKey + '"] .nrn-v', 'ماشینت را');
  // 2. image: set a custom URL into the hidden input like the media modal does
  await p.click('.nrn-tab[data-tab="brand"]');
  await p.evaluate(() => { const f = document.querySelector('[data-key="brand.i1"]'); f.querySelector('.nrn-v-url').value = 'http://127.0.0.1:8899/wp-content/plugins/neuron-landing/assets/img/product-s350.webp'; f.querySelector('.nrn-v-alt').value = 'لوگوی تست'; });
  // 3. switch off the "personal" section
  await p.click('.nrn-tab[data-tab="personal"]');
  await p.click('.nrn-panel[data-panel=personal] .nrn-switch');
  // 4. FAQ: add one question
  await p.click('.nrn-tab[data-tab="faq"]');
  await p.click('.nrn-add[data-list=faq]');
  await p.fill('.nrn-list[data-list=faq] .nrn-row:last-child [data-f=q]', 'سؤال تستی جدید؟');
  await p.fill('.nrn-list[data-list=faq] .nrn-row:last-child [data-f=a]', 'پاسخ تستی.');
  // 5. provinces: Isfahan becomes the max (20), default province -> Isfahan
  await p.click('.nrn-tab[data-tab="city"]');
  await p.fill('tr[data-slug=isfahan] .nrn-p-n', '20');
  await p.selectOption('[data-map-k=def]', 'isfahan');
  // 6. a footer phone number
  await p.click('.nrn-tab[data-tab="footer"]');
  const phoneKey = await p.evaluate(() => [...document.querySelectorAll('.nrn-panel[data-panel=footer] .nrn-field')].find(f => f.querySelector('.nrn-v') && f.querySelector('.nrn-v').value === '۰۵۱-۵۲۲۲۰۰۱۱').dataset.key);
  await p.fill('[data-key="' + phoneKey + '"] .nrn-v', '۰۲۱-۱۲۳۴۵۶۷۸');
  await Promise.all([p.waitForNavigation(), p.click('.nrn-save')]);
  check('saved notice', await p.locator('.notice-success').count() > 0);
  check('stays on last tab', (await p.url()).includes('tab=footer'));
  check('changed marker kept after reload', await p.locator('[data-key="' + phoneKey + '"].is-changed').count() === 1);

  // ---- front end, desktop
  const f = await ctx.newPage();
  f.on('pageerror', e => errs.push('front: ' + e.message));
  await f.goto(BASE + '/', { waitUntil: 'networkidle' });
  const r = await f.evaluate(() => {
    const d = document.querySelector('.nrn-d'), m = document.querySelector('.nrn-m');
    return {
      heroD: d.querySelector('.hero-big').textContent.trim(), heroM: m.querySelector('.hero-big').textContent.trim(),
      logo: d.querySelector('header img').getAttribute('src'), logoAlt: d.querySelector('header img').alt, logoM: m.querySelector('header img').getAttribute('src'),
      personal: !!d.querySelector('#personal'), personalM: !!m.querySelector('#m-personal'),
      faqD: d.querySelectorAll('details').length, faqLast: [...d.querySelectorAll('details summary')].pop().textContent.trim(),
      isf: d.querySelector('[data-province=isfahan]').getAttribute('fill'), teh: d.querySelector('[data-province=tehran]').getAttribute('fill'),
      mname: d.querySelector('[data-mname]').textContent, mcount: d.querySelector('[data-mcount]').textContent,
      phone: d.querySelector('footer').textContent.includes('۰۲۱-۱۲۳۴۵۶۷۸'),
      stops: d.querySelectorAll('.stop').length,
    };
  });
  check('hero text desktop', r.heroD.startsWith('ماشینت را'), r.heroD);
  check('hero text mobile (shared field)', r.heroM.startsWith('ماشینت را'), r.heroM);
  check('logo image replaced (desktop+mobile)', r.logo.includes('product-s350') && r.logoM.includes('product-s350') && r.logoAlt === 'لوگوی تست');
  check('section switched off (desktop+mobile)', !r.personal && !r.personalM);
  check('faq item added', r.faqD === 7 && r.faqLast === 'سؤال تستی جدید؟', r.faqD + ' ' + r.faqLast);
  check('province colour rescaled (isfahan=max -> brightest)', r.isf === '#C0662A', r.isf + ' tehran=' + r.teh);
  check('map info shows default province', r.mname === 'اصفهان' && r.mcount === '۲۰ نمایندگی فعال', r.mname + ' / ' + r.mcount);
  check('footer phone changed', r.phone);
  check('route rebuilt with remaining stops', r.stops === 6, 'stops=' + r.stops);
  // car parks on the new default province
  await f.waitForTimeout(3000); // let the 2.6 s intro drive finish first
  await f.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
  await f.waitForTimeout(2500);
  const park = await f.evaluate(() => {
    const root = document.querySelector('.nrn-d [data-ref=root]'), rr = root.getBoundingClientRect();
    const car = root.querySelector('.car'); const m = /translate3d\(([-\d.]+)px, ([-\d.]+)px/.exec(car.style.transform);
    const pr = root.querySelector('[data-province=isfahan]').getBoundingClientRect();
    return { parked: car.classList.contains('parked'), x: +m[1], y: +m[2], px: pr.left - rr.left + pr.width / 2, py: pr.top - rr.top + pr.height * 0.52 };
  });
  check('car parked on Isfahan', park.parked && Math.abs(park.x - park.px) < 3 && Math.abs(park.y - park.py) < 3, JSON.stringify(park));

  // ---- reset everything
  await p.goto(BASE + '/wp-admin/admin.php?page=neuron-landing&tab=settings');
  p.on('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.click('.nrn-reset-all')]);
  await f.goto(BASE + '/', { waitUntil: 'networkidle' });
  const back = await f.evaluate(() => ({ hero: document.querySelector('.nrn-d .hero-big').textContent.trim(), faq: document.querySelectorAll('.nrn-d details').length, personal: !!document.querySelector('#personal') }));
  check('reset all restores the design', back.hero.startsWith('خودرویت را') && back.faq === 6 && back.personal, JSON.stringify(back));
  check('no JS errors', errs.length === 0, errs.join(' | '));
  await b.close();
})();
