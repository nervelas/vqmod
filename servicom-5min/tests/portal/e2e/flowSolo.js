// Solo archivo: elegir plan -> subir PDF -> la web se construye sola (sin revisión ni formularios).
// Uso: node flowSolo.js <ancho> [info|tienda] [archivo]
const path = require('path');
const { launch, newPage, overflow } = require('./lib');
const BASE = process.env.S5_BASE || 'http://crear.servicom.test:8201';
const W = +process.argv[2] || 390;
const PLAN = process.argv[3] === 'tienda' ? 'tienda' : 'info';
const FILE = process.argv[4] || path.join(__dirname, '../../fixtures/solo_pdf/empresa.pdf');
const log = (...a) => console.log(`[${W} ${PLAN}]`, ...a);
let bad = 0; const ok = (c, m) => { if (!c) { bad++; log('FALLO:', m); } else log('ok', m); };
(async () => {
  const b = await launch(); const p = await newPage(b, W);
  const step = () => p.evaluate(() => (document.querySelector('.step:not([hidden])') || {}).dataset?.step);
  const visible = () => p.evaluate(() => [...document.querySelectorAll('input:not([type=hidden]):not(.sr):not([type=radio]),textarea,select')].filter(e => e.offsetParent !== null).map(e => e.id || e.name));
  await p.goto(BASE + '/crear', { waitUntil: 'networkidle' });
  ok(await step() === 'plan', 'empieza eligiendo el plan');
  const tw = () => p.evaluate(() => { const e = document.getElementById('tarj-wrap'); return !!e && !e.hidden && e.offsetParent !== null; });
  ok(!(await tw()), 'el pago con tarjeta no se ofrece antes de elegir');
  await p.click('#nav-next'); await p.waitForTimeout(300);
  ok(await step() === 'plan', 'sin elegir plan no avanza');
  await p.click('label.plan-o >> nth=' + (PLAN === 'tienda' ? 1 : 0)); await p.waitForTimeout(200);
  ok(PLAN === 'tienda' ? await tw() : !(await tw()), PLAN === 'tienda' ? 'tienda: se ofrece el pago con tarjeta (extra)' : 'informativa: NO se ofrece el pago con tarjeta');
  if (PLAN === 'tienda') { await p.check('input[data-k=tarjeta_extra][type=checkbox]'); }
  await p.click('#nav-next'); await p.waitForTimeout(1200);
  ok(await step() === 'pres', 'después del plan pide el archivo');
  ok((await visible()).length === 0, 'la pantalla del archivo no tiene ningún campo: ' + JSON.stringify(await visible()));
  const o = await overflow(p); ok(o.sw <= o.iw, 'sin desborde horizontal');
  await p.setInputFiles('#pres-file', FILE);
  const t0 = Date.now(); let last = '', seen = new Set();
  while (Date.now() - t0 < 300000) {
    const st = await p.evaluate(() => ({ step: (document.querySelector('.step:not([hidden])') || {}).dataset?.step, done: !document.getElementById('b-done').hidden, fail: !document.getElementById('b-fail').hidden, pct: document.getElementById('b-pct').textContent, cards: !document.getElementById('res-cards').hidden, idle: !document.getElementById('build-idle').hidden && document.getElementById('build').hidden }));
    seen.add(st.step); const k = JSON.stringify(st); if (k !== last) { log(Math.round((Date.now() - t0) / 1000) + 's', k); last = k; }
    if (st.cards) { bad++; log('FALLO: se mostró la revisión'); }
    if (st.done || st.fail) break; await p.waitForTimeout(1000);
  }
  ok(!seen.has('revision') && !seen.has('negocio'), 'nunca pide revisar ni llenar datos (pasos vistos: ' + [...seen].join(',') + ')');
  const done = await p.evaluate(() => !document.getElementById('b-done').hidden);
  ok(done, 'la vista previa quedó lista');
  const edit = await p.evaluate(() => { const e = document.getElementById('b-edit'); return !e || e.hidden || e.offsetParent === null; });
  ok(edit, 'no hay botón «Editar datos»');
  await p.screenshot({ path: `/tmp/solo-${W}-${PLAN}.png`, fullPage: true });
  ok(!p.errors.length, 'sin errores de consola ' + JSON.stringify(p.errors));
  console.log(bad ? 'SOLO ARCHIVO: FALLO' : 'SOLO ARCHIVO: OK');
  await b.close(); process.exit(bad ? 1 : 0);
})().catch(e => { console.error('FALLO', e.message); process.exit(1); });
