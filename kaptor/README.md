# Kaptor 1.3

Extractor profesional de **correos electrónicos y números de WhatsApp** a partir
de una URL, con **módulo de campañas de correo** incluido: extraes, filtras y
envías desde tu propio dominio, todo en la misma herramienta.
PHP 8.0+ · MySQL/MariaDB · sin Composer · listo para subir a `public_html`.

---

## Instalación en 4 pasos

1. **Sube los archivos.** Descomprime el ZIP y sube **todo su contenido** a la
   carpeta raíz de tu hosting (normalmente `public_html`). Si quieres que la
   herramienta viva en un subdirectorio, súbelo a `public_html/kaptor/`.

2. **Crea la base de datos.** Desde cPanel → *Bases de datos MySQL*, crea una
   base de datos y un usuario, y asígnale **todos los permisos**. Apunta el
   nombre, el usuario y la contraseña.

3. **Abre el instalador** en el navegador:
   `https://tudominio.com/install.php`
   Comprueba los requisitos, escribe los datos de la base de datos y crea tu
   cuenta de administrador. El instalador crea las tablas y el archivo
   `config/config.php` por ti.

4. **Borra `install.php`** del servidor. El instalador se bloquea solo, pero
   eliminarlo es la práctica recomendada. Después entra en
   `https://tudominio.com/admin/` con la cuenta que acabas de crear.

> **Permisos:** las carpetas `config/` y `storage/` deben tener permiso de
> escritura (755, o 775 si tu hosting lo exige). El resto puede quedarse en 644/755.

---

## Requisitos

| Requisito | Mínimo | Para qué se usa |
|---|---|---|
| PHP | 8.0 | Toda la aplicación |
| MySQL / MariaDB | 5.7 / 10.2 | Ajustes, usuarios, historial |
| Extensión `pdo_mysql` | Obligatoria | Conexión a la base de datos |
| Extensión `curl` | Obligatoria | Descarga de las páginas |
| Extensión `mbstring` | Obligatoria | Acentos y UTF-8 |
| Extensión `zip` | Recomendada | Exportación a Excel (.xlsx) |
| Extensión `openssl` | Recomendada | Descarga de webs con HTTPS |

No hace falta Composer: todas las piezas (tipografías, gráficas y el generador
de Excel) están incluidas en el propio paquete.

---

## Cómo se usa

1. Pega el enlace en la caja de la portada.
2. Activa **Rastreo profundo** si quieres recorrer también las páginas internas
   (contacto, nosotros, equipo…). El límite de páginas y la profundidad se
   configuran en el panel.
3. Pulsa **Extraer correos** y observa el radar: verás la página que se está
   analizando, cuántas se han revisado y los hallazgos apareciendo en vivo.
4. Los resultados llegan en dos pestañas: **Correos** y **WhatsApp y teléfonos**.
   Cada una tiene su buscador, sus filtros (dominio o país, tipo de correo o
   solo WhatsApp), casillas de selección y su botón de copiar.
5. Descarga en **TXT**, **CSV** o **Excel**. El Excel «con todo» trae dos hojas,
   una por cada conjunto. El archivo se nombra con el dominio y la fecha
   (`kaptor-midominio-com-completo-2026-09-18.xlsx`).

---

## Qué detecta el motor

Kaptor no se limita a buscar `algo@algo.com` en el HTML. Aplica 20 técnicas
sobre cada página, cada archivo JS o CSS enlazado y cada endpoint JSON que
encuentra:

