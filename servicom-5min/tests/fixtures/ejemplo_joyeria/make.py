# Genera el PDF de ejemplo «UNS Joyería»: presentación completa de una TIENDA VIRTUAL (categorías y productos con precio), tal como la necesita «Tu web en 5 minutos».
# Uso: python3 -I make.py salida.pdf
import sys, math, random, io
from PIL import Image, ImageDraw, ImageFilter, ImageFont
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
OUT = sys.argv[1]
F = '/usr/share/fonts/truetype/liberation/'
for n, f in [('Serif', 'LiberationSerif-Regular.ttf'), ('Serif-B', 'LiberationSerif-Bold.ttf'), ('Serif-I', 'LiberationSerif-Italic.ttf'), ('Sans', 'LiberationSans-Regular.ttf'), ('Sans-B', 'LiberationSans-Bold.ttf')]:
    pdfmetrics.registerFont(TTFont(n, F + f))
INK, CHAR, GOLD, CHAMP, CREAM, MUTE = '#171717', '#1F1B16', '#C8A24A', '#F1E4C3', '#FAF6EC', '#6B6254'
def rgb(h): h = h.lstrip('#'); return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))
S = 2
METAL = {'oro': ('#F3D98B', '#C8A24A', '#8A6A1E'), 'plata': ('#FFFFFF', '#D9DEE6', '#8C95A3'), 'rosa': ('#FFDCC8', '#E3A185', '#A5624B')}
GEM = {'diamante': ('#FFFFFF', '#CFE6FF'), 'esmeralda': ('#B8F2D0', '#1F9D62'), 'rubi': ('#FFC4CB', '#C2183A'), 'zafiro': ('#C7D8FF', '#1E3FA8'), 'perla': ('#FFFFFF', '#E8E1D6'), 'topacio': ('#FFE9B0', '#E5A100')}

def bgimg(w, h, c1, c2, seed):
    im = Image.new('RGB', (w * S, h * S)); px = im.load(); a, b = rgb(c1), rgb(c2)
    for y in range(h * S):
        t = y / (h * S)
        for x in range(w * S):
            tt = min(1, t * .8 + x / (w * S) * .2); px[x, y] = tuple(int(a[i] * (1 - tt) + b[i] * tt) for i in range(3))
    random.seed(seed); ov = Image.new('RGBA', im.size, (0, 0, 0, 0)); d = ImageDraw.Draw(ov)
    for _ in range(18):
        r = random.randint(40, 170) * S // 2; x = random.randint(0, im.size[0]); y = random.randint(0, im.size[1]); d.ellipse([x - r, y - r, x + r, y + r], fill=rgb(GOLD) + (random.randint(8, 30),))
    ov = ov.filter(ImageFilter.GaussianBlur(12 * S)); im.paste(ov, (0, 0), ov); return im
def grad_ellipse(im, box, c1, c2, c3):
    """Elipse con degradado metálico (claro arriba-izq, oscuro abajo-der)."""
    x0, y0, x1, y1 = [int(v) for v in box]; w, h = x1 - x0, y1 - y0
    g = Image.new('RGB', (w, h)); px = g.load(); a, b, c = rgb(c1), rgb(c2), rgb(c3)
    for y in range(h):
        for x in range(w):
            t = (x / w + y / h) / 2
            px[x, y] = tuple(int(a[i] * (1 - t * 2) + b[i] * t * 2) for i in range(3)) if t < .5 else tuple(int(b[i] * (2 - t * 2) + c[i] * (t * 2 - 1)) for i in range(3))
    m = Image.new('L', (w, h), 0); ImageDraw.Draw(m).ellipse([0, 0, w - 1, h - 1], fill=255); im.paste(g, (x0, y0), m)
def ring_band(im, cx, cy, rx, ry, thick, metal):
    c1, c2, c3 = METAL[metal]; d = ImageDraw.Draw(im)
    grad_ellipse(im, [cx - rx, cy - ry, cx + rx, cy + ry], c1, c2, c3)
    inner = Image.new('RGB', (int((rx - thick) * 2), int((ry - thick * .8) * 2)), (0, 0, 0))
    return d
