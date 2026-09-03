# Cotizador Servicom — estado del trabajo

Base: motor CotizaPro (PHP 8.0+, MVC propio, sin composer, PWA, PDO preparado,
Argon2id, CSRF, PWA e instalador de 3 pasos) ya verificado.

## Hecho
- Copia del motor a `/servicom` con el catálogo y la demo industrial retirados.
- Paleta de marca Servicom: azul señal `#1D5BFF`, tinta `#0A1024`,
  papel `#F6F8FC`; temas alternos en `App\Models\Company::THEMES`.
- Portada reescrita para empresa de servicios digitales (hero sin fotografía,
  composición de luz propia; secciones "Lo que hacemos", "Los planes más
  solicitados", pasos de cotización y muro de tecnología).
- 12 ilustraciones vectoriales propias en `assets/img/cards/`
  (`tools/generate-cards.php`): sitio corporativo, landing, tienda virtual,
  correo, dominio, hosting, SSL, SEO, mantenimiento, redes, diseño y genérica.

## Pendiente (bloqueado por acceso de red)
El contenedor de la sesión tiene bloqueado `servicom.gt` en el proxy de egreso
(las 6 URL responden 000 / CONNECT 403), por lo que no se pudo leer el
contenido literal de:

- https://servicom.gt/
- https://servicom.gt/paginas-web/
- https://servicom.gt/tiendas-virtuales/
- https://servicom.gt/cuentas-de-correo/
- https://servicom.gt/cpw
- https://servicom.gt/ctv

Falta con ese contenido: el catálogo de servicios y planes con sus precios,
los textos de las páginas y los datos de contacto definitivos.

Para desbloquear: abrir una sesión nueva (el cambio de red no aplica a un
contenedor ya creado) o pegar el texto de las páginas en el chat.

## Datos de la empresa confirmados por búsqueda
- Servicom.gt — Guatemala · más de 16 años de experiencia.
- WhatsApp (+502) 3204-0756 · info@servicom.gt
- Servicios: páginas web, tiendas virtuales, cuentas de correo corporativo.
- Páginas web: diseño visual profesional, secciones claras, adaptación a
  celulares y computadoras, formulario de contacto y enlace a WhatsApp;
  asesoría de dominio y hosting; entrega en pocos días con la información
  completa.
- Tiendas virtuales: diseño exclusivo y personalizable, múltiples métodos de
  pago, envíos y devoluciones, panel para inventario, pedidos y analítica.
- Precio: depende del tipo de sitio y la cantidad de secciones.
