// Load-time profile: long main-thread tasks in the first 4 s, FCP, LCP, bytes per layout.
// usage: node perf.js <url> <width> [cpu-slowdown=4]
const { chromium } = require('playwright');
(async () => {
  const [url, w, slow] = process.argv.slice(2);
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: +w, height: +w < 768 ? 844 : 900 } });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: +(slow || 4) });
  let bytes = 0; const imgs = new Set();
  p.on('response', async r => { try { const h = await r.allHeaders(); bytes += +(h['content-length'] || 0); if (r.request().resourceType() === 'image') imgs.add(r.url().split('/').pop()); } catch (e) {} });
  await p.addInitScript(() => {
    window.__lt = []; window.__lcp = 0;
    new PerformanceObserver(l => l.getEntries().forEach(e => window.__lt.push([Math.round(e.startTime), Math.round(e.duration)]))).observe({ type: 'longtask', buffered: true });
    new PerformanceObserver(l => l.getEntries().forEach(e => { window.__lcp = Math.round(e.startTime); })).observe({ type: 'largest-contentful-paint', buffered: true });
  });
  await p.goto(url, { waitUntil: 'load' });
  await p.waitForTimeout(4000);
  const r = await p.evaluate(() => {
    const fcp = performance.getEntriesByName('first-contentful-paint')[0];
    const lt = window.__lt;
    return { fcp: fcp ? Math.round(fcp.startTime) : null, lcp: window.__lcp, longTasks: lt.length, blocking: lt.reduce((s, x) => s + Math.max(0, x[1] - 50), 0), worst: lt.sort((a, b) => b[1] - a[1]).slice(0, 4) };
  });
  console.log(JSON.stringify(Object.assign(r, { images: imgs.size })));
  await b.close();
})();
