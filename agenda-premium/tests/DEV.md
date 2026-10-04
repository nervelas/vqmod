# Agenda Premium · Guía técnica de desarrollo (NO se incluye en el ZIP)

Producto: sistema de agendamiento de citas, 100 % en español (Guatemala), estilo "Atelier Horloger" (relojería de lujo, negro/oro/marfil).
Ruta del proyecto: `/home/user/vqmod/agenda-premium` (la raíz del ZIP final).

## 0. Reglas innegociables
1. **PHP 8.0 como mínimo.** Prohibido: enums, `readonly`, `never`, sintaxis `foo(...)` de callable de primera clase, `new` en inicializadores, tipos de intersección, `array_is_list`, `fsync`, y cualquier función/sintaxis de 8.1+. Sí se puede: `match`, `?->`, argumentos con nombre, promoción de propiedades en constructores, tipos unión, `str_contains/str_starts_with/str_ends_with`, `mixed`, `static` como retorno.
2. **Sin Composer, sin Node en producción, sin build.** JS vanilla ES2020 (sin módulos que requieran bundler; usa scripts clásicos con IIFE o `type="module"` solo si es necesario). Cero recursos externos en tiempo de ejecución (sin CDN, sin Google Fonts remoto, sin imágenes remotas).
3. **Idioma:** TODO texto visible, mensajes de error, correos y documentación en español natural de Guatemala (tuteo cálido y profesional; "cita", "reservar", "anfitrión/a", quetzales, `+502`, fecha día/mes/año). Nunca traducción literal. Cero texto de relleno (lorem ipsum).
4. **Sin marcas de terceros** en interfaz, código ni textos (excepción neutral: "Google Calendar", "Outlook", "Apple Calendar", "WhatsApp", "Zapier", "Make", "Jitsi" al describir integraciones, sin implicar afiliación). No copies código/diseño/textos de ningún producto. Todo es original.
5. **Seguridad:** el 100 % de las consultas con sentencias preparadas (usa `App\Core\Db`); toda salida con `e()`; CSRF en todo POST; autorización por rol en servidor; protección IDOR (un anfitrión solo ve lo suyo: `Auth::scopedHostId()`); nunca mostrar excepciones al usuario (se registran con `Logger`).
6. **CSP estricta:** `script-src 'self'`, `style-src 'self' 'nonce-…'`. Por eso: **prohibido `<script>` en línea y atributos `style=""` / `onclick=""`**. Para datos dinámicos usa `data-*`, `<script type="application/json" id="boot">` (no ejecutable, permitido) y `vars([...])` (atributo `data-vars` que `ui.js` aplica como variables CSS). Un bloque `<style nonce="<?= e(nonce()) ?>">…</style>` sí está permitido.
7. **Fechas:** se guardan en **UTC** (`Y-m-d H:i:s`). Se convierten con `App\Core\Tz` / `DateTimeZone` según la zona del anfitrión/invitado. Nunca `date('...')` con la hora local del servidor. El tiempo actual siempre con `App\Core\Clock::now()` (permite simular en pruebas).
8. **Accesibilidad WCAG AA:** foco visible, navegación completa por teclado, ARIA correcto, `prefers-reduced-motion` respetado (sin barridos ni sellos animados), contraste AA, estados vacío/carga/error diseñados.
9. **Sin código muerto, sin credenciales, sin rutas absolutas del entorno, sin archivos de prueba dentro de las carpetas que se entregan** (las pruebas viven en `tests/`).
10. **Rendimiento:** página pública < 1 MB, imágenes WebP con carga diferida, fuentes locales `woff2`.
11. Estilo de código: `declare(strict_types=1);`, PSR-12 razonable, comentarios breves y en español solo donde aclaren el "por qué".

