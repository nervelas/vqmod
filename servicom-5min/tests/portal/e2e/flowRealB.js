const { launch, newPage, overflow } = require('./lib');
const BASE = process.env.S5_BASE || 'http://crear.servicom.test:8201';
const FX = __dirname + '/fx/';
const W = +process.argv[2] || 390; const NPROD = +process.argv[3] || 60;
const log = (...a) => console.log(`[tienda ${W}]`, ...a);
(async () => {
  const b = await launch(); const p = await newPage(b, W);
  const step = () => p.evaluate(() => document.querySelector('.step:not([hidden])').dataset.step);
  const shot = async n => { await p.waitForTimeout(300); const o = await overflow(p); if (o.sw > o.iw) log('OVERFLOW', n, o); await p.screenshot({ path: `/tmp/shots/real/B-${W}-${n}.png`, fullPage: true }); };
  const next = async () => { await p.click('#nav-next'); await p.waitForTimeout(450); };
  await p.goto(BASE + '/crear?plan=tienda', { waitUntil: 'networkidle' });
  log('primer paso (solo archivo)', await step());
  await p.click('#pres-skip'); await p.waitForTimeout(900); log('step', await step());
  await p.fill('#f-negocio-nombre', 'Moda Ñandú "Aurora" <b>x</b> 😀');
  await p.click('label.rubro >> nth=3'); await p.click('label.estilo >> nth=4'); await next(); log('step', await step());
  await p.fill('#f-correo-contacto', 'dueno@gmail.com');
  await next(); log('step', await step());   // contenido
  await next(); log('step', await step());   // productos
  for (let i = 0; i < NPROD; i++) {
    await p.click('#prd-add');
    await p.fill('.item.open input >> nth=0', 'Producto ' + (i + 1) + ' ñ 😀');
    await p.fill('.item.open input >> nth=1', String(25 + i));
  }
  log('productos', await p.evaluate(() => document.querySelectorAll('#l-prd .item').length));
  await shot('01-productos');
  await next(); log('step', await step());
  await p.fill('#f-tienda-correo-pedidos', 'pedidos@moda.com');
  await next(); log('step', await step());
  await p.fill('#wa-n', '55551234'); await next(); log('step', await step());
  await shot('02-resumen');
  await p.click('#build-go');
  await p.waitForSelector('#b-done:not([hidden]), #b-fail:not([hidden])', { timeout: 600000 });
  log('resultado', await p.evaluate(() => document.getElementById('b-done').hidden ? 'FALLO' : 'LISTO'), await p.textContent('#b-msg'));
  await shot('03-listo');
  const href = await p.evaluate(() => document.getElementById('b-view').href); log('vista previa', href);
  await p.click('#b-pay'); await p.fill('#pay-nit', 'CF'); await p.setInputFiles('#pay-file', FX + 'small.jpg'); await p.waitForTimeout(700);
  await p.click('[data-pay-submit]'); await p.waitForSelector('[data-pay-done]:not([hidden])', { timeout: 15000 });
  await shot('04-pagado');
  log('errores consola:', JSON.stringify(p.errors));
  await b.close();
})().catch(e => { console.error('FALLO', e.message.slice(0, 500)); process.exit(1); });
