# INVENTARIO — gruasexpresstowing.gt (sitio original)

Levantado el 15-oct-2026 desde el sitio en producción. Solo se extrajeron **textos e imágenes**; no se copió ningún código de WordPress.

## 0. Hallazgos importantes del rastreo (infección)

| # | Hallazgo | Evidencia | Qué se hizo |
|---|----------|-----------|-------------|
| 1 | **`/contactenos/` y `/sobre-nosotros/` están secuestradas** con páginas de spam de apuestas en indonesio («LUXURY12 … Slot Gacor»), con recursos de `starterlab.xyz`, `luxury12cihuy.pages.dev`, `ayoklik.me`, `mothercare.co.id` y `teepublic.com`. | Se sirven con cualquier User-Agent; 752 KB y 461 KB. | **No se copió nada.** El contenido real se recuperó de la base de datos de WordPress (API `?rest_route=/wp/v2/pages`), no del HTML servido. |
| 2 | **`/sitemap.xml` es un archivo estático inyectado** que lista solo esas dos URLs (`lastmod 2026-09-27`). | `sitemap.xml` 200; `/wp-sitemap.xml` 404. | Se genera uno nuevo y limpio con las 4 URLs. |
| 3 | **`/servicio-de-gruas-24-7/` da 404** aunque la página existe en la base de datos y está en el menú. | HTTP 404 en la URL; REST la lista como publicada. | Se reconstruyó con su contenido real y se conserva la URL. |
| 4 | `robots.txt` responde 404 (no existe). | HTTP 404. | Se crea uno nuevo. |
| 5 | La página de inicio sí está limpia; stack: WordPress + Elementor 4.3.5 + OceanWP + Happy Elementor Addons + Ocean Extra + Forminator. | HTML de inicio. | Reemplazado por plataforma propia. |

> Recomendación urgente: cambiar **todas** las contraseñas (cPanel, FTP, correo, base de datos y WordPress) y borrar el WordPress antiguo del hosting antes de subir el sitio nuevo; si no, la puerta de entrada de la infección sigue abierta.

## 1. Estructura y URLs (se conservan idénticas)

| Página | URL | Título WP | `<title>` original | Meta description original |
|--------|-----|-----------|--------------------|---------------------------|
| Inicio | `/` | INICIO | `Grúas Express Towing Guatemala` | (ninguna) |
| Sobre nosotros | `/sobre-nosotros/` | SOBRE NOSOTROS | no recuperable (HTML infectado) | (ninguna) |
| Servicio de grúas 24/7 | `/servicio-de-gruas-24-7/` | SERVICIO DE GRÚAS 24/7 | no recuperable (404) | (ninguna) |
| Contáctenos | `/contactenos/` | CONTÁCTENOS | no recuperable (HTML infectado) | (ninguna) |

Canonical original de inicio: `https://gruasexpresstowing.gt/`. No hay entradas (blog), categorías ni otras páginas. Redirecciones 301 añadidas: `/?page_id=8|10|12|14`, `/inicio`, imágenes antiguas de `wp-content/uploads` → imágenes nuevas, `www` → sin `www`, HTTP → HTTPS.

**SEO nuevo (igual o mejorado):** títulos «Sobre nosotros - Grúas Express Towing Guatemala», «Servicio de grúas 24/7 - …», «Contáctenos - …»; meta descriptions armadas **solo con frases del propio sitio**; Open Graph, Twitter Card, canonical y JSON-LD `LocalBusiness` (nombre, URL, teléfono +50235567904, correo, país GT, 24/7, «Desde Q250»).

## 2. Elementos comunes

| Elemento | Valor exacto |
|----------|--------------|
| Barra superior | `Teléfono: 3556-7904 \| Email: info@gruasexpresstowing.gt` (teléfono → `tel:35567904`) |
| Logo | **Reemplazado a pedido del cliente** por el logo nuevo (WebP transparente 2000×667, «Grúas Express Towing Guatemala») en cabecera, menú móvil, pie y favicon → enlaza a `/`. Original anterior: `Logo.png` 1005×223 y `LM3.png` 251×56 (ya no se usan) |
| Favicon | el original **no tiene**; se genera del emblema del logo |
| Menú | INICIO `/` · SOBRE NOSOTROS `/sobre-nosotros/` · SERVICIO DE GRÚAS 24/7 `/servicio-de-gruas-24-7/` · CONTÁCTENOS `/contactenos/` |
| Pie de página | `Copyright 2026 - Grúas Express Towing Guatemala` + botón «subir» |
| Dirección, horarios, redes sociales | **no existen en el original** (campos opcionales vacíos en el panel) |

