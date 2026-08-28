<?php
/**
 * Menú del cliente: la vitrina.
 * @var array $r, $mesa, $categorias, $porCategoria, $destacados, $promociones, $apertura, $zonas, $conMods, $pedidoActivo, $modos, $propinas
 */
use MenuGold\Core\App;
use MenuGold\Core\Lang;
use MenuGold\Core\View;
use MenuGold\Models\Category;
use MenuGold\Models\Product;
use MenuGold\Models\Promotion;
use MenuGold\Models\Restaurant;

$simbolo   = (string)($r['simbolo'] ?? 'Q');
$aceptaPed = Restaurant::aceptaPedidos($r) && $apertura['abierto'];
$soloConsulta = !Restaurant::aceptaPedidos($r);
$idiomas   = array_filter(array_map('trim', explode(',', (string)($r['idiomas'] ?? 'es'))));

// Datos del menú para el navegador (precios se revalidan en el servidor)
$catalogo = [];
foreach ($porCategoria as $cid => $lista) {
    foreach ($lista as $p) {
        $catalogo[(int)$p['id']] = [
            'id'      => (int)$p['id'],
            'nombre'  => t($p, 'nombre'),
            'precio'  => Product::precioVigente($p),
            'imagen'  => $p['imagen'] ? uploaded((string)$p['imagen']) : '',
            'agotado' => (int)$p['agotado'] === 1,
            'mods'    => in_array((int)$p['id'], $conMods, true),
        ];
    }
}
?>
<header class="portada">
  <?php if (!empty($r['portada'])): ?>
    <img class="portada__foto" src="<?= e(uploaded((string)$r['portada'])) ?>" alt="" fetchpriority="high" width="1600" height="900">
  <?php endif; ?>
  <div class="portada__velo"></div>
  <div class="portada__contenido">
    <?php if (!empty($r['logo'])): ?>
      <img class="portada__logo" src="<?= e(uploaded((string)$r['logo'])) ?>" alt="<?= e((string)$r['nombre']) ?>" width="92" height="92">
    <?php else: ?>
      <div class="portada__logo portada__logo--texto" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string)$r['nombre'], 0, 1))) ?></div>
    <?php endif; ?>

    <h1 class="portada__nombre"><?= e((string)$r['nombre']) ?></h1>
    <?php if (!empty($r['eslogan'])): ?>
      <p class="portada__eslogan"><?= e((string)$r['eslogan']) ?></p>
    <?php endif; ?>

    <div class="filete" aria-hidden="true"><?= icon('utensils') ?></div>

    <div class="portada__meta">
      <span class="pastilla <?= $apertura['abierto'] ? 'pastilla--abierto' : 'pastilla--cerrado' ?>">
        <span class="punto"></span><?= e($apertura['texto']) ?>
      </span>
      <?php if ($apertura['proximo']): ?>
        <span class="pastilla"><?= icon('clock') ?><?= e($apertura['proximo']) ?></span>
      <?php endif; ?>
      <?php if (!empty($r['direccion'])): ?>
        <?php $mapa = !empty($r['mapa_lat']) ? 'https://maps.google.com/?q=' . $r['mapa_lat'] . ',' . $r['mapa_lng'] : 'https://maps.google.com/?q=' . rawurlencode((string)$r['direccion']); ?>
        <a class="pastilla" href="<?= e($mapa) ?>" target="_blank" rel="noopener"><?= icon('map') ?><?= e(mb_strimwidth((string)$r['direccion'], 0, 34, '…')) ?></a>
      <?php endif; ?>
      <?php if (count($idiomas) > 1): ?>
        <span class="idiomas">
          <?php foreach ($idiomas as $l): ?>
            <a href="?lang=<?= e($l) ?>" aria-current="<?= Lang::current() === $l ? 'true' : 'false' ?>"><?= e(mb_strtoupper($l)) ?></a>
          <?php endforeach; ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($mesa): ?>
  <?php
  // Evita "Mesa Mesa 3" cuando el nombre ya incluye la palabra
  $etqMesa = (string)$mesa['nombre'];
  if (!preg_match('/^\s*(mesa|table)\b/iu', $etqMesa)) $etqMesa = __('mesa') . ' ' . $etqMesa;
  ?>
  <div class="aviso-mesa"><?= icon('table') ?> <?= e($etqMesa) ?> · Estás pidiendo desde tu mesa</div>
