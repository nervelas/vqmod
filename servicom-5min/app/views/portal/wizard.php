<?php
/**
 * Wizard /crear y /continuar/<token>.
 * Variables: $cfg (wa_servicom, precio_info, precio_tienda, precio_tarjeta, pres_max_mb, banco[banco,cuenta,titular,tipo]),
 *            $token (string|null), $draft (array|null: data, estado, paso, analisis, construccion, archivos, creado_en),
 *            $plan (string|null, preselección desde ?plan=), $nonce (opcional).
 */
require_once __DIR__ . '/_icons.php';
$cfg   = isset($cfg) && is_array($cfg) ? $cfg : [];
$token = isset($token) && is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $token : '';
$draft = isset($draft) && is_array($draft) ? $draft : null;
$nonceA = !empty($nonce) ? ' nonce="' . e($nonce) . '"' : '';
$presMb = (int)($cfg['pres_max_mb'] ?? 10);
$pInfo = (float)($cfg['precio_info'] ?? 1250); $pTienda = (float)($cfg['precio_tienda'] ?? 1750); $pTarjeta = (float)($cfg['precio_tarjeta'] ?? 750);
$boot = [
    'token' => $token,
    'plan'  => in_array((string)($plan ?? ''), ['info', 'tienda'], true) ? $plan : '',
    'draft' => $draft,
    'cfg'   => [
        'precio_info' => $pInfo, 'precio_tienda' => $pTienda, 'precio_tarjeta' => $pTarjeta, 'pres_max_mb' => $presMb,
        'wa_servicom' => (string)($cfg['wa_servicom'] ?? ''),
    ],
];
if (!function_exists('wz_id')) {
    function wz_id(string $k): string { return 'f-' . str_replace(['.', '_'], '-', $k); }
    /** Etiqueta con insignia de origen y "(opcional)". */
    function wz_lbl(string $k, string $label, array $o = []): string
    {
        $h = '<label for="' . wz_id($k) . '">' . e($label);
        if (!empty($o['opt'])) { $h .= ' <span class="opt">(opcional)</span>'; }
        elseif (!empty($o['req'])) { $h .= ' <span class="req" aria-hidden="true">*</span>'; }
        $h .= ' <span class="tag" data-o="' . e($k) . '" hidden>Tomado de tu presentación</span></label>';
        return $h;
    }
    function wz_in(string $k, string $label, array $o = []): void
    {
        $id = wz_id($k);
        echo '<div class="field' . (!empty($o['cls']) ? ' ' . e($o['cls']) : '') . '">', wz_lbl($k, $label, $o);
        $attrs = ' class="inp" id="' . $id . '" data-k="' . e($k) . '" type="' . e($o['type'] ?? 'text') . '"'
            . (isset($o['max']) ? ' maxlength="' . (int)$o['max'] . '"' : '')
            . (isset($o['im']) ? ' inputmode="' . e($o['im']) . '"' : '')
            . (isset($o['ac']) ? ' autocomplete="' . e($o['ac']) . '"' : ' autocomplete="off"')
            . (isset($o['ph']) ? ' placeholder="' . e($o['ph']) . '"' : '')
            . (isset($o['v']) ? ' data-v="' . e($o['v']) . '"' : '')
            . (isset($o['fmt']) ? ' data-fmt="' . e($o['fmt']) . '"' : '')
            . (!empty($o['t']) ? ' data-t="' . e($o['t']) . '"' : '')
            . ' autocapitalize="' . e($o['cap'] ?? 'sentences') . '" enterkeyhint="next" aria-describedby="' . $id . '-e' . (!empty($o['help']) ? ' ' . $id . '-h' : '') . '"';
        if (!empty($o['pre'])) { echo '<div class="pfx"><span aria-hidden="true">', e($o['pre']), '</span><input', $attrs, '></div>'; }
        else { echo '<input', $attrs, '>'; }
        if (!empty($o['help'])) { echo '<p class="help" id="', $id, '-h">', e($o['help']), '</p>'; }
        echo '<p class="err" id="', $id, '-e"></p></div>';
    }
    function wz_ta(string $k, string $label, array $o = []): void
    {
        $id = wz_id($k);
        echo '<div class="field">', wz_lbl($k, $label, $o),
            '<textarea class="inp" id="', $id, '" data-k="', e($k), '" rows="', (int)($o['rows'] ?? 3), '"',
            isset($o['max']) ? ' maxlength="' . (int)$o['max'] . '"' : '',
            isset($o['ph']) ? ' placeholder="' . e($o['ph']) . '"' : '',
            isset($o['v']) ? ' data-v="' . e($o['v']) . '"' : '',
            ' aria-describedby="', $id, '-e', !empty($o['help']) ? ' ' . $id . '-h' : '', '"></textarea>';
        if (!empty($o['help'])) { echo '<p class="help" id="', $id, '-h">', e($o['help']), '</p>'; }
        echo '<p class="err" id="', $id, '-e"></p></div>';
    }
    /** Grupo de radios como segmentos. $opts = [valor => etiqueta] */
    function wz_seg(string $k, string $legend, array $opts, array $o = []): void
    {
        echo '<fieldset class="field seg-f"><legend>', e($legend), !empty($o['opt']) ? ' <span class="opt">(opcional)</span>' : '', ' <span class="tag" data-o="', e($k), '" hidden>Tomado de tu presentación</span></legend><div class="seg">';
        foreach ($opts as $v => $lab) {
            echo '<label class="seg__o"><input type="radio" name="', e($k), '" data-k="', e($k), '"', !empty($o['t']) ? ' data-t="' . e($o['t']) . '"' : '', ' value="', e((string)$v), '"><span>', e($lab), '</span></label>';
        }
        echo '</div><p class="err" id="', wz_id($k), '-e"></p></fieldset>';
    }
}
$rubros = [
    'abogado' => ['Abogado/a', 'scale'], 'clinica' => ['Clínica / médico', 'heart'], 'taller' => ['Taller', 'wrench'],
    'ropa' => ['Ropa', 'shirt'], 'restaurante' => ['Restaurante', 'fork'], 'transporte' => ['Transporte', 'truck'],
    'contabilidad' => ['Contabilidad', 'calc'], 'importaciones' => ['Importaciones', 'ship'], 'otro' => ['Otro', 'dots'],
];
$estilos = [1 => 'Oscuro elegante', 2 => 'Claro editorial', 3 => 'Oscuro moderno', 4 => 'Claro clásico', 5 => 'Claro audaz'];
$paises = ['502' => 'Guatemala', '503' => 'El Salvador', '504' => 'Honduras', '505' => 'Nicaragua', '506' => 'Costa Rica', '507' => 'Panamá', '52' => 'México', '1' => 'EE. UU. / Canadá', '34' => 'España', '57' => 'Colombia'];
$redes = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn'];
?>
<link rel="stylesheet" href="/assets/css/wizard.css?v=1">
<div class="wz" id="wz">
<header class="wz-top">
  <div class="wrap wz-top__in">
    <a class="brand" href="/" aria-label="Servicom, ir al inicio"><span>Servicom</span></a>
    <div class="wz-save" id="save" role="status" aria-live="polite"><i aria-hidden="true"></i><span id="save-t">Se guarda solo</span></div>
    <button type="button" class="btn btn--ghost btn--sm" id="copy-link" hidden><?= ico('copy') ?><span>Copiar enlace</span><span class="sr"> para retomar después</span></button>
  </div>
  <div class="wz-prog">
    <div class="wrap">
      <div class="wz-prog__t"><span id="prog-n">Paso 1 de 9</span><b id="prog-name">Plan</b></div>
      <div class="bar bar--s" role="progressbar" aria-label="Avance del formulario" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="prog"><i id="prog-bar"></i></div>
    </div>
  </div>
  <div class="wz-pill" id="pill" hidden role="status" aria-live="polite"></div>