## 1. Arquitectura
```
index.php            controlador frontal            cron.php   cron por CLI y URL con token
app/bootstrap.php    arranque (APP_ROOT, BASE_PATH, autoloader PSR-4 "App\" -> app/, Helpers.php, Config)
app/Core/            framework propio (ver §2)
app/Controllers/{Pub,Admin,Api}/   controladores (clases finales; namespace App\Controllers\Pub|Admin|Api)
app/Services/        lógica de negocio     app/Repositories/  acceso a datos reutilizable
app/Views/           vistas PHP (layouts/, admin/, public/, errors/, mail/)
app/routes/*.php     rutas (cada archivo `return static function (Router $r): void { ... };`) — cada agente tiene SU archivo
database/migrations/ migraciones numeradas (NNN_nombre.sql | NNN_nombre.php)
assets/{css,js,img,fonts,vendor}   estáticos       storage/  uploads, logs, cache, sessions, backups (bloqueado)
```
Subcarpeta: toda URL generada usa `url('/ruta')` (respeta `BASE_PATH`), nunca rutas absolutas escritas a mano. En JS: lee `<meta name="base-path">` (en el panel) o `data-base` del contenedor.

## 2. API del núcleo (`app/Core`) — léelo antes de escribir código
- `Db` (estático): `q($sql,$params)`, `all`, `one`, `val`, `col`, `exec`, `insert($tabla,$arr):id`, `update($tabla,$arr,$where,$params):filas`, `delete`, `tx(callable)`, `lastId()`. Marcadores solo `?`.
- `Settings`: `get($k,$def)`, `int`, `bool`, `set`, `setMany`, `all`, `tz()` (zona del negocio). Claves y valores por defecto en `Settings::defaults()` (solo el agente Admin4 edita `Settings::defaults()`; los demás leen con valor por defecto explícito `Settings::get('clave','defecto')` y listan en su informe las claves nuevas que usan).
- `Request` (`$req`): `get/input/str($k,$max)/int/bool/all/json/header/ip/isHttps/wantsJson/baseUrl/userAgent/isMobile`, `->post`, `->query`, `->files`, `->path`, `->method`.
- `Response`: `html/json/text/redirect/file/download`. `Controller` base: `view($tpl,$data,$layout)`, `json`, `redirect($path,$q)`, `flash($tipo,$msg)` (tipos: success, error, warn, info), `abort($status)`, `fail($req,$msg,$back)`.
- `View::render($tpl,$data,$layout)`; plantillas en `app/Views/<tpl>.php`. Layouts existentes: `layouts/public`, `layouts/admin`, `layouts/auth` (variables: `$title`, `$styles` y `$scripts` = arrays de rutas dentro de `assets/`, `$bodyClass`, `$active` (ruta activa del menú), `$embed`, `$noindex`).
- Helpers globales (`Helpers.php`): `e()`, `ej()` (JSON en atributo), `json_script()`, `url()`, `abs_url()`, `asset()`, `nonce()`, `csrf_field()`, `csrf_token()`, `public_csrf_field($scope)`, `public_csrf_token($scope)`, `setting()`, `money()`, `partial($tpl,$data)`, `icon($nombre,$clase)`, `vars([...])`, `sel()`, `chk()`.
- `Fmt`: `money`, `dateLong`, `dateShort`, `time`, `dateTime`, `tzLabel`, `duration`, `dayName`, `monthName`. `Tz`: `valid/safe/ts/fromTs/localToTs/localToUtc/format/formatTs/iso/parseIso/startOfLocalDay/list`. `Clock::now()/utc()`. `Str`: `token()`, `slug()`, `uniqueSlug()`, `template()`, `phone()` (normaliza a dígitos con 502), `phoneDisplay()`, `clean()`, `ipTrunc()`, `richText()`. `Validator`: `email/date/time/color/url/slug/intRange/money…`. `Crypto`: `hashPassword`, `encrypt/decrypt` (secretos en settings), `hmac`, `sign`. `Cache`, `RateLimiter::hit($cubeta,$limite,$ventanaSeg)`, `Upload::store($_FILES['x'],$opts)`, `Upload::url($fileId)`, `Auth` (`user()`, `role()`, `can($area)`, `hostId()`, `scopedHostId()`, `canAccessHost()`, `audit($accion,$entidad,$id,$detalle)`), `Csrf`, `Session`, `Logger`, `Totp`, `ApiAuth`, `Migrator`, `AdminNav`.
- Rutas: `$r->get($patrón, [Clase::class,'metodo'], $opts)`. Patrones: `/e/{slug}`, `/reserva/{token:[a-f0-9]{32}}`, `{id:\d+}`. Opciones: `auth=>true` (panel; exige sesión), `area=>'events'` (permiso por área: ver `Auth::AREAS`/`AdminNav`), `roles=>['admin']`, `csrf=>'public'|false` con `scope=>'book'` (POST público sin sesión usa token firmado; en POST autenticado el CSRF de sesión es automático — el campo `_csrf` o la cabecera `X-CSRF-Token`), `embed=>true` (permite iframe), `api=>'read'|'write'` (API v1 con clave). Un POST sin `csrf` declarado exige CSRF de sesión.
- Controladores: `public function accion(Request $req, array $p): Response|string|array`. Devuelve `$this->view('admin/eventos/lista', [...], 'layouts/admin')`.
- Áreas de permiso (clave `area` en rutas): `dashboard bookings calendar clients messages payments waitlist search profile availability calendars events schedules polls team workflows routing api analytics reviews embed settings`. Admin: todas. Recepción: dashboard, bookings, calendar, clients, messages, payments, waitlist, search, profile. Anfitrión: dashboard, bookings, calendar, availability, clients, messages, search, profile, calendars (solo lo suyo → filtra SIEMPRE por `Auth::scopedHostId()` cuando no sea null).
- Flash: `$this->flash('success','Guardado.')` y redirige; el layout admin las muestra.

