# CONTRATO TÉCNICO — "Tu web en 5 minutos" (Servicom)

Documento de referencia común. Todo módulo debe respetarlo. Si algo es ambiguo: decide lo más seguro y anótalo en `docs/DECISIONES.md` (añade al final, una línea por decisión, con tu módulo).

## 0. Reglas globales
- PHP **8.0 mínimo**. Prohibido: enums, `readonly`, `never`, first-class callable `f(...)`, `new` en inicializadores, intersection types, `array_is_list`, `fsync`, propiedades readonly, `final const`, sintaxis/funciones 8.1+. Permitido 8.0: `match`, nullsafe `?->`, named args, constructor promotion, union types, `str_contains/str_starts_with/str_ends_with`, atributos `#[...]`.
- Todo texto visible al usuario en **español** (Guatemala, trato de "usted" en textos de marca; "tú" en el portal ya es la voz definida: tuteo en UI del portal, "usted" en textos generados para las webs de clientes).
- Sin SEO: nada de plugins/campos/sitemaps de SEO. Las vistas previas llevan noindex.
- Sin CDN: fuentes, JS y CSS autoalojados. Sin librerías pesadas.
- Cero secretos en código. Cero dominios fijos (todo sale de configuración). Dominio base por defecto `servicom.gt`, portal `crear.servicom.gt`.
- Seguridad: escape de salida siempre, consultas preparadas, validación en servidor.
- Se prueba con PHP 8.3 nativo (`php`) y PHP 8.0 WASM (`/tmp/p80`, ver `tools/php80.mjs` cuando exista). MariaDB 10.11 local (`mysql` como root por socket, sin contraseña). WordPress core 6.4.3 listo en `/opt/wp-core` (es_ES incluido, SIN wp-config). PHPMailer en `/opt/vendor-phpmailer`.
- **No hay acceso a wordpress.org**: Elementor, WooCommerce y Fluent Forms NO se pueden descargar aquí. Para pruebas existe un simulador SOLO-PRUEBAS (`wp-pack/tests-only/elementor-sim.php`) que renderiza `_elementor_data`. El código debe estar escrito para el Elementor/WooCommerce reales (API pública documentada), con defensas `class_exists`/`function_exists` donde falten. Nunca hacer pasar el simulador por Elementor real.
- Nunca ejecutar nada de la carpeta `/home/user/vqmod` fuera de `servicom-5min/`. No tocar los archivos del repo ajenos al proyecto (CMS "Fuente de Vida").

## 1. Estructura del ZIP del portal (raíz = docroot)
```
index.php  install.php  .htaccess  robots.txt
app/            (deny web)  Core/ Services/ Controllers/ Provision/ views/ lang/
assets/         css/ js/ fonts/ img/   (público)
storage/        (deny web)  drafts/ uploads/ logs/ cache/ sessions/ jobs/
library/        (deny web)  fotos de stock por rubro + catalog.json + CREDITOS.md
wp-pack/        (deny web)  theme/servicom  mu-plugins/  provision/  plugins.json
tools/          (deny web)  CLI: build_base.php, cron.php, ...
vendor/phpmailer/
```
Secretos/config: `config.php` en carpeta hermana superior al docroot (`../servicom-secrets/config.php`) si es escribible; si no, `app/config.php` (deny). Valores sensibles (token cPanel, clave IA, SMTP, HMAC) van **cifrados** (AES-256-GCM con OpenSSL; sin sodium) en la tabla `settings`; la llave está solo en config.php.