## 3. Portada (hero) de cada página

Texto: `Grúas Express` / `Towing Guatemala` / `Servicio de Grúas 24/7` / botón **`Llámenos: 3556-7904`** → `tel:35567904`.

| Página | Imagen | Notas |
|--------|--------|-------|
| Inicio | `2026/02/1.jpeg` (1312×736) | |
| Sobre nosotros | `2026/02/sn4.png` (1312×735) | la imagen trae «Sobre Nosotros» incorporado |
| Servicio | `2026/02/SDG.png` (1536×1024) | título vacío; el subtítulo dice `Grúas Express Towing` (se conserva así) |
| Contáctenos | `2026/02/ctc2.png` (1536×1024) | la imagen trae «Contáctenos» incorporado |

## 4. Textos exactos por página

**Inicio**
1. **Bienvenidos a Express Towing** / Somos una empresa de servicio de grúas guatemalteca. / Contamos con equipo de remolque terrestre, pesado y liviano las 24 horas los 7 días de la semana.
2. Dos videos (`VID-20240927-WA0004.mp4`, luego `VID-20240927-WA0001.mp4`).
3. **Servicio de grúas las 24 horas** / **a toda la República de Guatemala desde Q250**
4. Tarjetas: **LLÁMENOS** «Teléfono: 3556-7904» · **ESCRÍBANOS** «Whatsapp: 3556-7904» · **ENVÍE UN EMAIL** «info@gruasexpresstowing.gt»
5. **Te garantizamos un excelente servicio.**
6. Galería de 22 imágenes (orden original, ver §6).

**Sobre nosotros**
1. Somos una empresa guatemalteca que brinda servicio de grúas. / Contamos con equipo de remolque, terrestre, pesado y liviano las 24 horas los 7 días de la semana.
2. **MISIÓN** — Proporcionar un servicio de alta calidad en el transporte y manejo de vehículos livianos y pesados así mismo todo tipo de maquinaria industrial dentro y fuera del país contamos con personal capacitado y equipo acorde al trabajo que se nos presenta.
3. **VISIÓN** — Ser una empresa reconocida a nivel nacional por el servicio de remolque de todo tipo de vehículos y maquinaria y que esto motive a los clientes a darnos la oportunidad de servirles.
4. Tarjetas de contacto (igual que Inicio).

**Servicio de grúas 24/7**
1. Servicio de grúas en Guatemala, urgentes, eficientes y a un precio justo. / Ofrecemos el servicio de guas en toda Guatemala, adaptados a sus necesidades y a un precio competitivo. / Sabemos la importancia de brindar seguridad y calidad en cada unos de nuestros servicios, es por eso que nos adaptamos a las necesidades de nuestros clientes. / **HACEMOS TODO LO POSIBLE PARA AUXILIARLO** / Además, ofrecemos diversos servicios a toda la República Mexicana las 24 horas, todos los días, para cubrir las necesidades de nuestros clientes.
2. Galería de 16 imágenes (§6). 3. Tarjetas de contacto.

**Contáctenos**
1. Tarjetas de contacto. 2. «Envíenos un mensaje» + formulario (§7).

> Se conservaron tal cual (aunque parecen errores del original): «guas», «República Mexicana», el título vacío de la portada de Servicio, y el enlace de correo `mailto:info@agroinco.com` (el texto visible es `info@gruasexpresstowing.gt`). Todo es editable desde el panel.

## 5. Botones y enlaces (texto → destino)

| Texto | Destino original | Destino nuevo |
|-------|------------------|---------------|
| Llámenos: 3556-7904 (portada) | `tel:35567904` | igual |
| (tarjeta) LLÁMENOS | `tel:35567904` (`_blank`) | `tel:35567904` |
| (tarjeta) ESCRÍBANOS | `https://api.whatsapp.com/send?phone=50235567904` (`_blank`) | igual (`_blank`, `noopener`) |
| (tarjeta) ENVÍE UN EMAIL | `mailto:info@agroinco.com` (`_blank`) | `mailto:info@agroinco.com` |
| Barra superior teléfono / correo | `tel:35567904` / (texto) | `tel:35567904` / `mailto:info@agroinco.com` |
| Menú (4) | URLs de §2 | igual (relativas) |
| Logo | `/` | `/` |
| Subir arriba (pie) | `#` | botón «subir» |
| Imágenes de galería | `#` (lightbox Magnific) | lightbox propio |
| **Añadidos a pedido:** botón flotante WhatsApp y de llamada | no existían | mismos destinos que las tarjetas |

