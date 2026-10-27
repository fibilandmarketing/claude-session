// End-to-end: real Chromium through the CDN emulator. Prints, per visit, the
// document cache state, tracker requests and the session id (to check the DB).
const { chromium } = require('playwright');
const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
const tag = process.argv[2];
const B = 'http://localhost:8080';
const PROD = '/product/%d9%85%d8%ad%d8%b5%d9%88%d9%84-%d8%aa%d8%b3%d8%aa-5/';
const visitors = [
  { name: 'instagram_utm', steps: [[`/?utm_source=instagram&utm_campaign=${tag}`, ''], ['/shop/', 'internal'], [PROD, 'internal']] },
  { name: 'google_organic', steps: [['/shop/', 'https://www.google.com/'], [PROD, 'internal']] },
  { name: 'direct', steps: [[PROD, '']] },
];
(async () => {
  const browser = await chromium.launch();
  const out = [];
  for (const v of visitors) {
    const ctx = await browser.newContext({ userAgent: UA });
    const page = await ctx.newPage();
    const hits = [];
    page.on('requestfinished', async r => { if (r.method() === 'POST') { const resp = await r.response(); hits.push({ url: r.url().replace(B, ''), status: resp ? resp.status() : 0 }); } });
    page.on('requestfailed', r => { if (r.method() === 'POST') hits.push({ url: r.url().replace(B, ''), status: 'failed:' + (r.failure() || {}).errorText }); });
    for (const [path, ref] of v.steps) {
      const before = hits.length;
      const opts = { waitUntil: 'load' };
      if (ref && ref !== 'internal') opts.referer = ref;
      let resp;
      if (ref === 'internal') { await page.evaluate(p => { const a = document.createElement('a'); a.href = p; document.body.appendChild(a); a.click(); }, path); resp = await page.waitForNavigation({ waitUntil: 'load' }); }
      else resp = await page.goto(B + path, opts);
      await page.waitForTimeout(1200);
      const t = await page.evaluate(() => { const n = performance.getEntriesByType('navigation')[0]; return { ttfb: Math.round(n.responseStart), load: Math.round(n.loadEventEnd) }; });
      const scripts = await page.evaluate(() => [...document.scripts].filter(s => /monitor-man|tracker/.test(s.src + s.id)).map(s => s.id || s.src));
      out.push({ visitor: v.name, path: decodeURI(path).slice(0, 40), xcache: resp.headers()['x-cache'], ...t, tracker_tags: scripts, hits: hits.slice(before) });
    }
    const sid = await page.evaluate(() => localStorage.getItem('mm_sid_v2'));
    out.push({ visitor: v.name, session_id: sid });
    await ctx.close();
  }
  console.log(JSON.stringify(out));
  await browser.close();
})();
