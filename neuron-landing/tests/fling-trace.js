// Fling on a phone, with a browser trace: how much painting / rasterising / style work it causes.
const { chromium, devices } = require('playwright');
const fs = require('fs');
(async () => {
  const [url, out, css] = process.argv.slice(2);
  const b = await chromium.launch();
  const ctx = await b.newContext({ ...devices['Pixel 7'] });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'load' });
  if (css) await p.addStyleTag({ content: css });
  await p.waitForTimeout(5000);
  await b.startTracing(p, { path: out, categories: ['devtools.timeline', 'disabled-by-default-devtools.timeline', 'cc', 'benchmark'] });
  await p.evaluate(async () => {
    const H = document.documentElement.scrollHeight - innerHeight, t0 = performance.now();
    await new Promise(res => { (function step() { const k = Math.min(1, (performance.now() - t0) / 800); window.scrollTo(0, H * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); else res(); })(); });
    await new Promise(res => setTimeout(res, 3000));
  });
  await b.stopTracing();
  const ev = JSON.parse(fs.readFileSync(out)).traceEvents;
  const sum = {};
  for (const e of ev) {
    if (e.ph !== 'X' || !e.dur) continue;
    if (['Paint', 'RasterTask', 'UpdateLayoutTree', 'Layout', 'PrePaint', 'Layerize', 'FireAnimationFrame', 'EventDispatch', 'ImageDecodeTask', 'Decode Image'].includes(e.name)) {
      sum[e.name] = sum[e.name] || [0, 0]; sum[e.name][0]++; sum[e.name][1] += e.dur / 1000;
    }
  }
  const r = {}; for (const k in sum) r[k] = sum[k][0] + 'x/' + Math.round(sum[k][1]) + 'ms';
  console.log(JSON.stringify(r));
  await b.close();
})();
