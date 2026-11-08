// Turns the landing on from the admin panel (settings tab switch + save).
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto('http://127.0.0.1:8899/wp-login.php');
  await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  await p.goto('http://127.0.0.1:8899/wp-admin/admin.php?page=neuron-landing');
  const warn = await p.locator('.notice-warning').count();
  await p.click('.nrn-panel[data-panel=settings] .nrn-switch');
  await Promise.all([p.waitForNavigation(), p.click('.nrn-save')]);
  console.log('warning before:', warn, '| saved:', await p.locator('.notice-success').count(), '| warning after:', await p.locator('.notice-warning').count());
  // logged-in view with the admin bar
  await p.goto('http://127.0.0.1:8899/', { waitUntil: 'networkidle' }); await p.waitForTimeout(3000);
  await p.evaluate(() => window.scrollTo(0, 4200)); await p.waitForTimeout(1800);
  await p.screenshot({ path: process.argv[2] + '/loggedin.png' });
  const r = await p.evaluate(() => ({ bar: !!document.getElementById('wpadminbar'), car: document.querySelector('.nrn-d .car').style.transform }));
  console.log('admin bar:', r.bar, '| car:', r.car);
  await b.close();
})();