## 2. Núcleo del portal (lo implementa el arquitecto; los demás lo USAN)
Namespace raíz `S5\`, autoload por carpeta: `S5\Core\X` → `app/Core/X.php`, `S5\Services\X` → `app/Services/X.php`, etc.
- `S5\Core\Config::get(string $key, $default=null)` — config.php (db, rutas, `secret_key`).
- `S5\Core\Settings::get(string $k, $default=null)`, `::set($k,$v,bool $secret=false)`, `::int()`, `::float()`, `::bool()`. Claves relevantes: `dominio_base`, `portal_sub`, `precio_info`(1250) `precio_tienda`(1750) `precio_tarjeta`(750), `wa_servicom`, `owner_email`, `ai_key`(secreto), `ai_model_main`(claude-sonnet-5-5) `ai_model_fallback`(claude-haiku-5-5) `ai_model_extract`(claude-haiku-5-5), `ai_cap_day`(1.00) `ai_cap_total`(10.00) (USD), `ai_price_in_<model>` / `ai_price_out_<model>` (USD por millón de tokens), `pres_max_mb`(10), `webs_path`, `reservados` (csv).
- `S5\Core\Db`: `Db::pdo()`, `Db::t('orders')` (nombre con prefijo), `Db::q($sql,$params=[])` (devuelve PDOStatement), `Db::one(...)`, `Db::all(...)`, `Db::val(...)`, `Db::insert($tabla,$arr)` (devuelve id), `Db::update($tabla,$arr,$where,$params)`. Siempre prepared statements.
- `S5\Core\Log::audit(string $accion, string $detalle='', ?int $orderId=null)`, `Log::error(string $msg, array $ctx=[])` (storage/logs/app.log).
- `S5\Core\Sanitize`: `Sanitize::text(string,int $max)` (UTF-8 válido, sin HTML/control chars, recorta), `Sanitize::url`, `Sanitize::email`, `Sanitize::slug`, `Sanitize::phoneDigits`.
- `S5\Core\Http::json(array,int $status=200)`, `Http::ip()`.
- `S5\Core\RateLimit::hit(string $key,int $max,int $windowSec): bool` (true = permitido).
- `S5\Core\Mailer::send(string $to,string $subject,string $htmlBody,array $opts=[]): bool`.
- Tablas (prefijo configurable, por defecto `s5_`): `users, settings, orders, build_steps, files, hosts, ai_usage, rate_limits, audit_log, login_attempts, meta`.
- `ai_usage`: `id, kind('redaccion'|'analisis'), model, tokens_in, tokens_out, cost_usd DECIMAL(10,6), order_id, ok TINYINT, created_at`.

## 3. Servicios de IA/presentación (los implementa el agente P)
En `app/Services/`:
- `TextClean` — saneo de texto extraído (UTF-8, sin HTML/scripts/control chars, normaliza espacios, recorta).
- `PresentationParser::inspect(string $path, string $nombreOriginal, int $maxBytes): array` → `['ok'=>bool,'tipo'=>'pdf'|'pptx'|'docx','codigo'=>string,'mensaje'=>string_es_amable]`. Códigos: `ok, vacio, muy_grande, formato_no_permitido, formato_antiguo (ppt/doc/key/canva…→"Guárdala como PDF y vuelve a subirla"), macros, protegido, corrupto, zip_sospechoso, mime_invalido`.
- `PresentationParser::extract(string $path, string $tipo, string $workdir): array` → `['texto'=>string,'imagenes'=>[['archivo'=>ruta,'w'=>int,'h'=>int,'hash'=>string]],'paginas'=>int]` (PPTX/DOCX local con ZipArchive+DOM sin entidades externas; imágenes ≥400px, sin duplicados/iconos, máx 40, recomprimidas; límites anti zip-bomb; lecturas con límite de memoria).
- `PresentationAnalyzer::analizar(int $orderId, string $path, string $tipo, string $workdir): array` → resultado normalizado (esquema §5.2) o lanza `S5\Services\AiUnavailable` (mensaje amable). Cuenta contra topes (`AiBudget`). Máx 3 por pedido (columna `orders.analysis_count`), límites por IP/día los aplica el controlador con `RateLimit`.
- `AiClient`: `redactar(array $brief, int $orderId): array` → `['texts'=>array (esquema §6),'fuente'=>'ia'|'base','modelo'=>string]` con cadena sonnet→haiku→textos base; `extraerPresentacion(...)`. Transporte HTTP reemplazable: `AiClient::setTransport(callable $fn)` donde `$fn(string $url,array $headers,string $jsonBody): array{status:int,body:string}` (los tests usan mock).
- `AiBudget`: `puedeGastar(string $kind): bool`, `registrar(...)`, `costo(string $model,int $in,int $out): float`, `resumen(): array` (`dia`, `total`, por `kind`).
- `BaseTexts`: `textos(array $brief): array` (esquema §6, sin inventar nada) y `industrias(): array` (por rubro: etiqueta, icono FontAwesome por defecto para servicios, tono, claves de fotos de stock).
- `TextSchema::validar(array $json, array $brief): array` — valida/recorta/sanea salida de IA.

## 4. Brief del cliente (`orders.data`, JSON) — esquema canónico
```
{
 "plan":"info"|"tienda", "tarjeta_extra":bool,
 "negocio":{"nombre","rubro":"abogado|clinica|taller|ropa|restaurante|transporte|contabilidad|importaciones|otro","rubro_otro","idioma":"es"|"en","estilo":1..5,"logo":fileId|null},
 "dominio":{"tiene":bool,"dominio","deseado"},
 "correos":["info","ventas"], "correo_contacto":"x@y.com",
 "contenido":{"frase","apoyo","banner":[fileId],"quienes","servicios":[{"nombre","descripcion","foto":fileId|null,"origen":"form"|"pres"}],"galeria":[fileId],"youtube"},
 "tienda":{"categorias":[{"nombre","padre":""}],"productos":[{"nombre","categoria","precio":number,"descripcion","foto":fileId|null,"stock":int,"origen"}],"umbral_stock":int,"correo_alertas","correo_pedidos","banco":{"banco","numero","titular","tipo"},"contra_entrega":bool,"nota_entrega"},
 "contacto":{"whatsapp","whatsapp_msg","telefono","direccion","mapa_url","horario","redes":{"facebook","instagram","tiktok","youtube","x","linkedin"}},
 "pago":{"nombre_nit","comprobante":fileId|null},
 "presentacion":{"file":fileId|null,"acepto":bool,"estado":"ninguna|pendiente|analizando|lista|error|confirmada|omitida","confirmada":bool,
                 "usar":{ ...flags de lo confirmado... },"fotos_usar":[fileId]},
 "origen":{ "ruta.campo":"presentacion" }   // para marcar "Tomado de tu presentación"
}
```
Prioridad: lo escrito por el cliente en el formulario SIEMPRE manda sobre la presentación.

### 5.2 Resultado de análisis de presentación (`orders.analysis`, JSON)
```
{"nombre":{"v":"","textual":bool},"rubro_sugerido":{"v":"","textual":bool},"frase_principal":{...},"quienes_somos":{...},
 "servicios":[{"nombre","descripcion","textual"}],"productos":[{"nombre","descripcion","precio","categoria","textual"}],"categorias":[""],
 "contacto":{"telefono":{"v","textual"},"whatsapp":{},"correo":{},"direccion":{},"horario":{},"redes":{"facebook":"","instagram":"",...}},
 "imagenes":[{"id","w","h"}],"conflictos":[{"campo","form","pres"}],"idioma":"es|en|otro"}
