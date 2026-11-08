// usage: node measure.js <url> <width> [ref]  -> prints top/height of each section of the visible layout
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const [url, w, ref] = process.argv.slice(2);
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: +w, height: 900 } });
  if (ref) await p.route('https://cdn.jsdelivr.net/**', r => {
    const S = process.env.S, u = r.request().url();
    const f = u.includes('react-dom') ? S + '/npmtmp/react-dom-18.3.1/package/umd/react-dom.production.min.js' : S + '/npmtmp/react-18.3.1/package/umd/react.production.min.js';
    r.fulfill({ status: 200, contentType: 'application/javascript', headers: { 'access-control-allow-origin': '*' }, body: fs.readFileSync(f) });
  });
  await p.goto(url, { waitUntil: 'networkidle' });
  await p.waitForTimeout(3500);
  const res = await p.evaluate(() => {
    const roots = [...document.querySelectorAll('#top, #m-top')].filter(r => r.offsetWidth);
    const root = roots[0];
    const rr = root.getBoundingClientRect();
    const out = [];
    root.querySelectorAll(':scope > section, :scope > footer, :scope > aside').forEach(el => {
      const r = el.getBoundingClientRect();
      out.push([el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.dataset.stop ? '[' + el.dataset.stop + ']' : ''), Math.round(r.top - rr.top), Math.round(r.height)]);
    });
    return out;
  });
  res.forEach(r => console.log(r.join('\t')));
  await b.close();
})();