## 3. Base de datos
Esquema completo en `database/migrations/001_schema.sql` (léelo: es la fuente de verdad de columnas). Si necesitas columnas/tablas nuevas crea `database/migrations/NNN_nombre.sql` con un número libre: **Servicios A: 010–019, B1: 020–029, B2: 030–039, Admin1–4: 040–069, Público: 070–079** (usa `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE` simples y compatibles con MySQL 5.7 y MariaDB 10.3). Todas las fechas `*_at`, `starts_at`… son UTC. Horarios (`schedule_rules`, `overrides`, `holidays`) son hora LOCAL de `schedules.timezone`; `weekday` 1=lunes … 7=domingo.

## 4. Servicios — CONTRATOS (namespace `App\Services`)
Cada servicio lo escribe el agente indicado; los demás lo llaman exactamente así. Todos los métodos deben ser robustos: validar, capturar excepciones de red/archivo, devolver errores amigables.

### Núcleo de reservas (escrito por el coordinador — ya disponible o en camino)
```php
HolidayService::ensureYear(int $year): void; ::regenerate(int $year): int; ::easter(int $year): string // Y-m-d
EventRepository::find(int $id): ?array       // fila + 'hosts'=>[{...host, weight, priority}], 'resources'=>[...], 'fields'=>[custom_fields activos aplicables], 'duration_list'=>[30,60]
EventRepository::findBySlug(string $slug): ?array
EventRepository::publicList(): array         // activos, visibles, no usados/caducados
EventRepository::unavailableReason(array $event): ?string  // null si se puede reservar (inactivo, caducado, enlace de un solo uso ya usado…)
EventRepository::save(array $data, ?int $id = null): int   // $data: campos de event_types + hosts[host_id=>['weight'=>,'priority'=>]] + resources[ids]; valida y lanza \InvalidArgumentException con mensaje en español
EventRepository::duplicate(int $id): int; ::singleUseCopy(int $id, ?string $expiresUtc): int; ::delete(int $id): void
AvailabilityService::slots(array $event, int $duration, string $fromUtc, string $toUtc, array $opts = []): array
   // lista ordenada de ['start'=>UTC,'end'=>UTC,'host_ids'=>[int],'resource_ids'=>[int],'seats_left'=>?int]
   // opts: host_id, exclude_booking_id, now (ts), ignore_notice (admin), nocache
AvailabilityService::slotsByDay(array $event, int $duration, string $fromDate, string $toDate, string $guestTz, array $opts = []): array // ['Y-m-d' (fecha local del invitado) => [slot,...]]
AvailabilityService::next(array $event, int $duration, array $opts = []): ?array   // primer horario libre
AvailabilityService::hostBusy(int $hostId, string $startUtc, string $endUtc, ?int $excludeBookingId = null): bool
BookingService::create(array $in): array    // ver §5; lanza BookingException (->getMessage() en español, ->errorCode)
BookingService::reschedule(int $bookingId, string $newStartUtc, array $actor, array $opts = []): array
BookingService::cancel(int $bookingId, string $reason, array $actor): void       // $actor=['type'=>'guest|user|system|api','label'=>'Nombre','user_id'=>?int]; el invitado respeta la política
BookingService::setStatus(int $bookingId, string $status, array $actor): void    // confirmed (aprobar), rejected, completed, no_show, pending
BookingService::find(int $id): ?array; ::findByToken(string $token): ?array
BookingService::guestRules(array $booking): array   // ['cancel'=>bool,'reschedule'=>bool,'reason'=>string,'deadline_utc'=>string]
BookingService::display(array $booking): array      // fila + 'event','host','hosts','attendees','answers','client' + 'when_local'
BookingService::log(int $bookingId, string $action, ?string $detail, string $actor): void
ClientService::upsert(array $d): int          // d: name,email,phone,nit,timezone,source → id (une por email, o por teléfono)
ClientService::policy(?string $email, ?string $phone): array   // ['blocked'=>bool,'require_deposit'=>bool,'noshow_count'=>int]
Hooks::booking(string $event, int $bookingId): void   // 'booking.created|approved|rescheduled|cancelled|completed|no_show' → WorkflowService + WebhookService (nunca lanza)
InstallService (instalador) · BookingException
```
Estados: `pending` (espera aprobación) · `confirmed` · `cancelled` · `completed` · `no_show` · `rejected`. "Activos" (bloquean horario): `pending`, `confirmed`.