<?php endif; ?>

<?php if (!empty($r['mensaje_bienvenida'])): ?>
  <p style="text-align:center;padding:16px 22px 0;color:var(--texto-suave);font-size:14.5px;margin:0"><?= e((string)$r['mensaje_bienvenida']) ?></p>
<?php endif; ?>

<?php if ($pedidoActivo): ?>
  <div style="padding:16px 16px 0">
    <a class="promo" href="<?= e(url('r/' . $r['slug'] . '/pedido/' . $pedidoActivo['codigo'])) ?>" style="text-decoration:none">
      <span class="promo__valor"><?= icon('clock') ?></span>
      <span class="crece">
        <span class="promo__nombre"><?= e(__('seguir_pedido')) ?> · <?= e((string)$pedidoActivo['codigo']) ?></span>
        <span class="promo__desc"><?= e(\MenuGold\Models\Order::ETIQUETA_ESTADO[$pedidoActivo['estado']] ?? '') ?> · <?= e(money($pedidoActivo['total'], $simbolo)) ?></span>
      </span>
      <?= icon('chevron-right') ?>
    </a>
  </div>
<?php endif; ?>

<!-- ================= Barra de categorías ================= -->
<nav class="barra-cat" id="barraCat" aria-label="Categorías del menú">
  <div class="barra-cat__pistas" id="pistas" role="tablist">
    <?php foreach ($categorias as $i => $c): ?>
      <button class="pista" type="button" role="tab"
              data-ir="cat-<?= (int)$c['id'] ?>"
              aria-current="<?= $i === 0 ? 'true' : 'false' ?>"><?= e(t($c, 'nombre')) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="barra-buscar">
    <div class="campo-buscar">
      <?= icon('search') ?>
      <label class="solo-lectores" for="buscar"><?= e(__('buscar')) ?></label>
      <input type="search" id="buscar" placeholder="<?= e(__('buscar')) ?>" autocomplete="off" enterkeyhint="search">
      <button type="button" class="campo-buscar__limpiar oculto" id="limpiarBuscar" aria-label="Limpiar búsqueda"><?= icon('x', 'ico-sm') ?></button>
    </div>
  </div>
</nav>

<main id="contenido">

<?php if ($promociones): ?>
  <section class="destacados" aria-labelledby="tit-promos">
    <div class="seccion__cabecera"><h2 class="seccion__titulo" id="tit-promos">Promociones de hoy</h2></div>
    <div class="promos">
      <?php foreach ($promociones as $pr): ?>
        <article class="promo">
          <span class="promo__valor"><?= e(Promotion::etiquetaTipo((string)$pr['tipo'], $pr['valor'], $simbolo)) ?></span>
          <span class="crece">
            <span class="promo__nombre"><?= e((string)$pr['nombre']) ?></span>
            <?php if (!empty($pr['descripcion'])): ?><span class="promo__desc"><?= e((string)$pr['descripcion']) ?></span><?php endif; ?>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($destacados): ?>
  <section class="destacados" aria-labelledby="tit-dest">
    <div class="seccion__cabecera">
      <h2 class="seccion__titulo" id="tit-dest">Recomendados de la casa</h2>
      <p class="seccion__desc">Lo que más piden nuestros comensales</p>
    </div>
    <div class="destacados__pista">
      <?php foreach ($destacados as $p): ?>
        <button class="destacado" type="button" data-producto="<?= (int)$p['id'] ?>">
          <div class="destacado__foto">
            <?php if ($p['imagen']): ?>
              <img src="<?= e(uploaded((string)$p['imagen'])) ?>" alt="<?= e(t($p, 'nombre')) ?>" loading="lazy" width="224" height="132">
            <?php endif; ?>
          </div>
          <div class="destacado__cuerpo">
            <h3 class="destacado__nombre"><?= e(t($p, 'nombre')) ?></h3>
            <span class="precio"><?= e(money(Product::precioVigente($p), $simbolo)) ?></span>
          </div>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (!$categorias): ?>
  <div class="vacio"><?= icon('utensils', 'ico-lg') ?><p>Este menú se está preparando. Vuelve pronto.</p></div>
<?php endif; ?>

