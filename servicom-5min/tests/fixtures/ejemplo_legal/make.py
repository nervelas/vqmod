# Genera el PDF de ejemplo «Lex & Aurea — Servicios Legales»: una presentación de negocio completa, tal como la necesita «Tu web en 5 minutos».
# Uso: python3 -I make.py salida.pdf
import sys, math, random, io, os
from PIL import Image, ImageDraw, ImageFilter, ImageFont
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

OUT = sys.argv[1]
F = '/usr/share/fonts/truetype/liberation/'
for n, f in [('Serif', 'LiberationSerif-Regular.ttf'), ('Serif-B', 'LiberationSerif-Bold.ttf'), ('Serif-I', 'LiberationSerif-Italic.ttf'), ('Sans', 'LiberationSans-Regular.ttf'), ('Sans-B', 'LiberationSans-Bold.ttf')]:
    pdfmetrics.registerFont(TTFont(n, F + f))
NAVY, DEEP, GOLD, CREAM, INK = '#10233D', '#0B1A2E', '#C9A45C', '#F6F1E6', '#1B2A3D'
def rgb(h): h = h.lstrip('#'); return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))
S = 2  # supersampling

# ------------------------------------------------------------------ ilustraciones (PIL)
def canvas_img(w, h, c1, c2, angle=0):
    im = Image.new('RGB', (w * S, h * S)); px = im.load(); a, b = rgb(c1), rgb(c2)
    for y in range(h * S):
        for x in range(0, w * S):
            t = (y / (h * S)) * 0.8 + (x / (w * S)) * 0.2
            px[x, y] = tuple(int(a[i] * (1 - t) + b[i] * t) for i in range(3))
    return im
def bokeh(im, seed, n=26, col=GOLD):
    random.seed(seed); ov = Image.new('RGBA', im.size, (0, 0, 0, 0)); d = ImageDraw.Draw(ov)
    for _ in range(n):
        r = random.randint(40, 190) * S // 2; x = random.randint(0, im.size[0]); y = random.randint(0, im.size[1]); al = random.randint(10, 38)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rgb(col) + (al,))
    ov = ov.filter(ImageFilter.GaussianBlur(14 * S)); im.paste(ov, (0, 0), ov)
def fin(im, w, h): return im.resize((w, h), Image.LANCZOS).convert('RGB')
def gold(d, shape, *a, **k): getattr(d, shape)(*a, **k)

