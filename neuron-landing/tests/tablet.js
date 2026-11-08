// Tablet widths: the desktop design is scaled; the car must stay on the road and park on the default province.
const { chromium } = require('playwright');
(async () => { const b = await chromium.launch();
 for (const w of [820, 1024]) {
  const p = await b.newPage({ viewport: { width: w, height: 1100 } }); const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/', { waitUntil: 'networkidle' }); await p.waitForTimeout(3500);
  await p.screenshot({ path: (process.argv[2] || '/tmp') + '/t' + w + '-0.png' });
  const info = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, cls: document.querySelector('.nrn-d').className, s: getComputedStyle(document.querySelector('.nrn-d')).getPropertyValue('--nrn-s'), H: document.documentElement.scrollHeight }));
  // a step mid-page, then the end: car must be on the drawn road / parked on Tehran
  const res = [];
  for (const f of [0.3, 1]) {
    await p.evaluate(f => window.scrollTo(0, (document.documentElement.scrollHeight - innerHeight) * f), f); await p.waitForTimeout(2200);
    res.push(await p.evaluate(() => {
      const root = document.querySelector('.nrn-d [data-ref=root]'), car = root.querySelector('.car');
      const cr = car.getBoundingClientRect();
      // distance from the car (screen px) to the nearest point of the road path
      const path = root.querySelector('[data-ref=path]'), L = path.getTotalLength(), rr = root.getBoundingClientRect(), s = rr.width / root.offsetWidth;
      let best = 1e9; for (let i = 0; i <= 4000; i++) { const q = path.getPointAtLength(L * i / 4000); const d = Math.hypot(rr.left + q.x * s - cr.left, rr.top + q.y * s - cr.top); if (d < best) best = d; }
      const t = root.querySelector('[data-province=tehran]').getBoundingClientRect();
      return { onRoad: +best.toFixed(1), parked: car.classList.contains('parked'), toTehran: +Math.hypot(t.left + t.width / 2 - cr.left, t.top + t.height * 0.52 - cr.top).toFixed(1) };
    }));
    await p.screenshot({ path: (process.argv[2] || '/tmp') + '/t' + w + '-' + f + '.png' });
  }
  console.log(w, JSON.stringify(info), JSON.stringify(res), errs.join('|')); await p.close(); }
 await b.close(); })();