</header>

<main id="main" class="wrap wz-grid">
  <nav class="wz-side" aria-label="Pasos" hidden><ol id="side"></ol></nav>
  <div class="wz-main">
    <div class="wz-tip" id="tip" hidden><?= ico('lock') ?><p>Tu avance se guarda solo. Para retomarlo después usa <b>Copiar enlace</b>.</p><button type="button" class="x" id="tip-x" aria-label="Cerrar aviso"><?= ico('x') ?></button></div>

    <!-- PLAN -->
    <section class="step" data-step="plan" aria-labelledby="t-plan" hidden>
      <h2 id="t-plan" tabindex="-1">Elige tu plan</h2>
      <p class="lead">Todos incluyen dominio .com, hosting, SSL y 10 correos por un año. Puedes cambiar de plan antes de crear tu vista previa.</p>
      <fieldset class="plans-f"><legend class="sr">Plan</legend>
        <label class="plan-o"><input type="radio" name="plan" data-k="plan" value="info"><span class="plan-o__c"><b class="plan-o__n"><?= ico('layout') ?>Página informativa</b><span class="plan-o__d">Presenta tu negocio y servicios, y recibe contactos.</span><span class="plan-o__p"><?= e(money_q($pInfo)) ?><small>/año</small></span></span></label>
        <label class="plan-o"><input type="radio" name="plan" data-k="plan" value="tienda"><span class="plan-o__c"><b class="plan-o__n"><?= ico('bag') ?>Tienda virtual</b><span class="plan-o__d">Vende con carrito, inventario y pedidos a tu correo.</span><span class="plan-o__p"><?= e(money_q($pTienda)) ?><small>/año</small></span></span></label>
      </fieldset>
      <p class="err" id="f-plan-e"></p>
      <label class="chk"><input type="checkbox" data-k="tarjeta_extra"><span><b>Pago con tarjeta (+<?= e(money_q($pTarjeta)) ?>/año)</b> <span class="opt">(opcional)</span><small>Se activa con Visanet/Epay y requiere afiliación.</small></span></label>
    </section>

    <!-- PRESENTACIÓN -->
    <section class="step" data-step="pres" aria-labelledby="t-pres" hidden>
      <div class="pres-hero">
        <span class="pres-ico"><?= ico('file') ?></span>
        <h2 id="t-pres" tabindex="-1">¿Tienes una presentación de tu negocio?</h2>
        <p class="lead">Súbela y nosotros llenamos los datos por ti. <span class="opt">(opcional)</span></p>
      </div>
      <label class="chk chk--k"><input type="checkbox" id="pres-ok" data-k="presentacion.acepto"><span>Mi archivo se usará solo para crear mi web, se procesa con inteligencia artificial y se elimina al finalizar.</span></label>
      <div id="pres-box">
        <label class="drop drop--big" id="pres-drop" for="pres-file" aria-disabled="true"><?= ico('upload') ?><span>Subir mi presentación</span><small>PDF, PowerPoint (.pptx) o Word (.docx) · hasta <?= (int)$presMb ?> MB</small>
          <input class="sr" type="file" id="pres-file" accept=".pdf,.pptx,.docx,application/pdf,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.openxmlformats-officedocument.wordprocessingml.document" disabled aria-describedby="pres-e"></label>
        <p class="help" id="pres-h">Marca la casilla de arriba para habilitar la subida.</p>
        <p class="err" id="pres-e" role="alert"></p>
        <div class="filecard" id="pres-card" hidden>
          <span class="filecard__i"><?= ico('file') ?></span>
          <div><b id="pres-name"></b><small id="pres-meta"></small><div class="bar bar--s" id="pres-up" hidden><i></i></div></div>
          <button type="button" class="btn btn--ghost btn--sm" id="pres-rm">Quitar</button>
        </div>
      </div>
      <button type="button" class="btn btn--ghost btn--block" id="pres-skip">Continuar sin presentación</button>
    </section>

    <!-- REVISIÓN -->
    <section class="step" data-step="revision" aria-labelledby="t-revision" hidden>
      <h2 id="t-revision" tabindex="-1">Esto encontramos en tu presentación</h2>
      <p class="lead">Marca lo que quieres usar y corrige lo que haga falta. Lo que ya escribiste en el formulario siempre tiene prioridad.</p>
      <div id="rev-conf" class="notice" hidden></div>
      <div id="rev-cards" class="rev"></div>
      <div class="rev-act">
        <button type="button" class="btn btn--gold btn--block" id="rev-use">Usar esto</button>
        <button type="button" class="btn btn--ghost btn--block" id="rev-no">No usar la presentación</button>
      </div>
      <p class="err" id="rev-e" role="alert"></p>
    </section>

    <!-- NEGOCIO -->
    <section class="step" data-step="negocio" aria-labelledby="t-negocio" hidden>
      <h2 id="t-negocio" tabindex="-1">Tu negocio</h2>
      <?php wz_in('negocio.nombre', 'Nombre de tu negocio', ['req' => 1, 'max' => 80, 'v' => 'req', 'ac' => 'organization', 'ph' => 'Ej. Bufete Pérez & Asociados']); ?>
      <fieldset class="field"><legend>Rubro <span class="req" aria-hidden="true">*</span> <span class="tag" data-o="negocio.rubro" hidden>Tomado de tu presentación</span></legend>
        <div class="rubros">
