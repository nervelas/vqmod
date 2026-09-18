<?php
/**
 * Kaptor - Ajustes del sitio.
 *
 * Todo lo que se puede personalizar sin tocar código: identidad, textos,
 * colores, motor de extracción, accesos y límites.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

/** Campos de texto libre que se guardan tal cual. */
const CAMPOS_TEXTO = [
    'sitio_nombre', 'sitio_lema', 'sitio_descripcion', 'pie_texto',
    'hero_titulo', 'hero_subtitulo', 'hero_placeholder', 'hero_boton', 'hero_etiqueta', 'aviso_legal',
    'user_agent', 'headless_binario', 'dominios_excluidos', 'prefijo_pais',
    'remitente_postal', 'cron_clave',
];
/** Interruptores (se guardan como 1 o 0). */
const CAMPOS_BOOL = [
    'rastreo_profundo', 'analizar_js_css', 'analizar_sitemap', 'analizar_json',
    'verificar_mx', 'tld_estricto', 'permitir_privadas', 'ssl_estricto', 'headless_activo', 'buscar_whatsapp',
    'campanas_activas', 'seguimiento_aperturas', 'seguimiento_clics',
    'acceso_publico', 'registro_publico', 'guardar_historial',
];
/** Números con su rango permitido: clave => [mínimo, máximo]. */
const CAMPOS_NUM = [
    'timeout'         => [3, 180],
    'max_paginas'     => [1, 1000],
    'max_profundidad' => [0, 10],
    'max_correos'     => [1, 100000],
    'max_telefonos'   => [1, 100000],
    'max_bytes'       => [50000, 20000000],
    'pausa_ms'        => [0, 5000],
    'limite_ip_hora'  => [0, 100000],
    'retencion_dias'  => [0, 3650],
    'smtp_timeout'    => [5, 120],
    'lote_envio'      => [1, 500],
];
/** Colores en formato #RRGGBB. */
const CAMPOS_COLOR = ['color_fondo', 'color_oro', 'color_neon', 'color_texto'];

$mensaje = '';

/**
 * Guarda el logo subido y devuelve su ruta relativa.
 *
 * @return array{ok:bool,ruta?:string,error?:string}
 */
function guardar_logo(array $archivo): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No se pudo subir el archivo (código ' . $archivo['error'] . ').'];
    }
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'El logo no puede pesar más de 2 MB.'];
    }

    $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
    $permitidas = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];
    if (!in_array($extension, $permitidas, true)) {
        return ['ok' => false, 'error' => 'Formato no admitido. Usa PNG, JPG, WEBP, GIF o SVG.'];
    }

    // Los mapas de bits deben ser imágenes de verdad.
    if ($extension !== 'svg') {
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
        }
    }

    $destinoDir = CR_RAIZ . '/assets/subidas';
    if (!is_dir($destinoDir)) { @mkdir($destinoDir, 0755, true); }
    if (!is_writable($destinoDir)) {
        return ['ok' => false, 'error' => 'La carpeta assets/subidas/ no tiene permiso de escritura.'];
    }

    $nombre  = 'logo-' . date('YmdHis') . '-' . substr(cr_aleatorio(4), 0, 6) . '.' . $extension;
    $destino = $destinoDir . '/' . $nombre;

    if ($extension === 'svg') {
        // Se limpia el SVG de scripts y de atributos de evento antes de guardarlo.
        $svg = (string) file_get_contents($archivo['tmp_name']);
        $svg = preg_replace('~<script\b.*?</script>~is', '', $svg) ?? $svg;
        $svg = preg_replace('~\son\w+\s*=\s*(["\']).*?\1~is', '', $svg) ?? $svg;
        $svg = preg_replace('~(href|xlink:href)\s*=\s*(["\'])\s*javascript:.*?\2~is', '', $svg) ?? $svg;
        if (@file_put_contents($destino, $svg) === false) {
            return ['ok' => false, 'error' => 'No se pudo guardar el logo.'];
        }
    } elseif (!@move_uploaded_file($archivo['tmp_name'], $destino)) {
        return ['ok' => false, 'error' => 'No se pudo guardar el logo.'];
    }

    @chmod($destino, 0644);
    return ['ok' => true, 'ruta' => 'assets/subidas/' . $nombre];
}

