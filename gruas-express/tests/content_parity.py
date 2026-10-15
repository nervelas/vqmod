#!/usr/bin/env python3
"""Compara los textos del contenido ORIGINAL (extraídos de la base de datos de WordPress, tests/original_texts.json)
con los textos visibles del sitio nuevo. Cada texto original debe aparecer tal cual."""
import html, json, os, re, sys, urllib.request
from html.parser import HTMLParser

BASE = os.environ.get('GX_BASE', 'http://127.0.0.1:8081')
orig = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'original_texts.json')))


class T(HTMLParser):
    def __init__(s):
        super().__init__(); s.skip = 0; s.out = []
    def handle_starttag(s, t, a):
        if t in ('script', 'style', 'svg', 'noscript'): s.skip += 1
        if t == 'br': s.out.append('\n')
    def handle_endtag(s, t):
        if t in ('script', 'style', 'svg', 'noscript'): s.skip = max(0, s.skip - 1)
    def handle_data(s, d):
        if not s.skip: s.out.append(d)


def new_text(path):
    r = urllib.request.Request(BASE + path, headers={'User-Agent': 'Mozilla/5.0 GxParity'})
    h = urllib.request.urlopen(r).read().decode()
    t = T(); t.feed(h)
    return re.sub(r'\s+', ' ', html.unescape(' '.join(t.out)))

norm = lambda s: re.sub(r'\s+', ' ', s).strip()
bad = 0; total = 0
for path, items in orig.items():
    if path == '_comun':
        continue
    page = new_text(path)
    common = new_text(path)
    for x in items + orig['_comun']:
        total += 1
        if norm(x) not in page:
            bad += 1; print('FALTA en %s: %r' % (path, x))
print('Textos originales verificados: %d · faltantes o distintos: %d' % (total, bad))
sys.exit(1 if bad else 0)