<?php foreach ($rubros as $k => $r): ?>
          <label class="rubro"><input type="radio" name="negocio.rubro" data-k="negocio.rubro" value="<?= e($k) ?>"><span><?= ico($r[1]) ?><b><?= e($r[0]) ?></b></span></label>
<?php endforeach; ?>
        </div>
        <p class="err" id="f-negocio-rubro-e"></p>
      </fieldset>
      <div id="rubro-otro" hidden><?php wz_in('negocio.rubro_otro', 'Especifica tu rubro', ['max' => 60, 'ph' => 'Ej. Veterinaria']); ?></div>
      <?php wz_seg('negocio.idioma', 'Idioma de tu web', ['es' => 'Español', 'en' => 'English']); ?>
      <div class="field"><span class="lbl">Logo <span class="opt">(opcional)</span></span>
        <div class="logo-f"><span class="thumb thumb--l" id="logo-th"><?= ico('image') ?></span>
          <div class="logo-f__b"><button type="button" class="btn btn--ghost btn--sm" id="logo-up">Subir logo</button><button type="button" class="btn btn--ghost btn--sm" id="logo-rm" hidden>Quitar</button><input class="sr" type="file" id="logo-file" accept="image/png,image/jpeg,image/webp" tabindex="-1" aria-label="Archivo de logo"></div></div>
        <div id="logo-cands" hidden><p class="help">¿Alguna de estas imágenes de tu presentación es tu logo? Tócala para usarla.</p><div class="cands" id="logo-grid"></div></div>
        <p class="err" id="logo-e" role="alert"></p>
      </div>
      <fieldset class="field"><legend>Ambiente de tu web</legend>
        <p class="help">Los colores de tu web se toman automáticamente de tu logo. Aquí eliges el ambiente (oscuro o claro) y el tipo de letra.</p>
        <div class="estilos">
