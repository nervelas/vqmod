# Kaptor 3.1

Extractor profesional de **correos electrónicos y números de WhatsApp** a partir
de una URL, con **módulo de campañas de correo** incluido: extraes, filtras y
envías desde tu propio dominio, todo en la misma herramienta.
PHP 8.0+ · MySQL/MariaDB · sin Composer · listo para subir a `public_html`.

## De uso privado

Kaptor no tiene página pública: **para usarlo hay que iniciar sesión**, y las
cuentas las crea el administrador desde *Panel → Usuarios*. No hay registro
abierto. Quien llegue sin sesión va al acceso y, al entrar, vuelve justo a
donde iba —con sus parámetros incluidos—; el destino se valida para que solo
puedan ser rutas del propio sitio.

Siguen abiertas a propósito tres cosas: la **baja de las campañas** (el enlace
de "darse de baja" tiene que funcionar para cualquiera que reciba un correo),
el **pixel** y el **registro de clics**, y el **instalador**.

---

## El diseño

Dirección de arte: **instrumento de precisión**. Kaptor no es un folleto, es un
aparato de medición, y desde que se entra lo primero que se ve es el panel de
mando. Modo claro de verdad (no un oscuro aclarado) y diez paletas que se
cambian de un clic desde el panel.

- **Composición asimétrica**: el mando a la izquierda, la lectura del
  instrumento a la derecha y el radar saliéndose del encuadre.
- **Tres pasos numerados** —de dónde, qué quiero, hasta dónde— en lugar de un
  formulario corrido.
- **Retícula técnica** de fondo y esquinas marcadas en los paneles, como el
  visor de un aparato.
- **Tipografía con carácter**: Fraunces con el eje óptico al máximo para los
  titulares, que a tamaño grande da unos remates dramáticos; JetBrains Mono
  —la misma letra con la que se leen los correos— para los rótulos, en
  versalitas y numerados.

- **Luz ambiental** que respira detrás del contenido, grano finísimo y viñeta:
  es lo que le quita a un fondo oscuro el aspecto de plástico.
- **Superficies de cristal** con filo de luz en el borde de arriba, el detalle
  que separa una interfaz cara de una barata.
- **Movimiento con intención**, de 160 a 320 ms y curva Expo: el radar late
  mientras rastrea, las filas nuevas entran en lugar de aparecer de golpe, los
  botones tienen un destello que los cruza y se hunden 2,5 % al pulsarlos.
- **Iconos** dentro de un disco de cristal con anillo de oro y halo propio.
- **Tipografía** Fraunces para los títulos, Inter para el texto y JetBrains
  Mono para los correos, servidas desde el propio servidor: sin llamadas a
  Google Fonts ni dependencias externas.

Todo vive en dos hojas aparte —`assets/css/lujo.css` y
`admin/assets/lujo-admin.css`— que se cargan después de las de siempre. No
añaden ni quitan un solo selector de los que usa el JavaScript, así que el
diseño se puede revisar, ajustar o quitar sin tocar el funcionamiento.

**Comprobado**: las diez paletas por encima del mínimo AA (la peor, 5,5:1), las
catorce páginas sin desbordes a 390, 768 y 1440 px, todo lo pulsable de 44 px o
más en el teléfono, anillo de foco en el recorrido completo con teclado, y
`prefers-reduced-motion` detiene hasta la luz de fondo.

---

## Cómo saber qué versión está instalada

En el pie de cualquier página, a la derecha del aviso, aparece el número de
versión: **v3.1.0**. Si después de subir un ZIP nuevo el pie sigue enseñando el
número anterior, es que los archivos no se han reemplazado —no que el diseño no
haya cambiado—. Si el número es el nuevo pero se sigue viendo lo de antes,
entonces es la caché del navegador: recarga con Ctrl+F5 (o mantén pulsado el
botón de recargar en el teléfono).

---

## Cómo se actualiza

El ZIP lleva los archivos **en la raíz**, sin carpeta que los envuelva: se
extrae directamente dentro de la carpeta del sitio y sobrescribe lo que haya.
No se pierde nada propio —`config/config.php`, la base de datos, el logo y los
textos que hayas escrito siguen igual— y la base de datos se pone al día sola
la primera vez que se abre una página.



---

## De dónde puede extraer

- **Una web.** Pega la dirección y listo.
- **Muchas webs a la vez.** Pega la lista, una por línea: se recorren todas en
  un solo escaneo y los resultados salen juntos.