| # | Técnica | Ejemplo que resuelve |
|---|---|---|
| 1 | Texto visible y código fuente | `escribe a info@web.com` |
| 2 | Enlaces `mailto:` con `cc`, `bcc` y `subject` | `mailto:a@web.com?cc=b@web.com` |
| 3 | Entidades HTML decimales y hexadecimales | `info&#64;web.com`, `&#x40;` |
| 4 | Codificación URL, incluso doble | `info%40web.com`, `%2540` |
| 5 | Ofuscación escrita | `info [at] web [dot] com`, `(arroba)`, `{punto}`, `-at-` |
| 6 | Cloudflare Scrape Shield | `data-cfemail`, `/cdn-cgi/l/email-protection#…` |
| 7 | Atributos | `data-*`, `value`, `content`, `title`, `alt`, `aria-label` |
| 8 | Metadatos, JSON-LD y microdatos | `"email":"info@web.com"` |
| 9 | Comentarios HTML | `<!-- info@web.com -->` |
| 10 | Correos partidos por etiquetas | `info<span>@</span>web.com` |
| 11 | Caracteres invisibles | espacios de ancho cero dentro del correo |
| 12 | Archivos JS y CSS enlazados | `content:"info\0040web.com"` |
| 13 | Base64 y `atob()` | `atob("aW5mb0B3ZWIuY29t")` |
| 14 | Concatenación en JavaScript | `"info" + "@" + "web" + ".com"` |
| 15 | Arrays unidos con `join("")` | `["info","@","web.com"].join("")` |
| 16 | `String.fromCharCode(...)` | `fromCharCode(105,110,102,111,64,…)` |
| 17 | Escapes `\x40`, `@`, `\0040` | cadenas escapadas en JS y CSS |
| 18 | ROT13 | `vasb@jro.pbz` |
| 19 | Texto invertido (`direction:rtl`) | `moc.bew@ofni` |
| 20 | SVG, iframes, sitemap.xml y endpoints JSON | contacto fuera del HTML principal |

### Qué detecta en WhatsApp y teléfonos

| # | Técnica | Ejemplo que resuelve |
|---|---|---|
| 1 | Enlaces `wa.me` | `https://wa.me/50255551234` |
| 2 | `api.whatsapp.com` y `web.whatsapp.com` | `...send?phone=34600111222&text=Hola` |
| 3 | Esquema `whatsapp://` | `whatsapp://send?phone=573001234567` |
| 4 | Widgets de los plugins más usados | Joinchat/Creame, WP Social Chat, Elementor, Chaty |
| 5 | Atributos `data-*` | `data-phone`, `data-whatsapp`, `data-number` |
| 6 | Enlaces `tel:`, `callto:` y `sms:` | `tel:+56 2 2345 6789` |
| 7 | JSON-LD y microdatos | `"telephone":"+34 910 000 111"` |
| 8 | Texto, URL-encoding, base64 y concatenación en JS | `"+" + "502" + "44445555"` |

También recoge los **enlaces cortos** (`wa.me/message/…`) y los **grupos**
(`chat.whatsapp.com/…`) que no muestran el número.

Cada número se lleva al formato internacional **E.164**, se identifica su
**país** por el prefijo (175 prefijos incluidos, con coincidencia más larga
primero para distinguir Puerto Rico de Estados Unidos) y se comprueba que la
longitud nacional sea posible. Se descartan fechas, precios, versiones,
marcas de tiempo Unix, códigos de barras, coordenadas y secuencias de relleno.
Los números que vienen de un enlace de WhatsApp se marcan como **WhatsApp
confirmado**; el resto quedan como **teléfono**, y cada uno lleva su propia
puntuación de confianza.

Si tu público escribe los números en formato local (`2222 3333`), indica tu
**prefijo de país** en *Ajustes → Motor* y también los reconocerá. Sin ese
ajuste, solo se aceptan números con prefijo internacional, que es lo más
preciso.

**Validación estricta.** Todo lo encontrado pasa por: normalización a
minúsculas, eliminación de duplicados, comprobación de la estructura, lista
blanca de terminaciones reales, descarte de nombres de archivo
(`logo@2x.png`, `app@bundle.js`), descarte de dominios de ejemplo y de
servicios técnicos (`example.com`, `sentry`, `wixpress`…), `filter_var()` y,
si lo activas, comprobación del registro **MX** del dominio (con respaldo por
DNS sobre HTTPS si tu hosting bloquea las consultas DNS).

Además, cada correo se clasifica como **genérico** (`info@`, `ventas@`) o
**personal**, y recibe una **puntuación de confianza** de 5 a 99 que tiene en
cuenta el método de detección, el dominio del sitio, la página donde aparece y
el resultado MX. También se recopilan los **teléfonos** y los **perfiles
sociales** que aparezcan por el camino.

### Contenido cargado por JavaScript

La mayoría de los casos se resuelven sin navegador: Kaptor lee los archivos
JS, decodifica base64/ROT13/`fromCharCode`, sigue los endpoints JSON y consulta
el `sitemap.xml`. Si tu servidor permite ejecutar procesos y tiene Chrome o
Chromium instalado, puedes activar el **navegador interno** en
*Ajustes → Motor*; si no, la aplicación te lo indica con un aviso y sigue
funcionando con el resto de técnicas.