## 6. Imágenes y videos

Todas descargadas, validadas (firma + MIME), recodificadas con GD a WEBP (≤1920 px + miniatura 640 px) y servidas desde `/uploads/`. Se añadió texto `alt` descriptivo (el original no tenía).

| Original (`wp-content/uploads/…`) | Nuevo | Dónde |
|---|---|---|
| 2022/10/SG-1 … SG-16.jpeg (16) | `sg-1.webp … sg-16.webp` | Inicio (todas menos SG-14) y Servicio (todas) |
| 2022/10/WhatsApp-Image-2022-10-04-at-19.24.40.jpeg | `wa-2022.webp` | Inicio |
| 2024/09/IMG-20240927-WA0002 … WA0007.jpg (6) | `izaje-2 … izaje-7.webp` | Inicio |
| 2026/02/1.jpeg · sn4.png · SDG.png · ctc2.png | `hero-inicio/sobre/servicio/contacto.webp` | portadas |
| (logo nuevo entregado por el cliente) | `logo.webp` | cabecera, pie, favicon, datos para Google |
| 2024/09/VID-20240927-WA0004.mp4 · WA0001.mp4 | `media/vid-1.mp4` · `vid-2.mp4` (sin metadatos, `faststart`) + póster | Inicio |

Orden de la galería de Inicio (22): WA0002, WA0007, WA0006, WA0005, WA0004, WA0003, SG-11, SG-10, SG-4, SG-5, SG-6, SG-7, SG-8, SG-9, SG-3, SG-2, SG-1, SG-16, SG-15, WhatsApp-Image-2022-10-04, SG-12, SG-13.
Orden de la galería de Servicio (16): SG-12, 13, 14, 15, 16, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11.
No migradas (existían en la biblioteca pero ninguna página las usa): `gr.jpg`, `qs.jpg`, `sn.png`, `ctc1.png`, `VID-…WA0002/0003.mp4`, miniaturas `-300x…`.

## 7. Formulario

Original (Forminator, formulario 82, solo en `/contactenos/`): **Nombre \***, **Teléfono \***, **Correo electrónico \***, **Mensaje \***, botón **Enviar mensaje**.

Nuevo (a pedido: «acorde a lo que ofrece la empresa» y «los datos deben llegar al WhatsApp»): Nombre \*, Teléfono \*, Correo electrónico (ahora opcional), **Tipo de servicio \*** (Remolque de vehículo liviano · Remolque de vehículo pesado · Transporte de maquinaria industrial · Otro servicio — editable), **Ubicación / destino**, Mensaje \*, botón **Enviar mensaje**. Al enviar: se abre `api.whatsapp.com` con el mensaje armado hacia **50235567904**, se guarda copia en el panel y se avisa por correo.

## 8. Diseño original (solo referencia; se reemplazó por la nueva dirección de arte)

Fuentes Roboto / Roboto Slab / Vollkorn (Google Fonts), iconos Font Awesome (`phone-alt`, `whatsapp`, `envelope`, `bullseye`, `eye`); barras azules `#184C7A`, iconos cian `#03A7C0`, verde `#03C054`, ámbar `#C07403`; banda gris `#E9E9E9`; fondo de bienvenida `#F5F5F5`.

## 9. Cambios respecto al original (todo lo demás es idéntico)

* **Diseño:** fondo blanco con los colores del logo, ancho completo, íconos en el menú. Solo cambia el diseño.
* **Formulario (pedido del cliente):** se agregan *Tipo de servicio* y *Ubicación / destino*; el correo pasa a ser opcional; al enviar se abre WhatsApp con los datos.
* **Botones flotantes** de llamada y WhatsApp (pedido del cliente); mismos destinos que las tarjetas.
* **SEO (invisible):** `alt` en imágenes, meta description, Open Graph y JSON-LD; títulos de las páginas internas.
* **Verificación:** `tests/content_parity.py` compara los 81 textos originales contra el sitio nuevo (0 diferencias).
* **Logo:** sustituido por el nuevo logo del cliente en todo el sitio (el anterior se eliminó).
