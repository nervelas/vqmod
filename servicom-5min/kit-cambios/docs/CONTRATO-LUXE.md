# CONTRATO LUXE — motor de webs de lujo (sustituye el render con Elementor)

Objetivo: cada web generada debe verse elegante, premium, de lujo, aunque el cliente dé muy poca información.
Reglas globales del `CONTRATO.md` siguen vigentes (PHP 8.0, español, sin CDN, sin secretos en código, nada de sintaxis 8.1+).

## 0. Decisiones
1. **Sin Elementor para el render.** Las páginas se generan con el tema `servicom` (secciones PHP propias + `luxe.css`/`luxe.js`). Elementor ya no se activa. (Motivo: en el hosting real Elementor 4.x no aplicó estilos al formato 3.x.)
2. **Colores = logo.** La paleta sale del logo del cliente (colores dominantes) con contraste AA garantizado. Sin logo: paleta de lujo por rubro. `style` (1–5) solo elige ambiente tipográfico/fondo (oscuro/claro), no los colores.
3. **Toda imagen tiene un `slot`.** Orden de resolución: foto del cliente/presentación → foto de stock descargada por el portal → **arte generado** (SVG inline en la paleta; nunca cajas vacías ni imágenes rotas).
4. **Todo es editable** por el cliente con un editor en vivo (ver §6) y un panel “Editar mi web”.
5. Prohibido inventar hechos verificables (años, cifras, premios, certificaciones, direcciones, teléfonos, testimonios, precios). Sí se redacta contenido persuasivo de apoyo (beneficios, proceso, preguntas frecuentes genéricas) y se pueden **sugerir servicios típicos del rubro** cuando el cliente da menos de 4 (marcados `origen:"sugerido"`).

## 1. Claves de iconos (set cerrado; el tema las implementa como SVG en línea)
Genéricos: `star shield check heart clock phone mail pin calendar users user handshake award crown gem sparkles lightbulb target rocket chat globe link lock key home building briefcase document pen book graduation chart trend coins wallet card receipt calculator percent tag gift bag cart truck box plane ship route map compass camera image video music play headset wrench gear hammer bolt car tools oil tire battery shirt scissors ruler hanger utensils coffee wine cake chef leaf flame droplet sun moon stethoscope pulse tooth pill syringe microscope eye brain bone baby paw scales gavel columns contract stamp fingerprint container warehouse barcode flag medal diamond thumbs trophy percent-badge support wifi cloud code printer`
Redes: `facebook instagram tiktok youtube x linkedin whatsapp telegram`
Por rubro (primeros = más frecuentes): abogado `scales gavel columns contract shield handshake book` · clinica `stethoscope pulse tooth pill heart microscope calendar` · taller `wrench gear car oil tire battery bolt` · ropa `shirt scissors bag ruler gem tag sparkles` · restaurante `utensils wine cake coffee flame leaf chef` · transporte `truck route box compass clock shield plane` · contabilidad `calculator chart coins receipt percent document wallet` · importaciones `globe ship container box plane barcode warehouse` · otro `star sparkles award briefcase check handshake gem`.

## 2. Textos ampliados (`manifest.texts`, además de §6 de CONTRATO.md)
```
hero_eyebrow(<=40)            // sobretítulo, p. ej. «Despacho jurídico · Guatemala» (rubro + ubicación solo si el cliente la dio)
nosotros_lead(<=200)          // frase destacada de «Nosotros»
cita(<=140)                   // frase de impacto (derivada de la frase/apoyo del cliente; sin atribuir a personas)
valores_titulo(<=60)
valores:[{titulo(<=36),texto(<=150),icono}]            // 4 a 6, icono ∈ claves §1
proceso_titulo(<=60)
proceso:[{titulo(<=36),texto(<=150)}]                  // 3 a 4 pasos genéricos del rubro («Conversamos», «Propuesta», …)
faq_titulo(<=60)
faq:[{p(<=110),r(<=330)}]                              // 4 a 6; respuestas no inventan datos: remiten a contacto/horario/WhatsApp del cliente
seo_descripcion(<=158)
servicios:[{resumen,descripcion,icono}]                // icono ∈ claves §1 (obligatorio)
```
`content.servicios[]` admite `origen:"form"|"pres"|"sugerido"` y `icono` (clave §1).

## 3. Imágenes en el manifiesto
`assets[]` añade `role` ∈ `logo|banner|servicio|galeria|about|stock` y `credit` (texto, opcional).
`content.stock`: `{"hero":["a10","a11"],"about":["a12"],"servicios":["a13",...],"galeria":["a20",...]}` (ids de assets; el portal los asigna; pueden faltar → arte generado).
Prioridad por slot (la resuelve el builder WP): foto del cliente > `content.stock.*` > arte.

