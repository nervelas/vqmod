<?php
/**
 * Kaptor - API del auditor web (AJAX).
 *
 * Acciones:
 *   iniciar   Crea la auditoría del sitio (y la de sus competidores).
 *   paso      Avanza la siguiente fase de la auditoría que toque.
 *   estado    Devuelve el progreso de toda la tanda sin tocar nada.
 *   cancelar  Marca como cortadas las que sigan en marcha.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$datos  = cr_cuerpo_json();
$accion = (string) ($datos['accion'] ?? '');

Seguridad::exigirCsrf((string) ($datos['csrf'] ?? ''), true);

if (!Auth::autenticado()) {
    cr_json(['ok' => false, 'error' => 'Necesitas iniciar sesión.', 'requiere_login' => true], 403);
}
if (!Ajustes::activo('auditor_activo', true)) {
    cr_json(['ok' => false, 'error' => 'El auditor está desactivado en los ajustes.'], 403);
}

$usuarioId = Auth::id();

switch ($accion) {

    // ---------------------------------------------------------------- iniciar
    case 'iniciar':
        if (Seguridad::limiteAlcanzado('auditoria')) {
            cr_json([
                'ok' => false,
                'error' => 'Has alcanzado el límite de auditorías por hora. Inténtalo más tarde.',
            ], 429);
        }

        $entrada = trim((string) ($datos['sitios'] ?? ''));
        $rivales = trim((string) ($datos['rivales'] ?? ''));

        $lineas = preg_split('~[\r\n,;]+~', $entrada) ?: [];
        $lineas = array_values(array_unique(array_filter(array_map('trim', $lineas), static fn($l) => $l !== '')));

        if (!$lineas) {
            cr_json(['ok' => false, 'error' => 'Escribe al menos una dirección web.'], 400);
        }

        $tope = Ajustes::entero('auditor_max_lote', 50, 1, 300);
        $recortadas = 0;
        if (count($lineas) > $tope) {
            $recortadas = count($lineas) - $tope;
            $lineas = array_slice($lineas, 0, $tope);
        }

        // La comparativa solo tiene sentido cuando se audita UN sitio: si se
        // pegan cincuenta, no hay contra quién compararlos uno a uno.
        $listaRivales = [];
        if (count($lineas) === 1 && $rivales !== '') {
            $listaRivales = array_slice(array_values(array_unique(array_filter(
                array_map('trim', preg_split('~[\r\n,;]+~', $rivales) ?: []),
                static fn($l) => $l !== ''
            ))), 0, 3);
        }

        $creadas = [];
        $fallos  = [];
        $todos   = [];   // principales Y competidores: son los que hay que avanzar

        foreach ($lineas as $sitio) {
            $lote = bin2hex(random_bytes(8));
            $c = Auditor::crear($sitio, $lote, 'principal', $usuarioId);
            if (!$c['ok']) { $fallos[] = $sitio . ': ' . $c['error']; continue; }

            $creadas[] = ['id' => $c['id'], 'lote' => $lote, 'sitio' => $sitio];
            $todos[]   = $c['id'];

            foreach ($listaRivales as $rival) {
                $cr = Auditor::crear($rival, $lote, 'competidor', $usuarioId);
                if ($cr['ok']) { $todos[] = $cr['id']; }
                else { $fallos[] = $rival . ': ' . $cr['error']; }
            }
        }

        if (!$creadas) {
            cr_json(['ok' => false, 'error' => $fallos ? implode(' · ', array_slice($fallos, 0, 3)) : 'No se pudo iniciar ninguna auditoría.'], 400);
        }

        Seguridad::registrarPeticion('auditoria');

        $aviso = '';
        if ($recortadas > 0) {
            $aviso = 'Se auditan las primeras ' . $tope . ' direcciones; quedaron fuera ' . $recortadas . '.';
        }
        if ($fallos) {
            $aviso = trim($aviso . ' No se pudieron leer: ' . implode(' · ', array_slice($fallos, 0, 3)));
        }

        cr_json([
            'ok'         => true,
            'auditorias' => $creadas,
            // El navegador avanza exactamente estos, sin tener que adivinar
            // los ids de los competidores a partir del de su sitio.
            'ids'        => $todos,
            'rivales'    => count($listaRivales),
            'aviso'      => $aviso,
        ]);
        break;

    // ------------------------------------------------------------------- paso
    case 'paso':
        $ids = array_values(array_filter(array_map('intval', (array) ($datos['ids'] ?? []))));
        if (!$ids) { cr_json(['ok' => false, 'error' => 'Falta indicar qué auditoría avanzar.'], 400); }

        // Solo se avanza lo propio.
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $mias = BD::todos(
            "SELECT `id` FROM `cr_auditorias`
              WHERE `id` IN ($marcas) AND `usuario_id` = ?
                AND `estado` NOT IN ('listo','error')
              ORDER BY (`papel` = 'principal') DESC, `id` ASC",
            array_merge($ids, [$usuarioId])
        );

        // El presupuesto se reparte: así una tanda de veinte sitios no deja
        // colgada la petición hasta que el servidor la corte.
        $presupuesto = 7000;
        $inicio = microtime(true);
        $avances = [];

        foreach ($mias as $m) {
            $queda = $presupuesto - (int) round((microtime(true) - $inicio) * 1000);
            if ($queda < 1200) { break; }
            $avances[] = Auditor::avanzar((int) $m['id'], min(6000, $queda));
        }

        // Estado completo de todas las pedidas, hayan avanzado o no. Se filtra
        // por dueño también aquí: si no, bastaría con probar números de id para
        // ir leyendo las auditorías de otro usuario.
        $estados = BD::todos(
            "SELECT `id`,`host`,`estado`,`fase`,`nota`,`error`,`papel`,`lote`,`token`
               FROM `cr_auditorias` WHERE `id` IN ($marcas) AND `usuario_id` = ?",
            array_merge($ids, [$usuarioId])
        );

        $salida = [];
        foreach ($estados as $e) {
            $fase = (string) $e['fase'];
            $i = array_search($fase, Auditor::FASES, true);
            $pct = $i === false ? 0 : (int) round((($i + 1) / count(Auditor::FASES)) * 100);
            $salida[] = [
                'id'       => (int) $e['id'],
                'host'     => (string) $e['host'],
                'estado'   => (string) $e['estado'],
                'fase'     => $fase,
                'etiqueta' => Auditor::ETIQUETAS[$fase] ?? '',
                'progreso' => $e['estado'] === 'listo' ? 100 : ($e['estado'] === 'error' ? 100 : min(95, $pct)),
                'nota'     => $e['nota'] !== null ? (int) $e['nota'] : null,
                'error'    => (string) $e['error'],
                'papel'    => (string) $e['papel'],
                'lote'     => (string) $e['lote'],
                'informe'  => cr_url('informe.php?id=' . (int) $e['id']),
            ];
        }

        $pendientes = count(array_filter($salida, static fn($s) => !in_array($s['estado'], ['listo', 'error'], true)));

        cr_json([
            'ok'          => true,
            'auditorias'  => $salida,
            'terminado'   => $pendientes === 0,
            'avanzadas'   => count($avances),
        ]);
        break;

    // ---------------------------------------------------------------- cancelar
    case 'cancelar':
        $ids = array_values(array_filter(array_map('intval', (array) ($datos['ids'] ?? []))));
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            BD::ejecutar(
                "UPDATE `cr_auditorias` SET `estado` = 'error', `error` = 'Cancelada'
                  WHERE `id` IN ($marcas) AND `usuario_id` = ? AND `estado` NOT IN ('listo','error')",
                array_merge($ids, [$usuarioId])
            );
        }
        cr_json(['ok' => true]);
        break;

    // ----------------------------------------------------------------- borrar
    case 'borrar':
        $id = (int) ($datos['id'] ?? 0);
        cr_json(['ok' => Auditor::borrar($id, $usuarioId)]);
        break;

    default:
        cr_json(['ok' => false, 'error' => 'Acción desconocida.'], 400);
}
