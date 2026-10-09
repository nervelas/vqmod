const { launch, BASE, newPage, overflow } = require('./lib');
const FX = __dirname + '/fx/';
const W = +process.argv[2] || 390;
const log = (...a) => console.log(`[${W}]`, ...a);
(async () => {
  const b = await launch();
  const p = await newPage(b, W);
  await p.context().addCookies([{ name: 'sim', value: 'fail', url: BASE }, { name: 'build', value: 'fail', url: BASE }]);
  const step = () => p.evaluate(() => document.querySelector('.step:not([hidden])').dataset.step);
  const shot = async n => { await p.waitForTimeout(300); const o = await overflow(p); if (o.sw > o.iw) log('OVERFLOW', n, o); await p.screenshot({ path: `/tmp/shots/f/B-${W}-${n}.png`, fullPage: true }); };
  const next = async () => { await p.click('#nav-next'); await p.waitForTimeout(400); };
  await p.goto(BASE + '/crear', { waitUntil: 'networkidle' });
  await p.click('label.plan-o >> nth=1'); await p.check('input[data-k=tarjeta_extra][type=checkbox]');
  await next();
  await p.check('#pres-ok'); await p.setInputFiles('#pres-file', FX + 'pres.pdf');
  await p.waitForTimeout(600); await next(); // negocio mientras analiza
  await p.waitForTimeout(6000);
  log('pill fallo:', await p.textContent('#pill'));
  await p.fill('#f-negocio-nombre', 'Moda Ñandú "Aurora" <b>x</b>');
  await p.click('label.rubro >> nth=3'); await next();
  await next(); // dominio
  log('step', await step());
  // 30 servicios
  const t = Date.now();
  await p.evaluate(() => { for (let i = 0; i < 30; i++) document.getElementById('srv-add').click(); });
  log('30 servicios ms', Date.now() - t, await p.evaluate(() => document.querySelectorAll('#l-srv .item').length));
  await p.fill('.item.open input >> nth=0', 'Último servicio');
  await shot('01-contenido-30');
  await next(); log('step', await step());
  await p.evaluate(() => { for (let i = 0; i < 60; i++) document.getElementById('prd-add').click(); });
  log('60 productos', await p.evaluate(() => document.querySelectorAll('#l-prd .item').length));
  await next(); log('validación productos, sigue en', await step(), await p.evaluate(() => document.querySelectorAll('#l-prd .item').length));
  await p.click('#prd-add');
  await p.fill('.item.open input >> nth=0', 'Vestido largo muy elegante de lino con bordado artesanal a mano y encaje francés');
  await p.fill('.item.open input >> nth=1', 'abc'); await p.press('.item.open input >> nth=1', 'Tab');
  await shot('02-producto-error');
  await p.fill('.item.open input >> nth=1', '1,250.50');
  await p.click('#cat-add'); await p.fill('#l-cat input >> nth=0', 'Vestidos'); await p.press('#l-cat input >> nth=0', 'Tab');
  await p.click('.item.open .item__a .ib >> nth=2'); // duplicar
  await p.click('.item.open .item__a .ib >> nth=0'); // mover arriba
  await shot('03-productos');
  await next(); log('step', await step());
  await p.fill('#f-tienda-correo-pedidos', 'pedidos@moda.com');
  await shot('04-cobros');
  await next(); await p.fill('#wa-n', '12'); await next();
  log('wa err', await p.textContent('#wa-e'));
  await p.fill('#wa-n', '55551234'); await next();
  await shot('05-resumen');
  await p.click('#build-go'); await p.waitForSelector('#b-fail:not([hidden])', { timeout: 30000 });
  await shot('06-build-fallo');
  // retomar: recargar la URL
  const url = p.url(); await p.waitForTimeout(1200);
  await p.goto(url, { waitUntil: 'networkidle' });
  log('retomado en', await step(), 'prod', await p.evaluate(() => document.querySelectorAll('#l-prd .item').length));
  // simular guardado fallido
  await p.context().addCookies([{ name: 'savefail', value: '1', url: BASE }]);
  await p.click('label.estilo >> nth=0').catch(() => {});
  log('errores consola:', JSON.stringify(p.errors.filter(e => !/403|500/.test(e))));
  await b.close();
})().catch(e => { console.error('FALLO', e.message.slice(0, 400)); process.exit(1); });
