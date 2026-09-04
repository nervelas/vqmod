# AGROINCO — Descargas de la entrega final (v1.0.2)

**Versión final: limpieza de malware + rediseño con encabezado en amarillo de marca + blindaje de seguridad.**

## Descarga recomendada (un solo clic cada una)

| Qué es | Enlace |
|---|---|
| **Sitio completo** (un solo ZIP, ~435 MB) | https://codeload.github.com/nervelas/vqmod/zip/refs/heads/entrega-sitio-completo |
| **Base de datos** (ZIP de 4 MB, se importa tal cual en phpMyAdmin) | https://github.com/nervelas/vqmod/raw/entrega-sitio-completo/BASE-DE-DATOS-agroinco.sql.zip |

El ZIP del sitio se extrae con el descompresor de Windows (clic derecho → "Extraer todo").
Lo que está DENTRO de la carpeta extraída va a `public_html`.

## Alternativa: este mismo directorio
- `agroinco-limpio.z01` a `.z04` + `agroinco-limpio.zip` — el mismo sitio v1.0.2 en partes (descargar TODAS a una misma carpeta y extraer con WinRAR/7-Zip).
- `agroinco-bd-limpia.sql` — la base de datos en SQL plano.
- `INFORME.md` — informe de limpieza, credenciales nuevas y checklist de subida.
- `TABLA-SEO-VERIFICACION.md` — verificación SEO de las 75 URLs.

## Después de subir
1. Borrar la carpeta `wp-content/cache` del hosting y recargar con Ctrl+F5.
2. Cambiar contraseñas de cPanel, FTP y MySQL.
3. Entrar a wp-admin (`Admin-SMS`) con la contraseña del INFORME y cambiarla.
4. Ejecutar el primer escaneo de Wordfence y programar el respaldo en BackWPup.
