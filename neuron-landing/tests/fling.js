// Phone "fling to the bottom": frame smoothness while the page and the car catch up.
// usage: node fling.js <url> [cpu-slowdown=4]
const { chromium, devices } = require('playwright');
(async () => {
  const [url, slow] = process.argv.slice(2);
  const b = await chromium.launch();
  const ctx = await b.newContext({ ...devices['Pixel 7'] });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: +(slow || 4) });
  await p.goto(url, { waitUntil: 'load' });
  await p.waitForTimeout(5000);
  const r = await p.evaluate(async () => {
    const gaps = []; let last = performance.now(), run = true;
    const lt = []; const po = new PerformanceObserver(l => l.getEntries().forEach(e => lt.push(Math.round(e.duration)))); po.observe({ type: 'longtask' });
    (function f(t) { gaps.push(t - last); last = t; if (run) requestAnimationFrame(f); })(performance.now());
    // a fast fling: the page reaches the bottom in ~0.8 s
    const H = document.documentElement.scrollHeight - innerHeight, t0 = performance.now();
    await new Promise(res => { (function step() { const k = Math.min(1, (performance.now() - t0) / 800); window.scrollTo(0, H * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); else res(); })(); });
    await new Promise(res => setTimeout(res, 3000));
    run = false; po.disconnect();
    const g = gaps.slice(1);
    const car = document.querySelector('.nrn-m .car');
    return { frames: g.length, janky: g.filter(x => x > 50).length, worst: Math.round(Math.max(...g)), longTasks: lt.length, longTaskMs: lt.reduce((a, x) => a + x, 0), parked: car && car.classList.contains('parked') };
  });
  console.log(JSON.stringify(r));
  await b.close();
})();