def gem(d, cx, cy, r, kind, facets=True):
    c1, c2 = GEM[kind]; col1, col2 = rgb(c1), rgb(c2)
    pts = [(cx - r, cy - r * .1), (cx - r * .55, cy - r * .75), (cx + r * .55, cy - r * .75), (cx + r, cy - r * .1), (cx, cy + r * .95)]
    d.polygon(pts, fill=col2)
    d.polygon([pts[0], pts[1], (cx, cy - r * .1)], fill=col1); d.polygon([pts[1], pts[2], (cx, cy - r * .1)], fill=tuple(min(255, v + 30) for v in col1)); d.polygon([pts[2], pts[3], (cx, cy - r * .1)], fill=col1)
    d.polygon([pts[0], (cx, cy - r * .1), pts[4]], fill=tuple(int(v * .8 + 30) for v in col2)); d.polygon([pts[3], (cx, cy - r * .1), pts[4]], fill=tuple(int(v * .65 + 20) for v in col2))
    d.line(pts + [pts[0]], fill=rgb('#FFFFFF') + (160,), width=max(2, S))
    d.ellipse([cx - r * .35, cy - r * .62, cx - r * .12, cy - r * .42], fill=(255, 255, 255, 190))
def draw_ring(d, cx, cy, s, metal, kind, style=0):
    c1, c2, c3 = METAL[metal]
    for k in range(18):  # aro
        t = k / 17; r = s * (1 - t * .0); col = tuple(int(rgb(c1)[i] * (1 - t) + rgb(c3)[i] * t) for i in range(3))
        d.ellipse([cx - s * 1.0 + k * 1.1 * S, cy - s * 1.05 + k * 1.1 * S, cx + s * 1.0 - k * 1.1 * S, cy + s * 1.05 - k * 1.1 * S], outline=col, width=int(3.2 * S))
    if style == 1:  # alianza lisa: sin piedra
        d.arc([cx - s * .86, cy - s * .9, cx + s * .86, cy + s * .9], 215, 300, fill=rgb('#FFFFFF') + (200,), width=int(3 * S)); return
    d.polygon([(cx - s * .28, cy - s * .98), (cx + s * .28, cy - s * .98), (cx + s * .16, cy - s * 1.22), (cx - s * .16, cy - s * 1.22)], fill=rgb(c2))
    gem(d, cx, cy - s * 1.5, s * .46, kind)
    if style == 2:
        for sx in (-1, 1): gem(d, cx + sx * s * .72, cy - s * 1.12, s * .2, 'diamante')
def draw_necklace(d, cx, cy, s, metal, kind, style=0):
    c1, c2, c3 = METAL[metal]; col = rgb(c2)
    ccy = cy - s * 1.0; rx, ry = s * 1.45, s * 1.75
    d.arc([cx - rx, ccy - ry, cx + rx, ccy + ry], 25, 155, fill=col, width=int(5 * S))
    for k in range(31):
        a = math.radians(25 + k * 130 / 30); x = cx + math.cos(a) * rx; y = ccy + math.sin(a) * ry
        d.ellipse([x - 5 * S, y - 5 * S, x + 5 * S, y + 5 * S], outline=rgb(c1), width=S)
    py = ccy + ry - s * .05
    if style == 0: gem(d, cx, py + s * .55, s * .55, kind)
    elif style == 1: d.ellipse([cx - s * .5, py, cx + s * .5, py + s * 1.0], fill=rgb(c1), outline=rgb(c3), width=3 * S); d.ellipse([cx - s * .38, py + s * .12, cx + s * .38, py + s * .88], outline=rgb(c2), width=2 * S); gem(d, cx, py + s * .5, s * .26, kind)
    elif style == 2:
        d.rounded_rectangle([cx - s * .13, py, cx + s * .13, py + s * 1.15], radius=s * .06, fill=rgb(c1)); d.rounded_rectangle([cx - s * .46, py + s * .28, cx + s * .46, py + s * .52], radius=s * .06, fill=rgb(c1))
    else:
        for k in range(3): d.ellipse([cx - s * .22 + (k - 1) * s * .46, py + abs(k - 1) * s * .14, cx + s * .22 + (k - 1) * s * .46, py + s * .44 + abs(k - 1) * s * .14], fill=rgb('#F8F4EA'), outline=rgb('#CFC6B3'), width=S)
def draw_earrings(d, cx, cy, s, metal, kind, style=0):
    c1, c2, c3 = METAL[metal]
    for sx in (-1, 1):
        x = cx + sx * s * .9
        d.ellipse([x - s * .09, cy - s * 1.3, x + s * .09, cy - s * 1.12], fill=rgb(c2))
        if style == 0: gem(d, x, cy - s * .45, s * .4, kind)                       # broquel
        elif style == 1: d.line([x, cy - s * 1.1, x, cy - s * .3], fill=rgb(c2), width=int(3 * S)); gem(d, x, cy + s * .15, s * .38, kind)  # colgante
        elif style == 2: d.ellipse([x - s * .55, cy - s * 1.05, x + s * .55, cy + s * .05], outline=rgb(c2), width=int(7 * S))   # argolla
        else:
            for k in range(3): d.ellipse([x - s * .15, cy - s * .95 + k * s * .45, x + s * .15, cy - s * .65 + k * s * .45], fill=rgb('#F8F4EA'), outline=rgb('#CFC6B3'), width=S)