// ------------------------------------------------------------------ guardar
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $nuevos = [];

    foreach (CAMPOS_TEXTO as $clave) {
        if (!array_key_exists($clave, $_POST)) { continue; }
        $nuevos[$clave] = mb_substr(trim((string) $_POST[$clave]), 0, 8000);
    }
    // Solo se tocan los interruptores que venían realmente en el formulario.
    // Cada uno envía un campo testigo (_presente[]), así un envío parcial nunca
    // apaga por error opciones que no estaban en pantalla.
    $presentes = (array) ($_POST['_presente'] ?? []);
    foreach (CAMPOS_BOOL as $clave) {
        if (!in_array($clave, $presentes, true)) { continue; }
        $nuevos[$clave] = !empty($_POST[$clave]) ? '1' : '0';
    }
    foreach (CAMPOS_NUM as $clave => [$min, $max]) {
        if (!isset($_POST[$clave])) { continue; }
        $nuevos[$clave] = (string) max($min, min($max, (int) $_POST[$clave]));
    }
    foreach (CAMPOS_COLOR as $clave) {
        if (!isset($_POST[$clave])) { continue; }
        $valor = trim((string) $_POST[$clave]);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) { $nuevos[$clave] = strtoupper($valor); }
    }
    if (isset($_POST['tema_por_defecto'])) {
        $nuevos['tema_por_defecto'] = (string) $_POST['tema_por_defecto'] === 'claro' ? 'claro' : 'oscuro';
    }

    // Logo: subida o borrado
    if (!empty($_POST['borrar_logo'])) {
        $nuevos['logo'] = '';
    } elseif (!empty($_FILES['logo_archivo']['name'])) {
        $sub = guardar_logo($_FILES['logo_archivo']);
        if (!empty($sub['error'])) {
            cr_flash('error', $sub['error']);
        } elseif (!empty($sub['ok'])) {
            $nuevos['logo'] = $sub['ruta'];
        }
    }

    Ajustes::guardarVarios($nuevos);
    cr_flash('exito', 'Ajustes guardados correctamente.');
    cr_redirigir('admin/ajustes.php');
}

$a = Ajustes::todos();

/** Dibuja una fila de ajuste con su etiqueta y su explicación. */
function fila(string $titulo, string $pista, string $control): void
{
    echo '<div class="ajuste"><div><div class="titulo">' . e($titulo) . '</div>'
        . '<div class="pista">' . $pista . '</div></div>'
        . '<div class="control">' . $control . '</div></div>';
}

/** Interruptor reutilizable. */
function interruptor(string $clave, bool $activo, string $texto = 'Activado'): string
{
    return '<input type="hidden" name="_presente[]" value="' . e($clave) . '">'
        . '<label class="interruptor"><input type="checkbox" name="' . e($clave) . '" value="1" '
        . ($activo ? 'checked' : '') . '><span class="pista" aria-hidden="true"></span>'
        . '<span class="txt">' . e($texto) . '</span></label>';
}

admin_cabecera(['titulo' => 'Ajustes', 'activo' => 'ajustes.php']);
?>