def icon_scales(d, cx, cy, s, col, lw):
    d.line([cx, cy - s, cx, cy + s * .9], fill=col, width=lw)                       # poste
    d.line([cx - s * .95, cy - s * .62, cx + s * .95, cy - s * .62], fill=col, width=lw)  # brazo
    d.ellipse([cx - lw * 1.2, cy - s - lw * 1.2, cx + lw * 1.2, cy - s + lw * 1.2], fill=col)
    d.rectangle([cx - s * .5, cy + s * .82, cx + s * .5, cy + s * .95], fill=col)    # base
    for sx in (-1, 1):
        x = cx + sx * s * .95
        d.line([x, cy - s * .62, x - s * .42, cy - s * .08], fill=col, width=max(2, lw // 2)); d.line([x, cy - s * .62, x + s * .42, cy - s * .08], fill=col, width=max(2, lw // 2))
        d.pieslice([x - s * .5, cy - s * .38, x + s * .5, cy + s * .22], 0, 180, fill=col)
def person(d, x, y, r, col, body=1.0):
    d.ellipse([x - r, y - r, x + r, y + r], fill=col); d.rounded_rectangle([x - r * 1.5, y + r * 1.15, x + r * 1.5, y + r * 1.15 + r * 2.6 * body], radius=r, fill=col)
def page_doc(d, x, y, w, h, col, line):
    d.rounded_rectangle([x, y, x + w, y + h], radius=w // 20, fill=col)
    for i in range(7): d.rounded_rectangle([x + w * .12, y + h * (.14 + i * .105), x + w * (.88 if i % 3 else .6), y + h * (.14 + i * .105) + h * .035], radius=h // 80, fill=line)
def columns(d, x0, x1, ytop, ybot, n, col):
    d.polygon([(x0 - 30 * S, ytop), ((x0 + x1) / 2, ytop - 90 * S), (x1 + 30 * S, ytop)], fill=col)
    d.rectangle([x0 - 40 * S, ytop, x1 + 40 * S, ytop + 22 * S], fill=col)
    step = (x1 - x0) / (n - 1)
    for i in range(n): d.rectangle([x0 + i * step - 16 * S, ytop + 30 * S, x0 + i * step + 16 * S, ybot], fill=col)
    d.rectangle([x0 - 60 * S, ybot, x1 + 60 * S, ybot + 26 * S], fill=col); d.rectangle([x0 - 80 * S, ybot + 26 * S, x1 + 80 * S, ybot + 50 * S], fill=col)

def img_hero(w=1800, h=1000):
    im = canvas_img(w, h, DEEP, NAVY); bokeh(im, 1); d = ImageDraw.Draw(im); g = rgb(GOLD) + (255,)
    columns(d, 1050 * S, 1650 * S, 330 * S, 800 * S, 6, rgb(GOLD) + (70,))
    icon_scales(d, 560 * S, 480 * S, 250 * S, rgb(GOLD), 12 * S)
    for i in range(0, 14): d.line([(0, (h - 70 + i * 4) * S), (w * S, (h - 70 + i * 4) * S)], fill=rgb(GOLD) + (14,), width=1)
    return fin(im, w, h)
def img_familia(w=1400, h=900):
    im = canvas_img(w, h, '#1E3A5F', '#8A6A3A'); bokeh(im, 2, 22, '#F6E7C1'); d = ImageDraw.Draw(im); c = rgb(CREAM)
    person(d, 520 * S, 330 * S, 62 * S, c); person(d, 760 * S, 330 * S, 62 * S, c); person(d, 640 * S, 470 * S, 44 * S, rgb('#E9D3A0'), .8)
    d.arc([380 * S, 180 * S, 900 * S, 700 * S], 200, 340, fill=rgb(GOLD), width=8 * S)
    icon_scales(d, 1130 * S, 450 * S, 150 * S, rgb(GOLD), 9 * S)
    return fin(im, w, h)
def img_civil(w=1400, h=900):
    im = canvas_img(w, h, DEEP, '#274B73'); bokeh(im, 3); d = ImageDraw.Draw(im)
    page_doc(d, 380 * S, 150 * S, 420 * S, 560 * S, rgb(CREAM), rgb('#B9A97F')); page_doc(d, 520 * S, 210 * S, 420 * S, 560 * S, rgb('#FFFFFF'), rgb('#C8D2DF'))
    d.line([760 * S, 660 * S, 1050 * S, 400 * S], fill=rgb(GOLD), width=18 * S); d.polygon([(1050 * S, 400 * S), (1080 * S, 360 * S), (1010 * S, 430 * S)], fill=rgb(GOLD))
    d.ellipse([930 * S, 600 * S, 1010 * S, 680 * S], outline=rgb(GOLD), width=8 * S); d.line([970 * S, 640 * S, 1010 * S, 700 * S], fill=rgb(GOLD), width=8 * S)
    return fin(im, w, h)
def img_mercantil(w=1400, h=900):
    im = canvas_img(w, h, '#0E2038', '#1F4E5F'); bokeh(im, 4, 30, '#7FD0C4'); d = ImageDraw.Draw(im); random.seed(5)
    x = 140
    for i in range(11):
        bh = random.randint(180, 620); bw = random.randint(60, 105)
        d.rectangle([x * S, (820 - bh) * S, (x + bw) * S, 820 * S], fill=rgb('#16324F') + (255,), outline=rgb(GOLD), width=2 * S)
        for wy in range(int(820 - bh + 24), 800, 44):
            for wx in range(x + 14, x + bw - 14, 26): d.rectangle([wx * S, wy * S, (wx + 10) * S, (wy + 18) * S], fill=rgb(GOLD) + (190,) if random.random() > .35 else rgb('#274B73'))
        x += bw + 28
    d.line([(100 * S, 820 * S), (1300 * S, 820 * S)], fill=rgb(GOLD), width=5 * S)
    for i, hh in enumerate([90, 150, 120, 230]): d.rectangle([(990 + i * 70) * S, (220 - hh // 2) * S, (1030 + i * 70) * S, 300 * S], fill=rgb(GOLD) + (230,))
    return fin(im, w, h)
def img_laboral(w=1400, h=900):
    im = canvas_img(w, h, '#14304F', '#6E4B2A'); bokeh(im, 6, 24, '#F0D9A0'); d = ImageDraw.Draw(im)
    for i, (x, col) in enumerate([(420, '#F6F1E6'), (640, '#E9D3A0'), (860, '#F6F1E6')]): person(d, x * S, (360 - (i % 2) * 40) * S, 58 * S, rgb(col))
    d.rounded_rectangle([360 * S, 640 * S, 940 * S, 720 * S], radius=40 * S, fill=rgb(GOLD))
    d.rounded_rectangle([1010 * S, 430 * S, 1250 * S, 620 * S], radius=20 * S, fill=rgb(NAVY), outline=rgb(GOLD), width=6 * S); d.rounded_rectangle([1090 * S, 390 * S, 1170 * S, 440 * S], radius=14 * S, outline=rgb(GOLD), width=6 * S); d.line([1010 * S, 520 * S, 1250 * S, 520 * S], fill=rgb(GOLD), width=5 * S)
    return fin(im, w, h)
def img_notarial(w=1400, h=900):
    im = canvas_img(w, h, '#1B1B2F', '#3B2F1E'); bokeh(im, 7, 22); d = ImageDraw.Draw(im)
    page_doc(d, 300 * S, 170 * S, 520 * S, 600 * S, rgb(CREAM), rgb('#B9A97F'))
    cx, cy = 930 * S, 560 * S
    for k in range(24): a = k * math.pi / 12; d.ellipse([cx + math.cos(a) * 150 * S - 24 * S, cy + math.sin(a) * 150 * S - 24 * S, cx + math.cos(a) * 150 * S + 24 * S, cy + math.sin(a) * 150 * S + 24 * S], fill=rgb(GOLD))
    d.ellipse([cx - 135 * S, cy - 135 * S, cx + 135 * S, cy + 135 * S], fill=rgb('#A9843F')); d.ellipse([cx - 95 * S, cy - 95 * S, cx + 95 * S, cy + 95 * S], outline=rgb('#F3DFA8'), width=6 * S)
    icon_scales(d, cx, cy + 6 * S, 62 * S, rgb('#F3DFA8'), 5 * S)
    d.line([560 * S, 660 * S, 760 * S, 540 * S], fill=rgb(GOLD), width=10 * S)
    return fin(im, w, h)
def img_nosotros(w=1400, h=900):
    im = canvas_img(w, h, NAVY, DEEP); bokeh(im, 8, 28); d = ImageDraw.Draw(im)
    for i, (x, y, r) in enumerate([(430, 330, 70), (700, 300, 78), (970, 330, 70)]): person(d, x * S, y * S, r * S, rgb(CREAM if i != 1 else '#E9D3A0'))
    d.rounded_rectangle([250 * S, 700 * S, 1150 * S, 770 * S], radius=35 * S, fill=rgb(GOLD) + (255,))
    return fin(im, w, h)
def logo(w=900):
    im = Image.new('RGBA', (w * S, w * S), (0, 0, 0, 0)); d = ImageDraw.Draw(im); n, g = rgb(NAVY) + (255,), rgb(GOLD) + (255,); c = w * S // 2
    d.rounded_rectangle([0, 0, w * S - 1, w * S - 1], radius=120 * S, fill=rgb(CREAM) + (255,))
    pts = [(c, 70 * S), (w * S - 150 * S, 160 * S), (w * S - 150 * S, 470 * S), (c, 700 * S), (150 * S, 470 * S), (150 * S, 160 * S)]
    d.polygon(pts, fill=n, outline=g); d.line(pts + [pts[0]], fill=g, width=14 * S, joint='curve')
    inner = [(c, 125 * S), (w * S - 200 * S, 195 * S), (w * S - 200 * S, 455 * S), (c, 640 * S), (200 * S, 455 * S), (200 * S, 195 * S)]; d.line(inner + [inner[0]], fill=rgb(GOLD) + (150,), width=4 * S)
    icon_scales(d, c, 345 * S, 150 * S, g, 11 * S)
    ft = ImageFont.truetype(F + 'LiberationSerif-Bold.ttf', 96 * S); t = 'LEX & AUREA'; tw = d.textlength(t, font=ft); d.text((c - tw / 2, 735 * S), t, font=ft, fill=n)
    fs = ImageFont.truetype(F + 'LiberationSerif-Regular.ttf', 44 * S); t2 = 'SERVICIOS LEGALES'; tw2 = d.textlength(t2, font=fs); d.text((c - tw2 / 2, 835 * S), t2, font=fs, fill=g)
    return im.resize((w, w), Image.LANCZOS)

IM = {k: v() for k, v in [('hero', img_hero), ('familia', img_familia), ('civil', img_civil), ('mercantil', img_mercantil), ('laboral', img_laboral), ('notarial', img_notarial), ('nosotros', img_nosotros)]}
LOGO = logo()
def jpg(im): b = io.BytesIO(); im.save(b, 'JPEG', quality=90); b.seek(0); return ImageReader(b)
def png(im): b = io.BytesIO(); im.save(b, 'PNG'); b.seek(0); return ImageReader(b)

# ------------------------------------------------------------------ contenido
NOMBRE = 'Lex & Aurea Servicios Legales'
FRASE = 'Su tranquilidad jurídica, nuestra responsabilidad'
RUBRO = 'Servicios legales (bufete de abogados y notarios)'
SERVICIOS = [
 ('Derecho de Familia', 'familia', 'Acompañamos a las familias en los momentos más sensibles con discreción, empatía y estrategia. Buscamos acuerdos justos y, cuando es necesario, defendemos sus derechos ante los tribunales.',
  ['Divorcios voluntarios y contenciosos', 'Pensión alimenticia: fijación, aumento y cobro', 'Guarda y custodia, régimen de visitas', 'Adopciones y reconocimiento de paternidad', 'Sucesiones y declaratoria de herederos'], 'Parejas y familias que necesitan resolver su situación con claridad y respeto.'),
 ('Derecho Civil y Contratos', 'civil', 'Redactamos, revisamos y negociamos contratos para que sus acuerdos estén protegidos desde el primer día. Le ayudamos a reclamar lo que le corresponde y a evitar conflictos futuros.',
  ['Contratos de compraventa, arrendamiento y servicios', 'Cobro de deudas y juicios ejecutivos', 'Reclamación de daños y perjuicios', 'Saneamiento y regularización de inmuebles', 'Conciliación y mediación de conflictos'], 'Personas y empresas que quieren acuerdos seguros y una defensa firme de sus intereses.'),
 ('Derecho Mercantil y Corporativo', 'mercantil', 'Acompañamos a emprendedores y empresas en cada etapa: desde la constitución de la sociedad hasta su crecimiento, reestructura y cumplimiento normativo.',
  ['Constitución de sociedades y patentes de comercio', 'Asesoría legal permanente para empresas', 'Contratos comerciales y de distribución', 'Registro de marcas y propiedad intelectual', 'Fusiones, reestructuras y disoluciones'], 'Emprendedores, pequeñas y medianas empresas y negocios en expansión.'),
 ('Derecho Laboral', 'laboral', 'Defendemos los derechos de trabajadores y orientamos a empleadores para cumplir la ley y prevenir conflictos laborales con asesoría clara y oportuna.',
  ['Despidos y reclamación de prestaciones', 'Contratos de trabajo y reglamentos internos', 'Conciliaciones ante la Inspección General de Trabajo', 'Juicios ordinarios laborales', 'Auditoría laboral preventiva para empresas'], 'Trabajadores que necesitan defender sus derechos y empresas que desean estar al día.'),
 ('Asesoría Notarial y Registros', 'notarial', 'Como notarios, damos fe de sus actos y trámites con rapidez y seguridad jurídica. Nos encargamos de los trámites ante registros y entidades públicas para que usted no pierda tiempo.',
  ['Escrituras públicas, poderes y testamentos', 'Actas notariales y legalización de firmas', 'Inscripciones en el Registro de la Propiedad y Mercantil', 'Traspasos de vehículos e inmuebles', 'Autorizaciones de viaje de menores'], 'Quienes necesitan trámites notariales y registrales sin complicaciones.'),
]
VALORES = [('Integridad', 'Actuamos con honestidad y transparencia en cada caso, sin promesas que no podamos cumplir.'),
           ('Compromiso', 'Cada cliente recibe atención personalizada y seguimiento constante de su caso.'),
           ('Excelencia', 'Nos mantenemos actualizados para ofrecer asesoría precisa y de alta calidad.'),
           ('Confidencialidad', 'Toda la información que usted comparte se maneja con absoluta reserva.')]
FAQ = [('¿Cuánto cuesta la primera consulta?', 'La primera consulta de orientación es sin compromiso. Le explicamos su caso y le presentamos las opciones y honorarios antes de comenzar.'),
       ('¿Atienden casos fuera de la ciudad?', 'Sí. Atendemos clientes en todo el país y podemos realizar reuniones por videollamada o por teléfono.'),
       ('¿Cuánto tarda un proceso legal?', 'Depende del tipo de caso y del tribunal. En la primera reunión le damos un estimado realista de tiempos y etapas.'),
       ('¿Mi información es confidencial?', 'Absolutamente. Todo lo que comparta con nosotros está protegido por el secreto profesional.')]
TEL = '+502 3204 0756'; CORREO = 'info@servicom.gt'
DIR = '6a Avenida 12-34, Zona 9, Edificio Plaza Legal, Oficina 5-B, Ciudad de Guatemala, Guatemala'
HORARIO = 'Lunes a viernes de 8:00 a 17:00 · Sábados de 9:00 a 12:00 (con cita)'

# ------------------------------------------------------------------ PDF (diapositivas 16:9)
W, H = 960, 540
c = canvas.Canvas(OUT, pagesize=(W, H)); c.setTitle(NOMBRE + ' — Presentación del negocio'); c.setAuthor(NOMBRE)
def bg(col): c.setFillColor(col); c.rect(0, 0, W, H, fill=1, stroke=0)
def tag(txt, dark=False):
    c.setFont('Sans-B', 10); c.setFillColor(GOLD); c.drawString(48, H - 40, txt.upper()); c.setStrokeColor(GOLD); c.setLineWidth(1.2); c.line(48, H - 48, 48 + 36, H - 48)
def foot(n, dark=False):
    c.setFont('Sans', 8.5); c.setFillColor('#8A94A3' if not dark else '#9FB0C6'); c.drawString(48, 22, NOMBRE); c.drawRightString(W - 48, 22, str(n)); c.showPage()
def wrap(txt, font, size, maxw):
    words, lines, cur = txt.split(), [], ''
    for w_ in words:
        t = (cur + ' ' + w_).strip()
        if pdfmetrics.stringWidth(t, font, size) <= maxw: cur = t
        else: lines.append(cur); cur = w_
    if cur: lines.append(cur)
    return lines
def para(txt, x, y, maxw, font='Sans', size=12, lead=17, col=INK):
    c.setFont(font, size); c.setFillColor(col)
    for ln in wrap(txt, font, size, maxw): c.drawString(x, y, ln); y -= lead
    return y
def title(txt, x, y, size=30, col=NAVY, maxw=420):
    c.setFont('Serif-B', size); c.setFillColor(col)
    for ln in wrap(txt, 'Serif-B', size, maxw): c.drawString(x, y, ln); y -= size * 1.18
    return y
n = 1
# 1 PORTADA / INICIO
bg(DEEP); c.drawImage(jpg(IM['hero']), W * .46, 0, W * .54, H, preserveAspectRatio=False)
c.setFillColor(DEEP); c.setFillAlpha(.35); c.rect(W * .46, 0, W * .54, H, fill=1, stroke=0); c.setFillAlpha(1)
c.drawImage(png(LOGO), 48, H - 214, 150, 150, mask='auto')
c.setFont('Sans-B', 11); c.setFillColor(GOLD); c.drawString(48, 262, 'INICIO · PRESENTACIÓN DEL NEGOCIO')
y = title(NOMBRE, 48, 226, 36, '#FFFFFF', 400)
c.setFont('Serif-I', 18); c.setFillColor('#E9D8AE'); c.drawString(48, y - 8, FRASE)
para('Despacho jurídico en Ciudad de Guatemala especializado en familia, contratos, empresas, trabajo y trámites notariales.', 48, y - 44, 390, 'Sans', 12.5, 18, '#C9D3E2')
foot(n, True); n += 1
# 2 INICIO - propuesta de valor
bg('#FFFFFF'); tag('Inicio · Por qué elegirnos')
y = title('Asesoría legal clara, cercana y confiable', 48, H - 92, 31, NAVY, 560)
y = para('En Lex & Aurea Servicios Legales acompañamos a personas, familias y empresas con soluciones jurídicas prácticas. Le explicamos su caso en lenguaje sencillo, le damos opciones claras y trabajamos con usted hasta lograr el mejor resultado posible.', 48, y - 6, 560, 'Sans', 13, 19)
for i, (t, d_) in enumerate([('Atención personalizada', 'Cada caso es único. Hablamos directamente con usted y le damos seguimiento constante.'), ('Experiencia comprobada', 'Más de 15 años de ejercicio profesional resolviendo casos civiles, mercantiles, familiares y laborales.'), ('Honorarios transparentes', 'Le presentamos costos y etapas por escrito antes de comenzar, sin sorpresas.')]):
    x = 48 + i * 292; c.setFillColor(CREAM); c.roundRect(x, 120, 272, 160, 10, fill=1, stroke=0); c.setFillColor(GOLD); c.rect(x, 120 + 160 - 4, 272, 4, fill=1, stroke=0)
    c.setFont('Serif-B', 16); c.setFillColor(NAVY); c.drawString(x + 18, 120 + 160 - 38, t); para(d_, x + 18, 120 + 160 - 64, 238, 'Sans', 12, 17)
foot(n); n += 1
# 3 SOBRE NOSOTROS
bg('#FFFFFF'); tag('Sobre nosotros'); c.drawImage(jpg(IM['nosotros']), W - 48 - 340, 70, 340, 240, preserveAspectRatio=False)
y = title('Quiénes somos', 48, H - 92, 31, NAVY, 480)
y = para('Somos un despacho de abogados y notarios con sede en la Ciudad de Guatemala, fundado hace más de 15 años con una idea simple: que acudir a un abogado no sea intimidante. Hoy atendemos a familias, emprendedores y empresas en todo el país con un equipo comprometido y actualizado.', 48, y - 4, 440, 'Sans', 12.5, 18.5)
y = para('Nuestra misión: brindar asesoría jurídica de excelencia, accesible y humana, que proteja los derechos e intereses de nuestros clientes.', 48, y - 8, 440, 'Sans-B', 12.5, 18.5, NAVY)
y = para('Nuestra visión: ser el despacho de confianza al que las familias y empresas de Guatemala recomiendan por su integridad y resultados.', 48, y - 8, 440, 'Sans', 12.5, 18.5)
c.setFont('Serif-B', 17); c.setFillColor(NAVY); c.drawString(W - 48 - 340, 52 + 22, '')
foot(n); n += 1
# 4 SOBRE NOSOTROS - valores
bg(CREAM); tag('Sobre nosotros · Nuestros valores')
title('Lo que nos define', 48, H - 92, 31, NAVY, 600)
for i, (t, d_) in enumerate(VALORES):
    x = 48 + (i % 2) * 436; yy = 400 - (i // 2) * 160
    c.setFillColor('#FFFFFF'); c.roundRect(x, yy - 112, 412, 126, 10, fill=1, stroke=0); c.setFillColor(GOLD); c.rect(x, yy - 112, 5, 126, fill=1, stroke=0)
    c.setFont('Serif-B', 18); c.setFillColor(NAVY); c.drawString(x + 24, yy - 28, t); para(d_, x + 24, yy - 54, 360, 'Sans', 12, 17.5)
foot(n); n += 1
# 5-9 SERVICIOS
for i, (nom, key, desc, items, ideal) in enumerate(SERVICIOS):
    bg('#FFFFFF'); tag(f'Nuestros servicios · {i + 1} de 5'); c.drawImage(jpg(IM[key]), W - 48 - 360, 100, 360, 270, preserveAspectRatio=False)
    c.setStrokeColor(GOLD); c.setLineWidth(1.2); c.rect(W - 48 - 360 + 8, 100 - 8, 360, 270, fill=0, stroke=1)
    y = title(nom, 48, H - 92, 30, NAVY, 470)
    y = para(desc, 48, y - 4, 470, 'Sans', 12.2, 18)
    c.setFont('Sans-B', 11); c.setFillColor(GOLD); c.drawString(48, y - 10, 'INCLUYE'); y -= 28
    for it in items:
        c.setFillColor(GOLD); c.circle(54, y + 4, 2.6, fill=1, stroke=0); y = para(it, 66, y, 450, 'Sans', 12, 17)
    para('Ideal para: ' + ideal, 48, 62, 600, 'Sans-B', 11, 15, NAVY) if False else None
    c.setFont('Sans-B', 11.5); c.setFillColor(NAVY); c.drawString(48, 62, 'IDEAL PARA'); para(ideal, 48, 46, 800, 'Sans', 12, 16)
    foot(n); n += 1
# 10 FAQ
bg(CREAM); tag('Preguntas frecuentes'); title('Resolvemos sus dudas', 48, H - 92, 31, NAVY, 600)
y = H - 150
for q, a in FAQ:
    c.setFont('Serif-B', 15); c.setFillColor(NAVY); c.drawString(48, y, q); y = para(a, 48, y - 19, 860, 'Sans', 12, 17.5) - 14
foot(n); n += 1
# 11 CONTACTO
bg(DEEP); tag('Contáctenos'); c.drawImage(png(LOGO), W - 48 - 170, H - 48 - 170, 170, 170, mask='auto')
title('Hablemos de su caso', 48, H - 92, 32, '#FFFFFF', 620)
rows = [('Teléfono y WhatsApp', TEL + '   (el mismo número para llamadas y mensajes de WhatsApp)'), ('Correo electrónico', CORREO), ('Dirección', DIR), ('Horario de atención', HORARIO), ('Ciudad / país', 'Ciudad de Guatemala, Guatemala')]
y = H - 150
for k, v in rows:
    c.setFont('Sans-B', 10.5); c.setFillColor(GOLD); c.drawString(48, y, k.upper()); y = para(v, 48, y - 19, 640, 'Sans', 14, 20, '#FFFFFF') - 16
c.setFont('Serif-I', 15); c.setFillColor('#E9D8AE'); c.drawString(48, 60, 'Primera consulta de orientación sin compromiso.')
foot(n, True); n += 1
# 12 IDENTIDAD
bg('#FFFFFF'); tag('Identidad de marca'); title('Colores y estilo de la marca', 48, H - 92, 28, NAVY, 700)
for i, (nm, hx, d_) in enumerate([('Azul marino', NAVY, 'Color principal del logo'), ('Dorado', GOLD, 'Color de acento del logo'), ('Crema', CREAM, 'Fondos suaves')]):
    x = 48 + i * 290; c.setFillColor(hx); c.roundRect(x, 270, 260, 110, 10, fill=1, stroke=1 if hx == CREAM else 0)
    c.setFont('Serif-B', 16); c.setFillColor(NAVY); c.drawString(x, 246, nm); c.setFont('Sans', 12); c.setFillColor(INK); c.drawString(x, 228, hx.upper() + ' · ' + d_)
para('Estilo deseado para la web: elegante, sobrio y de lujo, con predominio del azul marino y detalles dorados. Transmitir confianza, seriedad y cercanía. Usar el logo de la primera página en el encabezado.', 48, 180, 860, 'Sans', 13, 19)
foot(n); n += 1
c.save()
print('OK', OUT, n - 1, 'páginas')