<?php foreach ($categorias as $c): ?>
  <?php $items = $porCategoria[(int)$c['id']] ?? []; if (!$items) continue; ?>
  <section class="seccion" id="cat-<?= (int)$c['id'] ?>" aria-labelledby="tit-<?= (int)$c['id'] ?>">
    <div class="seccion__cabecera">
      <h2 class="seccion__titulo" id="tit-<?= (int)$c['id'] ?>"><?= e(t($c, 'nombre')) ?></h2>
      <?php if (!empty($c['descripcion'])): ?><p class="seccion__desc"><?= e(t($c, 'descripcion')) ?></p><?php endif; ?>
      <?php $h = Category::textoHorario($c); if ($h): ?>
        <span class="seccion__horario"><?= icon('clock') ?><?= e($h) ?></span>
      <?php endif; ?>
    </div>
    <div class="platillos">
      <?php foreach ($items as $p): ?>
        <?php View::partial('menu/platillo', ['p' => $p, 'simbolo' => $simbolo, 'soloConsulta' => $soloConsulta]); ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<div id="sinResultados" class="vacio oculto"><?= icon('search', 'ico-lg') ?><p>No encontramos platillos con esa búsqueda.</p></div>

<footer class="pie-menu">
  <div class="pie-menu__logo"><?= e((string)$r['nombre']) ?></div>
  <?php if (!empty($r['mensaje_pie'])): ?><p><?= e((string)$r['mensaje_pie']) ?></p><?php endif; ?>
  <?php if (!empty($r['direccion'])): ?><p><?= e((string)$r['direccion']) ?></p><?php endif; ?>
  <?php if (!empty($r['telefono'])): ?><p><a href="tel:<?= e(preg_replace('/\s/', '', (string)$r['telefono'])) ?>"><?= e((string)$r['telefono']) ?></a></p><?php endif; ?>

  <div class="pie-menu__redes">
    <?php if (!empty($r['whatsapp'])): ?>
      <a href="https://wa.me/<?= e(preg_replace('/\D/', '', (string)$r['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('whatsapp') ?></a>
    <?php endif; ?>
    <?php foreach (['facebook' => 'globe', 'instagram' => 'image', 'tiktok' => 'play'] as $red => $ic): ?>
      <?php if (!empty($r[$red])): ?>
        <a href="<?= e((string)$r[$red]) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($red)) ?>"><?= icon($ic) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!empty($r['google_reviews'])): ?>
      <a href="<?= e((string)$r['google_reviews']) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('dejar_resena')) ?>"><?= icon('star') ?></a>
    <?php endif; ?>
  </div>
  <p class="pie-menu__firma">Menú digital por <strong>MenúGold</strong></p>
</footer>
</main>

<?php if (!$soloConsulta): ?>
  <!-- ================= Acciones de mesa ================= -->
  <?php if ($mesa && Restaurant::permiteModo($r, 'mesa')): ?>
    <div class="acciones-mesa" id="accionesMesa">
      <button class="fab" type="button" id="btnMesero" title="<?= e(__('llamar_mesero')) ?>" aria-label="<?= e(__('llamar_mesero')) ?>"><?= icon('bell') ?></button>
      <button class="fab" type="button" id="btnCuenta" title="<?= e(__('pedir_cuenta')) ?>" aria-label="<?= e(__('pedir_cuenta')) ?>"><?= icon('receipt') ?></button>
    </div>
  <?php endif; ?>

  <!-- ================= Carrito flotante ================= -->
  <div class="barra-carrito" id="barraCarrito">
    <button class="barra-carrito__btn" type="button" id="abrirCarrito">
      <span class="barra-carrito__izq">
        <span class="globo" id="carritoConteo">0</span>
        <span><?= e(__('ver_pedido')) ?></span>
      </span>
      <span class="mono" id="carritoTotal"><?= e($simbolo) ?>0.00</span>
    </button>
  </div>
<?php endif; ?>

<!-- ================= Modal del platillo ================= -->
<div class="modal" id="modalPlatillo" role="dialog" aria-modal="true" aria-labelledby="modalTitulo" hidden>
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <div class="modal__asa" aria-hidden="true"></div>
    <button class="modal__cerrar" type="button" data-cerrar aria-label="<?= e(__('cerrar')) ?>"><?= icon('x') ?></button>
    <div class="modal__scroll" id="modalScroll"></div>
    <div class="modal__pie" id="modalPie"></div>
  </div>
</div>

