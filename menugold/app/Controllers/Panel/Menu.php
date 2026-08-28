<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Request;
use MenuGold\Core\Validator;
use MenuGold\Models\Category;
use MenuGold\Models\Modifier;
use MenuGold\Models\Product;
use MenuGold\Models\Promotion;

/**
 * Administracion del menu: categorias, platillos, modificadores y promociones.
 */
class Menu extends Base
{
    private function cats(): Category   { return (new Category())->forRestaurant($this->rid); }
    private function prods(): Product   { return (new Product())->forRestaurant($this->rid); }
    private function mods(): Modifier   { return (new Modifier())->forRestaurant($this->rid); }
    private function promos(): Promotion{ return (new Promotion())->forRestaurant($this->rid); }

    // =================================================================
    //  CATEGORÍAS
    // =================================================================
    public function categorias(): void
    {
        $this->exigir('menu');
        $this->panel('panel/categorias', [
            'categorias' => $this->cats()->conConteo(),
        ]);
    }

    public function categoriaGuardar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $datos = [
            'nombre'         => Request::str('nombre', '', 120),
            'nombre_en'      => Request::str('nombre_en', '', 120),
            'descripcion'    => Request::str('descripcion', '', 255),
            'descripcion_en' => Request::str('descripcion_en', '', 255),
            'icono'          => Request::str('icono', 'utensils', 30),
            'activo'         => Request::bool('activo', true) ? 1 : 0,
            'hora_inicio'    => $this->hora(Request::str('hora_inicio', '', 8)),
            'hora_fin'       => $this->hora(Request::str('hora_fin', '', 8)),
            'dias'           => implode(',', array_map('intval', array_slice(Request::arr('dias'), 0, 7))),
        ];
        $v = Validator::make($datos)->requerido('nombre', 'El nombre')->max('nombre', 120, 'El nombre');
        if ($v->falla()) $this->fail($v->primerError());