### Servicios A — comunicación (agente "Servicios A")
```php
Mailer::queue(string $toEmail, ?string $toName, string $subject, string $html, ?string $text = null, array $attachments = []): int  // adjuntos: [['name'=>'cita.ics','mime'=>'text/calendar','content'=>'...']]
Mailer::sendNow(string $toEmail, ?string $toName, string $subject, string $html, ?string $text = null, array $attachments = []): array // ['ok'=>bool,'error'=>?string]
Mailer::processQueue(int $limit = 20): array   // ['sent'=>n,'failed'=>n]; reintentos con espera creciente (máx. 5)
Mailer::layout(string $title, string $contentHtml, array $opts = []): string   // plantilla HTML sobria de marca (tablas + estilos en línea de correo; es el ÚNICO lugar donde se permiten estilos en línea, porque es correo)
Mailer::testConnection(): array
SmtpClient (nativo: SSL/STARTTLS, AUTH LOGIN/PLAIN) con respaldo a mail() cuando smtp_host está vacío
IcsService::generate(array $bookingDisplay): string; ::generateMany(array $list): string; ::hostFeed(int $hostId): string
IcsService::parse(string $ics, int $fromTs, int $toTs): array   // [[startTs,endTs],…] ocupados (RRULE básico DAILY/WEEKLY/MONTHLY/YEARLY, COUNT/UNTIL/INTERVAL/BYDAY, EXDATE, TZID, todo el día)
IcsService::googleLink(array $b): string; ::outlookLink(array $b): string
SafeHttp::get(string $url, array $opts = []): array   // ['ok'=>bool,'status'=>int,'body'=>string,'error'=>?string]; ::post(string $url, string $body, array $headers, array $opts): array; ::validateUrl(string $url): ?string (mensaje de error o null)
   // Protección SSRF: solo http/https, resuelve DNS y bloquea IPs privadas/loopback/link-local/reservadas/metadata (IPv4 e IPv6) salvo Config::get('allow_private_http') === true (solo pruebas); fija la IP resuelta (CURLOPT_RESOLVE); límite de tamaño (2 MB) y tiempo (10 s); redirecciones revalidadas.
ExternalCalendarService::sync(int $calendarId): array; ::syncDue(int $limit = 10): array; ::testUrl(string $url): array  // nunca lanza; actualiza estado/errores/backoff en external_calendars
WorkflowService::fire(string $trigger, int $bookingId): void       // crea workflow_runs (inmediatos y programados: before_start/after_end)
WorkflowService::cancelPending(int $bookingId): void
WorkflowService::runDue(int $limit = 25): array                    // ejecuta, reintenta (3 intentos, 5/15/60 min), registra
WorkflowService::variables(array $bookingDisplay): array           // {nombre} {evento} {fecha} {hora} {anfitrion} {enlace} {direccion} {zona} {videollamada} {precio} {telefono_negocio}
WorkflowService::render(string $tpl, array $bookingDisplay): string
WorkflowService::testRun(int $workflowId, int $bookingId): array
WhatsAppService::link(string $phone, string $text): string  // https://wa.me/<digitos>?text=...
WhatsAppService::send(string $phone, string $text): array   // API Cloud opcional; ['ok','error']
WebhookService::dispatch(string $event, array $payload): void; ::bookingPayload(int $bookingId): array; ::deliverDue(int $limit = 20): array
WebhookService::sign(string $secret, string $timestamp, string $body): string  // HMAC-SHA256 hex; cabeceras X-Agenda-Event, X-Agenda-Delivery, X-Agenda-Timestamp, X-Agenda-Signature: sha256=<hex> sobre "{timestamp}.{body}"
CronService::run(string $origen): array   // origen 'cli'|'url'|'visita'; bloqueo con GET_LOCK; actualiza settings.cron_last_run; ver cron.php
```
Disparadores de workflow: `booking.created, booking.approved, booking.rescheduled, booking.cancelled, booking.completed, booking.no_show` (inmediatos) y `booking.before_start` / `booking.after_end` (con `offset_minutes`). Acciones: `email, whatsapp, whatsapp_api, webhook, set_status, review_request, add_tag`.