- **Una búsqueda.** Escribe unas palabras (`colegios privados Guatemala`) o
  pega el enlace de una búsqueda ya hecha en Google, Bing o DuckDuckGo: Kaptor
  entra en cada web de los resultados y rastrea por dentro.
- **Facebook e Instagram.** Pega la dirección de una página o un perfil: lee la
  ficha de contacto y la biografía, y sigue la web que el negocio publica ahí.

### Listas grandes (cientos de webs de golpe)

Se pega la lista entera, una web por línea, y se lanza un solo escaneo. El tope
viene de fábrica en **300 webs** por lote y se sube hasta 2000 en
*Ajustes → Motor → «Webs por escaneo en lote»*. Si la lista pasa del tope,
Kaptor **lo dice**: «ATENCIÓN: 190 webs se quedaron fuera porque el tope por
lote está en 100» — antes recortaba en silencio.

Encolar no consulta el DNS: 300 webs entran en unos 150 ms. La comprobación
completa (DNS incluido, y también en cada redirección) la hace igualmente quien
se conecta, así que la protección contra SSRF es la misma; lo único que cambia
es que ya no se pagan 900 consultas de DNS antes de empezar, que era lo que
mataba el escaneo en los hosting con límite de 30 segundos.

Fuente recomendada para sacar la lista de dominios de un país o de un sector:
los registros públicos de certificados (Certificate Transparency). Ejemplo para
Guatemala: `https://crt.sh/?q=%.edu.gt&output=json`.

---

### Nivel educativo de cada centro

Un dominio `.edu.gt` no dice si el colegio llega a diversificado o se queda en
primaria: eso solo lo dice su web. Mientras rastrea, Kaptor lee el texto de cada
sitio y apunta los niveles que menciona:

**Preprimaria · Primaria · Básicos · Diversificado · Superior**

Se reconocen por cómo se describen los propios centros: *preprimaria*, *párvulos*,
*kínder*; *primaria*, *sexto grado*; *básicos*, *tercero básico*, *INEB*;
*bachillerato*, *perito contador*, *secretariado*, *magisterio*; y, para las
universidades, *facultad de*, *licenciatura*, *maestría*, *campus universitario*.
«Superior» pide dos señales, porque cualquier colegio dice de pasada que prepara
«para la universidad» y no por eso lo es.

Los niveles salen en su propia columna, en el desplegable **«Básicos o
diversificado»** que filtra la tabla y la descarga, y en el TXT, el CSV y el Excel.

---

### Filtrar la descarga por terminación de dominio

En la tabla de resultados, encima de los botones de descarga, hay un bloque que
dice **«Quiero solo los correos que terminen en:»**. Se escribe `.edu.gt` o se
pulsa su ficha, y la tabla y la descarga se quedan con **todos los correos de
todos los dominios que acaben así** —`colegio1.edu.gt`, `liceo.edu.gt`,
`sub.universidad.edu.gt`…—, no con un dominio concreto. Cada ficha dice cuántos
correos reúne y de cuántos dominios distintos salen, y los botones de descarga
llevan escrita la cuenta: `TXT (19)`.

Se pueden pedir varias a la vez (`.edu.gt, .com.gt`) y `.gt` recoge todas las
terminaciones guatemaltecas de golpe.

---

### Búsqueda inteligente: "solo quiero correos .edu.gt"

Debajo de la caja principal hay un campo llamado **Búsqueda inteligente**. Se
escribe la extensión que interesa (`.edu.gt`, `.com.gt`, `.gob.gt`…) o se pulsa
uno de los botones, y Kaptor hace dos cosas:

1. **Reescribe la consulta al buscador** con los operadores que entienden todos
   (`site:edu.gt colegios Guatemala`, `"@edu.gt" colegios Guatemala`), así que
   los resultados ya vienen filtrados desde el origen.
2. **Descarta cualquier correo que no cumpla** antes siquiera de guardarlo: si
   se pidió `.edu.gt`, en la tabla y en la base de datos solo habrá `.edu.gt`.

Se pueden pedir varias a la vez (`.edu.gt, .gob.gt`). Vacío = todos los dominios.

---

## Extraer dominios de un texto

Menú **Extraer dominios**, en `dominios.php`: su propia página, porque sacar
correos y sacar webs son dos trabajos distintos. Sirve para lo contrario que la otra: en vez
de sacar correos, saca **dominios**. Se pega el listado que sea —el JSON de
`crt.sh`, unos resultados de búsqueda, un directorio copiado— y devuelve una
lista limpia, lista para pegarla en la caja de extracción:

