<?php
/**
 * Kaptor - Portada.
 *
 * Una sola caja grande: pegar el enlace y pulsar "Extraer correos".
 * Todo el proceso (progreso, resultados, exportación) ocurre en esta página.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

// Kaptor es privado: sin sesión no se entra.
cr_exigir_sesion();

$puedeExtraer  = Auth::puedeExtraer();
$rastreoActivo = Ajustes::activo('rastreo_profundo', true);

cr_cabecera([
    'titulo' => Ajustes::obtener('sitio_lema'),
    'activo' => 'inicio',
]);
?>

<!-- ==========================================================================
     LA CONSOLA
     Kaptor es un instrumento, no un folleto: desde que se entra, lo primero
     es el panel de mando. Composición asimétrica — el mando a la izquierda,
     el radar a la derecha saliéndose del encuadre — y los tres pasos
     numerados como en un aparato de medición.
     ========================================================================== -->
<section class="consola">

  <!-- Radar: decorativo, gira mientras se escanea. Sangra por la derecha. -->
  <div class="radar" aria-hidden="true">
    <img src="<?= e(cr_url('assets/img/radar-malla.png')) ?>" alt="" width="900" height="900" loading="eager">
    <div class="radar-barrido"></div>
  </div>

  <div class="contenedor consola-int portada-int">

    <!-- ----------------------------- COLUMNA DE MANDO ----------------------------- -->
    <div class="consola-mando">

      <p class="rotulo">
        <span class="rotulo-punto" aria-hidden="true"></span>
        Correos y WhatsApp
        <span class="rotulo-linea" aria-hidden="true"></span>
        <span class="rotulo-usuario"><?= e(Auth::usuario()['usuario'] ?? '') ?></span>
      </p>

      <h1 class="titular"><?= cr_titulo_brillo(Ajustes::obtener('hero_titulo')) ?></h1>
      <p class="portada-sub"><?= e(Ajustes::obtener('hero_subtitulo')) ?></p>

      <p class="aviso-busqueda" id="aviso-busqueda" hidden></p>

      <form class="caja-radar panel" id="form-radar" autocomplete="off" novalidate>

        <!-- ····· 01 · de dónde ····· -->
        <div class="paso">
          <span class="paso-n" aria-hidden="true">01</span>
          <label class="caja-etiqueta" for="url"><?= e(Ajustes::obtener('hero_etiqueta', 'Pega tu enlace')) ?></label>
        </div>

        <div class="caja-fila">
          <!-- Un solo campo para las tres cosas: una web, una lista de webs o
               una búsqueda. Es un textarea para que quepan varias líneas y para
               que en el móvil la dirección se parta en lugar de salirse. -->
          <textarea id="url" name="url" class="caja-url" rows="1" spellcheck="false"
                 inputmode="url" enterkeyhint="go"
                 placeholder="<?= e(Ajustes::obtener('hero_placeholder')) ?>"
                 <?= $puedeExtraer ? '' : 'disabled' ?> required></textarea>
        </div>

        <?php if (Ajustes::activo('buscar_activo', true)): ?>
        <div class="caja-modos">
          <button type="button" class="modo" data-ejemplo="https://www.colegio.edu.gt">Una web</button>
          <button type="button" class="modo" data-ejemplo="colegio1.edu.gt&#10;colegio2.edu.gt&#10;colegio3.edu.gt">Lista de webs</button>
          <button type="button" class="modo" data-ejemplo="colegios privados Guatemala correo">Buscar en Google</button>
          <button type="button" class="modo" data-ejemplo="site:facebook.com colegios Guatemala">Buscar en Facebook</button>
          <button type="button" class="modo" data-ejemplo="https://www.facebook.com/nombredelapagina">Una página de Facebook</button>
        </div>
        <?php endif; ?>

        <!-- ····· 02 · qué quiero ·····
             Búsqueda inteligente: "solo correos que terminen en .edu.gt".
             Kaptor reescribe la consulta al buscador con site: y, además, tira
             todo correo que no cumpla antes siquiera de guardarlo. -->
        <div class="caja-objetivo">
          <div class="paso">
            <span class="paso-n" aria-hidden="true">02</span>
            <label class="caja-objetivo-tit" for="objetivo">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.6"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3"/>
              </svg>
              Búsqueda inteligente <span class="suave pequeno">— quiero <b>solo</b> correos que terminen en:</span>
            </label>
          </div>
          <input type="text" id="objetivo" name="objetivo" class="campo" autocomplete="off" spellcheck="false"
                 placeholder="" <?= $puedeExtraer ? '' : 'disabled' ?>>
          <div class="chips-ext" id="chips-objetivo">
            <button type="button" class="chip-ext chip-todos" data-ext="">Todos</button>
            <button type="button" class="chip-ext" data-ext="edu.gt">.edu.gt</button>
            <button type="button" class="chip-ext" data-ext="com.gt">.com.gt</button>
            <button type="button" class="chip-ext" data-ext="gob.gt">.gob.gt</button>
            <button type="button" class="chip-ext" data-ext="org.gt">.org.gt</button>
            <button type="button" class="chip-ext" data-ext="gt">.gt</button>
            <button type="button" class="chip-ext" data-ext="com">.com</button>
            <button type="button" class="chip-ext" data-ext="org">.org</button>
            <button type="button" class="chip-ext" data-ext="edu">.edu</button>
          </div>
        </div>

        <!-- ····· 03 · hasta dónde ····· -->
        <div class="caja-pie">
          <div class="paso">
            <span class="paso-n" aria-hidden="true">03</span>
            <?php if ($rastreoActivo): ?>
              <label class="interruptor" for="profundo" title="Rastrea también las páginas internas del mismo dominio">
                <input type="checkbox" id="profundo" name="profundo" <?= $puedeExtraer ? '' : 'disabled' ?>>
                <span class="pista" aria-hidden="true"></span>
                <span class="txt">Rastreo profundo
                  <span class="suave pequeno">(hasta <?= e((string) Ajustes::entero('max_paginas', 30)) ?> páginas)</span>
                </span>
              </label>
            <?php else: ?>
              <span class="caja-nota">Análisis de una sola página.</span>
            <?php endif; ?>
          </div>
        </div>

        <button type="submit" class="btn caja-boton" id="btn-extraer" <?= $puedeExtraer ? '' : 'disabled' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
          </svg>
          <?= e(Ajustes::obtener('hero_boton')) ?>
        </button>

        <p class="caja-nota caja-nota-legal"><?= e(Ajustes::obtener('aviso_legal')) ?></p>
      </form>

      <!-- El otro camino: no hay web que rastrear, ya se tiene el texto. -->
      <p class="atajo-texto">
        ¿Ya tienes el texto y no hay web que rastrear?
        <a href="<?= e(cr_url('depurar.php')) ?>">Extraer correos →</a>
        <span class="atajo-sep" aria-hidden="true">·</span>
        <a href="<?= e(cr_url('dominios.php')) ?>">Extraer dominios →</a>
      </p>
    </div>

  </div>
</section>

<!-- Lo que hace, en tres lineas. Dentro del contenedor, como todo lo demas:
     antes colgaba fuera y se salia por los dos lados de la pagina. -->
<section class="contenedor seccion-corta">
  <div class="ventajas">
      <div class="ventaja">
        <svg viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M224,48H32a8,8,0,0,0-8,8V192a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A8,8,0,0,0,224,48Zm-96,85.15L52.57,64H203.43ZM98.71,128,40,181.81V74.19Zm11.84,10.85L128,154.81l17.45-16L206.73,192H49.27ZM157.29,128,216,74.19V181.81Z"/></svg>
        <b>Correos y WhatsApp a la vez</b>
        <span>Los dos en la misma pasada.</span>
      </div>
      <div class="ventaja">
        <svg viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"/></svg>
        <b>Rastreo profundo</b>
        <span>Entra en las páginas internas del sitio.</span>
      </div>
      <div class="ventaja">
        <svg viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M224,144v64a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V144a8,8,0,0,1,16,0v64H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V32a8,8,0,0,0-16,0v92.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"/></svg>
        <b>TXT, CSV y Excel</b>
        <span>Descarga la lista y a trabajar.</span>
      </div>
  </div>
</section>

<!-- ================= PROGRESO EN TIEMPO REAL ================= -->
<section class="contenedor">
  <div class="aviso aviso-error oculto" id="error-escaneo" role="alert">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
      <circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5h.01"/>
    </svg>
    <span></span>
  </div>

  <div class="tarjeta progreso oculto" id="panel-progreso">
    <div class="progreso-cab">
      <div class="progreso-titulo">
        <span class="mini-radar" aria-hidden="true"></span>
        <span>Barriendo <span class="mono oro" id="destino"></span></span>
      </div>
      <button type="button" class="btn btn-fantasma btn-peq" id="btn-cancelar">Detener</button>
    </div>

    <div class="barra-progreso" role="progressbar" aria-label="Progreso del escaneo" id="barra"><i></i></div>
    <p class="progreso-url" id="url-actual" aria-live="polite">Preparando el radar…</p>

    <div class="contadores">
      <div class="contador"><b id="c-revisadas">0</b><span>Páginas</span></div>
      <div class="contador destacado"><b id="c-correos">0</b><span>Correos</span></div>
      <div class="contador wa"><b id="c-whatsapp">0</b><span>WhatsApp</span></div>
      <div class="contador"><b id="c-pendientes">0</b><span>En cola</span></div>
    </div>

    <div class="aviso aviso-info oculto" id="aviso-js" style="margin:18px 0 0">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
      </svg>
      <span></span>
    </div>
  </div>
</section>

<!-- ================= RESULTADOS ================= -->
<section class="contenedor resultados oculto" id="panel-resultados">

  <div class="resultados-cab">
    <div>
      <h2>Lo que hemos encontrado</h2>
      <p class="sub" id="resumen-hallazgos">—</p>
    </div>
  </div>

  <!-- Pestañas: correos / WhatsApp -->
  <div class="pestanas-res" role="tablist">
    <button type="button" class="activa" data-panel="p-correos" role="tab" aria-selected="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 7.6 5.1a1.6 1.6 0 0 0 1.8 0l7.6-5.1"/>
      </svg>
      Correos <span class="cuenta" id="n-correos">0</span>
    </button>
    <button type="button" data-panel="p-whatsapp" role="tab" aria-selected="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M20.5 11.6A8.4 8.4 0 0 1 7.8 19l-4.3 1.2 1.2-4.2A8.4 8.4 0 1 1 20.5 11.6Z"/>
        <path d="M8.9 8.4c.2-.5.4-.5.7-.5h.6c.2 0 .4 0 .6.5l.7 1.7c.1.3 0 .5-.1.7l-.4.5c-.1.2-.2.3 0 .6a6 6 0 0 0 2.6 2.3c.3.1.4 0 .6-.1l.5-.6c.2-.2.4-.2.6-.1l1.7.8c.3.1.4.3.4.5v.6c0 .4-.4.8-.8.9-1 .3-2.6.1-5-1.3a9 9 0 0 1-3.3-3.4c-.6-1.1-.7-2-.4-2.8Z"/>
      </svg>
      WhatsApp y teléfonos <span class="cuenta" id="n-telefonos">0</span>
    </button>
  </div>

  <!-- ---------------------------- CORREOS ---------------------------- -->
  <div class="panel-res activo" id="p-correos">
    <div class="herramientas">
      <div class="buscador">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" id="buscar" class="campo" placeholder="" aria-label="Buscar en los correos">
      </div>
      <select id="filtro-tipo" class="campo" aria-label="Filtrar por tipo de correo">
        <option value="">Todos los tipos</option>
        <option value="generico">Genéricos (info@, ventas@…)</option>
        <option value="personal">Personales (nombre@…)</option>
      </select>
      <select id="filtro-nivel" class="campo" aria-label="Filtrar por nivel educativo">
        <option value="">Todos los niveles</option>
        <option value="basicos,diversificado">Básicos o diversificado</option>
        <option value="basicos">Solo básicos</option>
        <option value="diversificado">Solo diversificado</option>
        <option value="primaria">Primaria</option>
        <option value="preprimaria">Preprimaria</option>
        <option value="superior">Superior (universidades)</option>
        <option value="_sin">Sin nivel detectado</option>
      </select>
      <button type="button" class="btn btn-fantasma btn-peq" id="btn-vista" aria-pressed="false">Ver detalles</button>
    </div>

    <!-- ============ ELEGIR QUÉ DESCARGAR, POR TERMINACIÓN DEL DOMINIO ============
         No se filtra dominio por dominio: se filtra por cómo TERMINA el dominio.
         Pulsar «.edu.gt» deja todos los correos de todos los dominios que acaben
         así (colegio1.edu.gt, liceo.edu.gt, sub.universidad.edu.gt...). -->
    <div class="caja-filtro-ext">
      <div class="filtro-ext-cab">
        <label for="filtro-ext">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 5h18l-7 8v6l-4 2v-8Z"/>
          </svg>
          Quiero solo los correos que terminen en:
        </label>
        <span class="filtro-ext-estado" id="filtro-ext-estado"></span>
      </div>

      <input type="text" id="filtro-ext" class="campo campo-ext" autocomplete="off" spellcheck="false"
             placeholder=""
             aria-label="Filtrar por terminación del dominio">

      <!-- Terminaciones encontradas, con cuántos correos y cuántos dominios
           distintos tiene cada una. Se pulsan para filtrar sin escribir. -->
      <div class="chips-ext" id="chips-ext-res" hidden></div>
    </div>

    <div class="exportar">
      <button type="button" class="btn btn-fantasma btn-peq" id="btn-copiar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="9" y="9" width="12" height="12" rx="2.2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
        </svg>
        Copiar correos
      </button>
      <span class="neon pequeno" id="n-seleccionados"></span>
      <span class="pequeno suave" id="n-filtrados"></span>
      <span style="flex:1"></span>
      <button type="button" class="btn btn-peq" data-exportar="txt" data-datos="correos">TXT <b class="n-bajar"></b></button>
      <button type="button" class="btn btn-peq" data-exportar="csv" data-datos="correos">CSV <b class="n-bajar"></b></button>
      <button type="button" class="btn btn-neon btn-peq" data-exportar="xlsx" data-datos="correos">Excel <b class="n-bajar"></b></button>
    </div>

    <div class="tabla-caja">
      <div class="tabla-scroll">
        <table class="tabla tabla-compacta" id="tabla-correos">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="sel-todos" aria-label="Seleccionar todos los correos"></th>
              <th>Correo</th><th class="col-dominio">Dominio</th><th>Niveles</th><th>Tipo</th>
              <th class="col-conf">Confianza</th><th class="col-mx">MX</th><th class="col-url">Página</th>
            </tr>
          </thead>
          <tbody id="tabla-cuerpo"></tbody>
        </table>
      </div>

      <div class="vacio oculto" id="sin-resultados">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M8 11h6"/>
        </svg>
        <p><b>No se encontró ningún correo.</b></p>
        <p class="pequeno">Prueba con el rastreo profundo.</p>
      </div>
      <div class="vacio oculto" id="sin-coincidencias"><p>Ningún correo coincide con el filtro.</p></div>
    </div>
  </div>

  <!-- --------------------------- WHATSAPP ---------------------------- -->
  <div class="panel-res" id="p-whatsapp">
    <div class="herramientas">
      <div class="buscador">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" id="buscar-tel" class="campo" placeholder="" aria-label="Buscar en los teléfonos">
      </div>
      <select id="filtro-pais" class="campo" aria-label="Filtrar por país">
        <option value="">Todos los países</option>
      </select>
      <select id="filtro-wa" class="campo" aria-label="Filtrar por tipo de número">
        <option value="">Todos los números</option>
        <option value="si">Solo WhatsApp</option>
        <option value="no">Solo teléfonos</option>
      </select>
    </div>

    <div class="exportar">
      <button type="button" class="btn btn-fantasma btn-peq" id="btn-copiar-tel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="9" y="9" width="12" height="12" rx="2.2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
        </svg>
        Copiar números
      </button>
      <span class="neon pequeno" id="n-sel-tel"></span>
      <span style="flex:1"></span>
      <button type="button" class="btn btn-peq" data-exportar="txt" data-datos="telefonos">TXT</button>
      <button type="button" class="btn btn-peq" data-exportar="csv" data-datos="telefonos">CSV</button>
      <button type="button" class="btn btn-neon btn-peq" data-exportar="xlsx" data-datos="todo">Excel con todo</button>
    </div>

    <div class="tabla-caja">
      <div class="tabla-scroll">
        <table class="tabla tabla-compacta" id="tabla-telefonos">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="sel-todos-tel" aria-label="Seleccionar todos los números"></th>
              <th>Número</th><th>País</th><th>Tipo</th><th class="col-conf">Confianza</th>
              <th class="col-url">Página</th><th>Abrir</th>
            </tr>
          </thead>
          <tbody id="tabla-tel"></tbody>
        </table>
      </div>

      <div class="vacio oculto" id="sin-telefonos">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20.5 11.6A8.4 8.4 0 0 1 7.8 19l-4.3 1.2 1.2-4.2A8.4 8.4 0 1 1 20.5 11.6Z"/>
        </svg>
        <p><b>No se encontró ningún número.</b></p>
        <p class="pequeno">Prueba con el rastreo profundo.</p>
      </div>
      <div class="vacio oculto" id="sin-coincidencias-tel"><p>Ningún número coincide con el filtro.</p></div>
    </div>

    <!-- Enlaces de WhatsApp sin número visible -->
    <div class="tarjeta oculto" id="caja-enlaces-wa" style="margin-top:16px">
      <h3>Enlaces de WhatsApp</h3>
      <p class="pequeno suave">Enlaces cortos y grupos que no muestran el número directamente.</p>
      <ul class="lista-extras" id="lista-enlaces-wa"></ul>
    </div>
  </div>

  <!-- Extra: perfiles sociales -->
  <div class="extras oculto" id="extras">
    <div class="tarjeta">
      <h3>Perfiles sociales</h3>
      <ul class="lista-extras" id="lista-redes"></ul>
    </div>
  </div>
</section>

<?php cr_pie(); ?>
