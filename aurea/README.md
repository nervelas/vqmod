# AUREA · Agenda profesional premium

Sistema de citas para cualquier profesional (salud, legal, contable, belleza, educación…). PHP 8.0+, MySQL 5.7+/MariaDB 10.3+, sin Composer ni Node. Un negocio por instalación, con uno o varios profesionales.

## Instalación en 5 pasos
1. **Sube** el contenido del ZIP a la carpeta raíz del hosting (`public_html`) o a una subcarpeta; descomprime allí (sin carpeta contenedora).
2. **Crea una base de datos** MySQL/MariaDB (utf8mb4) y un usuario con todos los permisos desde tu panel de hosting.
3. Abre `https://tu-dominio.com/instalar/` y sigue el asistente de 4 pasos (requisitos → base de datos → negocio y administrador → profesión).
4. **Elimina la carpeta `/instalar`** (se bloquea sola, pero debe borrarse). Entra a `/admin` y completa el asistente de inicio.
5. **Configura el cron** (abajo) y envía un correo de prueba desde *Panel → Sistema*.

Requisitos: PHP ≥ 8.0 con `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl` (recomendado `gd`), y `mod_rewrite` (Apache/LiteSpeed). Reglas para Nginx en `docs/nginx.conf.example`.

## Cron (recordatorios, correos, lista de espera)
En cPanel → *Tareas Cron*, cada minuto (o cada 5):
```
* * * * * /usr/local/bin/php /home/USUARIO/public_html/cron.php
```
Si tu hosting solo permite URL: `* * * * * curl -s 'https://tu-dominio.com/cron.php?token=TU_TOKEN' >/dev/null` (el token está en *Panel → Sistema*).
Sin cron, AUREA usa un **modo de respaldo** que ejecuta las tareas con las visitas (como máximo cada 5 min); los recordatorios por correo serán menos puntuales.

## Widget para WordPress / tu sitio web
*Panel → Compartir y widget* da el código listo. En WordPress: edita la página → bloque **HTML personalizado** → pega el código. Opciones: botón flotante "Agendar cita", reserva incrustada o iframe.

## Respaldo
*Panel → Sistema → Descargar respaldo (.sql)*. Para restaurar, importa el archivo con phpMyAdmin. Respalda además la carpeta `storage/private` (comprobantes y archivos de clientes) y `uploads/`.

## Notas
- Todos los textos de la interfaz pueden editarse en `lang/es.php`; la terminología (Paciente/Cliente, Cita/Consulta…) y los mensajes, desde el panel.
- WhatsApp: el centro *Mensajes por enviar hoy* abre `wa.me` con el mensaje redactado (sin costo). La API de WhatsApp Business Cloud es **opcional** y requiere cuenta de Meta propia y plantillas aprobadas.
- Pagos: transferencia/depósito con comprobante, registro manual y un enlace de pago externo configurable. AUREA **no almacena datos de tarjetas** ni integra pasarelas por API. La facturación electrónica (FEL) no está incluida; solo el campo NIT.
- Actualizaciones futuras: coloca los nuevos archivos y entra al panel; las migraciones numeradas de `database/migrations/` se aplican solas.
- Licencias de fuentes y librerías: `CREDITOS.txt`.
