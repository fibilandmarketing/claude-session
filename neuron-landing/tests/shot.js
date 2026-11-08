// usage: node shot.js <url> <width> <out.png> [height=900]
const { chromium } = require('playwright');
(async () => {
  const [url, w, out, h] = process.argv.slice(2);
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: +w, height: +(h || 900) }, deviceScaleFactor: 1 });
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  p.on('requestfailed', r => errs.push('failed: ' + r.url()));
  p.on('response', r => { if (r.status() >= 400) errs.push(r.status() + ' ' + r.url()); });
  await p.goto(url, { waitUntil: 'networkidle' });
  await p.waitForTimeout(4000);
  console.log('height', await p.evaluate(() => document.documentElement.scrollHeight));
  await p.screenshot({ path: out, fullPage: true });
  errs.forEach(e => console.log(e));
  await b.close();
})();
