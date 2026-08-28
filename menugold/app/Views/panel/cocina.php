<?php
/** @var array $r, $datos; string $estacion */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Cocina');
$estaciones = ['' => 'Todo', 'cocina' => 'Cocina', 'bar' => 'Bar', 'postres' => 'Postres'];
?>
<header class="kds-cab">
  <a class="bt bt--icono bt--suave" href="<?= e(url('panel')) ?>" aria-label="Volver al panel"><?= icon('arrow-left') ?></a>
  <h1 class="kds-cab__titulo"><?= icon('chef') ?> <span>Comandas</span></h1>
  <div class="kds-filtros" role="group" aria-label="Filtrar por estación">
    <?php foreach ($estaciones as $k => $v): ?>
      <a class="kds-filtro" href="<?= e(url('panel/cocina', $k !== '' ? ['estacion' => $k] : [])) ?>"
         aria-pressed="<?= $estacion === $k ? 'true' : 'false' ?>"><?= e($v) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="crece"></div>
  <button class="bt bt--icono bt--suave" type="button" id="btnSonido" aria-label="Activar o silenciar el sonido"><?= icon('volume') ?></button>
  <button class="bt bt--icono bt--suave" type="button" id="btnPantalla" aria-label="Pantalla completa"><?= icon('maximize') ?></button>
  <span class="kds-cab__reloj" id="reloj"><?= date('H:i') ?></span>
</header>