---

## Campañas de correo

Kaptor incluye su propio motor de envío. No necesitas Mailchimp ni Brevo
—que además prohíben en sus términos las listas extraídas— porque los mensajes
salen **desde tus propias cuentas de correo** por SMTP.

### Puesta en marcha

1. **Buzones** → añade la cuenta de tu dominio (`info@tudominio.com`) con los
   datos SMTP que te da tu hosting. Pulsa *Probar* y envíate un correo de prueba.
2. **Contactos** → crea una lista e impórtala directamente desde una extracción
   (con filtros de confianza, MX y tipo de buzón) o pegando un CSV.
3. **Plantillas** → escribe el mensaje con variables: `{{nombre}}`, `{{centro}}`,
   `{{correo}}`, `{{remitente}}`… El pie con el enlace de baja se añade solo.
4. **Campañas** → elige lista, plantilla y buzones, fija el ritmo y lanza.

### Cómo envía

- **Ritmo controlado**: límite por hora en la campaña, y límite por hora y por
  día en cada buzón. Entre correo y correo hay una pausa aleatoria.
- **Rotación de buzones**: si añades varios, el motor reparte la carga.
- **Sin dejar el navegador abierto**: programa una tarea cron cada 5 minutos y
  la campaña avanza sola durante días.

```
curl -s "https://tudominio.com/cron.php?clave=TU_CLAVE"
```

La clave está en *Ajustes → Campañas*. El cron también hace la limpieza del
historial y cierra los escaneos que se quedaron a medias.

### Lo que hace por ti para no acabar en spam

- Cabeceras `List-Unsubscribe` y `List-Unsubscribe-Post`, con **baja en un clic**
  (RFC 8058), que es lo que Gmail y Outlook exigen desde 2024.
- Mensaje en dos partes (texto y HTML), asunto codificado correctamente y
  `Message-ID` propio.
- **Lista de supresión global**: quien se da de baja o rebota queda excluido de
  todas las campañas, para siempre y sin excepción.
- Los rebotes definitivos (error 5xx) se detectan y se suprimen solos.
- Seguimiento de aperturas y de clics, con los enlaces firmados por HMAC para
  que nadie pueda usar tu dominio como redirector hacia sitios de phishing.
- Las contraseñas SMTP se guardan cifradas con AES-256-GCM.

### Antes de tu primera campaña

| Paso | Por qué importa |
|---|---|
| Configura **SPF, DKIM y DMARC** (cPanel → Autenticación de correo) | Sin esto, la mitad de tus correos van a spam |
| Envía una prueba a tu propio correo | Comprueba que llega a la bandeja de entrada |
| Empieza con **20–30 al día** por buzón y sube poco a poco | Un buzón nuevo que manda 500 de golpe se quema |
| Usa un dominio secundario si puedes | Protege la reputación de tu dominio principal |
| Revisa los rebotes | Más de un 5 % es señal de que la lista necesita limpieza |

Con un solo buzón a 40 correos/hora, una lista de 5.000 tarda unos cinco días.
Es lo normal y lo sano: con tres buzones baja a menos de dos días.

## Panel de administración

`https://tudominio.com/admin/`

- **Resumen:** métricas, actividad de los últimos 14 días, métodos de detección
  más frecuentes, dominios y sitios más analizados, y estado del servidor.
- **Historial:** todas las extracciones (correos y WhatsApp), con buscador,
  filtros, vista de detalle y nueva descarga en los tres formatos.
- **Usuarios:** alta, roles, activación, cambio de contraseña y borrado.
- **Campañas, Contactos, Plantillas, Buzones y Supresión:** todo el módulo de
  envío, con estadísticas de aperturas, clics, rebotes y bajas.
- **Ajustes:** nombre, logo, colores, todos los textos de la portada, límites del
  motor, tiempo de espera, profundidad del rastreo, dominios excluidos,
  detección de WhatsApp y prefijo de país, acceso libre o solo con cuenta,
  registro público, retención del historial, dirección postal del remitente,
  seguimiento y clave del cron.

---

## Seguridad incluida

- **Anti-SSRF:** se bloquean IP privadas y reservadas, `localhost`, metadatos de
  nube, CGNAT y puertos internos. Cada redirección se vuelve a validar.