### Servicios B1 — ventas y crecimiento (agente "Servicios B1")
```php
PricingService::quote(array $event, int $duration, int $seats, ?string $coupon, ?string $giftCode, ?int $clientId, ?array $policy = null): array
   // ['ok'=>bool,'error'=>?string,'price','discount','total','deposit_due','coupon_id','gift_card_id','gift_applied','client_package_id','needs_payment'=>bool]
PricingService::consume(array $quote, int $bookingId): void     // dentro de la transacción de la reserva: cupón++, saldo de certificado, sesión de paquete
PricingService::release(array $booking): void                   // al cancelar: devuelve sesión de paquete / cupón
PaymentService::record(int $bookingId, array $data, ?int $userId): int; ::proof(int $bookingId, int $fileId): int; ::verify(int $paymentId, bool $ok, ?int $userId): void; ::refund(...); ::receiptData(int $bookingId): array; ::recalc(int $bookingId): void  // recalcula paid_amount y payment_status
PackageService::sell(int $clientId, int $packageId, ?int $userId, bool $paid): int; ::balance(int $clientId): array
GiftCardService::create(array $d): int; ::lookup(string $code): ?array
CouponService::validate(string $code, ?int $eventId, float $amount): array
WaitlistService::join(array $event, int $duration, array $guest, ?string $wantDate, ?int $hostId): int
WaitlistService::onSlotFreed(array $booking): void              // ofrece al siguiente con enlace /espera/{token} de 15 min (cola de correo + WhatsApp)
WaitlistService::offerByToken(string $token): ?array; ::markBooked(string $token, int $bookingId): void; ::tick(): array   // expira ofertas vencidas y pasa al siguiente
RoutingService::run(array $form, array $answers, string $ip): array   // ['action','target','reason','rule_id','redirect'=>url|null,'message'=>?string] y registra en routing_logs
RoutingService::stats(int $formId): array; ::logs(int $formId, int $limit): array
AnalyticsService::track(string $step, array $ctx): void      // step view|slot|booked; ctx: event_type_id, host_id, visit_id, utm_source, utm_medium, utm_campaign, referrer_host, device
AnalyticsService::report(string $fromDate, string $toDate, array $filters = []): array  // KPIs, embudo, por evento, por fuente, por periodo, horas/días pico, ocupación por anfitrión, ingresos, comparación con el periodo anterior
ReportService::csv(string $kind, string $fromDate, string $toDate): string; ::weeklySummary(): void  // kind: bookings|clients|payments|analytics
PollService::create(array $d, array $optionsUtc): int; ::get(string $token): ?array; ::vote(string $token, string $name, string $email, array $votes): void; ::finalize(int $pollId, int $optionId, array $actor): array; ::close(int $pollId): void
```