<?php foreach ($estilos as $n => $nm): ?>
          <label class="estilo"><input type="radio" name="negocio.estilo" data-k="negocio.estilo" data-t="int" value="<?= $n ?>">
            <span class="mini st<?= $n ?>" aria-hidden="true"><span class="mh"><i></i><i></i><i></i></span><span class="mb"><b></b><b></b><u></u></span><span class="mc"><i></i><i></i><i></i></span></span>
            <span class="estilo__n"><?= e($nm) ?></span></label>
<?php endforeach; ?>
        </div>
      </fieldset>
    </section>

    <!-- DOMINIO Y CORREOS -->
    <section class="step" data-step="dominio" aria-labelledby="t-dominio" hidden>
      <h2 id="t-dominio" tabindex="-1">Dominio y correos</h2>
      <?php wz_seg('dominio.tiene', '¿Ya tienes un dominio .com?', ['1' => 'Sí, ya tengo uno', '0' => 'Quiero uno nuevo'], ['t' => 'bool']); ?>
      <div id="dom-si"><?php wz_in('dominio.dominio', 'Tu dominio .com', ['ph' => 'tunegocio.com', 'v' => 'domain', 'fmt' => 'domain', 'cap' => 'none', 'im' => 'url', 'max' => 80, 'help' => 'Lo conectaremos a tu nueva web.']); ?></div>
      <div id="dom-no"><?php wz_in('dominio.deseado', 'Nombre que te gustaría', ['opt' => 1, 'ph' => 'tunegocio', 'v' => 'domname', 'fmt' => 'domname', 'cap' => 'none', 'max' => 60, 'help' => 'Solo el nombre, sin .com. Verificamos que esté disponible.']); ?></div>
      <div class="field"><label for="mail-in">Correos corporativos <span class="opt">(hasta 10, opcional)</span></label>
        <div class="mailadd"><div class="pfx pfx--r"><input class="inp" id="mail-in" type="text" inputmode="email" autocapitalize="none" autocomplete="off" enterkeyhint="done" placeholder="ventas" maxlength="30" aria-describedby="mail-h mail-e"><span id="mail-sfx" aria-hidden="true">@tudominio.com</span></div><button type="button" class="btn btn--ghost" id="mail-add">Agregar</button></div>
        <p class="help" id="mail-h">Escribe solo lo anterior al @. Si lo dejas vacío usaremos <b>info@</b>.</p>
        <ul class="chips" id="mail-chips" aria-label="Correos agregados"></ul>
        <p class="err" id="mail-e"></p>
      </div>
      <?php wz_in('correo_contacto', 'Tu correo personal', ['opt' => 1, 'type' => 'email', 'im' => 'email', 'ac' => 'email', 'cap' => 'none', 'v' => 'email', 'ph' => 'tucorreo@gmail.com', 'max' => 120, 'help' => 'Aquí recibirás los mensajes del formulario de contacto.']); ?>
    </section>

    <!-- CONTENIDO -->
    <section class="step" data-step="contenido" aria-labelledby="t-contenido" hidden>
      <h2 id="t-contenido" tabindex="-1">Contenido de tu web</h2>
      <?php wz_in('contenido.frase', 'Frase principal', ['opt' => 1, 'max' => 90, 'ph' => 'Ej. Asesoría legal confiable en Guatemala']); ?>
      <?php wz_in('contenido.apoyo', 'Línea de apoyo', ['opt' => 1, 'max' => 180, 'ph' => 'Una frase corta que complemente la principal']); ?>
      <div class="field"><span class="lbl">Fotos del banner (1 a 3) <span class="opt">(opcional)</span></span><div class="gal" id="g-banner"></div><p class="err" id="g-banner-e" role="alert"></p></div>
      <?php wz_ta('contenido.quienes', 'Quiénes somos', ['opt' => 1, 'max' => 1200, 'rows' => 4, 'ph' => 'Cuéntanos tu historia, tu experiencia y lo que te diferencia.']); ?>
      <div class="field"><span class="lbl" id="srv-lbl">Servicios <span class="req" id="srv-req" aria-hidden="true">*</span><span class="opt" id="srv-opt" hidden> (opcional)</span> <span class="count" id="srv-n"></span></span>
        <p class="help">Agrega cada servicio con su nombre, una línea de descripción y una foto.</p>
        <div class="list" id="l-srv"></div>
        <button type="button" class="btn btn--ghost btn--block" id="srv-add"><?= ico('plus') ?>Agregar otro servicio</button>
        <p class="err" id="srv-e" role="alert"></p>
      </div>
      <div class="field"><span class="lbl">Galería de fotos <span class="opt">(opcional)</span></span><div class="gal" id="g-gal"></div></div>
      <?php wz_in('contenido.youtube', 'Video de YouTube', ['opt' => 1, 'type' => 'url', 'im' => 'url', 'cap' => 'none', 'v' => 'url', 'ph' => 'https://www.youtube.com/watch?v=…', 'max' => 200]); ?>
    </section>

    <!-- PRODUCTOS -->
    <section class="step" data-step="productos" aria-labelledby="t-productos" hidden>
      <h2 id="t-productos" tabindex="-1">Tus productos</h2>
      <div class="field"><span class="lbl">Categorías <span class="opt">(opcional)</span> <span class="count" id="cat-n"></span></span>
        <p class="help">Puedes crear subcategorías eligiendo una categoría principal.</p>
        <div class="list list--c" id="l-cat"></div>
        <button type="button" class="btn btn--ghost btn--block" id="cat-add"><?= ico('plus') ?>Agregar categoría</button>
      </div>
      <div class="field"><span class="lbl">Productos <span class="req" aria-hidden="true">*</span> <span class="count" id="prd-n"></span></span>
        <div class="list" id="l-prd"></div>
        <button type="button" class="btn btn--ghost btn--block" id="prd-add"><?= ico('plus') ?>Agregar otro producto</button>
        <p class="err" id="prd-e" role="alert"></p>
      </div>
      <div class="row2">
        <?php wz_in('tienda.umbral_stock', 'Avisarme cuando queden', ['im' => 'numeric', 'v' => 'int', 'max' => 5, 'cap' => 'none', 'help' => 'unidades o menos (stock bajo).']); ?>
        <?php wz_in('tienda.correo_alertas', 'Correo para alertas de stock', ['opt' => 1, 'type' => 'email', 'im' => 'email', 'ac' => 'email', 'cap' => 'none', 'v' => 'email', 'max' => 120]); ?>
      </div>
    </section>

    <!-- COBROS -->
    <section class="step" data-step="cobros" aria-labelledby="t-cobros" hidden>
      <h2 id="t-cobros" tabindex="-1">Cobros y pedidos</h2>
      <?php wz_in('tienda.correo_pedidos', 'Correo para recibir pedidos', ['req' => 1, 'type' => 'email', 'im' => 'email', 'ac' => 'email', 'cap' => 'none', 'v' => 'req email', 'max' => 120, 'ph' => 'pedidos@tunegocio.com']); ?>
      <fieldset class="field fs"><legend>Tus datos bancarios <span class="opt">(para que tus clientes te depositen)</span></legend>
        <?php wz_in('tienda.banco.banco', 'Banco', ['opt' => 1, 'max' => 60, 'ph' => 'Ej. Banco Industrial']); ?>
        <?php wz_in('tienda.banco.numero', 'Número de cuenta', ['opt' => 1, 'im' => 'numeric', 'max' => 30, 'cap' => 'none', 'v' => 'acct']); ?>
        <?php wz_in('tienda.banco.titular', 'Nombre del titular', ['opt' => 1, 'max' => 80, 'ac' => 'name']); ?>
        <div class="field"><label for="f-tienda-banco-tipo">Tipo de cuenta <span class="opt">(opcional)</span></label><select class="inp" id="f-tienda-banco-tipo" data-k="tienda.banco.tipo"><option value="">Selecciona…</option><option>Monetaria</option><option>Ahorro</option></select></div>
      </fieldset>
      <?php wz_seg('tienda.contra_entrega', '¿Aceptas pago contra entrega?', ['1' => 'Sí', '0' => 'No'], ['t' => 'bool']); ?>
      <?php wz_ta('tienda.nota_entrega', 'Nota de entrega', ['opt' => 1, 'max' => 300, 'rows' => 2, 'ph' => 'Ej. Entregas en la capital en 24 horas.']); ?>
      <fieldset class="field"><legend>¿Quieres pago con tarjeta?</legend>
        <div class="seg"><label class="seg__o"><input type="radio" name="tarjeta_extra" data-k="tarjeta_extra" data-t="bool" value="1"><span>Sí</span></label><label class="seg__o"><input type="radio" name="tarjeta_extra" data-k="tarjeta_extra" data-t="bool" value="0"><span>No</span></label></div>
        <p class="notice notice--i">Tiene un costo extra de <?= e(money_q($pTarjeta)) ?> al año y requiere afiliación a Visanet con Epay.</p>
      </fieldset>
    </section>

    <!-- CONTACTO -->
    <section class="step" data-step="contacto" aria-labelledby="t-contacto" hidden>
      <h2 id="t-contacto" tabindex="-1">Cómo te contactan</h2>
      <div class="field"><label for="wa-n">WhatsApp <span class="req" aria-hidden="true">*</span> <span class="tag" data-o="contacto.whatsapp" hidden>Tomado de tu presentación</span></label>
        <div class="wa-f"><select class="inp" id="wa-cc" aria-label="Código de país" autocomplete="tel-country-code">
