#!/usr/bin/env python3
"""
Genera el manual en PDF a partir de manual.html y las capturas.

    python3 generar.py              solo arma el PDF
    python3 generar.py --capturas   vuelve a tomar las capturas y arma el PDF

Para las capturas hace falta el sistema corriendo en BASE con los datos de
ejemplo cargados.
"""
import pathlib
import sys

from playwright.sync_api import sync_playwright

AQUI     = pathlib.Path(__file__).parent
CAPTURAS = AQUI / "capturas"
SALIDA   = AQUI.parent / "Manual-Facturacion-FEL.pdf"

BASE    = "http://127.0.0.1:8790/index.php"
USUARIO = "adrian"
CLAVE   = "claveDemo12345"
EMPRESA = 1

# Alto útil de una hoja carta con los márgenes de abajo, en píxeles CSS.
ANCHO_UTIL = int(186 / 25.4 * 96)
ALTO_UTIL  = 245.4 / 25.4 * 96

PIE = """<div style="width:100%;font-size:8pt;color:#7b8794;font-family:'Segoe UI',Arial,sans-serif;
 padding:0 15mm;display:flex;justify-content:space-between;">
 <span>Manual de uso y configuración — Sistema de Facturación FEL</span>
 <span class="pageNumber"></span></div>"""


def navegador(p):
    """Usa el Chromium del entorno si está, y si no el que traiga Playwright."""
    preinstalado = pathlib.Path("/opt/pw-browsers/chromium-1194/chrome-linux/chrome")
    if preinstalado.exists():
        return p.chromium.launch(executable_path=str(preinstalado), args=["--no-sandbox"])
    return p.chromium.launch(args=["--no-sandbox"])


def tomar_capturas(nav):
    """Recorta cada captura al elemento exacto, no a coordenadas fijas."""
    ctx = nav.new_context(viewport={"width": 1360, "height": 1200}, device_scale_factor=2)
    pg  = ctx.new_page()

    pg.goto(f"{BASE}?r=ingresar")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "01-ingreso.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 620})

    pg.fill("#usuario", USUARIO)
    pg.fill("#clave", CLAVE)
    pg.click("button[type=submit]")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "02-empresas.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 560})

    pg.goto(f"{BASE}?r=empresa_editar&id={EMPRESA}")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "03-empresa-form.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 900})
    y = pg.evaluate("document.querySelectorAll('fieldset')[2]"
                    ".getBoundingClientRect().top + window.scrollY")
    pg.screenshot(path=str(CAPTURAS / "03b-certificador.png"),
                  clip={"x": 0, "y": int(y) - 10, "width": 1360, "height": 760})

    pg.goto(f"{BASE}?r=usar_empresa&id={EMPRESA}")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "04-panel.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 1000})

    pg.goto(f"{BASE}?r=nuevo")
    pg.wait_for_load_state("networkidle")
    pg.select_option("#cliente_id", index=1)
    pg.locator("#lineas tr").first.locator(".f-producto").select_option(index=2)
    pg.click("#agregar-linea")
    fila = pg.locator("#lineas tr").nth(1)
    fila.locator(".f-producto").select_option(index=5)
    fila.locator(".f-cantidad").fill("8")
    pg.locator("#referencia_interna").fill("ORD-1007")
    pg.dispatch_event("#lineas", "input")
    pg.wait_for_timeout(300)
    y = pg.evaluate("document.querySelectorAll('fieldset')[2]"
                    ".getBoundingClientRect().top + window.scrollY")
    pg.screenshot(path=str(CAPTURAS / "05-nuevo-documento.png"),
                  clip={"x": 0, "y": max(0, int(y) - 470), "width": 1360, "height": 1000})

    # Listados: se recorta la tarjeta que los contiene.
    for ruta, nombre in [("documentos", "06-documentos"), ("clientes", "10-clientes"),
                         ("productos", "11-productos"), ("usuarios", "13-usuarios")]:
        pg.goto(f"{BASE}?r={ruta}")
        pg.wait_for_load_state("networkidle")
        tarjeta = pg.locator(".tarjeta").nth(1)
        tarjeta.scroll_into_view_if_needed()
        pg.wait_for_timeout(150)
        tarjeta.screenshot(path=str(CAPTURAS / f"{nombre}.png"))

    pg.goto(f"{BASE}?r=ver&id=1")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "07-detalle.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 980})

    pg.goto(f"{BASE}?r=imprimir&id=1&formato=carta")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "08-carta.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 1080})

    pg.goto(f"{BASE}?r=ajustes")
    pg.wait_for_load_state("networkidle")
    pg.screenshot(path=str(CAPTURAS / "12-ajustes.png"),
                  clip={"x": 0, "y": 0, "width": 1360, "height": 780})

    ticket = ctx.new_page()
    ticket.set_viewport_size({"width": 420, "height": 1700})
    ticket.goto(f"{BASE}?r=imprimir&id=1&formato=ticket")
    ticket.wait_for_load_state("networkidle")
    ticket.screenshot(path=str(CAPTURAS / "09-ticket.png"),
                      clip={"x": 20, "y": 0, "width": 380, "height": 1640})
    ticket.close()

    print("Capturas actualizadas.")


def revisar_desbordes(pg):
    """Ninguna sección debe pasar del alto útil: si lo hace, parte la página."""
    alturas = pg.evaluate(
        "() => [...document.querySelectorAll('section.pagina')]"
        ".map(s => Math.round(s.getBoundingClientRect().height))"
    )
    excedidas = [i + 1 for i, alto in enumerate(alturas) if alto > ALTO_UTIL]

    if excedidas:
        print(f"AVISO: las secciones {excedidas} se pasan del alto de la hoja.")
    else:
        print(f"Todas las secciones caben en su página ({len(alturas)} en total).")

    return len(alturas)


def main():
    with sync_playwright() as p:
        nav = navegador(p)

        if "--capturas" in sys.argv:
            tomar_capturas(nav)

        pg = nav.new_page(viewport={"width": ANCHO_UTIL, "height": int(ALTO_UTIL)})
        pg.goto(f"file://{AQUI}/manual.html")
        pg.wait_for_load_state("networkidle")

        secciones = revisar_desbordes(pg)

        pg.pdf(
            path=str(SALIDA),
            format="Letter",
            print_background=True,
            display_header_footer=True,
            header_template="<div></div>",
            footer_template=PIE,
            margin={"top": "16mm", "bottom": "18mm", "left": "15mm", "right": "15mm"},
        )
        nav.close()

    tamano = SALIDA.stat().st_size / 1024 / 1024
    print(f"PDF generado: {SALIDA}  ({tamano:.1f} MB, {secciones} páginas esperadas)")


if __name__ == "__main__":
    main()