### Servicios B2 — plataforma (agente "Servicios B2")
```php
ProfessionService::all(): array; ::apply(string $profession, bool $demo): array   // presets: medico, dentista, psicologo, nutricionista, fisioterapeuta, veterinario, abogado, notario, contador, arquitecto_ingeniero, consultor_coach, estetica_spa, academia_tutor, ventas_reuniones, otro. Crea eventos, preguntas, formulario de enrutamiento, flujos de recordatorio (confirmación, 24 h, 2 h, reseña, no asistió…), terminología (settings terms_label…), y datos demo SI $demo
LegalService::defaults(): array (privacy,terms,cookies en español, plantillas genéricas); ::save(array): void (incrementa legal_version); ::consentText(): array; ::recordConsent(?int $clientId, ?int $bookingId, ?string $email, string $ip): void
PrivacyService::exportPerson(int $clientId): array; ::erasePerson(int $clientId): void; ::applyRetention(): int
BackupService::create(): string (ruta del .sql(.gz) en storage/backups); ::list(): array; ::path(string $name): ?string; ::prune(int $keep): void  // volcado SQL con PDO (sin mysqldump)
ConfigPortabilityService::export(): array; ::import(array $data, bool $replace): array   // JSON completo de configuración (settings sin secretos, horarios, feriados, eventos, campos, flujos, enrutamiento, paquetes, cupones…)
Controladores API v1 (app/Controllers/Api/*) + /api-docs (autogenerado desde un registro de endpoints ApiDocs)
```

### Servicios compartidos que te toca consumir
- Correo desde plantillas del negocio → `WorkflowService::render` + `Mailer::layout`.
- Nunca llames a la red directamente: usa `SafeHttp`.

## 5. `BookingService::create(array $in)` — entrada
`event_id` (int) · `duration` (min) · `start` (UTC `Y-m-d H:i:s`) · `host_id` (opcional, preferencia) · `timezone` (zona del invitado) · `name`, `email`, `phone`, `nit` · `notes` · `answers` (`[field_id=>valor]`, archivos como `file_token`) · `guests` (`[['name'=>,'email'=>]]`) · `seats` · `coupon`, `gift_code` · `utm_source/utm_medium/utm_campaign/referrer_host` · `consent` (bool, obligatorio en público) · `created_via` (`public|admin|api|waitlist|poll`) · `force` (bool, solo admin: ignora aviso mínimo/anticipación/límites/horario laboral, **nunca** el doble agendado) · `status` (opcional, solo admin) · `created_by` (user id) · `routing_log_id`.
Salida: `['booking'=>fila principal,'bookings'=>[todas (series)],'status'=>'confirmed|pending','needs_payment'=>bool,'token'=>…]`.
Códigos de `BookingException::errorCode`: `slot_unavailable`, `validation`, `blocked`, `closed`, `rate_limit`, `policy`, `payment`.

