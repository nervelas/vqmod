<?php
/**
 * CorreoRadar - Portada.
 *
 * Una sola caja grande: pegar el enlace y pulsar "Extraer correos".
 * Todo el proceso (progreso, resultados, exportación) ocurre en esta página.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

$puedeExtraer  = Auth::puedeExtraer();
$rastreoActivo = Ajustes::activo('rastreo_profundo', true);

cr_cabecera([
    'titulo' => Ajustes::obtener('sitio_lema'),
    'activo' => 'inicio',
]);
?>

<section class="portada">
  <!-- Radar decorativo: gira mientras se escanea -->
  <div class="radar" aria-hidden="true">
    <img src="<?= e(cr_url('assets/img/radar-malla.png')) ?>" alt="" width="900" height="900" loading="eager">
    <div class="radar-barrido"></div>
  </div>

  <div class="contenedor portada-int">
    <span class="insignia"><span class="punto"></span> Correos y WhatsApp</span>

    <h1><?= e(Ajustes::obtener('hero_titulo')) ?></h1>
    <p class="portada-sub"><?= e(Ajustes::obtener('hero_subtitulo')) ?></p>

    <!-- ================= LA CAJA: todo en 1 clic ================= -->
    <form class="caja-radar" id="form-radar" autocomplete="off" novalidate>
      <label class="caja-etiqueta" for="url"><?= e(Ajustes::obtener('hero_etiqueta', 'Pega tu enlace')) ?></label>

      <div class="caja-fila">
        <input type="url" id="url" name="url" class="caja-url" inputmode="url" spellcheck="false"
               placeholder="<?= e(Ajustes::obtener('hero_placeholder')) ?>"
               <?= $puedeExtraer ? '' : 'disabled' ?> required>
        <button type="submit" class="btn caja-boton" id="btn-extraer" <?= $puedeExtraer ? '' : 'disabled' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
          </svg>
          <?= e(Ajustes::obtener('hero_boton')) ?>
        </button>
      </div>

      <div class="caja-pie">
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
        <span class="caja-nota"><?= e(Ajustes::obtener('aviso_legal')) ?></span>
      </div>
    </form>

    <?php if (!$puedeExtraer): ?>
      <div class="aviso aviso-info" style="max-width:760px;margin:20px auto 0;text-align:left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
        </svg>
        <span>
          Para usar el extractor necesitas una cuenta.
          <a href="<?= e(cr_url('login.php')) ?>">Inicia sesión</a>
          <?php if (Ajustes::activo('registro_publico', true)): ?>
            o <a href="<?= e(cr_url('registro.php')) ?>">crea una gratis</a>
          <?php endif; ?>.
        </span>
      </div>
    <?php endif; ?>

    <!-- Ventajas -->
    <div class="ventajas">
      <div class="ventaja">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 2 4 6v6c0 5 3.4 9.4 8 10 4.6-.6 8-5 8-10V6Z"/><path d="m9 12 2 2 4-4"/>
        </svg>
        <b>Correos y WhatsApp a la vez</b>
        <span>20 técnicas para los correos y 8 para los números: wa.me, api.whatsapp, widgets, tel:, JSON-LD y texto.</span>
      </div>
      <div class="ventaja">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 12a9 9 0 1 0 9-9"/><path d="M3 4v5h5"/><circle cx="12" cy="12" r="2.5"/>
        </svg>
        <b>Rastreo profundo</b>
        <span>Recorre contacto, nosotros, equipo y el resto de páginas internas, con sitemap incluido.</span>
      </div>
      <div class="ventaja">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
        </svg>
        <b>TXT, CSV y Excel</b>
        <span>Descarga los resultados con el nombre del dominio y la fecha, listos para tu CRM.</span>
      </div>
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
        <input type="search" id="buscar" class="campo" placeholder="Buscar correo, dominio o método…" aria-label="Buscar en los correos">
      </div>
      <select id="filtro-dominio" class="campo" aria-label="Filtrar por dominio">
        <option value="">Todos los dominios</option>
      </select>
      <select id="filtro-tipo" class="campo" aria-label="Filtrar por tipo de correo">
        <option value="">Todos los tipos</option>
        <option value="generico">Genéricos (info@, ventas@…)</option>
        <option value="personal">Personales (nombre@…)</option>
      </select>
    </div>

    <div class="exportar">
      <button type="button" class="btn btn-fantasma btn-peq" id="btn-copiar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="9" y="9" width="12" height="12" rx="2.2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
        </svg>
        Copiar correos
      </button>
      <span class="neon pequeno" id="n-seleccionados"></span>
      <span style="flex:1"></span>
      <button type="button" class="btn btn-peq" data-exportar="txt" data-datos="correos">TXT</button>
      <button type="button" class="btn btn-peq" data-exportar="csv" data-datos="correos">CSV</button>
      <button type="button" class="btn btn-neon btn-peq" data-exportar="xlsx" data-datos="correos">Excel</button>
    </div>

    <div class="tabla-caja">
      <div class="tabla-scroll">
        <table class="tabla">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="sel-todos" aria-label="Seleccionar todos los correos"></th>
              <th>Correo</th><th>Dominio</th><th>Tipo</th><th>Confianza</th><th>MX</th>
              <th>Método de detección</th><th>Página exacta</th>
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
        <p class="pequeno">Prueba a activar el <b>rastreo profundo</b> o apunta directamente a la página de contacto.</p>
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
        <input type="search" id="buscar-tel" class="campo" placeholder="Buscar número, país o método…" aria-label="Buscar en los teléfonos">
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
        <table class="tabla">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="sel-todos-tel" aria-label="Seleccionar todos los números"></th>
              <th>Número</th><th>País</th><th>Tipo</th><th>Confianza</th>
              <th>Método de detección</th><th>Página exacta</th><th>Abrir</th>
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
        <p class="pequeno">Muchos sitios solo publican el WhatsApp en la página de contacto: prueba con el rastreo profundo.</p>
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
