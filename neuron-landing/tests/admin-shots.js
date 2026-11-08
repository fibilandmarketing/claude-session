// Logs in, opens the plugin admin page and screenshots some tabs.
const { chromium } = require('playwright');
const OUT = process.argv[2];
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  await p.goto('http://127.0.0.1:8899/wp-login.php');
  await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=neuron-landing');
  await p.waitForTimeout(800);
  await p.screenshot({ path: OUT + '/adm-settings.png' });
  for (const t of ['why', 'city', 'faq']) {
    await p.click('.nrn-tab[data-tab="' + t + '"]');
    await p.waitForTimeout(300);
    await p.screenshot({ path: OUT + '/adm-' + t + '.png' });
  }
  console.log('fields:', await p.locator('.nrn-field').count(), 'rows:', await p.locator('.nrn-row').count());
  errs.forEach(e => console.log(e));
  await b.close();
})();
