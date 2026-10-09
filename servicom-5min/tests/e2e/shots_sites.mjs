// Capturas y comprobaciones de las webs generadas. Uso: node shots_sites.mjs <salida> <urlConClave> [<urlConClave>...]
// Requiere playwright-core (npm i playwright-core) y Chromium. Verifica: sin scroll horizontal, imágenes rotas, errores de consola, textos cortados.
import { chromium } from 'playwright-core';
import fs from 'fs';
const [,, out, ...urls] = process.argv;
fs.mkdirSync(out, { recursive: true });
const WIDTHS = [360, 390, 768, 1024, 1440];
const b = await chromium.launch({ executablePath: process.env.CHROME || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--host-resolver-rules=MAP *.servicom.test 127.0.0.1, MAP *.test 127.0.0.1'] });
let problems = 0;
for (const start of urls) {
  const u0 = new URL(start); const slug = u0.hostname.split('.')[0];
  const ctx = await b.newContext({ viewport: { width: 390, height: 800 } });
  const page = await ctx.newPage();
  await page.goto(start, { waitUntil: 'networkidle' }).catch(() => {});
  const links = await page.evaluate((origin) => [...new Set([...document.querySelectorAll('a[href]')].map(a => a.href).filter(h => h.startsWith(origin) && !/wp-admin|wp-login|#|\?|\.(jpg|png|webp|pdf)$/i.test(h)))].slice(0, 14), u0.origin);
  const pages = [u0.origin + '/', ...links.filter(l => l !== u0.origin + '/')].slice(0, 7);
  for (const url of pages) {
    for (const w of WIDTHS) {
      await page.setViewportSize({ width: w, height: 900 });
      const errs = [];
      const onc = m => { if (m.type() === 'error') errs.push(m.text()); }; const onp = e => errs.push('PAGEERR ' + e.message);
      page.on('console', onc); page.on('pageerror', onp);
      const resp = await page.goto(url, { waitUntil: 'networkidle' }).catch(e => null);
      const status = resp ? resp.status() : 0;
      await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 300) { scrollTo(0, y); await new Promise(r => setTimeout(r, 130)); } scrollTo(0, 0); await new Promise(r => setTimeout(r, 400)); });
      const res = await page.evaluate(() => {
        const sw = document.documentElement.scrollWidth, iw = innerWidth;
        const broken = [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.src && !i.src.startsWith('data:')).map(i => i.src);
        const wide = [...document.querySelectorAll('body *')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.right > innerWidth + 1 && getComputedStyle(e).position !== 'fixed' && !e.closest('[hidden],.screen-reader-text'); }).slice(0, 5).map(e => e.tagName + '.' + String(e.className).slice(0, 40));
        const unrevealed = [...document.querySelectorAll('.sc-reveal')].filter(e => !e.classList.contains('is-in')).length;
        const emptySec = [...document.querySelectorAll('.sc-sec')].filter(e => !e.innerText.trim() && !e.querySelector('img,iframe,svg,video')).length;
        const txt = document.body.innerText;
        return { sw, iw, broken, wide, emptySec, unrevealed, php: /(Warning|Notice|Fatal error|Deprecated):/.test(txt), h: document.body.scrollHeight };
      });
      const name = `${slug}-${new URL(url).pathname.replace(/\W+/g, '_') || 'home'}-${w}.png`;
      await page.screenshot({ path: `${out}/${name}`, fullPage: true });
      page.off('console', onc); page.off('pageerror', onp);
      const bad = status !== 200 || res.sw > res.iw || res.broken.length || res.emptySec || res.unrevealed || res.php || errs.filter(e => !/favicon|Failed to load resource.*(403|404)/.test(e)).length;
      if (bad) { problems++; console.log('PROBLEMA', url, w, JSON.stringify({ status, ...res, errs: errs.slice(0, 3) })); }
    }
  }
  console.log(`${slug}: ${pages.length} páginas × ${WIDTHS.length} anchos revisadas`);
  await ctx.close();
}
await b.close();
console.log(problems ? `${problems} problema(s)` : 'Sin problemas');
process.exit(problems ? 1 : 0);
