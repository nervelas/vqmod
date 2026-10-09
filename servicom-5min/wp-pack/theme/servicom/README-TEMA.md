# Tema Servicom

Tema ligero para Elementor (gratis) y WooCommerce. PHP 8.0+. Sin jQuery, sin CDN, sin SEO.

## Datos del negocio
`servicom_biz('clave')` usa `sc_biz()` del mu-plugin; si no existe, `get_theme_mod('sc_<clave>')`.
Claves: nombre, telefono, whatsapp, whatsapp_msg, correo, direccion, mapa_url, horario, facebook,
instagram, tiktok, youtube, x, linkedin, footer_credit, float_wa, mobile_bar, style.
Logo = `custom_logo`; sin logo se muestra el nombre en tipografía del estilo.

## Estilos
`sc_style` (1..5) carga `assets/css/style-N.css` y añade `sc-style-N` al body.
1 Oro y Obsidiana · 2 Marfil Editorial · 3 Azul Zafiro · 4 Esmeralda Elegante · 5 Terracota Boutique.
Orden de carga: fonts.css, base.css, style-N.css, woocommerce.css (solo con WooCommerce). Se encolan
con prioridad 9999 para ir después de Elementor.

### Variables CSS (contrato §11)
`--sc-bg --sc-bg-alt --sc-surface --sc-text --sc-muted --sc-primary --sc-primary-ink --sc-accent
--sc-border --sc-radius --sc-shadow --sc-font-head --sc-font-body --sc-space-sec --sc-space-sec-m`
Extras del tema: `--sc-shadow-hover --sc-tint --sc-tint-2 --sc-radius-btn --sc-btn-bg --sc-btn-shadow
--sc-head-weight --sc-head-track --sc-head-size --sc-head-align --sc-card-ratio --sc-hero-bg
--sc-hero-deco --sc-cta-bg --sc-header-bg/ink/line --sc-footer-bg/ink/muted/accent/line` y el
alcance oscuro `--sc-dark-bg/ink/muted/surface/border/primary/primary-ink/tint` (lo usan
`sc-sec--dark`, `sc-cta`, `sc-hero` con imagen, `sc-hero--plain` y `sc-page-hero`).

### Añadir un estilo (6)
1. Copie `style-5.css` a `style-6.css` y cambie las variables (todas están al inicio).
2. Añada sus fuentes (woff2 latin/latin-ext) a `assets/fonts/` y sus `@font-face` a `fonts.css`.
3. Amplíe el rango 1..5 en `servicom_style()` (inc/helpers.php) y el mapa de precarga en
   `servicom_preload_fonts()` (inc/enqueue.php).

## Clases (contrato §11)
Secciones: `sc-sec(--alt|--dark) sc-hero(--plain) sc-hero__inner/title/sub sc-head sc-title sc-lead
sc-grid sc-card(__media|__title|__text|--icon) sc-about(__media|__body) sc-cta sc-gallery sc-contact
(__info|__form) sc-map sc-video sc-btn(--primary|--ghost) sc-reveal sc-page-hero sc-service
(__media|__body) sc-prose`. Se estilan sobre los selectores reales de Elementor con prefijo
`body .elementor` (sin `!important`). `sc-grid` es una rejilla CSS con alturas iguales (4 elementos =
2x2 o 4x1, nunca 3+1). `sc-reveal` -> `is-in` con IntersectionObserver.
Clases del tema: `sc-header(.is-scrolled) sc-nav sc-menu sc-sub-toggle sc-burger sc-footer sc-float-wa
sc-bar sc-delivery-note`. Body: `sc-style-N`, `sc-has-bar`, `sc-has-float`, `sc-is-woo`; html: `sc-js`,
`sc-nav-open`.

## Ganchos y filtros usados
- `wp_enqueue_scripts` (9999): estilos y JS; desencola wp-block-library, global-styles, emoji, embed.
- `elementor/frontend/print_google_fonts` -> false (nada de Google Fonts por CDN).
- WooCommerce: `woocommerce_before_main_content` (5 cabecera con buscador, 10 apertura), `woocommerce_after_main_content`,
  `woocommerce_add_to_cart_fragments` (contador `a.sc-cart`).
- El mu-plugin imprime la nota de entrega; el tema solo estiliza `.sc-delivery-note`.

## Plantillas
`page.php`, `front-page.php`, `single.php` muestran `the_content()` a ancho completo si la página tiene
`_elementor_edit_mode = builder`; si no, añaden `sc-page-hero` con el título.
Menús: `primary` (con submenús desplegables y acordeón móvil) y `footer` (opcional).
