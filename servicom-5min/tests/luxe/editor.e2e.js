// Prueba E2E del editor en el sitio (barra, modo edición, REST, seguridad). Uso: node tests/luxe/editor.e2e.js [slug]
// Requisitos: sitio de prueba construido y sincronizado (tests/luxe/sync.sh <slug>), Apache :8200, chromium de /opt/pw-browsers.
// Inicio de sesión: el ayudante PHP (tests/luxe/editor.e2e.helper.php, ejecutado como www-data) crea las cookies con las
// funciones de WordPress; el plugin NO tiene ningún "modo de prueba". Al terminar restaura el sitio (`restore`).
// Capturas: /tmp/s5ed/shots/*.png (revisarlas a ojo).
const path = require('path');
const fs = require('fs');
const os = require('os');
const { execFileSync } = require('child_process');
const { chromium } = require(path.join(__dirname, '../portal/e2e/node_modules/playwright-core'));

const SLUG = process.argv[2] || 'bufete-nandu-asociados';
const BASE = `http://${SLUG}.servicom.test:8200/`;
const SHOTS = '/tmp/s5ed/shots';
const RUN = fs.mkdtempSync(path.join(os.tmpdir(), 'sc-ed-e2e-'));
fs.chmodSync(RUN, 0o755);
const HELPER = path.join(RUN, 'helper.php');
fs.copyFileSync(path.join(__dirname, 'editor.e2e.helper.php'), HELPER);
fs.chmodSync(HELPER, 0o644);
fs.mkdirSync(SHOTS, { recursive: true });
const SNAP = path.join(RUN, 'snap.ser');
const PNG = path.join(RUN, 'prueba.png');
fs.writeFileSync(SNAP, '');
fs.chmodSync(SNAP, 0o666);
fs.chmodSync(RUN, 0o777);

function helper(...args) {
  const out = execFileSync('runuser', ['-u', 'www-data', '--', 'env', 'SC_E2E_SITE=/tmp/s5test/webs/' + SLUG, 'SC_E2E_HOST=' + SLUG + '.servicom.test:8200', 'php', HELPER, ...args], { encoding: 'utf8' });
  try { return JSON.parse(out); } catch (e) { throw new Error('ayudante: ' + out.slice(0, 300)); }
}

let pass = 0, fail = 0;
function ok(c, msg, extra) { if (c) { pass++; console.log('  ok   ' + msg); } else { fail++; console.log('  FAIL ' + msg + (extra !== undefined ? '  -> ' + extra : '')); } }
function section(s) { console.log('\n== ' + s); }
const REST_EDIT = /sc(\/|%2F)v1(\/|%2F)edit/;

function lum(hex) {
  const c = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255).map(v => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)));
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
}
function contrast(a, b) { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }

