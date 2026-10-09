// SOLO PARA PRUEBAS. Capturas y comprobaciones con Playwright.
// uso: NODE_PATH=/opt/node-tools/node_modules node shots.js <siteDir> <puerto> <etiqueta> [anchos=360,390,768,1024,1440] [rutas=/,/nosotros/,...|auto]
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const [siteDir, port, tag, widthsArg, pathsArg] = process.argv.slice(2);
const widths = (widthsArg || '360,390,768,1024,1440').split(',').map(Number);
const base = `http://127.0.0.1:${port}`;
const man = JSON.parse(fs.readFileSync(path.join(siteDir, 'wp-content/sc-jobs/job1/manifest.json')));
const out = '/tmp/shots/w2b';
fs.mkdirSync(out, { recursive: true });
(async () => {
  let paths = (pathsArg && pathsArg !== 'auto') ? pathsArg.split(',') : null;
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const issues = [];
  for (const w of widths) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    const errs = [];
    page.on('console', m => { if (['error', 'warning'].includes(m.type())) errs.push(m.type() + ': ' + m.text().slice(0, 160)); });
    page.on('pageerror', e => errs.push('pageerror: ' + e.message.slice(0, 160)));
    page.on('requestfailed', r => { if (!/maps\.google|youtube/.test(r.url())) errs.push('requestfailed: ' + r.url().slice(0, 120)); });
    page.on('response', r => { if (r.status() >= 400 && !/favicon/.test(r.url())) errs.push('http ' + r.status() + ': ' + r.url().slice(0, 120)); });
    await page.goto(`${base}/?scpk=${man.preview.key}`, { waitUntil: 'load' });
    if (!paths) {
      paths = await page.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')].map(a => a.href).filter(h => h.startsWith(location.origin)).map(h => new URL(h).pathname))]);
      paths = paths.filter(p => !/wp-admin|wp-login|\.(php|xml)$/.test(p)).slice(0, +(process.env.MAXP || 14));
      if (!paths.includes('/')) paths.unshift('/');
    }
    for (const p of paths) {
      const r = await page.goto(base + p, { waitUntil: 'load' });
      await page.evaluate(async () => { // dispara animaciones al scroll y carga lazy
        const h = document.body.scrollHeight;
        for (let y = 0; y < h; y += 300) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 140)); }
        window.scrollTo(0, 0);
      });
      await page.waitForTimeout(1200);
      const info = await page.evaluate(() => ({
        sw: document.documentElement.scrollWidth, iw: window.innerWidth,
        broken: [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.src && !i.src.startsWith('data:')).map(i => i.src.slice(-60)),
        hasWarn: /<b>(Warning|Notice|Deprecated|Fatal error)<\/b>|Warning:|Notice:|Deprecated:/.test(document.body.innerText + document.body.innerHTML.slice(0, 200000)),
      }));
      const name = (p === '/' ? 'home' : p.replace(/^\/|\/$/g, '').replace(/\//g, '_')) || 'home';
      const file = `${out}/${tag}-${name}-${w}.png`;
      await page.screenshot({ path: file, fullPage: true });
      if (r.status() !== 200) issues.push(`${w} ${p}: HTTP ${r.status()}`);
      if (info.sw > info.iw + 1) issues.push(`${w} ${p}: overflow horizontal ${info.sw}>${info.iw}`);
      if (info.broken.length) issues.push(`${w} ${p}: imagenes rotas ${info.broken.join(',')}`);
      if (info.hasWarn) issues.push(`${w} ${p}: texto de Warning/Notice en la pagina`);
    }
    const uniq = [...new Set(errs)];
    if (uniq.length) issues.push(`${w}: consola/red: ${uniq.slice(0, 8).join(' | ')}`);
    await ctx.close();
  }
  await b.close();
  console.log(issues.length ? 'PROBLEMAS:\n' + issues.join('\n') : 'OK sin problemas');
})().catch(e => { console.error('FALLO', e); process.exit(1); });
