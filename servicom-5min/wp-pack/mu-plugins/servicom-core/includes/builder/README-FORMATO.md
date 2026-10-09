# Constructor de sitios: formato de datos de Elementor y WooCommerce

Escrito para **Elementor 3.2x (gratis)** y **WooCommerce 8.x**. En el sandbox no hay acceso a
wordpress.org, así que lo que sigue se probó solo contra los simuladores de `wp-pack/tests-only`
(NO son los plugins reales). Al final se listan los puntos sin validar.

## Estructura general de `_elementor_data`
Lista de contenedores raíz. Cada elemento: `{id, elType, settings, elements, isInner}` (+ `widgetType` en widgets).
- `id`: 7 hex, determinista (`md5(semilla|n)`), único por documento (`SC_El::id()`).
- Solo `elType` = `container` | `widget`. Prohibido `section/column` y el widget `html`.
- Metadatos de página: `_elementor_data` (JSON con `wp_slash`), `_elementor_edit_mode=builder`,
  `_elementor_template_type=wp-page`, `_elementor_version`, `_elementor_page_settings={hide_title:yes}`,
  `_wp_page_template=default`, y `_sc_hide_title=1` (para el tema), `_sc_key=page:<clave>`.

## Contenedor (`container`, flexbox)
Ajustes usados: `content_width` (`boxed`|`full`), `boxed_width {unit,size,sizes}`, `flex_direction`
(+`_tablet`/`_mobile`), `flex_gap {column,row,isLinked,unit,size}`, `flex_wrap`, `flex_justify_content`,
`flex_align_items` (valores `flex-start|center|flex-end|space-between`), `min_height {unit:vh|px}` +
`min_height_type`, `padding` (dimensión), `css_classes`, `_element_id`, `animation=fadeInUp`
+ `animation_duration`, fondo: `background_background` = `slideshow` | `gradient`, y overlay
`background_overlay_background/_color/_opacity`.

Slideshow (banner, 1 a 3 fotos): `background_slideshow_gallery [{id,url}]`, `_loop`, `_slide_duration`,
`_slide_transition` (nombre real del control) y `_transition` (alias redundante pedido por el contrato),
`_transition_duration`, `_ken_burns`, `_background_size`.
Sin banner: clase `sc-hero--plain` + fondo `gradient` (colores del estilo), nunca slideshow vacío.

## Widgets usados (todos gratuitos)
heading (`title`, `header_size`, `link`), text-editor (`editor`), image (`image{url,id,alt}`, `image_size`,
`link_to`, `link`), icon (`selected_icon{value,library}`, `size`), button (`text`, `link`, `align`, `size`),
image-gallery (`wp_gallery`, `thumbnail_size`, `gallery_columns`, `gallery_link=file`, `open_lightbox`),
google_maps (`address`, `zoom`, `height`), video (`video_type=youtube`, `youtube_url`, `yt_privacy`, `lazy_load`),
shortcode (`shortcode`). Implementados en `el.php` pero no usados por ahora: icon-box, image-box,
image-carousel, icon-list, social-icons, accordion. Prohibido: html. divider/spacer no se usan.
`SC_El::validate()` rechaza cualquier otro tipo y ids duplicados.

## Datos del negocio
Siempre por shortcodes (`[sc_telefono_link]`, `[sc_correo]`, `[sc_direccion]`, `[sc_horario]`, `[sc_redes]`,
`[sc_mapa_link]`) o los propios del constructor (`[sc_wa_btn]`, `[sc_contact_form]`, `[sc_product_search]`),
que leen `sc_biz()` / theme_mods en cada vista. Nunca se escriben literales en `_elementor_data`.

## Kit global
Post `elementor_library` (`_sc_key=kit:main`, `_elementor_template_type=kit`) y opción `elementor_active_kit`.
`_elementor_page_settings`: `system_colors` (primary/secondary/text/accent), `custom_colors`,
`system_typography` (primary/secondary/text/accent con `typography_font_family/weight`), `container_width`,
`space_between_widgets`, `container_padding`, tipografía/color de body y h1-h6, botones. Familias: las que
autoaloja el tema. Opciones: `elementor_google_font=0` (sin Google Fonts), `elementor_disable_color_schemes`,
`elementor_disable_typography_schemes`, `elementor_allow_tracking=no`, `elementor_experiment-container=active`.
Tokens por estilo en `tokens.php` (`sc_style_tokens()`, lee `--sc-*` de `style-N.css` del tema si existe).

## Formularios
`[sc_contact_form]` propio (nonce, honeypot, tiempo mínimo, límite por IP, `wp_mail`, sin guardar datos) es el
respaldo y el valor por defecto. Si Contact Form 7 está activo se crea su formulario y solo se usa si su
shortcode renderiza un `<form>`. Fluent Forms: no se crea formulario programáticamente (estructura interna no
verificable); se usa el propio.

## WooCommerce (`store.php`)
Opciones `woocommerce_*` (GTQ, GT, sin impuestos, stock, correos), BACS con `woocommerce_bacs_accounts`,
COD opcional, cheque/PayPal desactivados, zona de envío "Guatemala" con tarifa plana 0 ("Entrega a coordinar"),
categorías (`product_cat`, `_sc_key` en termmeta), productos con la API CRUD (`WC_Product_Simple`) o, sin
ella (simulador), con posts/meta equivalentes. Páginas Tienda/Carrito/Finalizar/Mi cuenta con shortcodes.
La página Tienda lleva solo intro + `[sc_product_search]`: el listado lo imprime el archivo de WooCommerce
(poner `[products]` en el contenido lo duplicaría).

## NO validado contra los plugins reales
- Que Elementor 3.2x acepte tal cual todos los ajustes (nombres de controles según memoria de la API
  pública): sobre todo `background_slideshow_*`, `flex_gap`, `min_height_type`, `_elementor_page_settings` del kit.
- La API CRUD de WooCommerce y `WC_Shipping_Zone` (ruta real no ejecutada), las opciones `woocommerce_*`.
- Creación del formulario de Contact Form 7.
- Regeneración real del CSS (`\Elementor\Core\Files\CSS\Post::create($id)->update()`).
- Iconos Font Awesome (el sandbox no tiene FA: el simulador pinta una estrella genérica).