def draw_bracelet(d, cx, cy, s, metal, kind, style=0):
    c1, c2, c3 = METAL[metal]
    if style in (0, 3):  # eslabones
        n = 22
        for k in range(n): a = 2 * math.pi * k / n; x = cx + math.cos(a) * s * 1.35; y = cy + math.sin(a) * s * .72; d.ellipse([x - 30 * S, y - 19 * S, x + 30 * S, y + 19 * S], outline=rgb(c2 if k % 2 else c1), width=int(8 * S))
    elif style == 1: d.ellipse([cx - s * 1.4, cy - s * .8, cx + s * 1.4, cy + s * .8], outline=rgb(c2), width=int(26 * S)); d.ellipse([cx - s * 1.25, cy - s * .68, cx + s * 1.25, cy + s * .68], outline=rgb(c1), width=int(5 * S))   # esclava
    else:
        d.ellipse([cx - s * 1.4, cy - s * .8, cx + s * 1.4, cy + s * .8], outline=rgb(c2), width=int(10 * S))
        for k in range(9): a = math.radians(180 + k * 22.5); gem(d, cx + math.cos(a) * s * 1.4, cy + math.sin(a) * s * .8, s * .24, kind)   # tennis
    if style == 3: gem(d, cx, cy + s * .75, s * .3, kind)
def draw_watch(d, cx, cy, s, metal, kind, style=0):
    c1, c2, c3 = METAL[metal]
    d.rounded_rectangle([cx - s * .5, cy - s * 1.9, cx + s * .5, cy + s * 1.9], radius=s * .15, fill=rgb('#2B2724') if style != 1 else rgb(c3))
    d.ellipse([cx - s * 1.0, cy - s * 1.0, cx + s * 1.0, cy + s * 1.0], fill=rgb(c2), outline=rgb(c1), width=int(5 * S))
    d.ellipse([cx - s * .82, cy - s * .82, cx + s * .82, cy + s * .82], fill=rgb('#101820') if style != 2 else rgb('#F8F4EA'))
    tc = rgb(c1) if style != 2 else rgb('#2B2724')
    for k in range(12): a = math.radians(k * 30); r1 = s * (.7 if k % 3 == 0 else .74); d.line([cx + math.cos(a) * r1, cy + math.sin(a) * r1, cx + math.cos(a) * s * .8, cy + math.sin(a) * s * .8], fill=tc, width=int((3 if k % 3 == 0 else 2) * S))
    d.line([cx, cy, cx + s * .05, cy - s * .55], fill=tc, width=int(5 * S)); d.line([cx, cy, cx + s * .42, cy + s * .12], fill=tc, width=int(4 * S)); d.ellipse([cx - 5 * S, cy - 5 * S, cx + 5 * S, cy + 5 * S], fill=rgb(c2))
def draw_chain(d, cx, cy, s, metal, kind, style=0):
    c1, c2, c3 = METAL[metal]
    if style in (0, 1):
        n = 30; ccy = cy - s * 1.0
        for k in range(n): a = math.radians(25 + k * 130 / (n - 1)); x = cx + math.cos(a) * s * 1.45; y = ccy + math.sin(a) * s * 1.75; d.ellipse([x - 18 * S, y - 11 * S, x + 18 * S, y + 11 * S], outline=rgb(c2 if k % 2 else c1), width=int((7 if style == 0 else 5) * S))
        if style == 1: d.rounded_rectangle([cx - s * .4, ccy + s * 1.7, cx + s * .4, ccy + s * 2.6], radius=s * .1, fill=rgb(c1), outline=rgb(c3), width=3 * S); gem(d, cx, ccy + s * 2.15, s * .22, 'topacio')
    elif style == 2:   # anillo sello
        d.ellipse([cx - s * .95, cy - s * .55, cx + s * .95, cy + s * 1.2], outline=rgb(c2), width=int(16 * S)); d.rounded_rectangle([cx - s * .62, cy - s * 1.05, cx + s * .62, cy - s * .15], radius=s * .12, fill=rgb(c1), outline=rgb(c3), width=3 * S); gem(d, cx, cy - s * .55, s * .26, kind)
    else:              # gemelos
        for sx in (-1, 1): x = cx + sx * s * .8; d.rounded_rectangle([x - s * .42, cy - s * .42, x + s * .42, cy + s * .42], radius=s * .1, fill=rgb(c2), outline=rgb(c1), width=3 * S); gem(d, x, cy - s * .02, s * .24, kind); d.line([x, cy + s * .42, x, cy + s * .95], fill=rgb(c1), width=int(5 * S))
