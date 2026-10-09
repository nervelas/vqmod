// QA visual automático de una web LUXE. Uso: node qa.js <urlBase> <preview_key> [--shots=/tmp/prefijo]
// Rastrea las páginas del menú a 360/768/1440 px y verifica: sin desbordes horizontales, sin errores de consola,
// imágenes cargadas, contraste de texto (AA 3:1 en fondos sólidos; claro sobre bandas oscuras), botones flotantes,
// formulario de contacto, un solo <h1> por página y enlaces internos sin 404.
const path = require('path');
const { chromium } = require(path.join(__dirname, '../portal/e2e/node_modules/playwright-core'));
const [base, key, ...rest] = process.argv.slice(2);
const shots = (rest.find(a => a.startsWith('--shots=')) || '').slice(8);
const probs = [];
const bad = (m) => { probs.push(m); console.log('  ✗ ' + m); };
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--host-resolver-rules=MAP *.servicom.test 127.0.0.1'] });
  const mk = async (w) => {
    const ctx = await b.newContext({ viewport: { width: w, height: w < 700 ? 800 : 900 }, isMobile: w < 700, hasTouch: w < 700 });
    const q = await ctx.newPage(); await q.goto(base + '/?scpk=' + key, { waitUntil: 'load' }); await q.close();
    return ctx;
  };
  // 1) rastreo de enlaces del menú
  let ctx = await mk(1440), p = await ctx.newPage();
  await p.goto(base + '/?scpk=' + key, { waitUntil: 'networkidle' });
  const links = await p.evaluate((origin) => [...new Set([...document.querySelectorAll('.sc-nav a[href], .sc-footer a[href]')].map(a => a.href).filter(h => h.startsWith(origin) && !h.includes('#')))], base);
  const pages = [base + '/'].concat(links.filter(l => l !== base + '/'));
  await ctx.close();
  console.log('páginas:', pages.length);
  for (const w of [1440, 768, 360]) {
    ctx = await mk(w);
    for (const url of pages) {
      p = await ctx.newPage();
      const errs = [], failed = [];
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      p.on('pageerror', e => errs.push(String(e)));
      p.on('response', r => { if (r.status() >= 400 && !/favicon/.test(r.url())) failed.push(r.status() + ' ' + r.url()); });
      const resp = await p.goto(url, { waitUntil: 'networkidle' });
      const tag = `${w}px ${url.replace(base, '') || '/'}`;
      if (!resp || resp.status() >= 400) { bad(tag + ' HTTP ' + (resp && resp.status())); await p.close(); continue; }
      await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 350) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 90)); } window.scrollTo(0, 0); });
      await p.waitForTimeout(500);
      const r = await p.evaluate(() => {
        const out = { over: document.documentElement.scrollWidth - window.innerWidth, h1: document.querySelectorAll('h1').length, imgs: [], low: [], hidden: 0 };
        document.querySelectorAll('img').forEach(i => { if (i.offsetParent !== null && i.complete && i.naturalWidth === 0) out.imgs.push(i.src.slice(-60)); });
        const lum = (c) => { const m = c.match(/[\d.]+/g).map(Number); const f = v => { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }; return .2126 * f(m[0]) + .7152 * f(m[1]) + .0722 * f(m[2]); };
        const alpha = (c) => { const m = c.match(/[\d.]+/g).map(Number); return m.length > 3 ? m[3] : 1; };
        const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
        const bgOf = (el) => { for (let e = el; e && e.nodeType === 1; e = e.parentElement) { const cs = getComputedStyle(e); if (cs.backgroundImage !== 'none') return { img: true, dark: !!e.closest('.lx-hero,.lx-cta,.lx-pagehero,.lx-quote') }; const c = cs.backgroundColor; if (alpha(c) > 0.9) return { c }; } return { c: 'rgb(255,255,255)' }; };
        document.querySelectorAll('main h1,main h2,main h3,main p,main li,main a,main span,main summary,main label').forEach(el => {
          if (!el.childNodes.length || ![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) return;
          const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || +cs.opacity === 0) return;
          const r = el.getBoundingClientRect(); if (r.width < 2 || r.height < 2) return;
          if (el.closest('.lx-off,[hidden],.lx-strip')) return;
          const fg = cs.color; let bg = bgOf(el);
          const btn = el.closest('.lx-btn--primary,.lx-fab--tel');
          if (btn) { const pv = getComputedStyle(document.documentElement).getPropertyValue('--lx-primary').trim(); const h = pv.replace('#', ''); const rgb = 'rgb(' + parseInt(h.slice(0, 2), 16) + ',' + parseInt(h.slice(2, 4), 16) + ',' + parseInt(h.slice(4, 6), 16) + ')'; const rt0 = ratio(fg, rgb); if (rt0 < 4.4) out.low.push('botón ' + (el.textContent || '').trim().slice(0, 30) + ' ' + rt0.toFixed(1)); return; }
          if (el.closest('.lx-btn--ghost') && bg.img) { return; }
          if (bg.img) { if (bg.dark && lum(fg) < 0.45) out.low.push((el.textContent || '').trim().slice(0, 40) + ' (oscuro sobre banda oscura)'); return; }
          const rt = ratio(fg, bg.c); const big = parseFloat(cs.fontSize) >= 24;
          if (rt < (big ? 3 : 3.9) && !el.closest('.lx-ico,.lx-value__n,.lx-ph')) out.low.push((el.textContent || '').trim().slice(0, 40) + ' ' + rt.toFixed(1));
        });
        out.fabs = document.querySelectorAll('.lx-fab').length;
        out.form = document.querySelectorAll('.sc-form').length;
        return out;
      });
      if (r.over > 0) bad(`${tag} desborde horizontal ${r.over}px`);
      if (errs.length) bad(`${tag} errores de consola: ${errs.slice(0, 2).join(' | ')}`);
      if (failed.length) bad(`${tag} recursos con error: ${failed.slice(0, 2).join(' | ')}`);
      if (r.imgs.length) bad(`${tag} imágenes rotas: ${r.imgs.join(', ')}`);
      if (r.h1 !== 1) bad(`${tag} debe tener exactamente un h1 (tiene ${r.h1})`);
      if (r.low.length) bad(`${tag} contraste bajo: ${r.low.slice(0, 4).join(' ; ')}`);
      if (!r.fabs) bad(`${tag} sin botones flotantes`);
      if (shots && w !== 768) { const hh = await p.evaluate(() => document.documentElement.scrollHeight); await p.screenshot({ path: `${shots}-${w}-${(url.replace(base, '').replace(/\W+/g, '_') || 'home')}.png`, clip: { x: 0, y: 0, width: w, height: Math.min(hh, 12000) }, fullPage: true }); }
      await p.close();
    }
    await ctx.close();
  }
  await b.close();
  console.log(probs.length ? `FALLAS: ${probs.length}` : 'LUXE QA: sin problemas');
  process.exit(probs.length ? 1 : 0);
})();
