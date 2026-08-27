<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Subida;
use App\Core\Validador;
use App\Models\Casa;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Visita;

final class CasasControlador extends Controlador
{
    public function index(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        [$porPagina, $desde, $pagina] = $this->paginacion(30);
        $filtros = [
            'fase'    => Peticion::entero('fase'),
            'calle'   => Peticion::entero('calle'),
            'estado'  => Peticion::texto('estado'),
            'buscar'  => Peticion::texto('buscar'),
            'morosas' => Peticion::bool('morosas'),
        ];
        Cuota::recalcularMora();
        $todas = Casa::listar($filtros, 2000);
        $total = count($todas);

        $this->mostrar('admin/casas/index', [
            'tituloPagina' => 'Viviendas',
            'subtitulo'    => $total . ' vivienda(s) registrada(s)',
            'casas'        => array_slice($todas, $desde, $porPagina),
            'total'        => $total,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'filtros'      => $filtros,
            'fases'        => Casa::fases(),
            'calles'       => Casa::calles($filtros['fase'] > 0 ? $filtros['fase'] : null),
        ]);
    }

    public function detalle(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $casa = Casa::porId($id);
        if ($casa === null) {
            $this->error('La vivienda no existe.', '/admin/casas');
        }
        Cuota::recalcularMora($id);

        $this->mostrar('admin/casas/ver', [
            'tituloPagina' => 'Vivienda ' . $casa['codigo'],
            'subtitulo'    => trim((string) $casa['fase'] . (!empty($casa['calle']) ? ' · ' . $casa['calle'] : '')),
            'casa'         => $casa,
            'residentes'   => Casa::residentes($id, false),
            'vehiculos'    => Casa::vehiculos($id),
            'mascotas'     => DB::todos('SELECT * FROM mascotas WHERE casa_id = :c ORDER BY nombre', ['c' => $id]),
            'empleados'    => DB::todos('SELECT * FROM empleados_casa WHERE casa_id = :c AND activo = 1 ORDER BY nombre', ['c' => $id]),
            'cargos'       => Cuota::cargos($id, 'vigentes', 60),
            'pagos'        => Pago::listar(['casa' => $id], 12),
            'visitas'      => Visita::listar(['casa' => $id], 10),
            'saldo'        => Casa::saldo($id),
            'antiguedad'   => Cuota::antiguedad($id),
            'aFavor'       => Pago::saldoAFavor($id),
            'dias'         => Casa::diasMora($id),
            'historial'    => DB::todos('SELECT * FROM residentes_historial WHERE casa_id = :c ORDER BY id DESC LIMIT 15', ['c' => $id]),
        ]);
    }

    public function nueva(): void
    {
        $this->exigirRol('admin');
        $this->formulario(0);
    }

    public function editar(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->formulario($id);
    }

