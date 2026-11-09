// Pixel-compares the WordPress page with the original design render.
// usage: S=<scratch> node compare.js <width> <ref-file> <wp-url> <outprefix>
const { chromium } = require('playwright');
const fs = require('fs');
async function shot(b, url, w, out, ref) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  if (ref) await p.route('https://cdn.jsdelivr.net/**', r => {
    const S = process.env.S, u = r.request().url();
    const f = u.includes('react-dom') ? S + '/npmtmp/react-dom-18.3.1/package/umd/react-dom.production.min.js' : S + '/npmtmp/react-18.3.1/package/umd/react.production.min.js';
    r.fulfill({ status: 200, contentType: 'application/javascript', headers: { 'access-control-allow-origin': '*' }, body: fs.readFileSync(f) });
  });
  await p.goto(url, { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);
  // scroll through once like a visitor so lazy images load, then back to the top
  const H = await p.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < H; y += 700) { await p.evaluate(y => window.scrollTo(0, y), y); await p.waitForTimeout(60); }
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.waitForTimeout(3500);
  await p.screenshot({ path: out, fullPage: true, animations: 'disabled' });
  await p.close();
}
(async () => {
  const [w, ref, wp, pre] = process.argv.slice(2);
  const b = await chromium.launch();
  await shot(b, 'http://127.0.0.1:8901/' + ref, +w, pre + '-ref.png', true);
  await shot(b, wp, +w, pre + '-wp.png', false);
  await b.close();
})();