```
Todo campo no encontrado = `""`/`[]`. Nunca inventar.

### 6. Textos de la web (`texts`, claves exactas; IA o BaseTexts)
```
hero_titulo(<=70) hero_subtitulo(<=170) hero_boton(<=26)
servicios_titulo(<=60) servicios_intro(<=220)
nosotros_titulo(<=60) nosotros_texto(<=900)
cta_titulo(<=70) cta_texto(<=200) cta_boton(<=26)
contacto_titulo(<=60) contacto_intro(<=220)
galeria_titulo(<=60)
tienda_titulo(<=60) tienda_intro(<=220)           (solo plan tienda)
servicios: [{"resumen"(<=140),"descripcion"(<=600)}]   (mismo orden e índice que contenido.servicios)
```
Prohibido inventar servicios, precios, años, certificaciones, direcciones, teléfonos, cifras, premios o testimonios. Trato de "usted", español neutro de Guatemala (o inglés si idioma=en).

## 7. Manifiesto de construcción (portal → sitio WordPress) — `manifest.json`
Vive en `<sitio>/wp-content/sc-jobs/<job>/manifest.json`; assets en `.../assets/`.
```
{
 "version":1,
 "site":{"url":"https://slug.servicom.gt","slug","title","locale":"es_ES"|"en_US","lang":"es"|"en","timezone":"America/Guatemala",
         "admin_user","admin_pass","admin_email","table_prefix"},
 "plan":"info"|"tienda", "style":1..5, "rubro":"…",
 "business":{"nombre","whatsapp"(solo dígitos con código país),"whatsapp_msg","telefono","correo_contacto","direccion","mapa_url","horario",
             "redes":{"facebook","instagram","tiktok","youtube","x","linkedin"},"logo":"a1"|null,"youtube":"url|''","footer_credit":"Sitio creado por Servicom"},
 "texts":{ …§6… },
 "content":{"banner":["a2"],"quienes":"","servicios":[{"nombre","descripcion","foto":"a3"|null,"icono":"fas fa-gavel"}],"galeria":["a9"]},
 "store":{"categorias":[{"nombre","padre"}],"productos":[{"nombre","categoria","precio","descripcion","foto":"a4"|null,"stock":int}],
          "umbral_stock","correo_alertas","correo_pedidos","banco":{"banco","numero","titular","tipo"},"contra_entrega":bool,"nota_entrega"},
 "assets":[{"id":"a1","path":"assets/a1.webp","mime":"image/webp","w":800,"h":600,"alt":"texto alternativo"}],
 "preview":{"mode":"preview"|"demo"|"published","key":"32hex","portal_url":"https://crear.servicom.gt","pay_url","edit_url","wa_servicom":"502…"},
 "support":{"wa_servicom":"502…","email_owner":"…"}
}
```
Reglas del builder: un `asset` con `foto:null` NO genera imagen rota: se usa caja de icono/monograma. Sin banner → hero con fondo degradado propio del estilo (no slideshow vacío). Sección sin contenido → no se renderiza. Textos largos: se recortan con CSS (line-clamp) en tarjetas, nunca se corta contenido en páginas de detalle.

## 8. Script de aprovisionamiento WP — `wp-pack/provision/sc-provision.php`
Se copia a la raíz del sitio durante la construcción y se BORRA al terminar. Dos transportes:
- CLI: `php sc-provision.php <jobdir> <step> [--arg=valor]`
- HTTP (alternativa): `sc-provision.php?job=<id>&step=<step>&ts=<unix>&sig=<hmac_sha256(secret, "step|job|ts")>`; `secret` está en `wp-content/sc-jobs/secret.php` (`return 'hex';`). ts ±120 s. Sin firma válida → 403 sin detalles.
Salida: **una línea JSON** `{"ok":true,"step":"…","data":{…},"more":false}` o `{"ok":false,"error":"mensaje técnico corto","retry":true|false}`. Pasos idempotentes y reanudables (`more:true` = volver a llamar):
`install` (wp_install con wp-config ya escrito por el portal; idioma, zona horaria, permalinks `/%postname%/`, quitar contenido demo, borrar plugins Hello Dolly/Akismet, activar tema `servicom` y plugins permitidos presentes) · `media` (importa assets a la biblioteca, genera miniaturas) · `pages` (kit global de Elementor, páginas, menús, ajustes del tema/customizer, formulario de contacto) · `store` (solo tienda: WooCommerce, categorías, productos, pagos, correos) · `finish` (flush rewrite, CSS de Elementor regenerado, `sc_mode`, marca de build completo) · `qa` (autochequeo interno: lista de URLs y problemas detectados) · `publish` (sc_mode=published, quita barra y noindex, crea rol/usuario cliente con `--email` y `--name`, devuelve enlace de definición de contraseña) · `replace_domain` (`--from=URL --to=URL`: reemplazo seguro en BD incl. datos serializados y JSON de Elementor, regenera CSS) · `reset` (borra contenido generado para regenerar) · `status`.
El portal es quien copia/borra el script y escribe `wp-config.php`.

## 9. Opciones/mods de WordPress (fuente de verdad del negocio)
`theme_mod`: `sc_nombre, sc_telefono, sc_whatsapp, sc_whatsapp_msg, sc_correo, sc_direccion, sc_mapa_url, sc_horario, sc_facebook, sc_instagram, sc_tiktok, sc_youtube, sc_x, sc_linkedin, sc_footer_credit, sc_float_wa (1/0), sc_mobile_bar (1/0), sc_style (1..5)`; logo = `custom_logo` nativo.
Opciones: `sc_mode` ('preview'|'demo'|'published'), `sc_preview_key`, `sc_portal_url`, `sc_pay_url`, `sc_edit_url`, `sc_wa_servicom`, `sc_plan` ('info'|'tienda'), `sc_build_state` (array), `sc_service_pages` (mapa índice→post_id), `sc_page_ids` (home, nosotros, servicios, galeria, contacto, tienda…), `sc_expiry` (fecha ISO).
Helper PHP (mu-plugin): `sc_biz(string $key, $default='')`, `sc_biz_all()`.
Shortcodes (mu-plugin): `[sc_nombre] [sc_telefono] [sc_telefono_link] [sc_whatsapp_link texto="…"] [sc_correo] [sc_direccion] [sc_horario] [sc_redes] [sc_mapa_link] [sc_anio]`. Los widgets de Elementor muestran datos de negocio SOLO mediante estos shortcodes (widget "shortcode"/texto) para que el Personalizador sea la única fuente de verdad.

## 10. Vista previa privada
`sc_mode=preview`: visitantes sin cookie válida ni sesión WP ven pantalla "Vista previa privada" (HTTP 403, noindex). El enlace al cliente es `https://slug…/?scpk=<sc_preview_key>` → cookie `sc_pk` 30 días y redirige a URL limpia. Con acceso válido: barra flotante discreta "Vista previa · Aprobar y pagar · Editar datos" (enlaces `pay_url`/`edit_url`, que contienen la clave de vista previa, NO el token del borrador). Cabeceras `X-Robots-Tag: noindex, nofollow` + `<meta robots>`. `sc_mode=demo`: público, sin barra, noindex. `sc_mode=published`: sin barra, indexable.