DRAW = {'anillo': draw_ring, 'collar': draw_necklace, 'aretes': draw_earrings, 'pulsera': draw_bracelet, 'reloj': draw_watch, 'caballero': draw_chain}
def product_img(kind_cat, metal, gemk, style, seed, bgs=(CHAR, '#3A2E1C')):
    w = h = 900; im = bgimg(w, h, bgs[0], bgs[1], seed); d = ImageDraw.Draw(im, 'RGBA')
    d.ellipse([w * S * .12, h * S * .62, w * S * .88, h * S * .86], fill=(0, 0, 0, 70)); ov = im.filter(ImageFilter.GaussianBlur(0))
    SCL = {'anillo': (1.0, .62), 'collar': (1.05, .42), 'aretes': (1.5, .55), 'pulsera': (1.3, .5), 'reloj': (1.1, .5), 'caballero': (1.0, .42)}[kind_cat]
    DRAW[kind_cat](d, w * S // 2, int(h * S * SCL[1]), int(190 * S * SCL[0]), metal, gemk, style)
    return im.resize((w, h), Image.LANCZOS).convert('RGB')
def hero_img(w=1800, h=1000):
    im = bgimg(w, h, '#0E0C09', '#3A2E1C', 11); d = ImageDraw.Draw(im, 'RGBA')
    draw_necklace(d, 560 * S, 470 * S, 250 * S, 'oro', 'esmeralda', 0); draw_ring(d, 1240 * S, 700 * S, 170 * S, 'oro', 'diamante', 2); draw_earrings(d, 1500 * S, 400 * S, 140 * S, 'oro', 'rubi', 1)
    return im.resize((w, h), Image.LANCZOS).convert('RGB')
def about_img(w=1400, h=900):
    im = bgimg(w, h, '#1A1510', '#4B3A20', 12); d = ImageDraw.Draw(im, 'RGBA'); gem(d, 700 * S, 430 * S, 250 * S, 'diamante')
    for sx, kk in [(-1, 'rubi'), (1, 'zafiro')]: gem(d, 700 * S + sx * 430 * S, 520 * S, 110 * S, kk)
    return im.resize((w, h), Image.LANCZOS).convert('RGB')
def logo(w=900):
    im = Image.new('RGBA', (w * S, w * S), (0, 0, 0, 0)); d = ImageDraw.Draw(im, 'RGBA'); c = w * S // 2
    d.rounded_rectangle([0, 0, w * S - 1, w * S - 1], radius=120 * S, fill=rgb(CREAM) + (255,))
    d.ellipse([130 * S, 70 * S, 770 * S, 710 * S], fill=rgb(INK)); d.ellipse([160 * S, 100 * S, 740 * S, 680 * S], outline=rgb(GOLD), width=8 * S)
    gem(d, c, 345 * S, 170 * S, 'diamante'); 
    ft = ImageFont.truetype(F + 'LiberationSerif-Bold.ttf', 120 * S); t = 'UNS'; tw = d.textlength(t, font=ft); d.text((c - tw / 2, 700 * S), t, font=ft, fill=rgb(INK))
    fs = ImageFont.truetype(F + 'LiberationSerif-Regular.ttf', 52 * S); t2 = 'J O Y E R Í A'; tw2 = d.textlength(t2, font=fs); d.text((c - tw2 / 2, 835 * S), t2, font=fs, fill=rgb('#9C7A22'))
    return im.resize((w, w), Image.LANCZOS)

# ------------------------------------------------------------------ catálogo
NOMBRE = 'UNS Joyería'
FRASE = 'Joyas que cuentan su historia'
CATS = [
 ('Anillos y Alianzas', 'anillo', 'Compromiso, matrimonio y regalos inolvidables, con piedras certificadas.', [
   ('Anillo Solitario Aurora', 'Oro 18K con diamante central de 0.50 quilates. Elegancia clásica para pedir matrimonio.', 12500, 'oro', 'diamante', 0),
   ('Anillo Halo Imperial', 'Oro blanco 14K con zafiro azul rodeado de diamantes pequeños.', 9800, 'plata', 'zafiro', 2),
   ('Alianza Clásica Oro 18K', 'Par de argollas de matrimonio lisas en oro 18K, acabado pulido. Precio por el par.', 7600, 'oro', 'diamante', 1),
   ('Anillo Rosa Esmeralda', 'Oro rosa 14K con esmeralda natural talla corazón. Pieza de edición limitada.', 8400, 'rosa', 'esmeralda', 0)]),
 ('Collares y Dijes', 'collar', 'Cadenas y dijes delicados para el día a día y para ocasiones especiales.', [
   ('Collar Luna con Diamante', 'Cadena de oro 18K de 45 cm con dije en forma de luna y diamante de 0.10 quilates.', 4900, 'oro', 'diamante', 0),
   ('Medallón Virgen de Guadalupe', 'Medalla ovalada de oro 14K con piedra al centro. Incluye cadena de 50 cm.', 3200, 'oro', 'rubi', 1),
   ('Collar Cruz Fe', 'Cruz de oro 14K con cadena fina de 45 cm. Un regalo de bautizo o primera comunión.', 2450, 'oro', 'diamante', 2),
   ('Collar de Perlas Marfil', 'Tres perlas cultivadas de agua dulce sobre cadena de plata 925 bañada en oro.', 2850, 'plata', 'perla', 3)]),
 ('Aretes y Broqueles', 'aretes', 'Desde broqueles discretos hasta aretes de gala para eventos.', [
   ('Broqueles Diamante 0.20 ct', 'Par de broqueles en oro 18K con diamantes de 0.10 quilates cada uno.', 5600, 'oro', 'diamante', 0),
   ('Aretes Gota Esmeralda', 'Aretes colgantes en oro 14K con esmeraldas talla gota. Ideales para eventos de gala.', 6900, 'oro', 'esmeralda', 1),
   ('Argollas Oro Clásicas', 'Argollas de oro 14K de 25 mm, ligeras y cómodas para uso diario.', 2100, 'oro', 'diamante', 2),
   ('Aretes Perlas Cascada', 'Aretes largos con perlas cultivadas en plata 925 bañada en oro rosa.', 1750, 'rosa', 'perla', 3)]),
 ('Pulseras y Esclavas', 'pulsera', 'Pulseras de oro y plata, esclavas grabables y tennis con piedras.', [
   ('Pulsera Tennis Diamantes', 'Pulsera en oro blanco 14K con 30 diamantes pequeños. Cierre de seguridad.', 14800, 'plata', 'diamante', 2),
   ('Esclava Oro 14K Grabable', 'Esclava rígida con espacio para grabar nombre o fecha (grabado incluido).', 3600, 'oro', 'diamante', 1),
   ('Pulsera Eslabones Oro 18K', 'Pulsera de eslabones ovalados de 19 cm en oro 18K, estilo clásico.', 5200, 'oro', 'diamante', 0),
   ('Pulsera Corazón Zafiro', 'Pulsera de plata 925 con dije de corazón y zafiro sintético azul.', 1450, 'plata', 'zafiro', 3)]),
 ('Relojes', 'reloj', 'Relojes de pulsera para dama y caballero, con garantía de funcionamiento.', [
   ('Reloj Clásico Dama Oro Rosa', 'Caja de 32 mm bañada en oro rosa, esfera nacarada y correa de cuero. Resistente al agua.', 3950, 'rosa', 'diamante', 2),
   ('Reloj Automático Caballero', 'Movimiento automático, caja de acero de 41 mm y cristal de zafiro. Garantía de 2 años.', 8900, 'plata', 'diamante', 0),
   ('Reloj Gold Executive', 'Caja de 40 mm bañada en oro 18K, esfera negra y brazalete de eslabones.', 6200, 'oro', 'diamante', 0),
   ('Reloj Cronógrafo Sport', 'Cronógrafo con caja de acero, bisel giratorio y correa de silicón. Resistente a 100 metros.', 4750, 'plata', 'diamante', 1)]),
 ('Joyería para Caballero', 'caballero', 'Cadenas, anillos de sello y gemelos con presencia y carácter.', [
   ('Cadena Cubana Oro 14K', 'Cadena de eslabones cubanos de 55 cm y 5 mm de ancho en oro 14K. Cierre de langosta.', 9400, 'oro', 'diamante', 0),
   ('Cadena con Medalla San Benito', 'Cadena de oro 14K de 60 cm con medalla de San Benito. Peso aproximado 12 g.', 5800, 'oro', 'diamante', 1),
   ('Anillo Sello Caballero', 'Anillo de sello en oro 14K con onix negro; se puede grabar iniciales.', 4300, 'oro', 'topacio', 2),
   ('Gemelos Zafiro Plata 925', 'Mancuernillas de plata 925 con zafiro sintético azul, presentadas en estuche.', 1650, 'plata', 'zafiro', 3)]),
]
TEL = '+502 3204 0756'; CORREO = 'info@servicom.gt'
DIR = '7a Avenida 14-32, Zona 1, Centro Comercial Joya Plaza, Local 28, Ciudad de Guatemala, Guatemala'
HORARIO = 'Lunes a sábado de 9:00 a 18:00 · Domingos de 10:00 a 14:00'
def q(v): return 'Q' + f'{v:,.2f}'

IMG = {'hero': hero_img(), 'about': about_img()}; LOGO = logo(); PROD = {}
seed = 20
for ci, (cn, ck, cd, items) in enumerate(CATS):
    for pi, (pn, pd, pr, metal, gm, st) in enumerate(items):
        seed += 1; PROD[(ci, pi)] = product_img(ck, metal, gm, st, seed)
CATIMG = {ci: PROD[(ci, 0)] for ci in range(len(CATS))}
def jpg(im): b = io.BytesIO(); im.save(b, 'JPEG', quality=88); b.seek(0); return ImageReader(b)
def png(im): b = io.BytesIO(); im.save(b, 'PNG'); b.seek(0); return ImageReader(b)

# ------------------------------------------------------------------ PDF
W, H = 960, 540
c = canvas.Canvas(OUT, pagesize=(W, H)); c.setTitle(NOMBRE + ' — Presentación de la tienda virtual'); c.setAuthor(NOMBRE)
def bg(col): c.setFillColor(col); c.rect(0, 0, W, H, fill=1, stroke=0)
def tag(txt): c.setFont('Sans-B', 10); c.setFillColor(GOLD); c.drawString(48, H - 40, txt.upper()); c.setStrokeColor(GOLD); c.setLineWidth(1.2); c.line(48, H - 48, 84, H - 48)
def foot(n, dark=False): c.setFont('Sans', 8.5); c.setFillColor('#B8AE98' if dark else '#8A8070'); c.drawString(48, 22, NOMBRE); c.drawRightString(W - 48, 22, str(n)); c.showPage()
def wrap(txt, font, size, maxw):
    lines, cur = [], ''
    for w_ in txt.split():
        t = (cur + ' ' + w_).strip()
        if pdfmetrics.stringWidth(t, font, size) <= maxw: cur = t
        else: lines.append(cur); cur = w_
    if cur: lines.append(cur)
    return lines
def para(txt, x, y, maxw, font='Sans', size=12, lead=17, col=INK):
    c.setFont(font, size); c.setFillColor(col)
    for ln in wrap(txt, font, size, maxw): c.drawString(x, y, ln); y -= lead
    return y
def title(txt, x, y, size=30, col=CHAR, maxw=420):
    c.setFont('Serif-B', size); c.setFillColor(col)
    for ln in wrap(txt, 'Serif-B', size, maxw): c.drawString(x, y, ln); y -= size * 1.18
    return y
n = 1
# 1 INICIO / portada
bg(INK); c.drawImage(jpg(IMG['hero']), W * .44, 0, W * .56, H, preserveAspectRatio=False)
c.setFillColor(INK); c.setFillAlpha(.28); c.rect(W * .44, 0, W * .56, H, fill=1, stroke=0); c.setFillAlpha(1)
c.drawImage(png(LOGO), 48, H - 214, 150, 150, mask='auto')
c.setFont('Sans-B', 11); c.setFillColor(GOLD); c.drawString(48, 262, 'INICIO · TIENDA VIRTUAL DE JOYERÍA FINA')
y = title(NOMBRE, 48, 226, 40, '#FFFFFF', 400)
c.setFont('Serif-I', 19); c.setFillColor(CHAMP); c.drawString(48, y - 8, FRASE)
para('Oro, plata, diamantes y piedras preciosas con certificado de autenticidad. Compre en línea con envíos a toda Guatemala.', 48, y - 44, 380, 'Sans', 12.5, 18, '#D9D0BC')
foot(n, True); n += 1
# 2 INICIO - por qué elegirnos
bg('#FFFFFF'); tag('Inicio · Por qué elegirnos'); y = title('Joyería fina, con la confianza de comprar en línea', 48, H - 92, 29, CHAR, 760)
y = para('En UNS Joyería seleccionamos cada pieza con criterio de joyero: metales con su ley comprobada, piedras de calidad y acabados impecables. Todo el catálogo está disponible en nuestra tienda virtual, listo para regalar.', 48, y - 2, 640, 'Sans', 13, 19)
for i, (t, d_) in enumerate([('Autenticidad certificada', 'Cada joya incluye certificado con el tipo de metal, quilataje y piedras.'), ('Garantía y servicio', 'Garantía contra defectos de fabricación y limpieza gratuita de por vida.'), ('Envíos a toda Guatemala', 'Entrega asegurada en estuche de regalo. Recoja gratis en tienda si lo prefiere.')]):
    x = 48 + i * 292; c.setFillColor(CREAM); c.roundRect(x, 150, 272, 160, 10, fill=1, stroke=0); c.setFillColor(GOLD); c.rect(x, 150 + 156, 272, 4, fill=1, stroke=0)
    c.setFont('Serif-B', 16); c.setFillColor(CHAR); c.drawString(x + 18, 150 + 122, t); para(d_, x + 18, 150 + 96, 238, 'Sans', 12, 17)
foot(n); n += 1
# 3 SOBRE NOSOTROS
bg('#FFFFFF'); tag('Sobre nosotros'); c.drawImage(jpg(IMG['about']), W - 48 - 340, 90, 340, 240, preserveAspectRatio=False)
y = title('Quiénes somos', 48, H - 92, 31, CHAR, 480)
y = para('UNS Joyería es una joyería familiar de la Ciudad de Guatemala con más de 20 años de experiencia. Nacimos en un pequeño taller y hoy ofrecemos una colección de anillos, collares, aretes, pulseras, relojes y joyería para caballero, ahora también en línea.', 48, y - 4, 440, 'Sans', 12.5, 18.5)
y = para('Nuestra misión: acompañar los momentos más importantes de la vida con joyas de calidad, a precios justos y con un trato cercano.', 48, y - 8, 440, 'Sans-B', 12.5, 18.5, CHAR)
y = para('Nuestra visión: ser la joyería de confianza de las familias guatemaltecas, en tienda y en línea.', 48, y - 8, 440, 'Sans', 12.5, 18.5)
foot(n); n += 1
# 4 valores / garantías
bg(CREAM); tag('Sobre nosotros · Nuestros compromisos'); title('Lo que usted recibe con cada compra', 48, H - 92, 30, CHAR, 760)
VAL = [('Piezas auténticas', 'Metales con su ley comprobada y piedras con certificado de autenticidad.'), ('Garantía de por vida', 'Reparamos defectos de fabricación y limpiamos su joya sin costo.'), ('Grabado personalizado', 'Grabamos nombres y fechas en esclavas, anillos y medallas sin costo extra.'), ('Estuche de regalo', 'Todas las compras se entregan en estuche y bolsa de regalo.')]
for i, (t, d_) in enumerate(VAL):
    x = 48 + (i % 2) * 436; yy = 400 - (i // 2) * 160
    c.setFillColor('#FFFFFF'); c.roundRect(x, yy - 112, 412, 126, 10, fill=1, stroke=0); c.setFillColor(GOLD); c.rect(x, yy - 112, 5, 126, fill=1, stroke=0)
    c.setFont('Serif-B', 18); c.setFillColor(CHAR); c.drawString(x + 24, yy - 28, t); para(d_, x + 24, yy - 54, 360, 'Sans', 12, 17.5)
foot(n); n += 1
# 5 CATEGORÍAS (resumen)
bg('#FFFFFF'); tag('Nuestros productos · Categorías'); title('6 categorías de joyería', 48, H - 92, 30, CHAR, 760)
for i, (cn, ck, cd, items) in enumerate(CATS):
    x = 48 + (i % 3) * 292; y0 = 230 - (i // 3) * 170
    c.setFillColor(CREAM); c.roundRect(x, y0, 272, 150, 10, fill=1, stroke=0); c.setFillColor(GOLD); c.rect(x, y0 + 146, 272, 4, fill=1, stroke=0)
    c.setFont('Serif-B', 17); c.setFillColor(CHAR); c.drawString(x + 16, y0 + 112, f'{i + 1}. {cn}'); para(cd, x + 16, y0 + 88, 240, 'Sans', 11.5, 16)
    c.setFont('Sans-B', 10.5); c.setFillColor('#8A6A1E'); c.drawString(x + 16, y0 + 14, f'{len(items)} productos · desde ' + q(min(p[2] for p in items)))
foot(n); n += 1
# 6-11 una hoja por categoría con sus productos
for ci, (cn, ck, cd, items) in enumerate(CATS):
    bg('#FFFFFF'); tag(f'Nuestros productos · Categoría {ci + 1} de 6'); y = title(cn, 48, H - 90, 27, CHAR, 560); para(cd, 48, y + 6, 800, 'Sans', 12, 16, MUTE)
    for pi, (pn, pd, pr, metal, gm, st) in enumerate(items):
        x = 48 + pi * 220; yy = 90
        c.setFillColor(CREAM); c.roundRect(x, yy, 204, 320, 8, fill=1, stroke=0)
        c.drawImage(jpg(PROD[(ci, pi)]), x, yy + 116, 204, 204, preserveAspectRatio=False)
        c.setFont('Serif-B', 13.5); c.setFillColor(CHAR); ly = yy + 98
        for ln in wrap(pn, 'Serif-B', 13.5, 180): c.drawString(x + 12, ly, ln); ly -= 16
        c.setFont('Sans-B', 14); c.setFillColor('#8A6A1E'); c.drawString(x + 12, ly - 3, q(pr)); ly -= 22
        para(pd, x + 12, ly, 182, 'Sans', 9.6, 12.4, INK)
    foot(n); n += 1
# 12 cómo comprar
bg(CREAM); tag('Cómo comprar'); title('Comprar en línea es fácil y seguro', 48, H - 92, 30, CHAR, 760)
PASOS = [('1. Elija su joya', 'Navegue por las categorías y agregue al carrito lo que le guste.'), ('2. Realice el pedido', 'Complete sus datos de entrega. Le confirmamos su pedido por correo y WhatsApp.'), ('3. Pague', 'Transferencia o depósito bancario, o pago contra entrega en la Ciudad de Guatemala.'), ('4. Reciba su joya', 'Entregamos en estuche de regalo en 24 a 72 horas, o la recoge sin costo en tienda.')]
for i, (t, d_) in enumerate(PASOS):
    x = 48 + (i % 2) * 436; yy = 390 - (i // 2) * 150
    c.setFillColor('#FFFFFF'); c.roundRect(x, yy - 100, 412, 114, 10, fill=1, stroke=0); c.setFillColor(GOLD); c.rect(x, yy - 100, 5, 114, fill=1, stroke=0)
    c.setFont('Serif-B', 17); c.setFillColor(CHAR); c.drawString(x + 24, yy - 26, t); para(d_, x + 24, yy - 50, 365, 'Sans', 12, 17)
foot(n); n += 1
# 13 FAQ
bg('#FFFFFF'); tag('Preguntas frecuentes'); title('Resolvemos sus dudas', 48, H - 92, 30, CHAR, 700)
FAQ = [('¿Las joyas tienen certificado de autenticidad?', 'Sí. Cada pieza incluye un certificado con el metal, el quilataje y las piedras que contiene.'),
       ('¿Hacen envíos a todo el país?', 'Sí. Enviamos a toda Guatemala con envío asegurado. En la Ciudad de Guatemala también puede pagar contra entrega.'),
       ('¿Puedo cambiar o devolver una joya?', 'Puede cambiarla dentro de los 15 días siguientes si está en perfecto estado y con su estuche.'),
       ('¿Ofrecen grabado personalizado?', 'Sí, el grabado de nombres y fechas es gratuito en esclavas, alianzas y medallas.'),
       ('¿Qué formas de pago aceptan?', 'Transferencia o depósito bancario y pago contra entrega en la capital. Pida también el pago con tarjeta por WhatsApp.')]
y = H - 148
for qn, a in FAQ:
    c.setFont('Serif-B', 14.5); c.setFillColor(CHAR); c.drawString(48, y, qn); y = para(a, 48, y - 18, 860, 'Sans', 11.8, 16.5) - 12
foot(n); n += 1
# 14 CONTACTO
bg(INK); tag('Contáctenos'); c.drawImage(png(LOGO), W - 48 - 170, H - 48 - 170, 170, 170, mask='auto'); title('Visítenos o escríbanos', 48, H - 92, 32, '#FFFFFF', 620)
y = H - 150
for k, v in [('Teléfono y WhatsApp', TEL + '   (el mismo número para llamadas y mensajes de WhatsApp)'), ('Correo electrónico', CORREO), ('Dirección de la tienda', DIR), ('Horario de atención', HORARIO), ('Ciudad / país', 'Ciudad de Guatemala, Guatemala')]:
    c.setFont('Sans-B', 10.5); c.setFillColor(GOLD); c.drawString(48, y, k.upper()); y = para(v, 48, y - 19, 640, 'Sans', 14, 20, '#FFFFFF') - 16
c.setFont('Serif-I', 15); c.setFillColor(CHAMP); c.drawString(48, 60, 'Atención personalizada para elegir el regalo perfecto.')
foot(n, True); n += 1
# 15 identidad
bg('#FFFFFF'); tag('Identidad de marca'); title('Colores y estilo de la marca', 48, H - 92, 28, CHAR, 700)
for i, (nm, hx, d_) in enumerate([('Negro carbón', INK, 'Color principal del logo'), ('Dorado', GOLD, 'Color de acento del logo'), ('Crema', CREAM, 'Fondos suaves')]):
    x = 48 + i * 290; c.setFillColor(hx); c.roundRect(x, 270, 260, 110, 10, fill=1, stroke=1 if hx == CREAM else 0)
    c.setFont('Serif-B', 16); c.setFillColor(CHAR); c.drawString(x, 246, nm); c.setFont('Sans', 12); c.setFillColor(INK); c.drawString(x, 228, hx.upper() + ' · ' + d_)
para('Estilo deseado para la tienda: elegante, de lujo, con fondo oscuro y detalles dorados que hagan resaltar las fotos de las joyas. Transmitir exclusividad, confianza y calidez. Usar el logo de la primera página en el encabezado.', 48, 180, 860, 'Sans', 13, 19)
foot(n); n += 1
c.save(); print('OK', OUT, n - 1, 'páginas,', sum(len(x[3]) for x in CATS), 'productos')
