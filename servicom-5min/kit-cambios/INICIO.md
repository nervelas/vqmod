# Cómo usar este kit (para el dueño)

1. Cree una carpeta nueva para la web del cliente, por ejemplo `cambios-bufete-perez/`.
2. Dentro ponga: **esta carpeta del kit** (`kit-cambios/` con todo su contenido), la carpeta **`wp-content`** de la web (descargada de cPanel; si pesa mucho,
   puede omitir `wp-content/uploads` salvo que el cambio involucre imágenes) y el **volcado `.sql`** de la base de datos (cPanel → phpMyAdmin → Exportar).
   **No** incluya `wp-config.php`.
3. Abra esa carpeta en Claude Code. Claude leerá `CLAUDE.md` solo.
4. Escriba el cambio y la dirección de la web. Ejemplo: «La web es https://bufete.servicom.gt — cambia el teléfono a 5555 1234, agrega el servicio
   Mediación familiar y cambia la frase de la portada por …».
5. Cuando termine, Claude le dará un ZIP en `entregas/` y los pasos para subirlo. Suba el ZIP a la raíz del sitio, **Extract**, y abra la web una vez.
6. Para el siguiente cambio: descargue de nuevo un volcado `.sql` actualizado (la web pudo cambiar) y repita desde el paso 4.