    private function formulario(int $id): void
    {
        $casa = $id > 0 ? Casa::porId($id) : null;
        if ($id > 0 && $casa === null) {
            $this->error('La vivienda no existe.', '/admin/casas');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $v = new Validador();
            $codigo = mb_strtoupper(Peticion::texto('codigo'));
            $v->requerido('codigo', $codigo, 'El código de la vivienda')
              ->largoMax('codigo', $codigo, 30, 'El código')
              ->numero('fase_id', Peticion::entero('fase_id'), 'La fase', 1)
              ->numero('metros', Peticion::decimal('metros'), 'Los metros', 0)
              ->numero('coeficiente', Peticion::decimal('coeficiente'), 'El coeficiente', 0, 100)
              ->en('estado', Peticion::texto('estado'), ['habitada', 'desocupada', 'venta', 'alquiler'], 'El estado');

            $repetido = DB::valor('SELECT id FROM casas WHERE codigo = :c AND id <> :x', ['c' => $codigo, 'x' => $id]);
            if ($repetido) {
                $v->agregar('codigo', 'Ya existe otra vivienda con el código ' . $codigo . '.');
            }

            if ($v->ok()) {
                $datos = [
                    'fase_id'     => Peticion::entero('fase_id'),
                    'calle_id'    => Peticion::entero('calle_id') ?: null,
                    'codigo'      => $codigo,
                    'tipo'        => Peticion::texto('tipo', 'casa'),
                    'metros'      => Peticion::decimal('metros'),
                    'coeficiente' => Peticion::decimal('coeficiente'),
                    'parqueos'    => Peticion::entero('parqueos'),
                    'bodegas'     => Peticion::entero('bodegas'),
                    'estado'      => Peticion::texto('estado', 'habitada'),
                    'mapa_x'      => Peticion::texto('mapa_x') !== '' ? Peticion::decimal('mapa_x') : null,
                    'mapa_y'      => Peticion::texto('mapa_y') !== '' ? Peticion::decimal('mapa_y') : null,
                    'notas'       => Peticion::texto('notas'),
                ];
                $foto = Subida::guardar('foto', 'casas', Subida::IMAGENES, 6);
                if ($foto !== null) {
                    $datos['foto'] = $foto;
                }
                if ($id > 0) {
                    DB::actualizar('casas', $datos, 'id = :id', ['id' => $id]);
                    Auditoria::registrar('editar_casa', 'casas', $id, $codigo);
                    $this->exito('Vivienda actualizada.', '/admin/casas/' . $id);
                }
                $nuevo = DB::insertar('casas', $datos);
                Auditoria::registrar('crear_casa', 'casas', $nuevo, $codigo);
                $this->exito('Vivienda registrada.', '/admin/casas/' . $nuevo);
            }
            $this->error($v->primerError(), $id > 0 ? '/admin/casas/' . $id . '/editar' : '/admin/casas/nueva');
        }

        $this->mostrar('admin/casas/editar', [
            'tituloPagina' => $id > 0 ? 'Editar vivienda ' . $casa['codigo'] : 'Nueva vivienda',
            'casa'         => $casa,
            'fases'        => Casa::fases(),
            'calles'       => Casa::calles(),
        ]);
    }

    public function eliminar(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $casa = Casa::porId($id);
        if ($casa === null) {
            $this->error('La vivienda no existe.', '/admin/casas');
        }
        $conCargos = (int) DB::valor('SELECT COUNT(*) FROM cargos WHERE casa_id = :c', ['c' => $id], 0);
        if ($conCargos > 0) {
            $this->error('No se puede eliminar: la vivienda tiene ' . $conCargos . ' cargo(s) en su historial. Cámbiela a "desocupada" si ya no está en uso.', '/admin/casas/' . $id);
        }
        DB::eliminar('residentes', 'casa_id = :c', ['c' => $id]);
        DB::eliminar('casas', 'id = :id', ['id' => $id]);
        Auditoria::registrar('eliminar_casa', 'casas', $id, (string) $casa['codigo']);
        $this->exito('Vivienda eliminada.', '/admin/casas');
    }

    // ------------------------------------------------------------ ESTRUCTURA

    public function estructura(): void
    {
        $this->exigirRol('admin');
        $this->mostrar('admin/casas/estructura', [
            'tituloPagina' => 'Fases y calles',
            'subtitulo'    => 'Estructura del residencial',
            'fases'        => DB::todos('SELECT f.*, (SELECT COUNT(*) FROM casas c WHERE c.fase_id = f.id) AS casas FROM fases f ORDER BY f.orden, f.nombre'),
            'calles'       => Casa::calles(),
        ]);
    }