        $m = $this->cats();
        if ($id > 0) {
            $antes = $m->findOrFail($id);
            $m->updateById($id, $datos);
            Audit::diff('categoria.editar', 'categories', $id, $antes, $datos);
            $this->ok([], 'Categoría actualizada');
        }
        $datos['orden'] = $m->maxOrder() + 1;
        $nuevo = $m->create($datos);
        Audit::log('categoria.crear', 'categories', $nuevo, null, $datos);
        $this->ok(['id' => $nuevo], 'Categoría creada');
    }

    public function categoriaBorrar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $m = $this->cats();
        $c = $m->findOrFail($id);
        $n = DB::int('SELECT COUNT(*) FROM products WHERE category_id=:c AND restaurant_id=:r',
            ['c' => $id, 'r' => $this->rid]);
        if ($n > 0 && !Request::bool('mover')) {
            $this->fail('Esa categoría tiene ' . $n . ' platillo(s). Muévelos o elimínalos primero.');
        }
        Image::delete((string)($c['imagen'] ?? ''));
        $m->deleteById($id);
        Audit::log('categoria.borrar', 'categories', $id, $c);
        $this->ok([], 'Categoría eliminada');
    }

    public function categoriaOrdenar(): void
    {
        $this->exigir('menu');
        $this->cats()->reorder(Request::arr('ids'));
        $this->ok([], 'Orden actualizado');
    }

    // =================================================================
    //  PLATILLOS
    // =================================================================
    public function productos(): void
    {
        $this->exigir('menu');
        $q        = Request::str('q', '', 80);
        $catId    = (int)($_GET['cat'] ?? 0);
        $filtro   = Request::enum('f', ['agotados', 'inactivos', 'destacados'], '');
        $lista    = $this->prods()->buscar($q, $catId, $filtro);
        $cats     = $this->cats()->all('orden ASC');
        $mapaCats = [];
        foreach ($cats as $c) $mapaCats[(int)$c['id']] = (string)$c['nombre'];

        $this->panel('panel/productos', [
            'productos' => $lista,
            'cats'      => $cats,
            'mapaCats'  => $mapaCats,
            'q'         => $q,
            'catId'     => $catId,
            'filtro'    => $filtro,
            'uso'       => count($lista),
            'limites'   => $this->limites(),
            'totalProd' => $this->prods()->count(),
        ]);
    }

    public function productoForm(array $p = []): void
    {
        $this->exigir('menu');
        $id = (int)($p['id'] ?? 0);
        $producto = null;
        $gruposSel = [];
        if ($id > 0) {
            $producto = $this->prods()->findOrFail($id);
            $gruposSel = DB::column('SELECT group_id FROM product_modifiers WHERE product_id=:p', ['p' => $id]);
            $gruposSel = array_map('intval', $gruposSel);
        }
        $this->panel('panel/producto-form', [
            'producto'  => $producto,
            'cats'      => $this->cats()->all('orden ASC'),
            'grupos'    => $this->mods()->conOpciones(),
            'gruposSel' => $gruposSel,
        ]);
    }

    public function productoGuardar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');

        if ($id <= 0) {
            [$cabe, $msg] = $this->cabeEnPlan('productos');
            if (!$cabe) {
                flash('error', $msg);
                redirect('panel/productos');
            }
        }

        $etiquetas = array_values(array_intersect(
            array_map('strval', Request::arr('etiquetas')),
            array_keys(Product::ETIQUETAS)
        ));
        $alergenos = array_values(array_intersect(
            array_map('strval', Request::arr('alergenos')),
            Product::ALERGENOS
        ));

        $datos = [
            'category_id'    => Request::int('category_id') ?: null,
            'nombre'         => Request::str('nombre', '', 160),
            'nombre_en'      => Request::str('nombre_en', '', 160),
            'descripcion'    => Request::str('descripcion', '', 900),
            'descripcion_en' => Request::str('descripcion_en', '', 900),
            'precio'         => round(max(0, Request::float('precio')), 2),
            'precio_promo'   => Request::float('precio_promo') > 0 ? round(Request::float('precio_promo'), 2) : null,
            'costo'          => Request::float('costo') > 0 ? round(Request::float('costo'), 2) : null,
            'sku'            => Request::str('sku', '', 40),
            'activo'         => Request::bool('activo', true) ? 1 : 0,
            'agotado'        => Request::bool('agotado') ? 1 : 0,
            'destacado'      => Request::bool('destacado') ? 1 : 0,
            'tiempo_prep'    => max(0, min(240, Request::int('tiempo_prep', 15))),
            'calorias'       => Request::int('calorias') > 0 ? Request::int('calorias') : null,
            'etiquetas'      => implode(',', $etiquetas),
            'alergenos'      => implode(',', $alergenos),
            'estacion'       => Request::enum('estacion', ['cocina', 'bar', 'postres'], 'cocina'),
            'hora_inicio'    => $this->hora(Request::str('hora_inicio', '', 8)),
            'hora_fin'       => $this->hora(Request::str('hora_fin', '', 8)),
            'dias'           => implode(',', array_map('intval', array_slice(Request::arr('dias'), 0, 7))),
        ];

        $v = Validator::make($datos)
            ->requerido('nombre', 'El nombre del platillo')
            ->max('nombre', 160, 'El nombre')
            ->numerico('precio', 'El precio', 0, 999999);
        if ($v->falla()) {
            flash('error', $v->primerError());
            $this->keepOld($datos);
            redirect($id > 0 ? 'panel/productos/' . $id : 'panel/productos/nuevo');
        }
        if ($datos['precio_promo'] !== null && $datos['precio_promo'] >= $datos['precio']) {
            $datos['precio_promo'] = null;
        }

        $m = $this->prods();
        $antes = $id > 0 ? $m->findOrFail($id) : null;

        // Imagen principal
        $foto = Request::file('imagen');
        if ($foto) {
            [$ok, $res] = Image::upload($foto, 'productos/' . $this->rid, 1400, 1050, 82);
            if ($ok) {
                if ($antes) Image::delete((string)$antes['imagen']);
                $datos['imagen'] = $res;
            } else {
                flash('error', $res);
            }
        } elseif (Request::bool('quitar_imagen') && $antes) {
            Image::delete((string)$antes['imagen']);
            $datos['imagen'] = '';
        }

        // Fotos adicionales
        $extras = $antes ? jdec($antes['imagenes'] ?? []) : [];
        if (!empty($_FILES['imagenes']) && is_array($_FILES['imagenes']['name'])) {
            $n = count($_FILES['imagenes']['name']);
            for ($i = 0; $i < min(5, $n); $i++) {
                if (($_FILES['imagenes']['error'][$i] ?? 4) !== UPLOAD_ERR_OK) continue;
                [$ok, $res] = Image::upload([
                    'name' => $_FILES['imagenes']['name'][$i],
                    'type' => $_FILES['imagenes']['type'][$i],
                    'tmp_name' => $_FILES['imagenes']['tmp_name'][$i],
                    'error' => $_FILES['imagenes']['error'][$i],
                    'size' => $_FILES['imagenes']['size'][$i],
                ], 'productos/' . $this->rid, 1400, 1050, 82);
                if ($ok && count($extras) < 6) $extras[] = $res;
            }
        }
        $datos['imagenes'] = $extras;

        if ($id > 0) {
            $m->updateById($id, $datos);
            // Bitácora especial de cambios de precio
            if ($antes && (float)$antes['precio'] !== (float)$datos['precio']) {
                Audit::log('precio', 'products', $id,
                    ['precio' => (float)$antes['precio']], ['precio' => (float)$datos['precio']]);
            }
            Audit::diff('producto.editar', 'products', $id, $antes ?? [], $datos);
            $m->setModificadores($id, Request::arr('grupos'));
            flash('exito', 'Platillo actualizado.');
            redirect('panel/productos/' . $id);
        }

        $datos['orden'] = $m->maxOrder() + 1;
        $nuevo = $m->create($datos);
        $m->setModificadores($nuevo, Request::arr('grupos'));
        Audit::log('producto.crear', 'products', $nuevo, null, $datos);
        flash('exito', 'Platillo creado. Ya aparece en tu menú.');
        redirect('panel/productos/' . $nuevo);
    }

    public function productoBorrar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $m = $this->prods();
        $p = $m->findOrFail($id);
        Image::delete((string)$p['imagen']);
        foreach (jdec($p['imagenes'] ?? []) as $img) Image::delete((string)$img);
        $m->deleteById($id);
        Audit::log('producto.borrar', 'products', $id, $p);
        $this->ok([], 'Platillo eliminado');
    }

    public function productoDuplicar(): void
    {
        $this->exigir('menu');
        [$cabe, $msg] = $this->cabeEnPlan('productos');
        if (!$cabe) $this->fail($msg);
        $nuevo = $this->prods()->duplicar(Request::int('id'));
        Audit::log('producto.duplicar', 'products', $nuevo);
        $this->ok(['id' => $nuevo, 'url' => url('panel/productos/' . $nuevo)], 'Platillo duplicado');
    }

    /** Botón "Agotado" de un toque. */
    public function productoAgotado(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $campo = Request::enum('campo', ['agotado', 'activo', 'destacado'], 'agotado');
        $valor = Request::int('valor') === 1 ? 1 : 0;
        $m = $this->prods();
        $p = $m->findOrFail($id);
        $m->updateById($id, [$campo => $valor]);
        Audit::log('producto.' . $campo, 'products', $id, [$campo => (int)$p[$campo]], [$campo => $valor]);
        $textos = [
            'agotado'   => $valor ? 'Marcado como agotado' : 'Disponible de nuevo',
            'activo'    => $valor ? 'Visible en el menú' : 'Oculto del menú',
            'destacado' => $valor ? 'Ahora es recomendado' : 'Ya no es recomendado',
        ];
        $this->ok([], $p['nombre'] . ': ' . $textos[$campo]);
    }

    public function productoOrdenar(): void
    {
        $this->exigir('menu');
        $this->prods()->reorder(Request::arr('ids'));
        $this->ok([], 'Orden actualizado');
    }

    public function productoImagenBorrar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $ruta = Request::str('ruta', '', 190);
        $m = $this->prods();
        $p = $m->findOrFail($id);
        $extras = jdec($p['imagenes'] ?? []);
        $nuevas = array_values(array_filter($extras, static fn($x) => (string)$x !== $ruta));
        if (count($nuevas) !== count($extras)) {
            Image::delete($ruta);
            $m->updateById($id, ['imagenes' => $nuevas]);
        }
        $this->ok([], 'Foto eliminada');
    }

    // =================================================================
    //  MODIFICADORES
    // =================================================================
    public function modificadores(): void
    {
        $this->exigir('menu');
        $grupos = $this->mods()->conOpciones();
        foreach ($grupos as &$g) {
            $g['usado'] = $this->mods()->usadoPor((int)$g['id']);
        }
        unset($g);
        $this->panel('panel/modificadores', ['grupos' => $grupos]);
    }

    public function modificadorGuardar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $tipo = Request::enum('tipo', ['unico', 'multiple'], 'unico');
        $datos = [
            'nombre'      => Request::str('nombre', '', 120),
            'nombre_en'   => Request::str('nombre_en', '', 120),
            'tipo'        => $tipo,
            'obligatorio' => Request::bool('obligatorio') ? 1 : 0,
            'min_sel'     => max(0, min(10, Request::int('min_sel'))),
            'max_sel'     => $tipo === 'unico' ? 1 : max(1, min(20, Request::int('max_sel', 1))),
            'activo'      => Request::bool('activo', true) ? 1 : 0,
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre del grupo.');
        if ($datos['min_sel'] > $datos['max_sel']) $datos['min_sel'] = $datos['max_sel'];

        $m = $this->mods();
        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            $this->ok([], 'Grupo actualizado');
        }
        $datos['orden'] = $m->maxOrder() + 1;
        $nuevo = $m->create($datos);
        $this->ok(['id' => $nuevo], 'Grupo creado');
    }

    public function modificadorBorrar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $m = $this->mods();
        $m->findOrFail($id);
        $m->deleteById($id);
        Audit::log('modificador.borrar', 'modifier_groups', $id);
        $this->ok([], 'Grupo eliminado');
    }

    public function opcionGuardar(): void
    {
        $this->exigir('menu');
        $m = $this->mods();
        $optionId = Request::int('option_id');
        $datos = [
            'nombre'         => Request::str('nombre', '', 120),
            'nombre_en'      => Request::str('nombre_en', '', 120),
            'precio_extra'   => round(max(0, Request::float('precio_extra')), 2),
            'orden'          => Request::int('orden'),
            'activo'         => Request::bool('activo', true),
            'agotado'        => Request::bool('agotado'),
            'predeterminado' => Request::bool('predeterminado'),
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre de la opción.');

        if ($optionId > 0) {
            $m->actualizarOpcion($optionId, $datos);
            $this->ok([], 'Opción actualizada');
        }
        $gid = Request::int('group_id');
        $id = $m->crearOpcion($gid, $datos);
        $this->ok(['id' => $id], 'Opción agregada');
    }

    public function opcionBorrar(): void
    {
        $this->exigir('menu');
        $this->mods()->borrarOpcion(Request::int('option_id'));
        $this->ok([], 'Opción eliminada');
    }

    // =================================================================
    //  PROMOCIONES
    // =================================================================
    public function promociones(): void
    {
        $this->exigir('menu');
        $this->panel('panel/promociones', [
            'promos'    => $this->promos()->all('orden ASC, id DESC'),
            'cats'      => $this->cats()->all('orden ASC'),
            'productos' => $this->prods()->where('activo=1', [], 'nombre ASC', 400),
        ]);
    }

    public function promocionGuardar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $datos = [
            'nombre'       => Request::str('nombre', '', 120),
            'descripcion'  => Request::str('descripcion', '', 255),
            'tipo'         => Request::enum('tipo', ['descuento', '2x1', 'combo', 'precio_fijo'], 'descuento'),
            'valor'        => round(max(0, Request::float('valor')), 2),
            'product_ids'  => implode(',', array_map('intval', array_slice(Request::arr('product_ids'), 0, 80))),
            'category_ids' => implode(',', array_map('intval', array_slice(Request::arr('category_ids'), 0, 40))),
            'desde'        => Request::date('desde') ?: null,
            'hasta'        => Request::date('hasta') ?: null,
            'dias'         => implode(',', array_map('intval', array_slice(Request::arr('dias'), 0, 7))),
            'activo'       => Request::bool('activo', true) ? 1 : 0,
        ];
        if ($datos['nombre'] === '') $this->fail('Escribe el nombre de la promoción.');
        if ($datos['tipo'] === 'descuento' && ($datos['valor'] <= 0 || $datos['valor'] > 100)) {
            $this->fail('El porcentaje de descuento debe estar entre 1 y 100.');
        }

        $m = $this->promos();
        if ($id > 0) {
            $m->findOrFail($id);
            $m->updateById($id, $datos);
            Audit::log('promocion.editar', 'promotions', $id, null, $datos);
            $this->ok([], 'Promoción actualizada');
        }
        $datos['orden'] = $m->maxOrder() + 1;
        $nuevo = $m->create($datos);
        Audit::log('promocion.crear', 'promotions', $nuevo, null, $datos);
        $this->ok(['id' => $nuevo], 'Promoción creada');
    }

    public function promocionBorrar(): void
    {
        $this->exigir('menu');
        $id = Request::int('id');
        $m = $this->promos();
        $m->findOrFail($id);
        $m->deleteById($id);
        Audit::log('promocion.borrar', 'promotions', $id);
        $this->ok([], 'Promoción eliminada');
    }

    // =================================================================
    private function hora(string $v): ?string
    {
        if ($v === '') return null;
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v)) {
            return strlen($v) === 5 ? $v . ':00' : $v;
        }
        return null;
    }
}
