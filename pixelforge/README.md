# PixelForge

1. Sube todo el contenido de este ZIP a la carpeta raíz de tu dominio (`public_html/`).
2. Abre tu dominio en el navegador.
3. Crea tu contraseña y empieza a generar: Pollinations funciona sin ninguna API key.

---

## Proveedores (verificado en septiembre de 2026)

| Proveedor | Gratis | Qué necesitas |
|---|---|---|
| **Pollinations.ai** (por defecto) | Sí, sin cuenta ni tarjeta | Nada. Límite anónimo: 1 imagen cada 15 s. La app espera sola ese intervalo. |
| **Hugging Face** (`black-forest-labs/FLUX.1-schnell`) | Capa gratuita muy corta | Token gratuito de huggingface.co. Las cuentas gratuitas reciben unos **0,10 USD al mes** de crédito de *Inference Providers*: alcanza para unas pocas imágenes al mes. Sirve como respaldo, no como proveedor principal. |
| **Google Gemini** (`gemini-2.5-flash-image`) | Sí vía AI Studio, con matices | API key gratuita de [aistudio.google.com](https://aistudio.google.com). Google ya **no publica cuota gratuita para sus modelos de imagen más nuevos** y cambia los identificadores con frecuencia (el antiguo `gemini-2.5-flash-image-preview` se apagó en enero de 2026). Si tu key deja de funcionar, cambia el nombre del modelo en el panel. |

Los tres siguen teniendo acceso gratuito, así que ninguno se descartó. Los dos opcionales vienen **desactivados**: actívalos en `/admin` solo si añades su key.

## Cómo funciona

- **Prompt exacto**: el texto se envía tal cual. Solo se le añade el sufijo fotográfico si activas «Potenciar realismo» (el sufijo se edita en `/admin`).
- **Tamaño exacto**: se pide al proveedor el tamaño soportado más cercano y el servidor recorta y escala a los píxeles exactos con GD o Imagick, sin deformar.
- **Respaldo en cadena**: si un proveedor falla o agota su cuota, se intenta con el siguiente activo y la tarjeta muestra cuál generó la imagen.
- **Prompt negativo**: solo Hugging Face lo admite; Pollinations y Gemini no lo tienen en su API y la app lo avisa en pantalla en lugar de alterar tu prompt.

## Requisitos

PHP 8.0 o superior con cURL y GD (o Imagick). SQLite es opcional: si falta, el historial y los ajustes se guardan en archivos JSON automáticamente. Nada más: sin Composer, sin Node, sin instalador.

## Estructura

```
index.php        estudio, login y auto-instalación
api.php          API interna (generar, historial, presets)
download.php     entrega de imágenes y ZIP
admin/           panel de administración
app/             código de la aplicación (sin acceso web)
views/           plantillas (sin acceso web)
assets/          CSS y JS
storage/         base de datos, imágenes, miniaturas, logs (sin acceso web)
```

## Mantenimiento

- Errores: `storage/logs/app.log`, visible también en `/admin`.
- Copia de seguridad: basta con guardar la carpeta `storage/`.
- Para reinstalar desde cero, borra `storage/db/` y vuelve a abrir el dominio.