<form method="post" enctype="multipart/form-data">
  <?= Seguridad::campoCsrf() ?>

  <div class="pestanas" role="tablist">
    <button type="button" class="activa" data-hoja="h-identidad">Identidad</button>
    <button type="button" data-hoja="h-textos">Textos</button>
    <button type="button" data-hoja="h-apariencia">Apariencia</button>
    <button type="button" data-hoja="h-motor">Motor</button>
    <button type="button" data-hoja="h-acceso">Acceso y límites</button>
    <button type="button" data-hoja="h-campanas">Campañas</button>
  </div>

  <!-- ====================== IDENTIDAD ====================== -->
  <div class="hoja activa tarjeta" id="h-identidad">
    <?php
    fila('Nombre del sitio', 'Aparece en la cabecera, en el título del navegador y en los archivos exportados.',
        '<input type="text" name="sitio_nombre" class="campo" maxlength="80" value="' . e($a['sitio_nombre']) . '">');

    fila('Lema', 'Frase corta que acompaña al nombre.',
        '<input type="text" name="sitio_lema" class="campo" maxlength="160" value="' . e($a['sitio_lema']) . '">');

    fila('Descripción para buscadores', 'Etiqueta meta description. Entre 120 y 160 caracteres es lo ideal.',
        '<textarea name="sitio_descripcion" class="campo" rows="3" maxlength="300">' . e($a['sitio_descripcion']) . '</textarea>');

    $logoActual = $a['logo'] !== ''
        ? '<div style="margin-bottom:12px;display:flex;align-items:center;gap:12px">'
          . '<img src="' . e(cr_url($a['logo'])) . '" alt="Logo actual" style="height:46px;width:auto;border-radius:8px">'
          . '<label class="interruptor"><input type="checkbox" name="borrar_logo" value="1">'
          . '<span class="pista" aria-hidden="true"></span><span class="txt">Quitar el logo</span></label></div>'
        : '<p class="pequeno suave" style="margin-bottom:10px">Ahora se usa el logotipo de radar incluido.</p>';
    fila('Logotipo', 'PNG, JPG, WEBP, GIF o SVG. Máximo 2 MB. Se recomienda un alto de 64&nbsp;px.',
        $logoActual . '<input type="file" name="logo_archivo" class="campo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">');

    fila('Texto del pie', 'Se muestra al final de todas las páginas públicas.',
        '<textarea name="pie_texto" class="campo" rows="2" maxlength="400">' . e($a['pie_texto']) . '</textarea>');
    ?>
  </div>

  <!-- ====================== TEXTOS ====================== -->
  <div class="hoja tarjeta" id="h-textos">
    <?php
    fila('Titular de la portada', 'El texto grande que se ve nada más entrar.',
        '<textarea name="hero_titulo" class="campo" rows="2" maxlength="200">' . e($a['hero_titulo']) . '</textarea>');

    fila('Subtítulo', 'Explica en una frase qué hace la herramienta.',
        '<textarea name="hero_subtitulo" class="campo" rows="3" maxlength="400">' . e($a['hero_subtitulo']) . '</textarea>');

    fila('Etiqueta de la caja', 'Texto que acompaña al campo de la dirección web.',
        '<input type="text" name="hero_etiqueta" class="campo" maxlength="80" value="' . e($a['hero_etiqueta']) . '">');

    fila('Texto de ejemplo del campo', 'El texto gris que se ve dentro del campo vacío.',
        '<input type="text" name="hero_placeholder" class="campo" maxlength="120" value="' . e($a['hero_placeholder']) . '">');

    fila('Texto del botón', 'Llamada a la acción principal.',
        '<input type="text" name="hero_boton" class="campo" maxlength="60" value="' . e($a['hero_boton']) . '">');

    fila('Aviso de uso responsable', 'Se muestra bajo la caja de extracción. Recomendado por privacidad y protección de datos.',
        '<textarea name="aviso_legal" class="campo" rows="2" maxlength="400">' . e($a['aviso_legal']) . '</textarea>');
    ?>
  </div>

  <!-- ====================== APARIENCIA ====================== -->
  <div class="hoja tarjeta" id="h-apariencia">
    <?php
    $colores = [
        'color_fondo' => ['Fondo obsidiana', 'Color base de todo el sitio en modo oscuro.'],
        'color_oro'   => ['Oro champán', 'Color principal de acento: botones, títulos y detalles.'],
        'color_neon'  => ['Verde fósforo', 'Color secundario: barrido del radar, aciertos y confirmaciones.'],
        'color_texto' => ['Texto', 'Color del texto principal en modo oscuro.'],
    ];
    foreach ($colores as $clave => [$titulo, $pista]) {
        fila($titulo, $pista,
            '<div class="color-fila">'
            . '<input type="color" value="' . e($a[$clave]) . '" aria-label="Selector de ' . e($titulo) . '">'
            . '<input type="text" name="' . e($clave) . '" class="campo" maxlength="7" value="' . e($a[$clave]) . '">'
            . '</div>');
    }

    fila('Tema por defecto', 'El que verán las visitas nuevas. Cada persona puede cambiarlo y se recuerda en su navegador.',
        '<select name="tema_por_defecto" class="campo" style="max-width:220px">'
        . '<option value="oscuro"' . ($a['tema_por_defecto'] === 'oscuro' ? ' selected' : '') . '>Oscuro (recomendado)</option>'
        . '<option value="claro"' . ($a['tema_por_defecto'] === 'claro' ? ' selected' : '') . '>Claro</option>'
        . '</select>');
    ?>
    <div class="aviso aviso-info" style="margin-top:18px">
      <span>Consejo: mantén un contraste alto entre el fondo y el texto para que la web siga siendo accesible.</span>
    </div>
  </div>

  <!-- ====================== MOTOR ====================== -->
  <div class="hoja tarjeta" id="h-motor">
    <?php
    fila('Tiempo máximo por página', 'Segundos que se espera la respuesta de cada página antes de darla por perdida.',
        '<input type="number" name="timeout" class="campo" style="max-width:140px" min="3" max="180" value="' . e($a['timeout']) . '"> <span class="suave pequeno">segundos</span>');

    fila('Rastreo profundo', 'Permite a los visitantes recorrer las páginas internas del mismo dominio.',
        interruptor('rastreo_profundo', Ajustes::activo('rastreo_profundo', true), 'Disponible en la portada'));

    fila('Páginas por rastreo', 'Tope de páginas que se revisan en un rastreo profundo.',
        '<input type="number" name="max_paginas" class="campo" style="max-width:140px" min="1" max="1000" value="' . e($a['max_paginas']) . '">');

    fila('Profundidad', 'Cuántos niveles de enlaces internos se siguen desde la página inicial.',
        '<input type="number" name="max_profundidad" class="campo" style="max-width:140px" min="0" max="10" value="' . e($a['max_profundidad']) . '">');

    fila('Buscar WhatsApp y teléfonos',
        'Detecta también los números: enlaces wa.me, api.whatsapp.com, widgets de WhatsApp, enlaces tel:, JSON-LD y números escritos en la página.',
        interruptor('buscar_whatsapp', Ajustes::activo('buscar_whatsapp', true), 'Extraer también los números'));

    fila('Prefijo de país por defecto',
        'Solo dígitos, sin el signo +. Por ejemplo <b class="mono">502</b> para Guatemala o <b class="mono">34</b> para España. '
        . 'Si lo rellenas, Kaptor también reconocerá los números escritos en formato local (2222&nbsp;3333). '
        . 'Déjalo vacío para aceptar únicamente números con prefijo internacional, que es lo más preciso.',
        '<input type="text" name="prefijo_pais" class="campo mono" style="max-width:140px" maxlength="4" pattern="[0-9]*" placeholder="502" value="' . e($a['prefijo_pais'] ?? '') . '">');

    fila('Números máximos', 'Se deja de guardar al llegar a esta cantidad de teléfonos.',
        '<input type="number" name="max_telefonos" class="campo" style="max-width:160px" min="1" max="100000" value="' . e($a['max_telefonos'] ?? '500') . '">');

    fila('Correos máximos', 'Se detiene el escaneo al llegar a esta cantidad.',
        '<input type="number" name="max_correos" class="campo" style="max-width:160px" min="1" max="100000" value="' . e($a['max_correos']) . '">');

    fila('Tamaño máximo por página', 'Bytes que se descargan como mucho de cada dirección. Protege la memoria del hosting.',
        '<input type="number" name="max_bytes" class="campo" style="max-width:180px" min="50000" max="20000000" value="' . e($a['max_bytes']) . '"> <span class="suave pequeno">bytes</span>');

    fila('Analizar archivos JS y CSS', 'Muchos sitios esconden el correo dentro de sus scripts u hojas de estilo.',
        interruptor('analizar_js_css', Ajustes::activo('analizar_js_css', true)));

    fila('Usar sitemap.xml y robots.txt', 'Descubre páginas que no están enlazadas en el menú.',
        interruptor('analizar_sitemap', Ajustes::activo('analizar_sitemap', true)));

    fila('Buscar endpoints JSON', 'Detecta las API internas (por ejemplo /wp-json) y las revisa también.',
        interruptor('analizar_json', Ajustes::activo('analizar_json', true)));

    fila('Verificar el registro MX', 'Comprueba por DNS si el dominio puede recibir correo. Más fiable, pero algo más lento.',
        interruptor('verificar_mx', Ajustes::activo('verificar_mx')));

    fila('Terminaciones estrictas', 'Descarta los correos cuya terminación no exista (evita falsos positivos como logo@2x.png).',
        interruptor('tld_estricto', Ajustes::activo('tld_estricto', true)));

    fila('Dominios excluidos', 'Uno por línea. Los correos de estos dominios nunca aparecerán en los resultados.',
        '<textarea name="dominios_excluidos" class="campo mono" rows="4" placeholder="ejemplo.com&#10;otrodominio.net">' . e($a['dominios_excluidos'] ?? '') . '</textarea>');

    fila('Pausa entre páginas', 'Milisegundos de espera entre descargas. Útil para no saturar servidores ajenos.',
        '<input type="number" name="pausa_ms" class="campo" style="max-width:140px" min="0" max="5000" value="' . e($a['pausa_ms']) . '"> <span class="suave pequeno">ms</span>');

    fila('Identificación del navegador', 'Cabecera User-Agent con la que Kaptor se presenta ante los sitios.',
        '<textarea name="user_agent" class="campo mono" rows="2" maxlength="300">' . e($a['user_agent']) . '</textarea>');

    fila('Certificados HTTPS estrictos', 'Si se activa, no se descargarán sitios con el certificado mal configurado. Muchos hosting compartidos tienen el paquete de certificados desactualizado.',
        interruptor('ssl_estricto', Ajustes::activo('ssl_estricto'), 'Exigir certificado válido'));

    fila('Permitir direcciones internas', '<b class="oro">Solo para uso en intranet.</b> Desactivado, se bloquean las IP privadas, localhost y los puertos internos (protección contra SSRF).',
        interruptor('permitir_privadas', Ajustes::activo('permitir_privadas'), 'Permitir redes privadas'));

    $binario = Headless::binario();
    $estadoHeadless = $binario !== ''
        ? '<span class="chip chip-neon">Navegador encontrado: ' . e($binario) . '</span>'
        : '<span class="chip chip-gris">No se ha encontrado ningún navegador en este servidor</span>';
    fila('Navegador interno (JavaScript)',
        'Renderiza la página con un navegador real para leer el contenido que se genera con JavaScript. '
        . 'La mayoría de hosting compartidos no lo permiten; en ese caso Kaptor avisa y sigue funcionando sin él.',
        $estadoHeadless . '<div style="margin-top:10px">' . interruptor('headless_activo', Ajustes::activo('headless_activo'), 'Usar navegador cuando esté disponible') . '</div>'
        . '<input type="text" name="headless_binario" class="campo mono" style="margin-top:10px" placeholder="/usr/bin/chromium" value="' . e($a['headless_binario']) . '">');
    ?>
  </div>

  <!-- ====================== ACCESO ====================== -->
  <div class="hoja tarjeta" id="h-acceso">
    <?php
    fila('Acceso libre', 'Con esta opción desactivada solo podrán extraer correos las personas que hayan iniciado sesión.',
        interruptor('acceso_publico', Ajustes::activo('acceso_publico', true), 'Cualquier visitante puede extraer'));

    fila('Registro abierto', 'Permite que cualquiera cree su cuenta desde la web. Si lo desactivas, las cuentas las creas tú desde Usuarios.',
        interruptor('registro_publico', Ajustes::activo('registro_publico', true), 'Registro público activo'));

    fila('Límite por IP y hora', 'Número máximo de extracciones que puede lanzar una misma IP en una hora. 0 = sin límite.',
        '<input type="number" name="limite_ip_hora" class="campo" style="max-width:140px" min="0" max="100000" value="' . e($a['limite_ip_hora']) . '">');

    fila('Guardar el historial', 'Conserva los resultados para poder volver a descargarlos más tarde.',
        interruptor('guardar_historial', Ajustes::activo('guardar_historial', true)));

    fila('Días de retención', 'Los escaneos más antiguos se borran automáticamente. 0 = conservar para siempre.',
        '<input type="number" name="retencion_dias" class="campo" style="max-width:140px" min="0" max="3650" value="' . e($a['retencion_dias']) . '"> <span class="suave pequeno">días</span>');
    ?>
  </div>

  <!-- ====================== CAMPAÑAS ====================== -->
  <div class="hoja tarjeta" id="h-campanas">
    <?php
    fila('Módulo de campañas', 'Permite crear listas, plantillas y enviar correo desde tus propios buzones.',
        interruptor('campanas_activas', Ajustes::activo('campanas_activas', true), 'Módulo activo'));

    fila('Dirección postal del remitente',
        'Se añade al pie de cada mensaje. En varios países es obligatoria en el correo comercial y, '
        . 'además, mejora bastante la entrega porque es una señal de remitente legítimo.',
        '<textarea name="remitente_postal" class="campo" rows="2" maxlength="300" placeholder="Servicom, 5a Avenida 1-23, Zona 10, Ciudad de Guatemala">'
        . e($a['remitente_postal'] ?? '') . '</textarea>');

    fila('Seguimiento de aperturas', 'Añade un píxel invisible para saber quién abrió el mensaje.',
        interruptor('seguimiento_aperturas', Ajustes::activo('seguimiento_aperturas', true)));

    fila('Seguimiento de clics', 'Los enlaces pasan por tu dominio para poder contarlos. Van firmados, así que no se pueden manipular.',
        interruptor('seguimiento_clics', Ajustes::activo('seguimiento_clics', true)));

    fila('Tiempo de espera SMTP', 'Segundos que se espera al servidor de correo antes de dar el envío por fallido.',
        '<input type="number" name="smtp_timeout" class="campo" style="max-width:140px" min="5" max="120" value="' . e($a['smtp_timeout'] ?? '20') . '"> <span class="suave pequeno">segundos</span>');

    $clave = $a['cron_clave'] ?? '';
    $urlCron = cr_url('cron.php?clave=' . $clave);
    fila('Clave del cron',
        'Protege la dirección que avanza las campañas automáticamente. Programa esta línea en cPanel &rarr; Tareas Cron, cada 5 minutos:'
        . ($clave !== '' ? '<br><code class="mono" style="display:block;margin-top:8px;word-break:break-all">curl -s "' . e($urlCron) . '"</code>' : ''),
        '<input type="text" name="cron_clave" class="campo mono" value="' . e($clave) . '" placeholder="pulsa Generar">'
        . '<button type="button" class="btn btn-fantasma btn-peq" style="margin-top:8px" onclick="'
        . "var c=document.getElementsByName('cron_clave')[0];var a=new Uint8Array(16);crypto.getRandomValues(a);"
        . "c.value=Array.from(a).map(function(b){return b.toString(16).padStart(2,'0')}).join('');"
        . '">Generar una clave nueva</button>');
    ?>

    <div class="aviso aviso-info" style="margin-top:18px">
      <span>
        <b>Antes de tu primera campaña:</b> configura SPF, DKIM y DMARC en tu dominio (cPanel &rarr; Autenticación de correo),
        envía una prueba a tu propio correo y empieza con 20–30 mensajes al día por buzón durante la primera semana.
      </span>
    </div>
  </div>

  <div class="guardar-barra">
    <span class="suave pequeno">Los cambios se aplican al instante en todo el sitio.</span>
    <button type="submit" class="btn">Guardar los ajustes</button>
  </div>
</form>

<?php admin_pie(); ?>