## 6. Dirección de arte "Atelier Horloger" (todo original)
Paleta (variables CSS en `core.css`): `--ink:#06080D` (medianoche), `--obsidian:#0E1118` (paneles), `--gold-1:#7A5420 --gold-2:#C9A050 --gold-3:#F0D9A0` (degradado latón), `--ivory:#F4EEDF`, texto secundario gris cálido, `--wine:#7A1F2B` SOLO errores, `--emerald:#1F5C45` SOLO confirmaciones. Tipografía: **Fraunces** (titulares, números grandes), **Manrope** (interfaz), **Space Mono** (horas y cifras tabulares). Modo oscuro por defecto y modo claro (marfil) con `data-theme="light|dark"` en `<html>`.
Firmas: esfera de reloj para elegir hora (Público), guilloché SVG generado (curvas de Lissajous/rosetas), sello de confirmación animado con `stroke-dashoffset` + vibración sutil en móvil, transición de pasos tipo cortina, contadores mecánicos (`data-ticker`), botón dorado con brillo especular que sigue el puntero (solo con ratón).

### Vocabulario de clases CSS (lo implementa `core.css`/`admin.css`; TODOS lo usan, sin inventar equivalentes)
- Diseño: `.container`, `.stack` (columna con separación), `.row` (fila flexible, `.row-between`, `.row-wrap`), `.grid` + `.cols-2/.cols-3/.cols-4` (colapsan en móvil), `.split`, `.page`, `.page-head` (con `h1.page-title`, `.page-sub`, `.page-actions`), `.divider`, `.sr-only`, `.skip-link`.
- Superficies: `.card` (`.card-head`, `.card-body`, `.card-foot`, `.card-gold` con borde de oro), `.panel`, `.guilloche` (fondo decorativo), `.stat` (`.stat-label`, `.stat-value`, `.stat-delta`), `.empty` (`.empty-title`, `.empty-text`, icono arriba), `.skeleton`, `.alert` + `.alert-ok/.alert-err/.alert-warn/.alert-info`.
- Botones: `.btn` + `.btn-gold` (principal), `.btn-ghost`, `.btn-outline`, `.btn-danger`, `.btn-sm`, `.btn-lg`, `.btn-icon`, `.btn-block`. Enlaces como botón: `<a class="btn …">`.
- Formularios: `.field` (contiene `<label>`, control, `.hint`, `.error`), `.input`, `.select`, `.textarea`, `.check` (casilla/radio con etiqueta), `.switch` (`<label class="switch"><input type=checkbox><span></span></label>`), `.form-row` (campos en fila), `.form-grid` (2 columnas), `.form-actions`, `.input-group`, `.chips` + `.chip` (`.is-active`), `.fieldset`.
- Datos: `.table-wrap` + `.table` (`.table-sm`), `.badge` (+ `.badge-gold/.badge-ok/.badge-warn/.badge-err/.badge-muted`), `.tabs` + `.tab` (`.is-active`), `.pagination`, `.avatar`, `.kbd`, `.progress`, `.toasts`, `dialog.modal` (con `.modal-head/.modal-body/.modal-foot`), `.dropdown` (`.dropdown-menu`), `.tooltip`.
- Utilidades: `.muted`, `.mono`, `.serif`, `.nowrap`, `.right`, `.center`, `.hide-sm`, `.hide-lg`, `.inline`, `.mt-1..5`, `.mb-1..5`, `.gap-1..4`, `.w-full`, `.text-gold`, `.text-ok`, `.text-err`.
- Panel: `.app`, `.sidebar`, `.sb-brand`, `.sb-nav`, `.sb-section`, `.sb-title`, `.sb-link` (`.is-active`), `.app-main`, `.topbar`, `.main`, `.cmdk-*` (paleta), `.toasts`.
- Iconos: `icon('nombre')` → sprite `assets/img/icons.svg` (ids `i-nombre`). Nombres disponibles: `calendar clock user users settings plus check x chevron-left chevron-right chevron-down chevron-up search bell mail phone whatsapp video map-pin link copy trash edit download upload menu sun moon star dollar chart list grid filter refresh alert info lock key qr code sparkle home inbox tag file drag external logout eye command flag repeat shield receipt gift package route zap globe building layers paperclip play pause undo arrow-right arrow-left dot`.
- JS global `assets/js/ui.js` (lo escribe Diseño): aplica `data-vars`, `[data-modal-open="#id"]`/`[data-modal-close]`, `[data-copy="texto|#selector"]`, `[data-confirm="mensaje"]` en formularios/botones, `[data-tabs]`, `.dropdown`, `[data-theme-toggle]`, `[data-ticker]`, `[data-sortable]` (arrastrar para ordenar: `data-url`, envía `ids[]` con CSRF), brillo especular de `.btn-gold`, y el objeto `window.Ap` con `Ap.fetchJson(url, {method, body})` (agrega CSRF de `<meta name="csrf-token">` y `X-Requested-With`), `Ap.toast(msg, tipo)`, `Ap.confirm(msg): Promise<boolean>`, `Ap.base` (ruta base), `Ap.url(path)`.
- Cada página nueva trae su CSS en el archivo de su dueño (`admin-p1..p4.css`, `public.css`, `booking.css`) y su JS en `assets/js/<nombre>.js`.

