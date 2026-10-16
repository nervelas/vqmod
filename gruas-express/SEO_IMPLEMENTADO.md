# SEO implementado — Grúas Express Towing

## Reglas aplicadas (Google)
| Elemento | Regla | Cómo se cumple |
|---|---|---|
| Title | hasta 60 caracteres, palabra clave al inicio, marca al final | Verificado en las 53 páginas |
| Meta description | hasta 160 caracteres, **siempre inicia con `Tel: 3556-7904`** | Verificado en las 53 páginas |
| H1 | uno solo por página | Es el título de la portada de cada página |
| H2 / H3 | jerarquía sin saltos (H1 → H2 → H3) | Secciones H2; vehículos y preguntas en H3 |
| URL | corta, con palabra clave, sin parámetros | `/gruas-mixco/`, `/gruas-zona-10-ciudad-de-guatemala/` |
| Canonical, sitemap, robots | una URL canónica por página; todas en `sitemap.xml` | Automático al pulsar «Publicar» |
| Datos estructurados | JSON-LD `LocalBusiness` con `areaServed` del municipio/zona | Automático |
| Enlaces internos | cada página recibe enlaces; cada municipio enlaza a otros 7 | Sección «Municipios que atendemos» en Servicio |
| Menú | solo Inicio, Sobre nosotros, Servicio y Contáctenos | Las páginas SEO no aparecen en el menú |

## Páginas
* 53 páginas en total: 4 existentes (con title y description SEO nuevos) + 36 municipios + 13 zonas.
* Zonas con página propia (demanda detectada en Google Guatemala): capital 1, 4, 5, 6, 7, 10, 11, 12, 18; Mixco 4, 6, 8, 11. Las demás zonas se nombran dentro de la página de su municipio.
* Cada página usa las 9 palabras clave principales: grúas + lugar, servicio de grúas, grúas 24 horas, grúas 24/7, grúas cerca de, precio (desde Q250), asistencia vial, Grúas Express, Servicio de Grúas 24/7.

## Contenido
Solo se usan datos reales: teléfono, «desde Q250», 24/7, servicios existentes y hechos geográficos verificados (distancia, departamento, zonas confirmadas). No se inventaron colonias, direcciones, tiempos de llegada ni precios.

## Verificación
`python3 tests/seo_check.py` → 796/796 comprobaciones (200 OK, title, description, H1/H2/H3, canonical, JSON-LD, alt, menú de 4 enlaces, enlaces internos sin 404, páginas sin enlaces entrantes, similitud de texto máxima 0.60).

## Limitaciones
* Nadie puede garantizar el puesto #1. Depende también del Perfil de Empresa de Google, reseñas, enlaces externos y la competencia.
* El texto por municipio comparte estructura; para diferenciarlo más haría falta información real del negocio (barrios atendidos, casos, fotos del lugar, reseñas).
* Zonas de Chinautla, Petapa, Villa Canales y Amatitlán: sin confirmar, por eso no se usan.

## Si ya tiene el sitio instalado
Al subir el ZIP sobre un sitio ya instalado, el monitor de integridad detectará archivos cambiados. Suba los archivos, borre `_core/data/baseline.json` y `_core/data/lockdown.flag` (si existe), entre al panel y pulse **Publicar**: se crean las 49 páginas nuevas y se actualizan los SEO de las 4 existentes (una sola vez).
