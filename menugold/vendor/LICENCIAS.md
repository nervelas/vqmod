# Librerías de terceros incluidas

| Librería | Uso | Licencia |
|---|---|---|
| **PHPMailer** 6.9.3 | Envío de correo por SMTP | LGPL-2.1 (`vendor/PHPMailer/LICENSE`) |
| **phpqrcode** (Dominik Dzienia) | Generación de códigos QR | LGPL-3.0 (`vendor/phpqrcode/LICENSE`) |
| **Chart.js** 4.4.7 | Gráficas del panel | MIT (`assets/vendor/chart.js-LICENSE.md`) |
| **Fraunces** e **Inter** | Tipografías, servidas desde el propio dominio | SIL Open Font License 1.1 |
| **Fraunces.ttf** (`tools/fuentes/`) | Solo para generar logotipos e iconos | SIL OFL 1.1 (`tools/fuentes/OFL.txt`) |

Cambios locales sobre `phpqrcode`, necesarios para PHP 8.2+:
las constantes de `qrconfig.php` se envolvieron en `if (!defined(...))` para poder
configurar la carpeta de caché desde la aplicación; se marcaron las clases con
`#[\AllowDynamicProperties]`; y dos divisiones se pasaron a `intdiv()` para evitar
avisos de pérdida de precisión. Se eliminó `qrvect.php` porque no se usa.

El generador de PDF (`app/Core/Pdf.php`), el lector y escritor de Excel
(`app/Core/Xlsx.php`) y el resto del sistema son código propio de MenúGold.
