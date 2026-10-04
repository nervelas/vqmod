# Aspectos legales y de privacidad implementados

> Este documento describe lo que el sistema hace técnicamente. **No es asesoría legal** ni promete cumplimiento de ninguna ley específica.
> Las plantillas de aviso de privacidad, términos y cookies son genéricas: **deben ser revisadas por un abogado** antes de publicarse.

## Datos personales
- **Minimización:** se piden solo nombre, correo, teléfono (según el evento), NIT opcional, respuestas a las preguntas que el negocio configure y notas.
- **Consentimiento explícito y registrado:** antes de guardar datos de una reserva se exige marcar la casilla de consentimiento. Se registra fecha (UTC), correo, documento, versión del texto vigente, huella (hash) del texto e **IP truncada** (IPv4 /24, IPv6 /48). Tabla `consents`.
- **Textos editables:** aviso de privacidad, términos de uso y aviso de cookies se editan desde el panel (Privacidad y términos). Cada cambio incrementa la versión.
- **Acceso y eliminación:** desde la ficha de cada cliente se puede **exportar** todos sus datos (JSON) y **eliminar/anonimizar** sus datos (se conservan únicamente agregados contables sin datos personales).
- **Retención configurable:** borrado/anonimización automática tras X meses (0 = nunca), ejecutada por el cron.
- **Registro de auditoría** de acciones del panel (usuario, acción, IP truncada).

## Cookies y rastreo
- Páginas públicas **sin cookies**. El panel usa solo la cookie de sesión estrictamente necesaria (HttpOnly, SameSite=Lax, Secure con HTTPS).
- Analítica **propia**, sin cookies ni rastreadores de terceros: se guardan eventos agregados (vista, horario elegido, reserva), UTM y dominio de referencia; sin IP.
- No hay recursos externos en tiempo de ejecución (fuentes, scripts y estilos son locales). Excepciones **solo si el administrador las activa**: Turnstile o hCaptcha (script del proveedor) y WhatsApp Cloud API (llamada del servidor).

## Pagos
- **No se almacenan datos de tarjetas.** Se registran pagos (efectivo, transferencia/depósito con comprobante, tarjeta en sitio, enlace de pago externo configurable) y su estado.

## Seguridad técnica (resumen)
Sentencias preparadas, escape de salida, CSRF, contraseñas con ARGON2ID/BCRYPT, sesiones endurecidas, límite de intentos de acceso, 2FA TOTP opcional, subida de archivos con lista blanca + `finfo` + renombrado aleatorio + entrega autorizada, cabeceras de seguridad (CSP sin scripts en línea), protección SSRF al importar calendarios, firmas HMAC-SHA256 en webhooks, claves de API con límite de frecuencia.

## Marcas y licencias
Ver `LICENCIAS.txt` y `CREDITOS.txt`. Las marcas de terceros (Google Calendar, Outlook, Apple Calendar, WhatsApp, Zapier, Make, Jitsi) se mencionan solo para describir compatibilidad técnica, sin afiliación.