<!-- ================= Hoja del carrito ================= -->
<div class="hoja" id="hojaCarrito" role="dialog" aria-modal="true" aria-labelledby="hojaTitulo" hidden>
  <div class="hoja__fondo" data-cerrar-hoja></div>
  <div class="hoja__caja">
    <div class="hoja__cab">
      <h2 class="hoja__titulo" id="hojaTitulo"><?= e(__('mi_pedido')) ?></h2>
      <div class="centrado">
        <button class="btn btn--linea" type="button" id="vaciarCarrito" style="min-height:38px;padding:8px 14px;font-size:13px"><?= e(__('vaciar')) ?></button>
        <button class="modal__cerrar" type="button" data-cerrar-hoja aria-label="<?= e(__('cerrar')) ?>" style="position:static"><?= icon('x') ?></button>
      </div>
    </div>
    <div class="hoja__scroll" id="hojaScroll"></div>
    <div class="hoja__pie" id="hojaPie"></div>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(\MenuGold\Core\Security::nonce()) ?>">
window.MG.catalogo = <?= json_encode(array_values($catalogo), JSON_UNESCAPED_UNICODE) ?>;
window.MG.mesa = <?= json_encode($mesa ? ['id' => (int)$mesa['id'], 'nombre' => (string)$mesa['nombre']] : null, JSON_UNESCAPED_UNICODE) ?>;
window.MG.modos = <?= json_encode(array_values($modos)) ?>;
window.MG.propinas = <?= json_encode(array_values($propinas)) ?>;
window.MG.abierto = <?= $apertura['abierto'] ? 'true' : 'false' ?>;
window.MG.acepta = <?= $aceptaPed ? 'true' : 'false' ?>;
window.MG.soloConsulta = <?= $soloConsulta ? 'true' : 'false' ?>;
window.MG.motivoCerrado = <?= json_encode(trim(($apertura['abierto'] ? '' : $apertura['texto'] . '. ' . $apertura['proximo'])), JSON_UNESCAPED_UNICODE) ?>;
window.MG.zonas = <?= json_encode(array_map(static fn($z) => [
    'id' => (int)$z['id'], 'nombre' => (string)$z['nombre'],
    'costo' => (float)$z['costo'], 'minimo' => (float)$z['minimo'], 'tiempo' => (int)$z['tiempo_min'],
], $zonas), JSON_UNESCAPED_UNICODE) ?>;
window.MG.pagos = <?= json_encode(array_values(array_filter(array_map('trim', explode(',', (string)($r['metodos_pago'] ?? '')))))) ?>;
window.MG.banco = <?= json_encode((string)($r['datos_bancarios'] ?? '')) ?>;
window.MG.linkPago = <?= json_encode((string)($r['link_pago'] ?? '')) ?>;
window.MG.whatsapp = <?= json_encode(preg_replace('/\D/', '', (string)($r['whatsapp'] ?? ''))) ?>;
window.MG.notas = <?= (int)($r['notas_activas'] ?? 1) === 1 ? 'true' : 'false' ?>;
window.MG.pedidoMinimo = <?= json_encode((float)($r['pedido_minimo'] ?? 0)) ?>;
window.MG.nombreRest = <?= json_encode((string)$r['nombre'], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php View::stop(); ?>

<?php View::start('jsonld'); ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Menu',
    'name'     => 'Menú de ' . (string)$r['nombre'],
    'url'      => Restaurant::urlMenu($r),
    'inLanguage' => Lang::current(),
    'hasMenuSection' => array_values(array_map(static function ($c) use ($porCategoria, $r) {
        return [
            '@type' => 'MenuSection',
            'name'  => (string)$c['nombre'],
            'description' => (string)($c['descripcion'] ?? '') ?: null,
            'hasMenuItem' => array_values(array_map(static function ($p) use ($r) {
                return [
                    '@type' => 'MenuItem',
                    'name'  => (string)$p['nombre'],
                    'description' => mb_substr(trim(strip_tags((string)($p['descripcion'] ?? ''))), 0, 200) ?: null,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format(Product::precioVigente($p), 2, '.', ''),
                        'priceCurrency' => (string)($r['moneda'] ?? 'GTQ'),
                        'availability' => (int)$p['agotado'] === 1
                            ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
                    ],
                    'image' => !empty($p['imagen']) ? uploaded((string)$p['imagen']) : null,
                ];
            }, array_slice($porCategoria[(int)$c['id']] ?? [], 0, 40))),
        ];
    }, array_slice($categorias, 0, 20))),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php View::stop(); ?>
