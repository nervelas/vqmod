# CGM – Crea Grandes Momentos · WordPress + WooCommerce

Tienda profesional, premium y **100 % administrable** para la marca **CGM (Crea Grandes Momentos)**: termos, pachones y accesorios. Construida sobre WordPress + WooCommerce con:

- **Tema:** `cgm-lifestyle`
- **Plugin:** `cgm-core`

Todo el contenido visible (logo, textos, banners, slider, badges, contacto, redes, políticas, combos, personalización, footer, SEO) se edita desde el administrador — **sin tocar código**.

---

## 1. Requisitos

| Componente | Mínimo |
|---|---|
| WordPress | 6.2+ |
| PHP | 7.4+ (probado en 8.4) |
| WooCommerce | 7.0+ (**obligatorio** para la tienda) |

> **Importante:** WooCommerce **no** viene incluido. Instálalo desde *Plugins → Añadir → “WooCommerce”* antes o después de activar CGM. El tema y el plugin funcionan sin él, pero la tienda (carrito, productos, checkout, combos, personalización) requiere WooCommerce.

No requiere Elementor Pro, ACF Pro, ni ningún plugin premium o servicio de pago externo para funcionar.

---

## 2. Instalación (rápida)

1. **Sube el plugin:** *Plugins → Añadir plugin → Subir plugin* → `cgm-core.zip` → **Instalar** → **Activar**.
2. **Sube el tema:** *Apariencia → Temas → Añadir tema → Subir tema* → `cgm-lifestyle.zip` → **Instalar** → **Activar**.
3. **Instala WooCommerce** (si aún no está) y completa su asistente.
4. **Carga el contenido inicial:** menú *CGM → Importar contenido → Ejecutar importación*.
   - Crea categorías, productos, páginas, slides del hero, menú principal e iconos de confianza con el contenido oficial de CGM.
   - Es **idempotente**: puedes ejecutarlo varias veces; no duplica ni borra nada.
5. Ajusta lo que necesites en el menú **CGM** (pestañas) y en **WooCommerce**.

---

## 3. Después de instalar (pasos del cliente)

- **Fotografías de producto:** *Productos → Editar → Imagen del producto / Galería.*
  (Los productos se crean sin foto a propósito; se cargan aquí cuando estén listas.)
- **Colores por foto:** convierte el producto a **Variable**, crea el atributo **Color** con sus términos y asigna **una imagen a cada variación**. Al elegir un color, la foto principal cambia automáticamente y se muestra como *swatch*.
- **Envíos:** *WooCommerce → Ajustes → Envío* (zonas y tarifas por destino/peso).
- **Pagos:** *WooCommerce → Ajustes → Pagos* — activa **Depósito bancario** (BACS) y añade la pasarela de tarjeta/VisaLink cuando tengas las credenciales. Los logos Visa/Mastercard/VisaLink de la home son **solo visuales** (se activan/desactivan en *CGM → Políticas*).
- **Combos:** *CGM → Productos y Combos* — porcentaje de descuento y categorías elegibles.
- **Personalización:** se activa por producto (*Datos del producto → General → “Permitir personalización CGM”*) o automáticamente en la categoría *Personalizados*. Cargo y longitud máxima en *CGM → Personalizados*.

---

## 4. ¿Qué es administrable?

Desde el menú **CGM** (pestañas): General (logo, favicon, nombre), Contacto y Redes, Barra superior, ¿Quiénes Somos?, Misión/Visión/Filosofía, Productos y Combos, Personalizados, Accesorios, Políticas (envío, cambios, pago seguro, compra responsable + logos de pago), Atención Personalizada, Footer, SEO y Confianza (badges). El **Hero/Carrusel** se gestiona en *CGM → Slides (Hero)* (añadir, reordenar por “Orden”, activar/desactivar, imagen desktop/móvil, título, descripción, CTA y URL).

Productos, categorías, precios, inventario, cupones, pedidos, clientes, envíos y pagos se gestionan con **WooCommerce** nativo.

---

## 5. Notas técnicas

- **Seguridad:** sanitización de entradas, escape de salidas, nonces, verificación de capacidades y APIs nativas. No modifica el núcleo de WordPress ni WooCommerce (usa hooks/filtros).
- **Rendimiento:** CSS/JS propios ligeros (sin librerías pesadas), imágenes responsive nativas, LCP del hero con `fetchpriority=high`, lazy-loading en el resto, `prefers-reduced-motion` respetado.
- **SEO:** HTML5 semántico, un H1 por página, Schema Organization/WebSite/Product (WooCommerce), Open Graph. Se desactiva solo si detecta **Yoast** o **Rank Math**.
- **i18n:** listo para traducción (text domains `cgm-lifestyle` y `cgm`).

---

## 6. Reconstruir los ZIP

```bash
./build.sh
# genera dist/cgm-lifestyle.zip y dist/cgm-core.zip
```

---

## 7. Contenido y contacto (del cliente)

- WhatsApp asesor: `https://wa.me/message/PDEHSEOR4N6TG1` · WhatsApp visible: **5978-0608**
- Correo: **servicioalcliente@cgmlifestyle.com**
- Facebook / Instagram / TikTok configurados en *CGM → Contacto y Redes*.

Todo el texto, precios y características provienen del documento oficial del cliente. No se inventó información: los datos faltantes (fotos de producto, tarifas de envío, credenciales de pasarela) quedan preparados para completarse desde el administrador.