- Entiende todas las formas en que aparecen: `"name_value":"x.com\nwww.x.com"`
  del JSON, el comodín `*.x.com` de los certificados, enlaces completos y
  dominios a secas.
- **Un dominio por centro**: `www.x.edu.gt`, `mail.x.edu.gt` y `webmail.x.edu.gt`
  se convierten en `x.edu.gt`.
- Filtro por terminación (`.com.gt`, `.org.gt`, `.net`…) y por palabra: *solo las
  que digan colegio, liceo, instituto, escuela*. Con botones hechos para
  Colegios, Universidades y Academias.
- Descarga en TXT (un dominio por línea), CSV y Excel.

Ejemplo real: 128 nombres de un volcado de certificados se quedan en 9 colegios.

Para conseguir el listado de partida, los registros públicos de certificados:

```
https://crt.sh/?q=%.edu.gt&output=json
https://crt.sh/?q=%.com.gt&output=json
https://crt.sh/?q=%.org.gt&output=json
```

---

## Extraer correos de un texto

Menú **Extraer correos**. No hace falta que haya una web que rastrear: se
pega directamente el texto y Kaptor saca los correos que lleve dentro. Da igual
lo que sea —un artículo, un PDF copiado, un correo reenviado con cien firmas,
una columna de Excel, un CSV entero o una lista suelta— y sale una lista limpia:

- **Sin repetidos.** Se normaliza a minúsculas y se deduplica.
- **Solo las terminaciones que se pidan.** Se escriben a mano (`.com, .com.gt,
  .edu.gt`) o se pulsan los botones. Vacío o `TODOS` = sin filtro. El filtro es
  por *terminación*, no por dominio suelto: `.edu.gt` recoge los correos de
  todos los dominios acabados así, sean diez o mil.
- **Extensiones que no se quieren** (`.ru, .cn, .xyz`) en su propio campo.
- **Solo genéricos** (info@, ventas@) o **solo personales**, a elegir.
- Quita `noreply@`, correos temporales de usar y tirar, y los que estén en la
  **lista de bajas**.
- **Un solo correo por dominio**, si se quiere una lista de empresas.
- **Comprobar MX**: deja solo dominios que de verdad reciben correo.
- Marcador con lo pegado, lo distinto, lo repetido y lo descartado, con el
  motivo de cada descarte.
- Descarga en **TXT, CSV y Excel**, o copia al portapapeles.

Admite hasta 3 MB de texto pegado (unos 100.000 correos) en una sola pasada.

---

### Lo que hay que saber de cada fuente

**Buscadores.** Google bloquea con frecuencia las consultas automáticas hechas
desde un servidor y responde con captcha. Kaptor prueba DuckDuckGo, luego Bing
y por último Google, y usa el primero que conteste; si ninguno responde, lo
dice. Se puede elegir el buscador en *Ajustes → Motor*.

**Facebook e Instagram.** Meta sirve parte de los datos públicos a quien no ha
iniciado sesión, pero levanta muros de acceso a menudo y sus condiciones de uso
no permiten el rastreo automático. Kaptor prueba varias direcciones de la misma
página y, cuando se topa con el muro, lo indica en lugar de decir que no hay
correos. La vía más fiable sigue siendo la web propia del negocio: por eso, si
la página publica su sitio, se rastrea también.

---

## Instalarlo como aplicación

Kaptor es una aplicación instalable (PWA). Al entrar en la web aparece abajo
un aviso para instalarla; también se puede hacer desde el navegador:

- **Android (Chrome):** el aviso sale solo. Si no, menú ⋮ → *Instalar aplicación*.
- **iPhone y iPad (Safari):** botón Compartir → *Añadir a pantalla de inicio*.
- **Windows, macOS y Linux (Chrome o Edge):** icono de instalar en la barra de
  direcciones, o el aviso de la propia web.

Una vez instalada abre a pantalla completa, con su icono propio, y el listado
de resultados sigue disponible aunque se pierda la conexión.

> **Requisito:** el navegador solo permite instalar sitios servidos por HTTPS.
> Activa el certificado SSL gratuito de tu hosting (cPanel → SSL/TLS Status)
> antes de probarlo. Sin HTTPS la web funciona igual, pero no se instala.

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