<?php foreach ($paises as $c => $n): ?><option value="<?= e((string)$c) ?>">+<?= e((string)$c) ?> <?= e($n) ?></option><?php endforeach; ?>
        </select><input class="inp" id="wa-n" type="tel" inputmode="tel" autocomplete="tel-national" placeholder="5555 1234" maxlength="16" aria-describedby="wa-e"></div>
        <p class="err" id="wa-e"></p></div>
      <?php wz_ta('contacto.whatsapp_msg', 'Mensaje inicial de WhatsApp', ['opt' => 1, 'max' => 200, 'rows' => 2, 'help' => 'Lo que verá tu cliente escrito al tocar el botón de WhatsApp.']); ?>
      <?php wz_in('contacto.telefono', 'Teléfono', ['opt' => 1, 'type' => 'tel', 'im' => 'tel', 'ac' => 'tel', 'max' => 30, 'cap' => 'none', 'v' => 'phone']); ?>
      <?php wz_in('contacto.direccion', 'Dirección', ['opt' => 1, 'max' => 160, 'ac' => 'street-address', 'ph' => 'Zona, ciudad']); ?>
      <?php wz_in('contacto.mapa_url', 'Enlace de Google Maps', ['opt' => 1, 'type' => 'url', 'im' => 'url', 'cap' => 'none', 'v' => 'url', 'max' => 300, 'ph' => 'https://maps.app.goo.gl/…', 'help' => 'En Google Maps toca Compartir y copia el enlace.']); ?>
      <?php wz_in('contacto.horario', 'Horario de atención', ['opt' => 1, 'max' => 120, 'ph' => 'Lunes a viernes, 8:00 a 17:00']); ?>
      <details class="redes"><summary>Redes sociales <span class="opt">(opcional)</span><?= ico('down') ?></summary>