## 11. Clases CSS de secciones (acuerdo tema ⇄ builder)
Contenedores Elementor reciben `css_classes` (Elementor las pone en el wrapper). Obligatorias:
`sc-sec` (sección; variantes `sc-sec--alt`, `sc-sec--dark`), `sc-hero` (+`sc-hero--plain` si no hay slideshow), `sc-hero__inner`, `sc-hero__title`, `sc-hero__sub`, `sc-head`, `sc-title`, `sc-lead`, `sc-grid`, `sc-card`, `sc-card__media`, `sc-card__title`, `sc-card__text`, `sc-card--icon`, `sc-about`, `sc-about__media`, `sc-about__body`, `sc-cta`, `sc-gallery`, `sc-contact`, `sc-contact__info`, `sc-contact__form`, `sc-map`, `sc-video`, `sc-btn`, `sc-btn--primary`, `sc-btn--ghost`, `sc-reveal`, `sc-page-hero` (cabecera de páginas internas), `sc-service` (página de servicio), `sc-service__media`, `sc-service__body`, `sc-prose`.
Variables CSS del tema (definidas por estilo): `--sc-bg --sc-bg-alt --sc-surface --sc-text --sc-muted --sc-primary --sc-primary-ink --sc-accent --sc-border --sc-radius --sc-shadow --sc-font-head --sc-font-body --sc-space-sec --sc-space-sec-m`.