## 4. Modelo de contenido del sitio (`option sc_site`, JSON; lo escribe el builder y lo edita el editor)
```
{ "v":1,
  "design":{ "palette":{bg,bg2,surface,ink,muted,primary,primary_ink,accent,accent_ink,line,dark,dark_ink,glow},
             "fonts":{"head":"cormorant|playfair|fraunces|dmserif|sora","body":"inter|manrope|dmsans|lato|nunito"},
             "mood":"dark|light", "hero":"split|center|full", "motif":"scales|pulse|gear|fabric|plate|route|chart|globe|abstract", "seed":int },
  "brand":{"nombre","logo":attachmentId|0,"eyebrow"},
  "pages":{ "home":{"sections":[S...]}, "nosotros":{...}, "servicios":{...}, "galeria":{...}, "contacto":{...}, "tienda":{...} },
  "services":[{"id":"s1","nombre","resumen","descripcion","icono","img":{"id":0,"seed":n},"post":postId,"origen":"form|pres|sugerido","on":true}],
  "footer":{"texto","credit"}
}
S = {"id":"hero","type":"hero|strip|about|services|values|process|gallery|quote|faq|cta|contact|video|products|text","on":true,"data":{...}}
```
Slots de imagen: `{"id":attachmentId(0 = sin foto),"seed":int}`. Textos: string. Iconos: clave §1. Enlaces: `{"text","url"}`.
Cada valor editable se identifica por **ruta** (`pages.home.sections.2.data.title`) y se imprime en el HTML como `data-sc="<ruta>"` + `data-sc-t="text|rich|img|icon|link"`.
Las rutas de servicios usan `services.<n>.<campo>`.

## 5. Renderizado (tema `servicom`)
- `wp-pack/theme/servicom/inc/luxe-icons.php` → `sc_icon(string $key, array|string $opts=''): string` (SVG en línea, `aria-hidden`), `sc_icon_keys(): array`, `sc_icon_for(string $text, string $rubro=''): string`.
- `wp-pack/theme/servicom/inc/luxe-art.php` → `sc_art(int $seed, string $motif, array $palette, string $variant='cover'): string` (SVG inline, sin ids duplicados: prefijo único por llamada).
- `wp-pack/mu-plugins/servicom-core/includes/design.php` → paleta desde logo; `sc_design_palette_from_image(string $path): array`, `sc_design_default(string $rubro, int $style, int $seed): array`, `sc_design_css(array $design): string` (variables `--lx-*`).
- Tema: `inc/luxe-render.php` (`sc_site(): array`, `sc_render_page(string $key)`, secciones en `template-parts/luxe/section-<type>.php`), CSS `assets/css/luxe.css`, JS `assets/js/luxe.js`.
- Botones flotantes (llamar, WhatsApp, correo) siempre presentes si el dato existe; formulario de contacto propio `[sc_contact_form]`.

## 6. Editor en vivo + panel (mu-plugin `includes/editor.php`)
- Capacidad `sc_edit_site` (rol cliente y administrador). Barra superior en el frontal para quien la tiene: «Editar mi web» (activa modo edición), «Instrucciones», «Panel».
- Modo edición (`?sc_edit=1` o botón): los elementos con `data-sc` se editan en el sitio (texto: contenteditable plano o rico; imagen: abrir mediateca/subir; icono: selector con todos los iconos; enlace: texto+URL; secciones: mostrar/ocultar y reordenar con flechas; servicios: agregar/eliminar/duplicar). Guardado por REST `sc/v1/edit` (nonce + capacidad), validación por tipo, `wp_kses` limitado. Nada se guarda sin acción explícita (autoguardado con aviso «Guardado»).
- Datos de contacto/redes/horario/logo: panel lateral «Datos del negocio» dentro del mismo editor (escribe `theme_mod` sc_* y `custom_logo`).
- INSTRUCCIONES (menú del admin): cada tarea con botón de enlace directo a `/?sc_edit=1&sc_focus=<ruta>#<ancla>` que abre el editor ya enfocado en ese elemento.

## 7. Reparto de archivos (propiedad exclusiva)
- AGENTE ICONOS: `wp-pack/theme/servicom/inc/luxe-icons.php`, `wp-pack/tests-only/icons/*`.
- AGENTE ARTE: `wp-pack/theme/servicom/inc/luxe-art.php`, `wp-pack/tests-only/art/*`.
- AGENTE PORTAL-IMÁGENES: `app/Services/StockImages.php`, `Pipeline.php` (solo paso `medios`), ajustes (`settings.php` y campos en `AdminController` solo para `pexels_key`/`stock_online`), `Diagnostics.php` (ítem de fotos), `tests/e2e/mock_stock.php`, `tests/stock/*`.
- AGENTE PORTAL-TEXTOS: `app/Services/{TextSchema,AiClient,BaseTexts,Brief,Manifest}.php`, `tests/e2e/mock_ai.php` (solo respuesta de redacción), `tests/ai/*`.
- COORDINADOR (yo): todo lo demás (design, tema, builder, editor, pruebas e2e, empaquetado).
Contrato entre PORTAL-IMÁGENES y PORTAL-TEXTOS: `StockImages` deja en `orders.data._stock` = `{"assets":[{"file":fileId,"role":"stock","credit":""}],"slots":{"hero":[fileId],"about":[fileId],"servicios":[fileId],"galeria":[fileId]}}`; `Manifest` lo lee y llena `assets[]`/`content.stock` (convirtiendo fileId→id de asset como ya hace con las fotos del cliente).
