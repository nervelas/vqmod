#!/usr/bin/env python3
"""Verificación SEO de todas las páginas publicadas (sitemap). Uso: python3 tests/seo_check.py  (Apache en :8081, sitio en /srv/gxwww)."""
import json, os, re, sys, urllib.request, itertools
from html.parser import HTMLParser

BASE = os.environ.get('GX_BASE', 'http://127.0.0.1:8081')
res = []


def ok(n, c, d=''):
    res.append(bool(c))
    if not c:
        print('  FAIL', n, d)
    return c


def get(u):
    try:
        r = urllib.request.urlopen(urllib.request.Request(BASE + u, headers={'User-Agent': 'seo-check'}), timeout=20)
        return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, ''


class P(HTMLParser):
    def __init__(s):
        super().__init__()
        s.h = []; s.cur = None; s.text = []; s.nav = []; s.in_nav = 0; s.links = []; s.imgs = []; s.canon = None; s.desc = None; s.title = ''; s.in_title = False
        s.ld = []; s.in_ld = False; s.robots = ''; s.skip = 0; s.in_main = False

    def handle_starttag(s, t, a):
        a = dict(a)
        if t == 'title': s.in_title = True
        if t == 'meta' and a.get('name') == 'description': s.desc = a.get('content')
        if t == 'meta' and a.get('name') == 'robots': s.robots = a.get('content', '')
        if t == 'link' and a.get('rel') == 'canonical': s.canon = a.get('href')
        if t == 'script' and a.get('type') == 'application/ld+json': s.in_ld = True
        if t == 'main': s.in_main = True
        if t in ('h1', 'h2', 'h3', 'h4'): s.cur = [t, '']
        if t == 'nav': s.in_nav += 1
        if t == 'a' and a.get('href'):
            s.links.append(a['href'])
            if s.in_nav: s.nav.append(a['href'])
        if t == 'img': s.imgs.append(a)
        if t in ('script', 'style'): s.skip += 1

    def handle_endtag(s, t):
        if t == 'title': s.in_title = False
        if t == 'script':
            s.in_ld = False
        if t in ('script', 'style'): s.skip -= 1
        if t in ('h1', 'h2', 'h3', 'h4') and s.cur: s.h.append(tuple(s.cur)); s.cur = None
        if t == 'nav': s.in_nav -= 1
        if t == 'main': s.in_main = False

    def handle_data(s, d):
        if s.in_title: s.title += d
        if s.in_ld: s.ld.append(d)
        if s.cur is not None: s.cur[1] += d
        if s.in_main and not s.skip: s.text.append(d)


st, sm = get('/sitemap.xml')
ok('sitemap 200', st == 200)
urls = re.findall(r'<loc>https?://[^/]+(/[^<]*)</loc>', sm)
print('páginas en sitemap:', len(urls))
ok('sitemap >= 53 páginas', len(urls) >= 53, len(urls))
pages = {}
titles, descs = {}, {}
for u in urls:
    st, h = get(u)
    if not ok('200 ' + u, st == 200, st):
        continue
    p = P(); p.feed(h); pages[u] = p
    t, d = p.title.strip(), (p.desc or '')
    ok(u + ' title<=60', 10 <= len(t) <= 60, len(t))
    ok(u + ' desc<=160', 50 <= len(d) <= 160, len(d))
    ok(u + ' desc empieza con "Tel: 3556-7904"', d.startswith('Tel: 3556-7904'), d[:30])
    hs = [x[0] for x in p.h]
    ok(u + ' un solo H1', hs.count('h1') == 1, hs.count('h1'))
    ok(u + ' tiene H2', hs.count('h2') >= 1)
    lv = [int(x[1]) for x in hs]
    ok(u + ' jerarquía sin saltos', all(b - a <= 1 for a, b in zip(lv, lv[1:])) and lv[0] == 1, lv)
    ok(u + ' canonical', p.canon and p.canon.endswith(u), p.canon)
    ok(u + ' indexable', 'noindex' not in p.robots)
    ok(u + ' title único', t not in titles, titles.get(t)); titles[t] = u
    ok(u + ' description única', d not in descs, descs.get(d)); descs[d] = u
    ok(u + ' imágenes con alt', all(i.get('alt') for i in p.imgs if 'aria-hidden' not in i))
    try:
        ld = json.loads(''.join(p.ld)); ok(u + ' JSON-LD válido', ld.get('@type') == 'LocalBusiness')
    except Exception as e:
        ok(u + ' JSON-LD válido', False, e)
    ok(u + ' menú solo 4 enlaces', sorted(set(p.nav)) == ['/', '/contactenos/', '/servicio-de-gruas-24-7/', '/sobre-nosotros/'], sorted(set(p.nav)))
    if u.startswith('/gruas-'):
        wc = len(re.findall(r'\w+', ' '.join(p.text)))
        ok(u + ' >= 300 palabras', wc >= 300, wc)
# enlaces internos
bad = set()
for u, p in pages.items():
    for l in set(p.links):
        if l.startswith('/') and not l.startswith('//') and l not in urls and not l.startswith(('/assets', '/uploads', '/media')) and '#' not in l:
            if get(l)[0] != 200: bad.add((u, l))
ok('enlaces internos sin 404', not bad, list(bad)[:5])
# cada página SEO tiene enlaces entrantes
inc = {u: 0 for u in urls}
for u, p in pages.items():
    for l in set(p.links):
        if l in inc and l != u: inc[l] += 1
ok('todas las páginas tienen enlaces entrantes', all(v > 0 for v in inc.values()), [k for k, v in inc.items() if v == 0][:5])


# contenido duplicado: similitud de 5-gramas entre páginas SEO
def sh(txt, n=5):
    w = re.findall(r'\w+', txt.lower())
    return set(tuple(w[i:i + n]) for i in range(len(w) - n))


S = {u: sh(' '.join(p.text)) for u, p in pages.items() if u.startswith('/gruas-') and 'zona' not in u}
mx = 0; pair = None
for a, b in itertools.combinations(S, 2):
    j = len(S[a] & S[b]) / max(1, len(S[a] | S[b]))
    if j > mx: mx, pair = j, (a, b)
print('similitud máxima entre municipios (Jaccard 5-gramas): %.2f %s' % (mx, pair))
ok('contenido no duplicado (<0.75)', mx < 0.75, mx)
f = res.count(False)
print('PASS %d / %d  FAIL %d' % (len(res) - f, len(res), f))
sys.exit(1 if f else 0)