- **CSRF** en todos los formularios y en todas las llamadas AJAX.
- **PDO con consultas preparadas** en el 100 % de las operaciones.
- **Escapado de salida** en todas las plantillas.
- **Contraseñas** con `password_hash()` y recifrado automático.
- **Límite de peticiones por IP** configurable y freno a los intentos de acceso.
- **Cabeceras de seguridad** (CSP, `X-Frame-Options`, `nosniff`, HSTS en HTTPS)
  y archivos `.htaccess` que protegen `config/`, `includes/`, `storage/` y
  `database/`.

---

## Estructura de carpetas

```
/                     index.php, login.php, registro.php, install.php,
                      baja.php, cron.php, .htaccess
/admin                panel de administración
/api                  escaneo.php, exportar.php, campana.php, pixel.php, clic.php
/assets               css, js, fuentes propias, imágenes y subidas
/config               config.php (lo genera el instalador)
/database             schema.sql
/includes             el motor: Extractor, Validador, Rastreador, Http, Seguridad…
/storage              registros, caché y sesiones (no accesible por web)
```

---

## Preguntas frecuentes

**No me deja instalar: «No se pudo conectar con la base de datos».**
Revisa el nombre exacto de la base de datos y del usuario (en cPanel suelen
llevar un prefijo del tipo `micuenta_`). Prueba con `localhost` y también con
`127.0.0.1` como servidor.

**La extracción da «No se pudo descargar la página».**
El sitio de destino puede estar bloqueando peticiones automáticas, o tu hosting
no tiene salida a internet. Sube el *tiempo máximo por página* en
*Ajustes → Motor* y vuelve a probarlo.

**No encuentra el WhatsApp de una web que sí lo tiene.**
Casi siempre el botón de WhatsApp solo está en la página de contacto: activa el
**rastreo profundo**. Si el número aparece escrito sin prefijo internacional
(`2222 3333`), configura tu prefijo de país en *Ajustes → Motor*.

**Los correos de la campaña llegan a spam.**
Casi siempre es falta de SPF/DKIM/DMARC. Compruébalo en tu panel de hosting y
usa una herramienta como mail-tester.com antes de lanzar la campaña de verdad.
Lo segundo más común es ir demasiado rápido al principio.

**El cron no envía nada.**
Comprueba que la clave coincide con la de *Ajustes → Campañas* y que la campaña
está en estado «enviando». Si tu hosting no permite cron, puedes pulsar
«Enviar ahora un lote» desde el panel.

**El Excel no se descarga.**
Tu servidor no tiene la extensión `zip` de PHP. Pídesela a tu proveedor; TXT y
CSV seguirán funcionando.

**Quiero reinstalar desde cero.**
Borra `config/config.php` y `storage/instalado.lock`, sube de nuevo
`install.php` y vuelve a ejecutarlo.

---

## Aviso de uso

Kaptor extrae información **pública** de páginas web. Úsalo sobre sitios
propios o con autorización y respeta siempre la legislación que te aplique.

La ley que cuenta suele ser la **del destinatario**, no la tuya:

- **España y la UE** (RGPD + LSSI): el correo comercial necesita consentimiento
  previo salvo excepciones muy concretas.
- **Canadá** (CASL): consentimiento obligatorio, sanciones muy altas.
- **Estados Unidos** (CAN-SPAM): permite el correo B2B en frío si te identificas
  con datos reales, incluyes dirección postal y respetas las bajas.
- **Guatemala**: todavía no hay una ley integral de protección de datos en
  vigor, aunque hay una iniciativa avanzada en el Congreso.

En la práctica: escribe a buzones corporativos (`info@`, `contacto@`) antes que a
direcciones personales, identifícate de verdad, deja siempre el enlace de baja y
**respeta la lista de supresión sin excepciones**. Para WhatsApp, recuerda que el
envío masivo no solicitado incumple los Términos de Servicio de Meta y suele
acabar con el número bloqueado: usa la API oficial con consentimiento previo.

---

## Créditos técnicos

- Tipografías **Fraunces**, **Inter** y **JetBrains Mono**, con licencia
  SIL Open Font License 1.1, incluidas en `assets/fonts/` (sin peticiones a
  servicios externos).
- Imágenes y texturas generadas con la librería GD dentro del propio proyecto:
  libres de derechos y servidas desde `assets/img/`.
- Archivos `.xlsx` generados con `ZipArchive` nativo de PHP, sin dependencias.