## 12. API del portal (borrador) — sesión + CSRF
Todas JSON; cabecera `X-CSRF: <token de <meta name="csrf">>`; el `token` del borrador (64 hex) va en la URL. Errores: `{ok:false,error:"mensaje en español"}`.
- `POST /api/borrador` `{plan}` → `{ok,token}` (cookie/enlace para retomar `/continuar/<token>`)
- `GET  /api/borrador/<token>` → `{ok,data,estado,paso,analisis,construccion}`
- `POST /api/borrador/<token>/guardar` `{data,paso}` → `{ok,guardado_en}` (autoguardado; mezcla segura, valida tipos/longitudes)
- `POST /api/borrador/<token>/subir` multipart `{tipo:'foto'|'logo'|'comprobante'|'presentacion', archivo}` → `{ok,id,nombre,url_miniatura,mensaje?}`; `DELETE /api/borrador/<token>/archivo/<id>`
- `POST /api/borrador/<token>/analizar` → `{ok,estado}`; `GET …/analisis` → `{ok,estado:'pendiente|procesando|lista|error|omitida',resultado?,mensaje?}`
- `POST /api/borrador/<token>/confirmar-presentacion` `{usar:{…},fotos:[id]}` → `{ok,data}` (fusiona con prioridad del formulario; devuelve data con pre-llenado y `origen`)
- `POST /api/borrador/<token>/crear` → inicia construcción `{ok}`; `GET …/construccion` → `{ok,estado,progreso:0-100,pasos:[{clave,titulo,estado}],mensaje,url?}`
- `POST /api/borrador/<token>/regenerar` · `POST /api/borrador/<token>/pagar` `{nombre_nit,comprobante}` → estado `pago_revisar`
- Vistas: `/` portal, `/crear` wizard (`/continuar/<token>`), `/vista-previa/<token>` (estado/progreso y enlace), `/vp/<key>/pagar`, `/vp/<key>/editar`, `/pago/<token>`.
- Variables que el layout público recibe: `$cfg` = `['wa_servicom','precio_info','precio_tienda','precio_tarjeta','dominio_base','portal_sub','demos'=>[['nombre','url','rubro']]]`.

