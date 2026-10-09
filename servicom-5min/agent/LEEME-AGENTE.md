# Agente de Servicom (segundo hosting)

Ejecuta **localmente** la creación de webs de clientes en este hosting, con el token de cPanel de **este** hosting. El portal le envía órdenes firmadas (HMAC-SHA256 + marca de tiempo ±120 s + nonce de un solo uso).

## Instalación (4 pasos)
1. Cree en cPanel un subdominio o carpeta para el agente (p. ej. `https://hosting2.tudominio.com/agente/`) y suba/descomprima aquí este ZIP.
2. Abra `instalar-agente.php` en el navegador: pida usuario y **token de API** de cPanel de ESTE hosting, la carpeta de webs (absoluta y **fuera** de `public_html`, p. ej. `/home/USUARIO/webs-clientes`), el dominio base y la URL del portal. Muestra la **URL del agente** y un **secreto** (cópielo; no se vuelve a mostrar). El instalador se elimina solo; la configuración queda en `../servicom-agent-secrets/config.php` (o `storage/config.php` protegido).
3. Construya el paquete base de WordPress de este hosting (una vez): `php tools/build_base_agent.php` (descarga solo de wordpress.org).
4. En el portal → **Hostings → Agregar** tipo *Agente*: URL del agente (`…/agent.php`) y el secreto. Haga clic en *Diagnóstico* para verificar token, espacio y extensiones de este hosting.

## Seguridad
- Solo responde a POST firmados; cualquier otra petición recibe 403 sin detalles.
- Lista de IP permitidas opcional (en la configuración, `allowed_ips`).
- Solo descarga archivos de cliente desde `<portal>/ag/asset?…` con URL firmada de un solo uso y 15 minutos de vigencia.
- Todas las operaciones de archivos están confinadas a la carpeta de webs; el paquete `_base` y cualquier ruta fuera de ella se rechazan.
- Los errores internos se guardan en `storage/agent.log`, nunca se muestran.