## 7. Cómo probar en local
```
php tests/dev-install.php --name=ap_<tu_nombre> --config=/tmp/ap-<tu_nombre>.config.php     # crea BD + config + admin (admin@demo.test / Demo#Admin2026)
AP_CONFIG=/tmp/ap-<tu_nombre>.config.php php -S 127.0.0.1:<PUERTO> -t . tests/router.php   # desde agenda-premium/
```
MariaDB local ya está encendido (usuario `ap` / `ap_test_pw`, host `127.0.0.1`). Puertos: Diseño 8101, Público 8102, Admin1 8103, Admin2 8104, Admin3 8105, Admin4 8106, Servicios A 8107, B1 8108, B2 8109.
Navegador headless: Chromium + Playwright están instalados (`PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers`; usa `executablePath: '/opt/pw-browsers/chromium'` si hace falta; módulo `playwright` vía `npm i playwright-core` en `/tmp`, NO dentro del proyecto). Sin acceso a internet general (solo npm/pip/apt). **No modifiques archivos de otros agentes**; si necesitas algo de otro dominio, déjalo anotado en tu informe final. Para revisar sintaxis: `php -l archivo.php`.
Cada agente trabaja SOLO dentro de sus archivos (ver tu encargo). No hagas `git commit` (el coordinador lo hace).

## 8. Pruebas automatizadas (obligatorio para servicios y APIs)
- `tests/lib/T.php` (utilidad) y `tests/run.php` (ejecutor: `php tests/run.php [filtro]`). Cada prueba es un script `tests/cases/<prefijo>_<tema>_test.php` (prefijos: `ds`, `pub`, `a1`…`a4`, `sa`, `sb1`, `sb2`). Ejemplo en `tests/cases/core_test.php`: `T::boot('nombre')` crea la BD limpia `ap_t_nombre` e instala el sistema; luego usa `T::ok/eq/throws/section` y termina con `T::done()`.
- Para probar HTTP real usa `php -S` en tu puerto con tu config (ver §7) y `curl`/Playwright. Las pruebas que levantan servidores deben apagarlos al terminar (usa el PID que tú mismo lanzaste; **no uses `pkill -f` con patrones amplios**).
- Las pruebas deben cubrir casos felices, de error, seguridad (XSS/SQLi/CSRF/permisos/IDOR) y bordes.
