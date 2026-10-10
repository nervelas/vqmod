// Asistente: plan -> archivo (errores amables) -> construcción automática con un PPTX. Uso: node flowReal.js <ancho>
const { launch, newPage, overflow } = require('./lib');
const BASE = process.env.S5_BASE || 'http://crear.servicom.test:8201';
const FX = __dirname + '/fx/';
const W = +process.argv[2] || 390;
const log = (...a) => console.log(`[${W}]`, ...a);
let bad = 0;
(async () => {
  const b = await launch(); const p = await newPage(b, W);
  const step = () => p.evaluate(() => (document.querySelector('.step:not([hidden])') || {}).dataset?.step);
  await p.goto(BASE + '/crear', { waitUntil: 'networkidle' });
  log('primer paso', await step());
  const o1 = await overflow(p); if (o1.sw > o1.iw) { bad++; log('OVERFLOW plan'); }
  await p.click('label.plan-o >> nth=0'); await p.click('#nav-next'); await p.waitForTimeout(1200);
  log('paso', await step());
  log('campos visibles', JSON.stringify(await p.evaluate(() => [...document.querySelectorAll('input:not([type=hidden]):not(.sr),textarea,select')].filter(e => e.offsetParent !== null).map(e => e.id || e.name))));
  for (const f of ['old.ppt', 'notes.txt', 'big.pdf']) {
    await p.setInputFiles('#pres-file', (f === 'big.pdf' ? '/home/user/vqmod/servicom-5min/tests/fixtures/presentaciones/out/grande.pdf' : FX + f)); await p.waitForTimeout(500);
    log('err', f, '=>', await p.textContent('#pres-e'));
  }
  const o2 = await overflow(p); if (o2.sw > o2.iw) { bad++; log('OVERFLOW archivo'); }
  await p.setInputFiles('#pres-file', '/home/user/vqmod/servicom-5min/tests/fixtures/presentaciones/out/taller.pptx');
  await p.waitForFunction(() => (document.querySelector('.step:not([hidden])') || {}).dataset?.step === 'resumen', null, { timeout: 150000 });
  await p.waitForSelector('#b-done:not([hidden]), #b-fail:not([hidden])', { timeout: 240000 });
  const done = await p.evaluate(() => !document.getElementById('b-done').hidden);
  log('construcción automática', done ? 'LISTA' : 'FALLÓ');
  if (!done) bad++;
  const o3 = await overflow(p); if (o3.sw > o3.iw) { bad++; log('OVERFLOW resumen'); }
  await p.click('#b-pay'); await p.fill('#pay-nit', 'CF'); await p.setInputFiles('#pay-file', FX + 'small.jpg'); await p.waitForTimeout(700);
  await p.click('[data-pay-submit]'); await p.waitForSelector('[data-pay-done]:not([hidden])', { timeout: 15000 });
  log('pago enviado');
  log('errores consola:', JSON.stringify(p.errors), 'overflows:', bad);
  await b.close(); process.exit(bad || p.errors.length ? 1 : 0);
})().catch(e => { console.error('FALLO', e.message); process.exit(1); });
