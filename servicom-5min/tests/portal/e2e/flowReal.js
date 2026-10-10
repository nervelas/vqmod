// Flujo informativo con presentación. Uso: node flowA.js <ancho>
const { launch, newPage, overflow } = require('./lib');
const BASE = process.env.S5_BASE || 'http://crear.servicom.test:8201';
const FX = __dirname + '/fx/';
const W = +process.argv[2] || 390;
const log = (...a) => console.log(`[${W}]`, ...a);
let bad = 0;
(async () => {
  const b = await launch();
  const p = await newPage(b, W);
  
  const step = () => p.evaluate(() => (document.querySelector('.step:not([hidden])') || {}).dataset?.step);
  const shot = async (n, full = true) => {
    await p.waitForTimeout(350);
    const o = await overflow(p); if (o.sw > o.iw) { bad++; log('OVERFLOW', n, o); }
    await p.screenshot({ path: `/tmp/shots/real/A-${W}-${n}.png`, fullPage: full });
  };
  const next = async () => { await p.click('#nav-next'); await p.waitForTimeout(450); };
  await p.goto(BASE + '/crear?plan=info', { waitUntil: 'networkidle' });
  // plan preseleccionado por query
  log('plan checked', await p.evaluate(() => document.querySelector('input[name=plan]:checked')?.value));
  await shot('01-plan');
  await next();
  log('step', await step());
  // presentación: subir deshabilitado hasta marcar casilla
  log('file disabled', await p.evaluate(() => document.getElementById('pres-file').disabled));
  await shot('02-pres');
  await p.check('#pres-ok');
  log('file enabled', await p.evaluate(() => !document.getElementById('pres-file').disabled));
  for (const f of ['old.ppt', 'notes.txt', 'big.pdf']) {
    await p.setInputFiles('#pres-file', (f === 'big.pdf' ? '/home/user/vqmod/servicom-5min/tests/fixtures/presentaciones/out/grande.pdf' : FX + f)); await p.waitForTimeout(500);
    log('err', f, '=>', await p.textContent('#pres-e'));
  }
  await shot('03-pres-error');
  await p.setInputFiles('#pres-file', '/home/user/vqmod/servicom-5min/tests/fixtures/presentaciones/out/taller.pptx'); await p.waitForTimeout(900);
  await shot('04-pres-subida');
  log('pill', await p.textContent('#pill'));
  await p.waitForTimeout(2500);
  let st = await step(); log('step tras análisis', st);
  // modo «solo archivo»: el asistente aplica la presentación solo y salta al resumen, sin pedir nada más
  if (st !== 'resumen') { log('FALLO: debía saltar al resumen'); bad++; }
  await shot('07-resumen-automatico');
  log('botón construir visible', await p.evaluate(() => { const b = document.getElementById('build-go'); return !!b && b.offsetParent !== null; }));
  // volver atrás a revisar/editar a mano el resto del asistente
  for (let i = 0; i < 8 && (await step()) !== 'negocio'; i++) { await p.click('#nav-back'); await p.waitForTimeout(400); }
  log('step', await step());
  await p.fill('#f-negocio-nombre', 'Bufete 🌟 Pérez');
  log('badge visible', await p.evaluate(() => [...document.querySelectorAll('[data-o]')].filter(n => !n.hidden).map(n => n.dataset.o).join(',')));
  await shot('08-negocio-prellenado');
  // negocio completo
  await p.click('label.rubro >> nth=0'); await p.waitForTimeout(100);
  await p.setInputFiles('#logo-file', FX + 'logo.png'); await p.waitForTimeout(900);
  log('logo set', await p.evaluate(() => !document.getElementById('logo-rm').hidden));
  await p.click('label.estilo >> nth=2');
  await shot('09-negocio-completo');
  await next(); log('step', await step());
  // dominio
  await shot('10-dominio');
  await p.click('.step[data-step=dominio] label.seg__o >> nth=0');
  await p.fill('#f-dominio-dominio', 'HTTPS://www.Bufete-Perez.com/inicio'); await p.press('#f-dominio-dominio', 'Tab');
  log('dominio fmt', await p.inputValue('#f-dominio-dominio'));
  await p.fill('#mail-in', 'Ventas'); await p.press('#mail-in', 'Enter');
  await p.fill('#mail-in', 'ventas'); await p.press('#mail-in', 'Enter');
  log('dup err', await p.textContent('#mail-e'));
  await p.fill('#mail-in', 'info@x.com'); await p.click('#mail-add');
  await p.fill('#f-correo_contacto'.replace('_', '-'), 'mal-correo'); await p.press('#f-correo-contacto', 'Tab');
  log('mail err', await p.textContent('#f-correo-contacto-e'));
  await p.fill('#f-correo-contacto', 'dueno@gmail.com');
  await shot('11-dominio-lleno');
  await next(); log('step', await step());
  // contenido
  await shot('12-contenido');
  log('servicios previos', await p.evaluate(() => document.querySelectorAll('#l-srv .item').length));
  await p.setInputFiles('#g-banner input[type=file]', [FX + 'big.jpg', FX + 'small.jpg']); await p.waitForTimeout(1500);
  for (let i = 0; i < 2; i++) { await p.click('#srv-add'); await p.fill('.item.open input >> nth=0', 'Servicio ' + (i + 4) + ' con un nombre bastante largo para probar el recorte de texto en la lista'); }
  await p.setInputFiles('.item.open input[type=file]', FX + 'small.jpg'); await p.waitForTimeout(900);
  await shot('13-contenido-lleno');
  await next(); log('step', await step());
  // contacto
  await p.fill('#wa-n', '5555 1234');
  await p.click('.redes summary'); await p.fill('#f-contacto-redes-instagram', '@bufete');
  await shot('14-contacto');
  await next(); log('step', await step());
  // resumen
  await shot('15-resumen');
  await p.click('#build-go'); await p.waitForTimeout(1200);
  await shot('16-construyendo');
  await p.waitForSelector('#b-done:not([hidden])', { timeout: 180000 });
  await shot('17-listo');
  await p.click('#b-pay'); await p.waitForTimeout(500);
  log('step', await step());
  await shot('18-pago');
  await p.fill('#pay-nit', 'CF'); await p.setInputFiles('#pay-file', FX + 'small.jpg'); await p.waitForTimeout(700);
  await p.click('[data-pay-submit]'); await p.waitForSelector('[data-pay-done]:not([hidden])', { timeout: 8000 });
  await shot('19-pagado');
  log('guardado', await p.textContent('#save-t'));
  log('errores consola:', JSON.stringify(p.errors), 'overflows:', bad);
  await b.close();
})().catch(e => { console.error('FALLO', e); process.exit(1); });
