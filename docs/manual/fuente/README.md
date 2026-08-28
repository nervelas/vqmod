# Cómo regenerar el manual

El PDF de `docs/manual/` se arma a partir de `manual.html` y de las capturas de
`capturas/`, que son pantallas reales del sistema.

## Regenerar solo el PDF (si únicamente cambió el texto)

Requiere Python con Playwright y un Chromium:

```bash
pip install playwright
python3 generar.py
```

## Volver a tomar las capturas (si cambió la interfaz)

1. Levante una instalación con datos de ejemplo:

```bash
php bin/instalar.php
php -S 127.0.0.1:8790 -t public
```

2. Ajuste el usuario y la contraseña dentro de `generar.py` y ejecútelo con:

```bash
python3 generar.py --capturas
```

Las capturas se recortan al elemento exacto de cada pantalla, no por
coordenadas fijas: si la interfaz cambia de alto, siguen saliendo bien.

## Verificar que no se desborde ninguna página

`generar.py` mide cada sección contra el alto útil de la hoja carta y avisa
cuál se pasa. El PDF debe tener tantas páginas como secciones tenga el HTML.
