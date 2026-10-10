# Genera empresa.pdf: una presentación de negocio "real" (logo + 3 fotos + textos + contactos) para probar el modo «solo archivo».
import random, io, sys, os
from PIL import Image, ImageDraw, ImageFilter
from reportlab.lib.pagesizes import landscape, A4
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
out = sys.argv[1]
random.seed(7)
def photo(w, h, c1, c2):
    im = Image.new('RGB', (w, h)); d = ImageDraw.Draw(im)
    for y in range(h):
        t = y / h; d.line([(0, y), (w, y)], fill=tuple(int(c1[i] * (1 - t) + c2[i] * t) for i in range(3)))
    for _ in range(40):
        x, y = random.randint(0, w), random.randint(0, h); r = random.randint(20, 120)
        d.ellipse([x - r, y - r, x + r, y + r], fill=tuple(random.randint(30, 230) for _ in range(3)))
    return im.filter(ImageFilter.GaussianBlur(6))
logo = Image.new('RGB', (420, 420), (255, 255, 255)); d = ImageDraw.Draw(logo)
d.ellipse([40, 40, 380, 380], fill=(11, 61, 145)); d.polygon([(210, 90), (330, 300), (90, 300)], fill=(245, 166, 35))
W, H = landscape(A4)
c = canvas.Canvas(out, pagesize=(W, H))
c.setFont('Helvetica-Bold', 34); c.drawImage(ImageReader(logo), 50, H - 190, 140, 140); c.drawString(210, H - 120, 'Grupo Aurora Ingenieria'); c.setFont('Helvetica', 16)
c.drawString(210, H - 150, 'Soluciones electricas y de telecomunicaciones en Guatemala'); c.drawImage(ImageReader(photo(1400, 800, (20, 60, 120), (240, 170, 40))), 50, 60, W - 100, 300); c.showPage()
c.setFont('Helvetica-Bold', 26); c.drawString(50, H - 70, 'Quienes somos'); c.setFont('Helvetica', 14)
for i, l in enumerate(['Somos una empresa guatemalteca con 15 anios de experiencia en instalaciones electricas industriales,', 'redes de datos y energia solar para comercios, industrias y residencias.', 'Contamos con personal certificado y atendemos todo el pais.']):
    c.drawString(50, H - 110 - i * 22, l)
c.drawImage(ImageReader(photo(1200, 800, (30, 100, 60), (200, 220, 90))), 50, 60, 360, 240); c.drawImage(ImageReader(photo(1200, 800, (120, 40, 40), (250, 200, 160))), 430, 60, 360, 240); c.showPage()
c.setFont('Helvetica-Bold', 26); c.drawString(50, H - 70, 'Servicios'); c.setFont('Helvetica', 16)
for i, s in enumerate(['Instalaciones electricas industriales', 'Redes de datos y cableado estructurado', 'Energia solar fotovoltaica', 'Mantenimiento preventivo y correctivo']):
    c.drawString(60, H - 120 - i * 30, '- ' + s)
c.setFont('Helvetica-Bold', 20); c.drawString(50, 150, 'Contacto'); c.setFont('Helvetica', 15)
c.drawString(50, 120, 'Tel: +502 2345 6789   |   info@grupoaurora.example   |   Zona 10, Ciudad de Guatemala'); c.showPage()
c.save()
