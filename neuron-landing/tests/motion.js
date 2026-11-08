// Scrolls through the page and records the car state at each step, for the
// original design (ref) and the WordPress page, then compares them.
// usage: S=<scratch> node motion.js <width> <ref-file> <wp-url>
const { chromium } = require('playwright');
const fs = require('fs');
async function run(b, url, w, ref) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  if (ref) await p.route('https://cdn.jsdelivr.net/**', r => {
    const S = process.env.S, u = r.request().url();
    const f = u.includes('react-dom') ? S + '/npmtmp/react-dom-18.3.1/package/umd/react-dom.production.min.js' : S + '/npmtmp/react-18.3.1/package/umd/react.production.min.js';
    r.fulfill({ status: 200, contentType: 'application/javascript', headers: { 'access-control-allow-origin': '*' }, body: fs.readFileSync(f) });
  });
  await p.goto(url, { waitUntil: 'networkidle' });
  await p.waitForTimeout(3500);
  const H = await p.evaluate(() => document.documentElement.scrollHeight - innerHeight);
  const res = [];
  const probe = () => p.evaluate(() => {
    const root = [...document.querySelectorAll('#top, #m-top')].find(r => r.offsetWidth);
    const car = root.querySelector('.car');
    const m = /translate3d\(([-\d.]+)px, ([-\d.]+)px.*rotate\(([-\d.]+)deg/.exec(car.style.transform) || [];
    return { x: +(+m[1]).toFixed(1), y: +(+m[2]).toFixed(1), a: +(+m[3]).toFixed(1),
      lit: root.querySelectorAll('.stop.lit').length, stops: root.querySelectorAll('.stop').length,
      done: root.querySelectorAll('.leg.done').length, visited: root.querySelectorAll('[data-stop].visited').length,
      parked: car.classList.contains('parked'), arrived: !!root.querySelector('[data-park].arrived'),
      tunnel: root.querySelectorAll('.tunnel').length, cls: car.className };
  });
  for (let i = 0; i <= 12; i++) {
    const y = Math.round(H * i / 12);
    await p.evaluate(y => window.scrollTo(0, y), y);
    await p.waitForTimeout(1600);
    res.push(Object.assign({ scroll: y }, await probe()));
  }
  // scroll back up fast: reverse lights must switch on while moving backwards
  await p.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight * 0.5));
  await p.waitForTimeout(120);
  res.push(Object.assign({ scroll: 'back-up (moving)' }, await probe()));
  await p.close();
  return res;
}
(async () => {
  const [w, ref, wp] = process.argv.slice(2);
  const b = await chromium.launch();
  const a = await run(b, 'http://127.0.0.1:8901/' + ref, +w, true);
  const c = await run(b, wp, +w, false);
  let bad = 0;
  for (let i = 0; i < a.length; i++) {
    const dx = Math.abs(a[i].x - c[i].x), dy = Math.abs(a[i].y - c[i].y);
    const ok = dx < 3 && dy < 3 && a[i].lit === c[i].lit && a[i].parked === c[i].parked && a[i].arrived === c[i].arrived && a[i].visited === c[i].visited;
    if (!ok) bad++;
    console.log((ok ? 'OK  ' : 'DIFF') + ' scroll=' + a[i].scroll,
      '| ref car', a[i].x, a[i].y, a[i].a + '°', 'lit', a[i].lit + '/' + a[i].stops, a[i].parked ? 'PARKED' : '', a[i].cls.replace('car', '').trim(),
      '| wp car', c[i].x, c[i].y, c[i].a + '°', 'lit', c[i].lit + '/' + c[i].stops, c[i].parked ? 'PARKED' : '', c[i].cls.replace('car', '').trim());
  }
  console.log(bad ? bad + ' mismatches' : 'all steps match');
  await b.close();
})();
