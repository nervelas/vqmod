// Uso: node shot.js <url> <prefijo> [anchos...]  → /tmp/<prefijo>-<ancho>.png (página completa)
const path = require('path');
const { chromium } = require(path.join(__dirname, '../portal/e2e/node_modules/playwright-core'));
(async () => {
  const [url, pre, ...ws] = process.argv.slice(2);
  const widths = ws.length ? ws.map(Number) : [1440, 390];
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--host-resolver-rules=MAP *.servicom.test 127.0.0.1'] });
  for (const w of widths) {
    const ctx = await b.newContext({ viewport: { width: w, height: w > 700 ? 900 : 844 }, isMobile: w < 700, hasTouch: w < 700, deviceScaleFactor: 1 });
    const p = await ctx.newPage();
    const errs = [];
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    p.on('pageerror', e => errs.push(String(e)));
    await p.goto(url, { waitUntil: 'networkidle' });
    await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 300) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 140)); } window.scrollTo(0, 0); });
    await p.waitForTimeout(900);
    const hh = await p.evaluate(() => document.documentElement.scrollHeight);
    await p.screenshot({ path: `/tmp/${pre}-${w}.png`, fullPage: true, clip: { x: 0, y: 0, width: w, height: hh } });
    const ov = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    const dims = await p.evaluate(() => [document.documentElement.scrollWidth, document.body.scrollWidth, window.innerWidth]);
    console.log(w, 'overflow-x:', ov, 'dims', dims, 'errores:', JSON.stringify(errs.slice(0, 5)));
    await p.close();
  }
  await b.close();
})();
