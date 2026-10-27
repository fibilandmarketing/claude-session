// usage: node browser.js <label> <runs> [path...]
const { chromium } = require('playwright');
const label = process.argv[2], runs = +process.argv[3], paths = process.argv.slice(4);
(async () => {
  const browser = await chromium.launch();
  const out = {};
  for (const p of paths) {
    const agg = [];
    for (let i = 0; i < runs; i++) {
      const ctx = await browser.newContext({ userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' }); // fresh visitor each run
      const page = await ctx.newPage();
      const reqs = [];
      page.on('requestfinished', async r => { const t = r.timing(); reqs.push({ url: r.url(), method: r.method(), type: r.resourceType(), start: t.startTime, dur: t.responseEnd }); });
      const t0 = Date.now();
      await page.goto('http://localhost:8080' + p, { waitUntil: 'load' });
      await page.waitForTimeout(1500);
      const perf = await page.evaluate(() => {
        const n = performance.getEntriesByType('navigation')[0];
        const fcp = (performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime;
        const lt = performance.getEntriesByType('longtask') || [];
        return { ttfb: n.responseStart, fcp, dcl: n.domContentLoadedEventEnd, load: n.loadEventEnd };
      });
      const mm = reqs.filter(r => /monitor-man|mm_record_visit|admin-ajax|mm-collect/.test(r.url) || r.method === 'POST');
      agg.push({ ...perf, requests: reqs.length, mm: mm.map(r => ({ u: r.url.replace('http://localhost:8080', ''), m: r.method, dur: Math.round(r.dur) })) });
      await ctx.close();
    }
    const med = k => { const v = agg.map(a => a[k]).filter(x => x != null).sort((a, b) => a - b); return Math.round(v[Math.floor(v.length / 2)]); };
    out[p] = { ttfb: med('ttfb'), fcp: med('fcp'), dcl: med('dcl'), load: med('load'), requests: med('requests'), mm_requests: agg[0].mm };
  }
  console.log(JSON.stringify({ label, ...out }, null, 1));
  await browser.close();
})();