## 13. Reparto de carpetas (propiedad exclusiva)
- Agente W1 (tema): `wp-pack/theme/servicom/**`
- Agente W2a (mu-plugin infraestructura): `wp-pack/mu-plugins/servicom-core.php`, `wp-pack/mu-plugins/servicom-core/includes/{hardening,business,roles,instructions,preview,domain,qa-support}.php` y `.../assets/**` del mu-plugin.
- Agente W2b (constructor): `wp-pack/mu-plugins/servicom-core/includes/builder/**`, `wp-pack/provision/**`, `wp-pack/tests-only/**`.
- Agente P (IA/presentación): `app/Services/{TextClean,PresentationParser,PresentationAnalyzer,AiClient,AiBudget,AiUnavailable,BaseTexts,TextSchema}.php`, `tests/ai/**`, `tests/fixtures/presentaciones/**`.
- Agente F (frontend portal): `assets/**`, `app/views/portal/**`, `app/views/layout.php`.
- Arquitecto: resto.

## 7b. Ampliaciones LUXE del manifiesto (ver CONTRATO-LUXE)
Todas son **aditivas**: un manifiesto sin ellas sigue siendo válido (el builder usa arte/textos base).
- `texts` incluye, además de §6: `hero_eyebrow`, `nosotros_lead`, `cita`, `valores_titulo`, `valores[{titulo,texto,icono}]` (4–6), `proceso_titulo`, `proceso[{titulo,texto}]` (3–4), `faq_titulo`, `faq[{p,r}]` (4–6), `seo_descripcion`; y `servicios[]` pasa a `{resumen,descripcion,icono}` (**uno por cada** `content.servicios`, incluidos los sugeridos). Límites en CONTRATO-LUXE §2. `icono` ∈ lista cerrada de CONTRATO-LUXE §1 (`TextSchema::ICONOS`); cualquier otro valor se reemplaza por una clave válida. El portal garantiza el esquema completo (si la IA omite o corrompe un bloque, o los textos son anteriores a LUXE, se rellena con `BaseTexts`).
- `content.servicios[]`: `{nombre,descripcion,foto,icono,origen}`; `icono` es ahora una clave de CONTRATO-LUXE §1 (ya no clases FontAwesome); `origen` ∈ `form|pres|sugerido`. Si el cliente dejó menos de 4 servicios (plan `info`), el portal añade servicios típicos del rubro hasta 6 con `origen:"sugerido"` (`Brief::completarServicios`; en plan `tienda` nunca). La descripción vacía de un servicio del cliente se completa con el texto redactado.
- `assets[]` añade `role` ∈ `logo|banner|servicio|galeria|about|stock` y `credit` (solo si lo hay; las fotos de stock traen la atribución).
- `content.stock` (solo si el portal descargó fotos, `orders.data._stock` escrito por StockImages): `{"hero":["a10"],"about":["a12"],"servicios":["a13"],"galeria":["a20"]}` con ids de `assets[]`; claves vacías se omiten. Prioridad por slot: foto del cliente > `content.stock.*` > arte. Si no hay stock, la clave `stock` no existe.
- `design.hint` = `{"rubro":"abogado|…","estilo":1..5}` (pista para la paleta de lujo por rubro cuando no hay logo; la paleta final la decide el builder).