    public function guardarFase(): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $id     = Peticion::entero('id');
        $nombre = Peticion::texto('nombre');
        if ($nombre === '') {
            $this->error('Escriba el nombre de la fase.', '/admin/estructura');
        }
        $datos = [
            'nombre'      => $nombre,
            'descripcion' => Peticion::texto('descripcion'),
            'orden'       => Peticion::entero('orden'),
            'activo'      => Peticion::bool('activo') ? 1 : 0,
        ];
        if ($id > 0) {
            DB::actualizar('fases', $datos, 'id = :id', ['id' => $id]);
            Auditoria::registrar('editar_fase', 'fases', $id, $nombre);
            $this->exito('Fase actualizada.', '/admin/estructura');
        }
        $nuevo = DB::insertar('fases', $datos);
        Auditoria::registrar('crear_fase', 'fases', $nuevo, $nombre);
        $this->exito('Fase agregada.', '/admin/estructura');
    }

    public function guardarCalle(): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $id     = Peticion::entero('id');
        $nombre = Peticion::texto('nombre');
        $fase   = Peticion::entero('fase_id');
        if ($nombre === '' || $fase <= 0) {
            $this->error('Indique la fase y el nombre de la calle.', '/admin/estructura');
        }
        $datos = ['fase_id' => $fase, 'nombre' => $nombre, 'orden' => Peticion::entero('orden')];
        if ($id > 0) {
            DB::actualizar('calles', $datos, 'id = :id', ['id' => $id]);
            $this->exito('Calle actualizada.', '/admin/estructura');
        }
        DB::insertar('calles', $datos);
        $this->exito('Calle agregada.', '/admin/estructura');
    }

    // ----------------------------------------------------------------- MAPA

    public function mapa(): void
    {
        $this->exigirRol('admin', 'junta');
        if ($this->post()) {
            $this->verificarCsrf();
            if (Peticion::texto('accion') === 'plano') {
                $archivo = Subida::guardar('plano', 'casas', Subida::IMAGENES, 8);
                if ($archivo === null) {
                    $this->error(Subida::$ultimoError !== '' ? Subida::$ultimoError : 'Seleccione una imagen del plano.', '/admin/mapa');
                }
                \App\Core\Ajustes::set('mapa_plano', $archivo, 'sitio');
                Auditoria::registrar('subir_plano', null, null, $archivo);
                $this->exito('Plano actualizado.', '/admin/mapa');
            }
            if (Peticion::texto('accion') === 'punto') {
                $casaId = Peticion::entero('casa_id');
                DB::actualizar('casas', [
                    'mapa_x' => Peticion::decimal('x'),
                    'mapa_y' => Peticion::decimal('y'),
                ], 'id = :id', ['id' => $casaId]);
                $this->json(['ok' => true]);
            }
        }
        Cuota::recalcularMora();
        $this->mostrar('admin/casas/mapa', [
            'tituloPagina' => 'Mapa del residencial',
            'subtitulo'    => 'Ubicación y estado de cada vivienda',
            'casas'        => Casa::listar([], 1000),
            'plano'        => \App\Core\Ajustes::get('mapa_plano', ''),
        ]);
    }

    // ------------------------------------------------------------ IMPORTAR

    public function importar(): void
    {
        $this->exigirRol('admin');
        $resultado = null;

        if ($this->post()) {
            $this->verificarCsrf();
            $archivo = $_FILES['archivo'] ?? null;
            if (!is_array($archivo) || ($archivo['error'] ?? 1) !== UPLOAD_ERR_OK) {
                $this->error('Seleccione el archivo CSV con las viviendas.', '/admin/casas/importar');
            }
            $resultado = $this->procesarCsv((string) $archivo['tmp_name'], Peticion::bool('actualizar'));
            Auditoria::registrar('importar_casas', 'casas', null,
                $resultado['creadas'] . ' creadas, ' . $resultado['actualizadas'] . ' actualizadas');
        }

        $this->mostrar('admin/casas/importar', [
            'tituloPagina' => 'Importar viviendas',
            'subtitulo'    => 'Carga masiva desde una hoja de cálculo',
            'resultado'    => $resultado,
            'fases'        => Casa::fases(),
        ]);
    }

    /** Lee un CSV: codigo, fase, calle, tipo, metros, coeficiente, parqueos, bodegas, estado, residente, dpi, correo, telefono */
    private function procesarCsv(string $ruta, bool $actualizar): array
    {
        $fh = fopen($ruta, 'r');
        if ($fh === false) {
            return ['creadas' => 0, 'actualizadas' => 0, 'errores' => ['No se pudo leer el archivo.']];
        }
        $primera = fgets($fh);
        $sep = (substr_count((string) $primera, ';') > substr_count((string) $primera, ',')) ? ';' : ',';
        rewind($fh);

        $cabecera = fgetcsv($fh, 0, $sep);
        if ($cabecera === false) {
            fclose($fh);
            return ['creadas' => 0, 'actualizadas' => 0, 'errores' => ['El archivo está vacío.']];
        }
        $cabecera = array_map(static fn($c) => slug(trim((string) $c)), $cabecera);
        $indice = array_flip($cabecera);
        $col = static function (array $fila, array $indice, string $nombre, string $def = '') {
            return isset($indice[$nombre]) ? trim((string) ($fila[$indice[$nombre]] ?? '')) : $def;
        };

        $creadas = 0;
        $actualizadas = 0;
        $errores = [];
        $linea = 1;
        $fasesCache = [];
        $callesCache = [];

        while (($fila = fgetcsv($fh, 0, $sep)) !== false) {
            $linea++;
            $codigo = mb_strtoupper($col($fila, $indice, 'codigo'));
            if ($codigo === '') {
                continue;
            }
            $faseNombre = $col($fila, $indice, 'fase', 'Fase única');
            if ($faseNombre === '') {
                $faseNombre = 'Fase única';
            }
            if (!isset($fasesCache[$faseNombre])) {
                $fid = (int) DB::valor('SELECT id FROM fases WHERE nombre = :n', ['n' => $faseNombre], 0);
                if ($fid === 0) {
                    $fid = DB::insertar('fases', ['nombre' => $faseNombre, 'orden' => count($fasesCache) + 1]);
                }
                $fasesCache[$faseNombre] = $fid;
            }
            $faseId = $fasesCache[$faseNombre];

            $calleNombre = $col($fila, $indice, 'calle');
            $calleId = null;
            if ($calleNombre !== '') {
                $llave = $faseId . '|' . $calleNombre;
                if (!isset($callesCache[$llave])) {
                    $cid = (int) DB::valor('SELECT id FROM calles WHERE nombre = :n AND fase_id = :f',
                        ['n' => $calleNombre, 'f' => $faseId], 0);
                    if ($cid === 0) {
                        $cid = DB::insertar('calles', ['fase_id' => $faseId, 'nombre' => $calleNombre, 'orden' => 0]);
                    }
                    $callesCache[$llave] = $cid;
                }
                $calleId = $callesCache[$llave];
            }

            $estado = mb_strtolower($col($fila, $indice, 'estado', 'habitada'));
            if (!in_array($estado, ['habitada', 'desocupada', 'venta', 'alquiler'], true)) {
                $estado = 'habitada';
            }
            $datos = [
                'fase_id'     => $faseId,
                'calle_id'    => $calleId,
                'codigo'      => $codigo,
                'tipo'        => in_array($col($fila, $indice, 'tipo', 'casa'), ['casa', 'apartamento', 'lote', 'local'], true)
                                 ? $col($fila, $indice, 'tipo', 'casa') : 'casa',
                'metros'      => (float) str_replace(',', '', $col($fila, $indice, 'metros', '0')),
                'coeficiente' => (float) str_replace(',', '', $col($fila, $indice, 'coeficiente', '0')),
                'parqueos'    => (int) $col($fila, $indice, 'parqueos', '0'),
                'bodegas'     => (int) $col($fila, $indice, 'bodegas', '0'),
                'estado'      => $estado,
            ];

            $existente = (int) DB::valor('SELECT id FROM casas WHERE codigo = :c', ['c' => $codigo], 0);
            if ($existente > 0) {
                if ($actualizar) {
                    DB::actualizar('casas', $datos, 'id = :id', ['id' => $existente]);
                    $actualizadas++;
                    $casaId = $existente;
                } else {
                    $errores[] = 'Línea ' . $linea . ': la vivienda ' . $codigo . ' ya existe y no se actualizó.';
                    continue;
                }
            } else {
                $casaId = DB::insertar('casas', $datos);
                $creadas++;
            }

            $residente = $col($fila, $indice, 'residente');
            if ($residente !== '') {
                $yaEsta = DB::valor('SELECT id FROM residentes WHERE casa_id = :c AND nombre = :n',
                    ['c' => $casaId, 'n' => $residente]);
                if (!$yaEsta) {
                    DB::insertar('residentes', [
                        'casa_id'  => $casaId,
                        'nombre'   => $residente,
                        'tipo'     => 'propietario',
                        'dpi'      => $col($fila, $indice, 'dpi') ?: null,
                        'correo'   => $col($fila, $indice, 'correo') ?: null,
                        'telefono' => $col($fila, $indice, 'telefono') ?: null,
                        'activo'   => 1,
                    ]);
                }
            }
            if (count($errores) > 40) {
                $errores[] = 'Se omitieron más errores…';
                break;
            }
        }
        fclose($fh);
        return ['creadas' => $creadas, 'actualizadas' => $actualizadas, 'errores' => $errores];
    }

}