(async () => {
  helper('png', PNG);
  fs.chmodSync(PNG, 0o644);
  helper('snapshot', SNAP);
  const info0 = helper('info');
  const key = info0.preview_key;
  const secIdx = (type) => info0.site.pages.home.sections.findIndex(s => s.type === type);
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--host-resolver-rules=MAP *.servicom.test 127.0.0.1'] });
  const errs = [];
  async function ctxFor(role, w, opt) {
    const small = (w || 1440) < 700;
    const ctx = await b.newContext({ viewport: { width: w || 1440, height: small ? 844 : 900 }, hasTouch: small, isMobile: small, deviceScaleFactor: small ? 2 : 1 });
    if (role === 'visitante') {
      await ctx.addCookies([{ name: 'sc_pk', value: key, url: BASE }]);
    } else {
      const ck = helper('login', role);
      const host = SLUG + '.servicom.test';
      await ctx.addCookies([{ name: ck.name, value: ck.value, domain: host, path: '/' }, { name: ck.auth_name, value: ck.auth_value, domain: host, path: '/wp-admin' }]);
      ctx.__login = ck;
    }
    if (!(opt && opt.tour)) { await ctx.addInitScript(() => { try { localStorage.setItem('sc_ed_tour', '1'); } catch (e) { /* nada */ } }); }
    return ctx;
  }
  async function open(ctx, url) {
    const p = await ctx.newPage();
    p.on('pageerror', e => errs.push('pageerror ' + e));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) { errs.push('console ' + m.text()); } });
    await p.goto(BASE + url, { waitUntil: 'load' });
    await p.waitForTimeout(500);
    return p;
  }
  async function saved(p, action) {
    const [r] = await Promise.all([p.waitForResponse(x => REST_EDIT.test(x.url()) && x.request().method() === 'POST', { timeout: 15000 }), action()]);
    return r.json();
  }
  async function reloaded(p, action) {
    await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 20000 }), action()]);
    await p.waitForTimeout(400);
  }
  async function shot(p, name) { await p.waitForTimeout(520); const f = `${SHOTS}/editor-${name}.png`; await p.screenshot({ path: f }); return f; }
  const sel = (p) => `[data-sc="${p}"]`;
  async function jump(loc) { await loc.evaluate(e => e.scrollIntoView({ block: 'center', behavior: 'instant' })); }
  async function stable(p, loc) { // caja del elemento una vez terminado el desplazamiento suave
    let prev = null;
    for (let i = 0; i < 25; i++) {
      await jump(loc);
      const bx = await loc.boundingBox();
      if (prev && Math.abs(bx.y - prev.y) < 0.5 && Math.abs(bx.x - prev.x) < 0.5) { return bx; }
      prev = bx; await p.waitForTimeout(160);
    }
    return prev;
  }

  try {
    /* ---------------------------------------------------------- visitante */
    section('Visitante sin sesión: nada de herramientas ni API');
    const vis = await ctxFor('visitante');
    const vp = await open(vis, '');
    const html = await vp.content();
    ok(!(await vp.$('#sc-ed-bar, #sc-ed-pill')), 'sin barra ni botón de edición');
    ok(!/data-sc-t=|data-sc-sec|data-sc-li|sc-editor/.test(html), 'el HTML no trae atributos del editor ni editor.js');
    ok(!(await vp.$$eval('script[src]', s => s.some(x => /editor\.js|media-editor/.test(x.src)))), 'no se carga editor.js ni medios');
    for (const [m, u, body] of [['GET', '?rest_route=/sc/v1/state'], ['GET', '?rest_route=/sc/v1/icons'], ['POST', '?rest_route=/sc/v1/edit', { ops: [{ op: 'text', path: 'pages.home.sections.0.data.title', value: 'hackeado' }] }]]) {
      const r = await vis.request.fetch(BASE + u, { method: m, data: body, headers: { 'Content-Type': 'application/json' } });
      ok([401, 403].includes(r.status()), `${m} ${u.split('=')[1]} sin sesión → ${r.status()}`);
    }
    // con sesión pero sin nonce → tampoco
    const cli0 = await ctxFor('cliente');
    const r0 = await cli0.request.fetch(BASE + '?rest_route=/sc/v1/edit', { method: 'POST', data: { ops: [{ op: 'text', path: 'pages.home.sections.0.data.title', value: 'hackeado' }] }, headers: { 'Content-Type': 'application/json' } });
    ok([401, 403].includes(r0.status()), `POST con cookie pero sin nonce → ${r0.status()}`);
    ok(helper('info').site.pages.home.sections[0].data.title === info0.site.pages.home.sections[0].data.title, 'el título no cambió con los intentos anónimos');
    await vis.close();

    /* ---------------------------------------------------------- cliente: botón flotante */
    section('Cliente (rol): botón flotante sin edición');
    const cli = await ctxFor('cliente');
    ok(cli.__login.sc_edit_site === true, 'el rol cliente tiene la capacidad sc_edit_site');
    let p = await open(cli, '');
    ok(!!(await p.$('#sc-ed-pill')), 'aparece el botón «Editar mi web»');
    await p.click('.sc-ed-pill__more');
    const menuTxt = await p.$$eval('.sc-ed-pill__menu a', a => a.map(x => x.textContent.trim()));
    ok(menuTxt.includes('Panel') && menuTxt.includes('Instrucciones'), 'menú con Panel e Instrucciones', menuTxt.join(','));
    await shot(p, 'pill-1440');
    ok(!(await p.$('.media-modal')) && !(await p.$$eval('script[src]', s => s.some(x => /media-editor/.test(x.src)))), 'sin edición no se carga la mediateca');
    await p.close();

    /* ---------------------------------------------------------- modo edición */
    section('Modo edición: barra y empuje de la página');
    p = await open(cli, '?sc_edit=1');
    await p.waitForSelector('#sc-ed-bar');
    const geo = await p.evaluate(() => {
      const bar = document.querySelector('#sc-ed-bar').getBoundingClientRect();
      const hd = document.querySelector('.sc-header');
      return { barH: bar.height, htmlTop: parseFloat(getComputedStyle(document.documentElement).marginTop), hdTop: hd ? hd.getBoundingClientRect().top : -1, ov: document.documentElement.scrollWidth - innerWidth, labels: [...document.querySelectorAll('#sc-ed-bar .sc-ed-lbl')].map(x => x.textContent.trim()) };
    });
    ok(geo.barH === 56 && geo.htmlTop === 56, 'barra de 56 px y html empujado', JSON.stringify(geo));
    ok(geo.hdTop >= 56, 'el encabezado del sitio queda debajo de la barra', geo.hdTop);
    ok(['Editar mi web', 'Deshacer', 'Datos del negocio', 'Diseño', 'Secciones', 'Ver sin edición', 'Panel'].every(l => geo.labels.includes(l)), 'botones de la barra en español', geo.labels.join('|'));
    await shot(p, 'edit-1440');

    section('Texto: editar, guardar, persistir');
    const heroT = sel('pages.home.sections.0.data.title');
    const newTitle = 'Su tranquilidad E2E';
    await p.hover(heroT);
    await p.waitForTimeout(150);
    ok(await p.evaluate(() => document.querySelector('.sc-ed-hl.is-show .sc-ed-hl__l, .sc-ed-hl.is-show')?.textContent.includes('Clic para editar')), 'realce con la etiqueta «Clic para editar»');
    await shot(p, 'hover-text-1440');
    await p.click(heroT);
    ok(await p.evaluate(() => document.activeElement && document.activeElement.isContentEditable), 'el texto queda editable');
    await p.keyboard.press('Control+A');
    await p.keyboard.type(newTitle);
    let j = await saved(p, () => p.keyboard.press('Enter'));
    ok(j.ok && j.changed[0].startsWith('text:'), 'Enter guarda por REST', JSON.stringify(j).slice(0, 120));
    ok((await p.textContent('#sc-ed-bar .sc-ed-status')).includes('Guardado'), 'estado «Guardado ✓»');
    await p.reload({ waitUntil: 'load' });
    ok((await p.textContent(heroT)).trim() === newTitle, 'el título persiste tras recargar');
    ok(helper('info').site.pages.home.sections[0].data.title === newTitle, 'persiste en la base de datos');


    section('Teclado, pegado y enlaces del sitio');
    await p.click(heroT);
    await p.keyboard.press('Control+A');
    await p.keyboard.type('NO GUARDAR');
    let reqs = 0;
    const onReq = (r) => { if (REST_EDIT.test(r.url()) && r.method() === 'POST') { reqs++; } };
    p.on('request', onReq);
    await p.keyboard.press('Escape');
    await p.waitForTimeout(500);
    ok((await p.textContent(heroT)).trim() === newTitle && reqs === 0, 'Esc cancela la edición sin guardar nada');
    await p.click(heroT);
    await p.keyboard.press('Control+A');
    await p.evaluate(() => {
      const el = document.activeElement; const dt = new DataTransfer();
      dt.setData('text/plain', 'Pegado <b>plano</b>'); dt.setData('text/html', '<b>Pegado</b> <i>rico</i>');
      el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
    });
    ok(await p.$eval(heroT, e => !e.querySelector('b, i') && e.textContent === 'Pegado <b>plano</b>'), 'al pegar entra solo texto plano (sin formato)');
    await p.keyboard.press('Escape');
    p.off('request', onReq);
    const u0 = p.url();
    await p.locator('.sc-header nav a').nth(1).click({ noWaitAfter: true });
    await p.waitForTimeout(600);
    ok(p.url() === u0, 'en modo edición los enlaces del sitio no navegan');
    ok(await p.$$eval('.lx-rv', els => els.length > 5 && els.every(e => getComputedStyle(e).opacity === '1')), 'las animaciones de revelado quedan visibles');

    section('Seguridad: HTML malicioso no se guarda ni se ejecuta');
    const heroSub = sel('pages.home.sections.0.data.sub');
    await p.click(heroSub);
    await p.keyboard.press('Control+A');
    await p.keyboard.insertText('Hola <script>window.__xss=1</script><img src=x onerror="window.__xss2=1"> mundo');
    j = await saved(p, () => p.keyboard.press('Control+Enter')); // el subtítulo admite saltos de línea: Ctrl+Enter guarda
    const subSaved = helper('info').site.pages.home.sections[0].data.sub;
    ok(j.ok && !/[<>]|script|onerror/i.test(subSaved), 'el servidor guarda solo texto', JSON.stringify(subSaved));
    await p.reload({ waitUntil: 'load' });
    ok(await p.evaluate(() => !window.__xss && !window.__xss2 && !document.querySelector('[data-sc="pages.home.sections.0.data.sub"] script, [data-sc="pages.home.sections.0.data.sub"] img')), 'nada se ejecuta al volver a cargar');
    // directo a la API con nonce válido
    const nonce = await p.evaluate(() => window.SC_ED.nonce);
    const evil = await p.evaluate(async ([n]) => {
      const r = await fetch(window.SC_ED.rest + 'edit', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': n }, body: JSON.stringify({ ops: [{ op: 'text', path: 'pages.home.sections.0.data.eyebrow', value: '<script>alert(1)</script><b onmouseover=alert(2)>negrita</b>' }] }) });
      return r.json();
    }, [nonce]);
    const eb = helper('info').site.pages.home.sections[0].data.eyebrow;
    ok(evil.ok && eb === 'negrita', 'API: etiquetas fuera, queda texto plano', JSON.stringify(eb));
    const bad = await p.evaluate(async ([n]) => {
      const post = (ops) => fetch(window.SC_ED.rest + 'edit', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': n }, body: JSON.stringify({ ops }) }).then(async r => ({ s: r.status, j: await r.json() }));
      return {
        v: await post([{ op: 'text', path: 'v', value: '2' }]),
        pal: await post([{ op: 'text', path: 'design.palette.bg', value: '#000000' }]),
        nuevo: await post([{ op: 'text', path: 'pages.home.sections.0.data.inventado', value: 'x' }]),
        ruta: await post([{ op: 'text', path: '../../x', value: 'x' }]),
        icono: await post([{ op: 'icon', path: 'pages.home.sections.0.data.badge_icon', key: '<svg onload=alert(1)>' }]),
        url: await post([{ op: 'link', path: 'pages.home.sections.0.data.btn1', text: 'x', url: 'javascript:alert(1)' }]),
        img: await post([{ op: 'img', path: 'pages.home.sections.0.data.img', id: 999999 }]),
        muchas: await post(Array.from({ length: 41 }, () => ({ op: 'text', path: 'seo.description', value: 'a' }))),
        largo: await post([{ op: 'text', path: 'seo.description', value: 'x'.repeat(2500) }]),
        atomica: await post([{ op: 'text', path: 'seo.description', value: 'cambio parcial' }, { op: 'icon', path: 'pages.home.sections.0.data.badge_icon', key: 'no-existe' }]),
      };
    }, [nonce]);
    for (const k of Object.keys(bad)) { ok(bad[k].s >= 400 && bad[k].j.ok === false && /[a-záéíóú]{4}/i.test(bad[k].j.msg || ''), `API rechaza «${k}» con mensaje en español`, bad[k].s + ' ' + (bad[k].j.msg || '')); }
    ok(helper('info').site.seo.description === info0.site.seo.description, 'una operación inválida no deja cambios a medias (atómico)');

    section('Enlace / botón');
    const btn1 = sel('pages.home.sections.0.data.btn1');
    await p.click(btn1);
    await p.waitForSelector('#sc-ed-lk-t');
    await p.waitForTimeout(450);
    await shot(p, 'dialog-link-1440');
    await p.fill('#sc-ed-lk-t', 'Hablemos hoy');
    await p.selectOption('#sc-ed-lk-k', 'tel');
    j = await saved(p, () => p.click('.sc-ed-panel .sc-ed-b--pri'));
    ok(j.ok && j.results[0].url === 'tel', 'enlace guardado (tel)');
    ok((await p.getAttribute(btn1, 'href')).startsWith('tel:'), 'el botón apunta a tel: sin recargar');
    await p.reload({ waitUntil: 'load' });
    ok((await p.textContent(btn1)).trim() === 'Hablemos hoy' && (await p.getAttribute(btn1, 'href')).startsWith('tel:'), 'texto y destino persisten');
    // destino no válido desde la ventana
    await p.click(btn1);
    await p.waitForSelector('#sc-ed-lk-k');
    await p.selectOption('#sc-ed-lk-k', 'url');
    await p.fill('#sc-ed-lk-u', 'javascript:alert(1)');
    await p.click('.sc-ed-panel .sc-ed-b--pri');
    await p.waitForSelector('.sc-ed-panel .sc-ed-err:not(:empty)');
    ok((await p.textContent('.sc-ed-panel .sc-ed-err')).includes('destino'), 'URL peligrosa rechazada con aviso claro');
    await p.keyboard.press('Escape');

    section('Ícono');
    const iconEl = await p.$('[data-sc-t="icon"][data-sc*="items"]');
    const iconPath = await iconEl.getAttribute('data-sc');
    await jump(p.locator(`[data-sc="${iconPath}"]`).first());
    await p.click(sel(iconPath));
    await p.waitForSelector('.sc-ed-icons .sc-ed-icon');
    const nIcons = await p.$$eval('.sc-ed-icon', x => x.length);
    ok(nIcons > 100, 'cuadrícula con todos los íconos', nIcons);
    await p.fill('.sc-ed-panel input[type=search]', 'reloj');
    await p.waitForTimeout(450);
    await shot(p, 'dialog-icons-1440');
    j = await saved(p, () => p.click('.sc-ed-icon[data-k="clock"]'));
    ok(j.ok && j.results[0].key === 'clock', 'ícono guardado');
    await p.reload({ waitUntil: 'load' });
    ok(await p.$eval(sel(iconPath), e => !!e.querySelector('.sc-ic--clock')), 'el ícono nuevo persiste');

    section('Imagen: elegir de la mediateca (sube un archivo de prueba)');
    const aboutImg = sel(`pages.home.sections.${secIdx('about')}.data.img`);
    const box = await stable(p, p.locator(aboutImg));
    await p.mouse.move(box.x + box.width * 0.5, box.y + box.height * 0.45);
    await p.waitForSelector('.sc-ed-chip--img.is-show');
    await shot(p, 'hover-image-1440');
    await p.click('.sc-ed-chip--img .sc-ed-cb:first-child');
    await p.waitForSelector('.media-modal', { timeout: 15000 });
    const tabUp = await p.$('.media-modal #menu-item-upload');
    if (tabUp) { await tabUp.click(); }
    await p.waitForTimeout(600);
    await p.setInputFiles('.media-modal input[type=file]', PNG);
    await p.waitForSelector('.media-modal .attachment.selected, .media-modal .attachment.save-ready', { timeout: 30000 });
    await p.waitForTimeout(1200);
    await shot(p, 'media-modal-1440');
    j = await saved(p, () => p.click('.media-modal .media-button-select'));
    ok(j.ok && j.results[0].id > 0, 'imagen elegida y guardada', JSON.stringify(j.results && j.results[0]));
    ok(await p.$eval(aboutImg, e => e.classList.contains('has-img') && !!e.querySelector('img.lx-img[src*="uploads"]')), 'la vista se actualiza sin recargar');
    await p.reload({ waitUntil: 'load' });
    ok(await p.$eval(aboutImg, e => e.classList.contains('has-img') && !!e.querySelector('img.lx-img')), 'la foto persiste tras recargar');
    // quitar → vuelve al arte
    const box2 = await stable(p, p.locator(aboutImg));
    await p.mouse.move(box2.x + box2.width * 0.5, box2.y + box2.height * 0.45);
    await p.waitForSelector('.sc-ed-chip--img.is-show');
    await p.click('.sc-ed-chip--img .sc-ed-cb--warn');
    await p.waitForSelector('.sc-ed-confirm');
    await reloaded(p, () => p.click('.sc-ed-confirm .sc-ed-b--danger'));
    ok(await p.$eval(aboutImg, e => e.classList.contains('is-art')), 'al quitar la foto vuelve el arte decorativo');

    section('Secciones: ocultar/mostrar y mover');
    const iStrip = secIdx('strip');
    const secSel = (i) => `[data-sc-sec="pages.home.sections.${i}"]`;
    await jump(p.locator(secSel(iStrip)));
    j = await saved(p, () => p.click(`${secSel(iStrip)} .sc-ed-secbar .sc-ed-sb:nth-of-type(3)`));
    ok(j.ok && j.results[0].on === false, 'ocultar sección guardado');
    ok(await p.$eval(secSel(iStrip), e => e.classList.contains('lx-off')), 'la sección se ve atenuada en modo edición');
    let st = helper('info');
    ok(st.site.pages.home.sections[iStrip].on === false, 'on=false en la base de datos');
    const pub = await b.newContext(); await pub.addCookies([{ name: 'sc_pk', value: key, url: BASE }]);
    let pubPage = await pub.newPage(); await pubPage.goto(BASE, { waitUntil: 'load' });
    ok(!(await pubPage.$('.lx-sec--strip')), 'los visitantes ya no ven la sección oculta');
    j = await saved(p, () => p.click(`${secSel(iStrip)} .sc-ed-secbar .sc-ed-sb:nth-of-type(3)`));
    ok(j.ok && j.results[0].on === true, 'mostrar de nuevo');
    await pubPage.reload({ waitUntil: 'load' });
    ok(!!(await pubPage.$('.lx-sec--strip')), 'los visitantes vuelven a ver la sección');
    await pub.close();
    // mover hacia abajo (↓ = segundo botón)
    const before = st.site.pages.home.sections.map(s => s.type);
    await reloaded(p, async () => { await p.click(`${secSel(iStrip)} .sc-ed-secbar .sc-ed-sb:nth-of-type(2)`); });
    st = helper('info');
    const after = st.site.pages.home.sections.map(s => s.type);
    ok(after[iStrip] === before[iStrip + 1] && after[iStrip + 1] === 'strip', 'la sección bajó un lugar', after.slice(0, 4).join(','));
    await jump(p.locator(secSel(iStrip + 1)));
    await reloaded(p, async () => { await p.click(`${secSel(iStrip + 1)} .sc-ed-secbar .sc-ed-sb:nth-of-type(1)`); });
    ok(helper('info').site.pages.home.sections.map(s => s.type).join() === before.join(), 'subir la devuelve a su lugar');

    section('Preguntas frecuentes: agregar y quitar');
    const iFaq = secIdx('faq');
    const nFaq = () => p.$$eval('.lx-qa', x => x.length);
    const n0 = await nFaq();
    await jump(p.locator(secSel(iFaq)));
    await reloaded(p, () => p.click(`${secSel(iFaq)} .sc-ed-secbar .sc-ed-sb--add`));
    await p.waitForTimeout(1600);
    ok((await nFaq()) === n0 + 1, 'se agregó una pregunta', (await nFaq()) + ' vs ' + n0);
    ok(await p.evaluate(() => document.activeElement && document.activeElement.isContentEditable && /Nueva pregunta/.test(document.activeElement.textContent)), 'la nueva pregunta queda lista para escribir');
    await p.keyboard.press('Control+A');
    await p.keyboard.type('¿Pregunta E2E?');
    j = await saved(p, () => p.keyboard.press('Enter'));
    ok(j.ok, 'pregunta nueva escrita y guardada');
    await shot(p, 'faq-1440');
    // quitar la última
    const last = p.locator('.lx-qa').last();
    const lb = await stable(p, last);
    await p.mouse.move(lb.x + lb.width - 20, lb.y + 10);
    await p.waitForSelector('.sc-ed-chip--li.is-show');
    await p.click('.sc-ed-chip--li .sc-ed-cb--warn');
    await p.waitForSelector('.sc-ed-confirm');
    await reloaded(p, () => p.click('.sc-ed-confirm .sc-ed-b--danger'));
    ok((await nFaq()) === n0, 'la pregunta se quitó');
    // mover un ítem
    const firstQ = await p.$eval('.lx-qa:nth-child(1) summary span', e => e.textContent);
    const f1 = p.locator('.lx-qa').nth(0);
    const fb = await stable(p, f1);
    await p.mouse.move(fb.x + fb.width - 20, fb.y + 10);
    await p.waitForSelector('.sc-ed-chip--li.is-show');
    await reloaded(p, () => p.click('.sc-ed-chip--li .sc-ed-cb:nth-child(2)'));
    ok((await p.$eval('.lx-qa:nth-child(2) summary span', e => e.textContent)) === firstQ, '↓ mueve la pregunta al segundo lugar');
    const f2 = await stable(p, p.locator('.lx-qa').nth(1));
    await p.mouse.move(f2.x + f2.width - 20, f2.y + 10);
    await p.waitForSelector('.sc-ed-chip--li.is-show');
    await reloaded(p, () => p.click('.sc-ed-chip--li .sc-ed-cb:nth-child(1)'));
    ok((await p.$eval('.lx-qa:nth-child(1) summary span', e => e.textContent)) === firstQ, '↑ la devuelve al primer lugar');

    section('Servicios: agregar (página + menú), duplicar y eliminar');
    const svc0 = helper('info');
    const iSv = secIdx('services');
    await jump(p.locator(secSel(iSv)));
    await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 20000 }), p.click(`${secSel(iSv)} .sc-ed-secbar .sc-ed-sb--add`)]);
    await p.waitForTimeout(1800);
    ok(/sc_edit=1/.test(p.url()) && !/sc_focus/.test(p.url()), 'abre la página del nuevo servicio en modo edición', p.url());
    ok(await p.evaluate(() => document.activeElement && document.activeElement.isContentEditable), 'el nombre del servicio queda listo para escribir');
    await p.keyboard.press('Control+A');
    await p.keyboard.type('Servicio E2E');
    j = await saved(p, () => p.keyboard.press('Enter'));
    ok(j.ok, 'nombre guardado');
    st = helper('info');
    const nuevo = st.svc[st.svc.length - 1];
    ok(st.svc.length === svc0.svc.length + 1 && nuevo.on && nuevo.status === 'publish' && nuevo.meta === 'svc:' + (st.svc.length - 1) && nuevo.parent === st.pageids.servicios, 'página WordPress creada, publicada, hija de Servicios y con meta _sc_luxe', JSON.stringify(nuevo));
    ok(nuevo.title === 'Servicio E2E', 'el título de la página se sincroniza con el nombre', nuevo.title);
    ok(st.menu.some(m => m.title === 'Servicio E2E' && m.parent > 0), 'ítem de menú bajo «Servicios»', JSON.stringify(st.menu.map(m => m.title)));
    ok(st.map[st.svc.length - 1] === nuevo.post, 'opción sc_service_pages actualizada');
    ok(st.site.services[st.svc.length - 1].origen === 'form' && !!st.site.services[st.svc.length - 1].icono, 'origen «form» e ícono asignado');
    ok(st.site.services.slice(0, svc0.svc.length).every((s, i) => s.id === svc0.site.services[i].id && s.nombre === svc0.site.services[i].nombre), 'los índices anteriores no se movieron');
    await shot(p, 'service-page-1440');
    // verlo en el menú del sitio (cliente sin edición)
    const np = await open(cli, '');
    ok(await np.$$eval('.sc-header a, nav a', a => a.some(x => /Servicio E2E/.test(x.textContent))), 'aparece en el menú principal del sitio');
    await np.close();
    // duplicar y eliminar desde la página de Servicios
    await p.goto(BASE + 'servicios/?sc_edit=1', { waitUntil: 'load' });
    await p.waitForTimeout(700);
    const cardSel = `[data-sc-item="services.${svc0.svc.length}"]`;
    let cb = await stable(p, p.locator(cardSel));
    await p.mouse.move(cb.x + cb.width / 2, cb.y + cb.height * 0.7);
    await p.waitForSelector('.sc-ed-chip--item.is-show');
    await shot(p, 'service-chip-1440');
    await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 20000 }), p.click('.sc-ed-chip--item .sc-ed-cb:nth-child(1)')]);
    await p.waitForTimeout(500);
    st = helper('info');
    ok(st.svc.length === svc0.svc.length + 2 && /copia/.test(st.svc[st.svc.length - 1].nombre) && st.svc[st.svc.length - 1].status === 'publish', 'duplicar crea otro servicio con su página', JSON.stringify(st.svc.slice(-1)));
    // eliminar los dos de prueba
    for (let k = 0; k < 2; k++) {
      const idx = svc0.svc.length + (1 - k);
      await p.goto(BASE + 'servicios/?sc_edit=1', { waitUntil: 'load' });
      await p.waitForTimeout(600);
      const cs = `[data-sc-item="services.${idx}"]`;
      cb = await stable(p, p.locator(cs));
      await p.mouse.move(cb.x + cb.width / 2, cb.y + cb.height * 0.7);
      await p.waitForSelector('.sc-ed-chip--item.is-show');
      await p.click('.sc-ed-chip--item .sc-ed-cb--warn');
      await p.waitForSelector('.sc-ed-confirm');
      await reloaded(p, () => p.click('.sc-ed-confirm .sc-ed-b--danger'));
    }
    st = helper('info');
    ok(st.svc.slice(svc0.svc.length).every(s => !s.on && s.status === 'trash'), 'eliminar: on=false y la página va a la papelera', JSON.stringify(st.svc.slice(svc0.svc.length)));
    ok(!st.menu.some(m => /Servicio E2E/.test(m.title)), 'y desaparece del menú');
    ok(st.svc.length === svc0.svc.length + 2 && st.site.services.slice(0, svc0.svc.length).every((s, i) => s.id === svc0.site.services[i].id), 'los índices no se reordenan');

    section('Deshacer');
    await p.goto(BASE + '?sc_edit=1', { waitUntil: 'load' });
    await p.waitForTimeout(500);
    const subBefore = await p.textContent(heroSub);
    await p.click(heroSub);
    await p.keyboard.press('Control+A');
    await p.keyboard.type('Texto para deshacer');
    await saved(p, () => p.keyboard.press('Control+Enter'));
    ok(!(await p.$eval('#sc-ed-bar .sc-ed-grp:first-child .sc-ed-btn:nth-child(2)', e => e.disabled)), 'el botón Deshacer se activa');
    await reloaded(p, () => p.click('#sc-ed-bar .sc-ed-grp:first-child .sc-ed-btn:nth-child(2)'));
    ok((await p.textContent(heroSub)).trim() === subBefore.trim(), 'Deshacer devuelve el texto anterior', await p.textContent(heroSub));

    section('Diseño: colores con contraste AA, ambiente y tipografías');
    const vars = () => p.evaluate(() => { const s = getComputedStyle(document.documentElement); const g = (k) => s.getPropertyValue('--lx-' + k).trim(); return { bg: g('bg'), ink: g('ink'), muted: g('muted'), primary: g('primary'), pi: g('primary-ink'), accent: g('accent'), headF: g('font-head'), dark: g('dark'), dink: g('dark-ink') }; });
    const v0 = await vars();
    await p.click('#sc-ed-bar .sc-ed-btn:has-text("Diseño")');
    await p.waitForSelector('#sc-ed-pri');
    await shot(p, 'drawer-design-1440');
    await p.fill('.sc-ed-hex >> nth=0', '#8a1c2b');
    j = await saved(p, () => p.dispatchEvent('.sc-ed-hex >> nth=0', 'change'));
    ok(j.ok && j.results[0].css.includes('--lx-primary'), 'color de marca guardado y CSS recalculado');
    await p.waitForTimeout(300);
    const v1 = await vars();
    ok(v1.primary !== v0.primary && v1.bg !== v0.bg, 'la paleta cambió en vivo', v0.primary + ' → ' + v1.primary);
    const aa = (v) => [contrast(v.ink, v.bg), contrast(v.muted, v.bg), contrast(v.primary, v.bg), contrast(v.accent, v.bg), contrast(v.pi, v.primary), contrast(v.dink, v.dark)];
    ok(aa(v1).every(c => c >= 4.5), 'contraste AA (≥4.5) en todos los pares de texto', aa(v1).map(x => x.toFixed(1)).join(' '));
    const stInfo = helper('info');
    ok(stInfo.site.design.base.primary === '#8a1c2b' && stInfo.site.design.palette.brand === '#8a1c2b', 'design.base guardado y paleta recalculada en la BD');
    j = await saved(p, () => p.click('.sc-ed-mood.is-light'));
    await p.waitForTimeout(300);
    const v2 = await vars();
    ok(j.ok && (await p.evaluate(() => document.body.classList.contains('lx-body-light'))) && aa(v2).every(c => c >= 4.5), 'ambiente claro con contraste AA', aa(v2).map(x => x.toFixed(1)).join(' '));
    j = await saved(p, () => p.click('.sc-ed-font:has-text("Playfair")'));
    await p.waitForTimeout(200);
    ok(j.ok && (await vars()).headF.includes('Playfair'), 'tipografía de títulos cambia');
    await shot(p, 'drawer-design-light-1440');
    await p.keyboard.press('Escape');
    await p.waitForTimeout(350);
    await shot(p, 'edit-light-1440');
    // volver al aspecto original (también lo hace restore)
    await p.click('#sc-ed-bar .sc-ed-btn:has-text("Diseño")');
    await p.waitForSelector('.sc-ed-mood.is-dark');
    await saved(p, () => p.click('.sc-ed-mood.is-dark'));
    await p.keyboard.press('Escape');
    const badc = await p.evaluate(async ([n]) => { const r = await fetch(window.SC_ED.rest + 'edit', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': n }, body: JSON.stringify({ ops: [{ op: 'design', primary: 'rojo' }] }) }); return { s: r.status, j: await r.json() }; }, [nonce]);
    ok(badc.s === 400 && /color/i.test(badc.j.msg), 'color no válido rechazado en español', badc.j.msg);

    section('Datos del negocio: teléfono visible en el pie');
    await p.click('#sc-ed-bar .sc-ed-btn:has-text("Datos del negocio")');
    await p.waitForSelector('#sc-ed-b-telefono');
    await shot(p, 'drawer-biz-1440');
    await p.fill('#sc-ed-b-correo', 'no-es-correo');
    await p.dispatchEvent('#sc-ed-b-correo', 'change');
    await p.waitForSelector('.sc-ed-fmsg.is-err');
    ok((await p.textContent('.sc-ed-fmsg.is-err')).includes('no es válido'), 'correo inválido: aviso claro y no se guarda');
    await p.fill('#sc-ed-b-correo', '');
    await p.fill('#sc-ed-b-telefono', '2255-9988');
    j = await saved(p, () => p.dispatchEvent('#sc-ed-b-telefono', 'change'));
    ok(j.ok && helper('info').tel === '2255-9988', 'teléfono guardado en el tema (sc_telefono)');
    await reloaded(p, () => p.keyboard.press('Escape'));
    const foot = await p.$eval('footer', e => e.textContent);
    ok(foot.includes('2255-9988'), 'el nuevo teléfono aparece en el pie de página');
    await p.evaluate(() => scrollTo(0, 0));

    section('Foco desde ?sc_focus=');
    await p.goto(BASE + '?sc_edit=1&sc_focus=pages.home.sections.0.data.sub', { waitUntil: 'load' });
    await p.waitForTimeout(2000);
    ok(await p.evaluate(() => { const a = document.activeElement; return a && a.getAttribute('data-sc') === 'pages.home.sections.0.data.sub' && a.isContentEditable; }), 'enfoca y abre la edición del texto');
    ok(!/sc_focus/.test(p.url()), 'limpia sc_focus de la dirección');
    await p.keyboard.press('Escape');
    await p.goto(BASE + `?sc_edit=1&sc_focus=pages.home.sections.${iSv}`, { waitUntil: 'load' });
    await p.waitForTimeout(1500);
    ok(await p.$eval(secSel(iSv), e => e.classList.contains('sc-ed-pulse')), 'resalta la sección con pulso');
    await p.goto(BASE + '?sc_edit=1&sc_focus=' + iconPath, { waitUntil: 'load' });
    await p.waitForSelector('.sc-ed-icons', { timeout: 8000 }).catch(() => {});
    ok(!!(await p.$('.sc-ed-icons')), 'un ícono abre su selector');
    await p.keyboard.press('Escape');

    section('Pendientes: ?sc_biz= abre «Datos del negocio» en el campo exacto');
    await p.goto(BASE + '?sc_edit=1&sc_biz=whatsapp', { waitUntil: 'load' });
    await p.waitForSelector('#sc-ed-b-whatsapp', { timeout: 8000 }).catch(() => {});
    await p.waitForTimeout(800);
    ok(await p.evaluate(() => document.activeElement && document.activeElement.id === 'sc-ed-b-whatsapp'), 'abre el cajón con el campo de WhatsApp enfocado');
    ok(!/sc_biz/.test(p.url()), 'limpia sc_biz de la dirección');
    await p.keyboard.press('Escape');
    await p.goto(BASE + '?sc_edit=1&sc_biz=logo', { waitUntil: 'load' });
    await p.waitForSelector('.sc-ed-panel .sc-ed-logo', { timeout: 8000 }).catch(() => {});
    ok(!!(await p.$('.sc-ed-panel .sc-ed-logo')), 'sc_biz=logo abre el cajón en la sección del logo');
    await p.keyboard.press('Escape');

    section('Ver sin edición / Secciones');
    await p.goto(BASE + '?sc_edit=1', { waitUntil: 'load' });
    await p.click('#sc-ed-bar .sc-ed-btn:has-text("Secciones")');
    await p.waitForSelector('.sc-ed-seclist');
    ok((await p.$$eval('.sc-ed-secrow', r => r.length)) === info0.site.pages.home.sections.length, 'la lista muestra todas las secciones de la página');
    await shot(p, 'drawer-sections-1440');
    await p.keyboard.press('Escape');
    await p.waitForTimeout(350);
    await p.click('#sc-ed-bar .sc-ed-btn:has-text("Ver sin edición")');
    ok(await p.evaluate(() => document.body.classList.contains('sc-ed-peek') && getComputedStyle(document.querySelector('.sc-ed-secbar')).display === 'none'), '«Ver sin edición» oculta las herramientas');
    await shot(p, 'peek-1440');
    await p.close();


    section('Recorrido de la primera vez');
    const tc = await ctxFor('cliente', 1440, { tour: true });
    const tp = await open(tc, '?sc_edit=1');
    await tp.waitForSelector('.sc-ed-tour', { timeout: 8000 });
    await shot(tp, 'tour-1440');
    let steps = 1;
    while (await tp.$eval('.sc-ed-tour .sc-ed-b--pri', e => e.textContent.trim()) !== 'Entendido' && steps < 8) { await tp.click('.sc-ed-tour .sc-ed-b--pri'); steps++; }
    ok(steps === 4, 'el recorrido tiene 4 pasos', steps);
    await tp.click('.sc-ed-tour .sc-ed-b--pri');
    ok(!(await tp.$('.sc-ed-tour')) && (await tp.evaluate(() => localStorage.getItem('sc_ed_tour'))) === '1', 'termina y se recuerda en localStorage');
    await tp.reload({ waitUntil: 'load' }); await tp.waitForTimeout(1500);
    ok(!(await tp.$('.sc-ed-tour')), 'no vuelve a mostrarse');
    await tc.close();

    /* ---------------------------------------------------------- administrador y móvil */
    section('Administrador y vista móvil (390 px)');
    const adm = await ctxFor('admin');
    p = await open(adm, '?sc_edit=1');
    ok(!!(await p.$('#sc-ed-bar')), 'el administrador también ve la barra');
    await p.close();
    await adm.close();
    const mob = await ctxFor('cliente', 390);
    p = await open(mob, '?sc_edit=1');
    await p.waitForSelector('#sc-ed-bar');
    const mg = await p.evaluate(() => {
      const els = [...document.querySelectorAll('#sc-ed-bar .sc-ed-btn')].filter(e => e.offsetParent !== null);
      const rs = els.map(e => e.getBoundingClientRect());
      let overlap = false;
      rs.forEach((a, i) => rs.forEach((c, k) => { if (i < k && a.left < c.right - 1 && c.left < a.right - 1 && a.top < c.bottom && c.top < a.bottom) { overlap = true; } }));
      return { n: els.length, overlap, last: Math.max(...rs.map(r => r.right)), ov: document.documentElement.scrollWidth - innerWidth, h: document.querySelector('#sc-ed-bar').getBoundingClientRect().height };
    });
    ok(!mg.overlap && mg.last <= 390 && mg.ov <= 0 && mg.h === 56, 'barra móvil sin solapes ni desbordes', JSON.stringify(mg));
    await shot(p, 'edit-390');
    await p.evaluate(() => window.scrollTo(0, 900)); await p.waitForTimeout(500);
    await shot(p, 'edit-scrolled-390');
    await p.click('#sc-ed-bar .sc-ed-btn:has-text("Datos"), #sc-ed-bar .sc-ed-btn[title^="Teléfono"]');
    await p.waitForSelector('#sc-ed-b-telefono');
    await p.waitForTimeout(400);
    await shot(p, 'drawer-biz-390');
    await p.keyboard.press('Escape'); await p.waitForTimeout(300);
    await p.click('#sc-ed-bar .sc-ed-btn[title^="Colores"]');
    await p.waitForSelector('#sc-ed-pri'); await p.waitForTimeout(400);
    await shot(p, 'drawer-design-390');
    await p.keyboard.press('Escape'); await p.waitForTimeout(300);
    await p.click('#sc-ed-bar .sc-ed-btn[title^="Mostrar, ocultar"]');
    await p.waitForSelector('.sc-ed-seclist'); await p.waitForTimeout(400);
    await shot(p, 'drawer-sections-390');
    await p.keyboard.press('Escape'); await p.waitForTimeout(300);
    // toque sobre un texto en móvil
    await p.evaluate(() => scrollTo(0, 0));
    await p.tap(sel('pages.home.sections.0.data.title'));
    ok(await p.evaluate(() => document.activeElement && document.activeElement.isContentEditable), 'en el móvil un toque abre la edición del texto');
    await shot(p, 'editing-390');
    await p.keyboard.press('Escape');
    await mob.close();

    ok(errs.length === 0, 'sin errores de JavaScript ni de consola', errs.slice(0, 4).join(' | '));
  } catch (e) {
    fail++;
    console.log('  FAIL excepción: ' + (e && e.stack || e));
  } finally {
    try { const r = helper('restore', SNAP); console.log('\nsitio restaurado:', JSON.stringify(r)); } catch (e) { console.log('NO se pudo restaurar el sitio:', e.message); }
    await b.close();
  }
  console.log(`\neditor.e2e: ${pass} ok, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})();