<?php foreach ($redes as $k => $n): wz_in('contacto.redes.' . $k, $n, ['opt' => 1, 'type' => 'text', 'cap' => 'none', 'im' => 'url', 'v' => 'social', 'max' => 200, 'ph' => 'Enlace o @usuario']); endforeach; ?>
      </details>
    </section>

    <!-- RESUMEN -->
    <section class="step" data-step="resumen" aria-labelledby="t-resumen" hidden>
      <h2 id="t-resumen" tabindex="-1">Revisa y crea tu vista previa</h2>
      <div id="res-pend" class="notice notice--i" hidden></div>
      <div id="res-cards" class="sum"></div>
      <div class="hp" aria-hidden="true"><label>No llenes este campo<input type="text" name="web_sitio" id="web_sitio" tabindex="-1" autocomplete="off"></label></div>
      <p class="err" id="res-e" role="alert"></p>
      <div id="build-idle"><button type="button" class="btn btn--gold btn--block btn--lg" id="build-go"><?= ico('spark') ?><span>Crear mi vista previa</span></button><p class="help ctr">Toma unos minutos. Podrás editar todo antes de pagar.</p></div>
      <div id="build" class="card buildbox" hidden aria-live="polite">
        <h3 id="b-title">Armando tu web</h3>
        <div class="bar" role="progressbar" aria-label="Progreso de construcción" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="b-prog"><i id="b-bar"></i></div>
        <p class="help"><span id="b-pct">0 %</span> · <span id="b-msg">Iniciando…</span></p>
        <ul class="steps" id="b-steps"></ul>
        <div id="b-done" hidden>
          <p class="b-ok"><?= ico('check') ?>¡Tu vista previa está lista!</p>
          <a class="btn btn--gold btn--block" id="b-view" href="#" target="_blank" rel="noopener">Ver mi vista previa<span class="sr"> (se abre en una pestaña nueva)</span></a>
          <div class="row2"><button type="button" class="btn btn--gold btn--block" id="b-pay">Aprobar y pagar</button><button type="button" class="btn btn--ghost btn--block" id="b-edit">Editar datos</button></div>
        </div>
        <div id="b-fail" hidden><p class="help">Está tardando más de lo normal. Tus datos están a salvo.</p><button type="button" class="btn btn--gold btn--block" id="b-retry">Volver a intentar</button></div>
      </div>
    </section>

    <!-- PAGO -->
    <section class="step" data-step="pago" aria-labelledby="t-pago" hidden>
      <h2 id="t-pago" tabindex="-1">Aprueba y paga</h2>
      <p class="lead">Paga por transferencia o depósito y sube tu comprobante.</p>
      <?php $plan = 'info'; $tarjeta = false; $total = $pInfo; include __DIR__ . '/_pay.php'; ?>
    </section>

    <div class="wz-nav" id="nav">
      <button type="button" class="btn btn--ghost" id="nav-back"><?= ico('arrow', 'flip') ?>Atrás</button>
      <button type="button" class="btn btn--gold" id="nav-next"><span id="nav-next-t">Continuar</span><?= ico('arrow') ?></button>
    </div>
  </div>
</main>
</div>
<script type="application/json" id="s5-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?></script>
<script src="/assets/js/s5.js?v=1" defer<?= $nonceA ?>></script>
<script src="/assets/js/wizard.js?v=1" defer<?= $nonceA ?>></script>