<div class="kds-tablero" id="tablero">
  <?php
  $cols = [
    'nuevo'      => ['Nuevos', 'Preparar'],
    'preparando' => ['Preparando', 'Marcar listo'],
    'listo'      => ['Listos', 'Entregado'],
  ];
  foreach ($cols as $k => $v): ?>
    <section class="kds-col kds-col--<?= e($k) ?>" data-col="<?= e($k) ?>">
      <header class="kds-col__cab">
        <span><?= e($v[0]) ?></span>
        <span class="kds-col__conteo" data-conteo="<?= e($k) ?>"><?= count($datos[$k] ?? []) ?></span>
      </header>
      <div class="kds-col__lista" data-lista="<?= e($k) ?>"></div>
    </section>
  <?php endforeach; ?>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var estacion = <?= json_encode($estacion) ?>;
  var ACCION = { nuevo: 'Preparar', preparando: 'Marcar listo', listo: 'Entregado' };
  var SIGUIENTE = { nuevo: 'preparando', preparando: 'listo', listo: 'entregado' };
  var tablero = <?= json_encode($datos, JSON_UNESCAPED_UNICODE) ?>;
  var conocidos = {};
  var sonido = true;
  try { sonido = localStorage.getItem('mg_kds_sonido') !== '0'; } catch (e) {}

  // ---------------------------------------------------------- sonido
  var ctx = null;
  function bip() {
    if (!sonido) return;
    try {
      ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
      if (ctx.state === 'suspended') ctx.resume();
      [0, 0.16].forEach(function (t, i) {
        var o = ctx.createOscillator(), g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = i === 0 ? 880 : 1180;
        g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
        g.gain.exponentialRampToValueAtTime(0.22, ctx.currentTime + t + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.14);
        o.connect(g); g.connect(ctx.destination);
        o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.16);
      });
      if (navigator.vibrate) navigator.vibrate([90, 60, 90]);
    } catch (e) {}
  }
  var bs = document.getElementById('btnSonido');
  function pintarSonido() {
    bs.innerHTML = sonido
      ? '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>'
      : '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg>';
    bs.style.color = sonido ? 'var(--p-oro)' : '';
  }
  bs.addEventListener('click', function () {
    sonido = !sonido;
    try { localStorage.setItem('mg_kds_sonido', sonido ? '1' : '0'); } catch (e) {}
    pintarSonido();
    if (sonido) bip();
  });
  pintarSonido();

  // ---------------------------------------------------------- pantalla completa
  document.getElementById('btnPantalla').addEventListener('click', function () {
    if (!document.fullscreenElement) { (document.documentElement.requestFullscreen || function () {}).call(document.documentElement); }
    else if (document.exitFullscreen) document.exitFullscreen();
  });

  // ---------------------------------------------------------- pintar
  function comandaHtml(o, col) {
    var h = '<article class="comanda comanda--' + o.alerta + '" data-id="' + o.id + '">';
    h += '<div class="comanda__cab"><div><div class="comanda__mesa">' + M.esc(o.mesa) + '</div>'
       + '<div class="comanda__codigo">' + M.esc(o.codigo) + ' · ' + M.esc(o.creado) + '</div></div>'
       + '<span class="comanda__tiempo">' + o.minutos + '\'</span></div>';
    h += '<div class="comanda__items">';
    o.items.forEach(function (i) {
      h += '<div class="comanda__item"><span class="comanda__cant">' + i.cantidad + '×</span><div>'
         + '<div>' + M.esc(i.nombre) + '</div>'
         + (i.mods.length ? '<div class="comanda__mods">' + M.esc(i.mods.join(' · ')) + '</div>' : '')
         + (i.notas ? '<div class="comanda__nota">📝 ' + M.esc(i.notas) + '</div>' : '')
         + '</div></div>';
    });
    h += '</div>';
    if (o.notas) h += '<div class="comanda__nota" style="margin-bottom:10px">📝 ' + M.esc(o.notas) + '</div>';
    h += '<div class="comanda__pie">';
    if (col !== 'nuevo') {
      h += '<button class="bt bt--suave" type="button" data-retroceder="' + o.id + '" aria-label="Regresar">'
         + '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg></button>';
    }
    h += '<button class="bt bt--oro" type="button" data-avanzar="' + o.id + '" data-col="' + col + '">'
       + M.esc(ACCION[col]) + '</button></div></article>';
    return h;
  }

  function pintar(t, avisarNuevos) {
    tablero = t;
    Object.keys(ACCION).forEach(function (col) {
      var lista = document.querySelector('[data-lista="' + col + '"]');
      var conteo = document.querySelector('[data-conteo="' + col + '"]');
      var arr = t[col] || [];
      if (conteo) conteo.textContent = arr.length;
      if (!lista) return;
      if (!arr.length) {
        lista.innerHTML = '<div style="text-align:center;padding:26px 12px;color:var(--p-tenue);font-size:13.5px">Sin comandas</div>';
        return;
      }
      lista.innerHTML = arr.map(function (o) { return comandaHtml(o, col); }).join('');
    });

    if (avisarNuevos) {
      var hayNuevo = false;
      (t.nuevo || []).forEach(function (o) {
        if (!conocidos[o.id]) { hayNuevo = true; }
        conocidos[o.id] = true;
      });
      if (hayNuevo) { bip(); M.avisar('¡Nueva comanda!', 'ok'); }
    } else {
      (t.nuevo || []).forEach(function (o) { conocidos[o.id] = true; });
    }
  }
  pintar(tablero, false);

  // ---------------------------------------------------------- acciones
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-avanzar]');
    if (a) {
      a.disabled = true;
      M.pedir('panel/cocina/avanzar', { id: Number(a.dataset.avanzar), estacion: estacion }).then(function (r) {
        if (r.ok) { pintar(r.tablero, false); M.avisar(r.mensaje, 'ok'); }
        else { a.disabled = false; M.avisar(r.error, 'error'); }
      });
      return;
    }
    var b = ev.target.closest('[data-retroceder]');
    if (b) {
      var id = Number(b.dataset.retroceder);
      var col = b.closest('[data-col]').dataset.col;
      var anterior = col === 'listo' ? 'preparando' : 'nuevo';
      M.pedir('panel/cocina/avanzar', { id: id, estado: anterior, estacion: estacion }).then(function (r) {
        if (r.ok) pintar(r.tablero, false);
        else M.avisar(r.error, 'error');
      });
    }
  });

  // ---------------------------------------------------------- tiempo real
  var sse = null, sondeo = null;
  function conectar() {
    if (!window.EventSource) { sondear(); return; }
    try {
      sse = new EventSource(M.url('panel/cocina/sse' + (estacion ? '?estacion=' + estacion : '')));
      sse.addEventListener('tablero', function (ev) {
        try { pintar(JSON.parse(ev.data), true); } catch (e) {}
      });
      sse.onerror = function () { if (sse && sse.readyState === 2) { sse = null; sondear(); } };
    } catch (e) { sondear(); }
  }
  function sondear() {
    if (sondeo) return;
    sondeo = setInterval(function () {
      if (document.hidden) return;
      M.pedir('panel/cocina/datos' + (estacion ? '?estacion=' + estacion : '')).then(function (r) {
        if (r.ok) pintar(r.tablero, true);
      });
    }, 5000);
  }
  conectar();

  // Reloj y contadores de minutos
  setInterval(function () {
    var d = new Date();
    document.getElementById('reloj').textContent =
      String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }, 10000);

  setInterval(function () {
    document.querySelectorAll('.comanda').forEach(function (c) {
      var t = c.querySelector('.comanda__tiempo');
      if (!t) return;
      var m = parseInt(t.textContent, 10) + 1;
      t.textContent = m + "'";
      c.classList.remove('comanda--verde', 'comanda--ambar', 'comanda--rojo');
      c.classList.add(m >= 25 ? 'comanda--rojo' : (m >= 12 ? 'comanda--ambar' : 'comanda--verde'));
    });
  }, 60000);

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !sse && !sondeo) conectar();
  });
})();
</script>
<?php View::stop(); ?>
