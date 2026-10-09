# Fotos de stock FALTANTES

En el entorno de construcción no hubo acceso a Unsplash/Pexels, por lo que **la biblioteca está vacía**. Mientras falten fotos, las webs usan cajas de icono (servicios) y fondo degradado del estilo (banner): nunca imágenes falsas.

Para cada rubro se necesitan: **3 fotos `hero`** (horizontal ≥ 1600 px) y **6 fotos `servicio`** (≥ 900 px). Búsqueda sugerida:

- **abogado**: hero ×3, servicio ×6 — despacho jurídico, balanza de la justicia, reunión con cliente
- **clinica**: hero ×3, servicio ×6 — consultorio médico, estetoscopio, recepción de clínica
- **taller**: hero ×3, servicio ×6 — taller mecánico, herramientas, mecánico trabajando
- **ropa**: hero ×3, servicio ×6 — boutique de ropa, percheros, vitrina
- **restaurante**: hero ×3, servicio ×6 — plato servido, cocina, comedor
- **transporte**: hero ×3, servicio ×6 — camión de carga, ruta, bodega
- **contabilidad**: hero ×3, servicio ×6 — oficina contable, calculadora, documentos
- **importaciones**: hero ×3, servicio ×6 — contenedores, puerto, bodega
- **otro**: hero ×3, servicio ×6 — oficina moderna, equipo de trabajo, atención al cliente

Cómo agregarlas: `php tools/stock_add.php <rubro> <hero|servicio> <archivo.jpg> "texto alternativo" "Autor / Unsplash" "https://enlace-de-la-foto" "Unsplash License"` (optimiza a ≤1600 px WebP/JPG y actualiza `catalog.json` y `CREDITOS.md`).
