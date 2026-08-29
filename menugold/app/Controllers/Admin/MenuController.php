<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Money;
use MenuGold\Core\Session;
use MenuGold\Core\Str;
use MenuGold\Core\Validator;
use MenuGold\Core\Xlsx;
use MenuGold\Models\Restaurant;

class MenuController extends BaseController
{
    protected $ability = 'menu';

    /* ================= Vista general ================= */

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $rid = $this->rid();

        $categoryId = $this->request->int('categoria', 0);
        $where = 'p.restaurant_id = :r';
        $params = array('r' => $rid);
        if ($categoryId > 0) { $where .= ' AND p.category_id = :c'; $params['c'] = $categoryId; }
        $q = $this->request->str('q', '');
        if ($q !== '') { $where .= ' AND p.name LIKE :q'; $params['q'] = '%' . $q . '%'; }

        return $this->view('admin/menu/index', array(
            'categories' => DB::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products_count
                                     FROM categories c WHERE c.restaurant_id = :r ORDER BY c.sort, c.id', array('r' => $rid)),
            'products'   => DB::all('SELECT p.*, c.name AS category_name FROM products p
                                     LEFT JOIN categories c ON c.id = p.category_id
                                     WHERE ' . $where . ' ORDER BY c.sort, p.sort, p.id', $params),
            'categoryId' => $categoryId,
            'q'          => $q,
            'usage'      => Restaurant::usage($rid),
        ));
    }

    /* ================= Categorías ================= */

    public function category(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nueva' ? 0 : (int)$params['id'];
        $category = $id > 0 ? $this->own('categories', $id) : null;
        if ($id > 0 && !$category) { return $this->notFound('Esa categoría no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/menu/category', array('category' => $category));
        }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $v = new Validator($this->request->post);
        $v->required('name', 'El nombre')->max('name', 'El nombre', 120);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            return $this->back('/panel/menu');
        }

        $data = array(
            'name'           => $this->request->str('name'),
            'name_en'        => $this->request->str('name_en'),
            'description'    => $this->request->str('description'),
            'description_en' => $this->request->str('description_en'),
            'roman'          => $this->request->str('roman'),
            'is_active'      => $this->request->bool('is_active') ? 1 : 0,
            'days_mask'      => $this->daysMask(),
            'available_from' => $this->timeOrNull('available_from'),
            'available_to'   => $this->timeOrNull('available_to'),
        );

        if (!empty($this->request->files['image']['name'])) {
            try {
                $data['image'] = Image::store($this->request->files['image'], $this->rid(), 'categorias', 1600);
                if ($category && $category['image'] !== '') { Image::remove($category['image']); }
            } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); }
        }

        if ($category) {
            DB::update('categories', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
            Audit::log('category_updated', 'category', $id, array('name' => $data['name']));
            Session::flash('success', 'Categoría actualizada.');
        } else {
            $data['restaurant_id'] = $this->rid();
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM categories WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
            if ($data['roman'] === '') { $data['roman'] = mg_roman($data['sort']); }
            $id = DB::insert('categories', $data);
            Audit::log('category_created', 'category', $id, array('name' => $data['name']));
            Session::flash('success', 'Categoría creada.');
        }
        return $this->redirect('/panel/menu');
    }

    public function reorderCategories()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        return $this->reorder('categories');
    }

    public function reorderProducts()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        return $this->reorder('products');
    }

    private function reorder($table)
    {
        $order = $this->request->arr('order');
        $sort = 0;
        foreach ($order as $id) {
            $sort++;
            DB::update($table, array('sort' => $sort), 'id = :id AND restaurant_id = :r',
                array('id' => (int)$id, 'r' => $this->rid()));
        }
        Audit::log('reorder', $table, 0, array('count' => count($order)));
        return $this->ok();
    }

    public function deleteCategory(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $category = $this->own('categories', (int)$params['id']);
        if (!$category) { return $this->notFound('Esa categoría no existe.'); }
        $count = (int)DB::value('SELECT COUNT(*) FROM products WHERE category_id = :c', array('c' => (int)$category['id']), 0);
        if ($count > 0) {
            Session::flash('error', 'Primero mueve o elimina los ' . $count . ' platillos de esa categoría.');
            return $this->redirect('/panel/menu');
        }
        if ($category['image'] !== '') { Image::remove($category['image']); }
        DB::delete('categories', 'id = :id AND restaurant_id = :r', array('id' => (int)$category['id'], 'r' => $this->rid()));
        Audit::log('category_deleted', 'category', (int)$category['id'], array('name' => $category['name']));
        Session::flash('success', 'Categoría eliminada.');
        return $this->redirect('/panel/menu');
    }

    /* ================= Productos ================= */

    public function product(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nuevo' ? 0 : (int)$params['id'];
        $product = $id > 0 ? $this->own('products', $id) : null;
        if ($id > 0 && !$product) { return $this->notFound('Ese platillo no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/menu/product', array(
                'product'    => $product,
                'categories' => DB::all('SELECT * FROM categories WHERE restaurant_id = :r ORDER BY sort', array('r' => $this->rid())),
                'groups'     => DB::all('SELECT * FROM modifier_groups WHERE restaurant_id = :r ORDER BY sort, id', array('r' => $this->rid())),
                'linked'     => $product ? DB::column('SELECT group_id FROM product_modifier_groups WHERE product_id = :p', array('p' => $id)) : array(),
                'variants'   => $product ? DB::all('SELECT * FROM variants WHERE product_id = :p ORDER BY sort, id', array('p' => $id)) : array(),
                'images'     => $product ? DB::all('SELECT * FROM product_images WHERE product_id = :p ORDER BY sort, id', array('p' => $id)) : array(),
            ));
        }

        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        if (!$product && Restaurant::limitReached($this->rid(), 'products')) {
            Session::flash('error', 'Alcanzaste el límite de platillos de tu plan. Contacta a soporte para ampliarlo.');
            return $this->redirect('/panel/menu');
        }

        $v = new Validator($this->request->post);
        $v->required('name', 'El nombre')->max('name', 'El nombre', 160)
          ->numeric('price', 'El precio', 0, 999999)
          ->numeric('compare_price', 'El precio comparativo', 0, 999999);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            return $this->back('/panel/menu');
        }

        $categoryId = $this->request->int('category_id', 0);
        if ($categoryId > 0 && !$this->own('categories', $categoryId)) { $categoryId = 0; }

        $tags = array();
        foreach ($this->request->arr('tags') as $t) {
            $t = preg_replace('/[^a-z_]/', '', strtolower((string)$t));
            if ($t !== '') { $tags[] = $t; }
        }

        $data = array(
            'category_id'     => $categoryId > 0 ? $categoryId : null,
            'sku'             => $this->request->str('sku'),
            'name'            => $this->request->str('name'),
            'name_en'         => $this->request->str('name_en'),
            'description'     => $this->request->str('description'),
            'description_en'  => $this->request->str('description_en'),
            'price'           => Money::round($this->request->float('price')),
            'compare_price'   => Money::round($this->request->float('compare_price')),
            'cost'            => Money::round($this->request->float('cost')),
            'prep_minutes'    => max(0, min(240, $this->request->int('prep_minutes', 15))),
            'calories'        => max(0, min(9999, $this->request->int('calories', 0))),
            'tags'            => implode(',', array_unique($tags)),
            'is_active'       => $this->request->bool('is_active') ? 1 : 0,
            'is_featured'     => $this->request->bool('is_featured') ? 1 : 0,
            'is_out_of_stock' => $this->request->bool('is_out_of_stock') ? 1 : 0,
            'days_mask'       => $this->daysMask(),
            'available_from'  => $this->timeOrNull('available_from'),
            'available_to'    => $this->timeOrNull('available_to'),
        );

        if ($product) {
            DB::update('products', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
        } else {
            $data['restaurant_id'] = $this->rid();
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM products WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
            $id = DB::insert('products', $data);
        }

        // Imagen principal e imágenes adicionales.
        if (!empty($this->request->files['image']['name'])) {
            try {
                $base = Image::store($this->request->files['image'], $this->rid(), 'platillos', 1600);
                if ($product && $product['image'] !== '') { Image::remove($product['image']); }
                DB::update('products', array('image' => $base), 'id = :id', array('id' => $id));
            } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); }
        }
        $this->storeExtraImages($id);
        $this->syncVariants($id);
        $this->syncModifierGroups($id);

        Audit::log($product ? 'product_updated' : 'product_created', 'product', $id, array('name' => $data['name']));
        Session::flash('success', $product ? 'Platillo actualizado.' : 'Platillo creado.');
        return $this->redirect('/panel/menu/producto/' . $id);
    }

    private function storeExtraImages($productId)
    {
        if (empty($this->request->files['images']) || empty($this->request->files['images']['name'])) { return; }
        $files = $this->request->files['images'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < min(6, $count); $i++) {
            if ($files['name'][$i] === '') { continue; }
            $one = array(
                'name' => $files['name'][$i], 'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i],
            );
            try {
                $base = Image::store($one, $this->rid(), 'platillos', 1600);
                DB::insert('product_images', array('product_id' => (int)$productId, 'path' => $base, 'sort' => $i + 1));
            } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); }
        }
    }

    private function syncVariants($productId)
    {
        $names  = $this->request->arr('variant_name');
        $deltas = $this->request->arr('variant_delta');
        $ids    = $this->request->arr('variant_id');
        $keep = array();
        foreach ($names as $i => $name) {
            $name = trim((string)$name);
            if ($name === '') { continue; }
            $row = array(
                'name' => mb_substr($name, 0, 80),
                'price_delta' => Money::round(isset($deltas[$i]) ? (float)str_replace(',', '.', $deltas[$i]) : 0),
                'sort' => $i,
            );
            $existing = isset($ids[$i]) ? (int)$ids[$i] : 0;
            if ($existing > 0) {
                DB::update('variants', $row, 'id = :id AND product_id = :p', array('id' => $existing, 'p' => (int)$productId));
                $keep[] = $existing;
            } else {
                $row['product_id'] = (int)$productId;
                $keep[] = DB::insert('variants', $row);
            }
        }
        if ($keep) {
            DB::run('DELETE FROM variants WHERE product_id = ? AND id NOT IN (' . DB::placeholders($keep) . ')',
                array_merge(array((int)$productId), $keep));
        } else {
            DB::delete('variants', 'product_id = :p', array('p' => (int)$productId));
        }
    }

    private function syncModifierGroups($productId)
    {
        DB::delete('product_modifier_groups', 'product_id = :p', array('p' => (int)$productId));
        $sort = 0;
        foreach ($this->request->arr('groups') as $gid) {
            $g = $this->own('modifier_groups', (int)$gid);
            if (!$g) { continue; }
            DB::insert('product_modifier_groups', array(
                'product_id' => (int)$productId, 'group_id' => (int)$g['id'], 'sort' => $sort++,
            ));
        }
    }

    public function toggleStock(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $product = $this->own('products', (int)$params['id']);
        if (!$product) { return $this->fail('Ese platillo no existe.', 404); }
        $new = (int)$product['is_out_of_stock'] === 1 ? 0 : 1;
        DB::update('products', array('is_out_of_stock' => $new), 'id = :id AND restaurant_id = :r',
            array('id' => (int)$product['id'], 'r' => $this->rid()));
        Audit::log('product_stock', 'product', (int)$product['id'], array('out_of_stock' => $new));

        return $this->ok(array(
            'state'   => $new === 1,
            'label'   => $new === 1 ? 'Agotado' : 'Disponible',
            'message' => $new === 1 ? '«' . $product['name'] . '» marcado como agotado.' : '«' . $product['name'] . '» vuelve al menú.',
        ));
    }

    public function duplicate(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $p = $this->own('products', (int)$params['id']);
        if (!$p) { return $this->fail('Ese platillo no existe.', 404); }
        if (Restaurant::limitReached($this->rid(), 'products')) {
            return $this->fail('Alcanzaste el límite de platillos de tu plan.');
        }

        unset($p['id']);
        $p['name'] = mb_substr($p['name'] . ' (copia)', 0, 160);
        $p['is_active'] = 0;
        $p['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM products WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
        $p['views'] = 0;
        unset($p['created_at'], $p['updated_at']);
        $newId = DB::insert('products', $p);

        foreach (DB::all('SELECT * FROM variants WHERE product_id = :p', array('p' => (int)$params['id'])) as $v) {
            DB::insert('variants', array('product_id' => $newId, 'name' => $v['name'], 'name_en' => $v['name_en'],
                'price_delta' => $v['price_delta'], 'is_default' => $v['is_default'], 'sort' => $v['sort']));
        }
        foreach (DB::all('SELECT * FROM product_modifier_groups WHERE product_id = :p', array('p' => (int)$params['id'])) as $g) {
            DB::insert('product_modifier_groups', array('product_id' => $newId, 'group_id' => $g['group_id'], 'sort' => $g['sort']));
        }
        Audit::log('product_duplicated', 'product', $newId, array('from' => (int)$params['id']));
        return $this->ok(array('message' => 'Platillo duplicado. Revísalo y actívalo.', 'reload' => true));
    }

    public function deleteProduct(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $p = $this->own('products', (int)$params['id']);
        if (!$p) { return $this->notFound('Ese platillo no existe.'); }
        if ($p['image'] !== '') { Image::remove($p['image']); }
        foreach (DB::column('SELECT path FROM product_images WHERE product_id = :p', array('p' => (int)$p['id'])) as $path) {
            Image::remove($path);
        }
        DB::delete('products', 'id = :id AND restaurant_id = :r', array('id' => (int)$p['id'], 'r' => $this->rid()));
        Audit::log('product_deleted', 'product', (int)$p['id'], array('name' => $p['name']));
        Session::flash('success', 'Platillo eliminado.');
        return $this->redirect('/panel/menu');
    }

    public function deleteImage(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $img = DB::first('SELECT pi.* FROM product_images pi
                          INNER JOIN products p ON p.id = pi.product_id
                          WHERE pi.id = :id AND p.restaurant_id = :r',
                         array('id' => (int)$params['id'], 'r' => $this->rid()));
        if (!$img) { return $this->fail('Esa imagen no existe.', 404); }
        Image::remove($img['path']);
        DB::delete('product_images', 'id = :id', array('id' => (int)$img['id']));
        return $this->ok(array('message' => 'Imagen eliminada.', 'reload' => true));
    }

    /* ================= Modificadores ================= */

    public function modifiers()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $groups = DB::all('SELECT * FROM modifier_groups WHERE restaurant_id = :r ORDER BY sort, id', array('r' => $this->rid()));
        foreach ($groups as $i => $g) {
            $groups[$i]['options'] = DB::all('SELECT * FROM modifier_options WHERE group_id = :g ORDER BY sort, id', array('g' => (int)$g['id']));
            $groups[$i]['used'] = (int)DB::value('SELECT COUNT(*) FROM product_modifier_groups WHERE group_id = :g', array('g' => (int)$g['id']), 0);
        }
        return $this->view('admin/menu/modifiers', array('groups' => $groups));
    }

    public function modifier(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nuevo' ? 0 : (int)$params['id'];
        $group = $id > 0 ? $this->own('modifier_groups', $id) : null;
        if ($id > 0 && !$group) { return $this->notFound('Ese grupo no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/menu/modifier', array(
                'group'   => $group,
                'options' => $group ? DB::all('SELECT * FROM modifier_options WHERE group_id = :g ORDER BY sort, id', array('g' => $id)) : array(),
            ));
        }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $v = new Validator($this->request->post);
        $v->required('name', 'El nombre')->max('name', 'El nombre', 120);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            return $this->back('/panel/menu/modificadores');
        }

        $type = $this->request->str('type', 'single') === 'multi' ? 'multi' : 'single';
        $data = array(
            'name'        => $this->request->str('name'),
            'name_en'     => $this->request->str('name_en'),
            'help'        => $this->request->str('help'),
            'type'        => $type,
            'min_select'  => max(0, min(20, $this->request->int('min_select', 0))),
            'max_select'  => $type === 'single' ? 1 : max(0, min(20, $this->request->int('max_select', 3))),
            'is_required' => $this->request->bool('is_required') ? 1 : 0,
        );

        if ($group) {
            DB::update('modifier_groups', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
        } else {
            $data['restaurant_id'] = $this->rid();
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM modifier_groups WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
            $id = DB::insert('modifier_groups', $data);
        }

        // Opciones del grupo
        $names  = $this->request->arr('option_name');
        $deltas = $this->request->arr('option_delta');
        $ids    = $this->request->arr('option_id');
        $keep = array();
        foreach ($names as $i => $name) {
            $name = trim((string)$name);
            if ($name === '') { continue; }
            $row = array(
                'name'        => mb_substr($name, 0, 120),
                'price_delta' => Money::round(isset($deltas[$i]) ? (float)str_replace(',', '.', $deltas[$i]) : 0),
                'sort'        => $i,
                'is_active'   => 1,
            );
            $existing = isset($ids[$i]) ? (int)$ids[$i] : 0;
            if ($existing > 0) {
                DB::update('modifier_options', $row, 'id = :id AND group_id = :g', array('id' => $existing, 'g' => $id));
                $keep[] = $existing;
            } else {
                $row['group_id'] = $id;
                $keep[] = DB::insert('modifier_options', $row);
            }
        }
        if ($keep) {
            DB::run('DELETE FROM modifier_options WHERE group_id = ? AND id NOT IN (' . DB::placeholders($keep) . ')',
                array_merge(array($id), $keep));
        } else {
            DB::delete('modifier_options', 'group_id = :g', array('g' => $id));
        }

        Audit::log('modifier_saved', 'modifier_group', $id, array('name' => $data['name']));
        Session::flash('success', 'Grupo de modificadores guardado.');
        return $this->redirect('/panel/menu/modificadores');
    }

    public function deleteModifier(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $g = $this->own('modifier_groups', (int)$params['id']);
        if (!$g) { return $this->notFound('Ese grupo no existe.'); }
        DB::delete('modifier_groups', 'id = :id AND restaurant_id = :r', array('id' => (int)$g['id'], 'r' => $this->rid()));
        Audit::log('modifier_deleted', 'modifier_group', (int)$g['id'], array('name' => $g['name']));
        Session::flash('success', 'Grupo eliminado.');
        return $this->redirect('/panel/menu/modificadores');
    }

    /* ================= Promociones ================= */

    public function promotions()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $v = new Validator($this->request->post);
            $v->required('name', 'El nombre')->numeric('value', 'El valor', 0, 100000);
            if ($v->fails()) {
                Session::flash('error', $v->firstError());
                return $this->redirect('/panel/menu/promociones');
            }
            $scope = $this->request->str('scope', 'all');
            if (!in_array($scope, array('all', 'category', 'product'), true)) { $scope = 'all'; }
            $scopeId = $this->request->int('scope_id', 0);
            if ($scope === 'category' && !$this->own('categories', $scopeId)) { $scope = 'all'; $scopeId = 0; }
            if ($scope === 'product'  && !$this->own('products', $scopeId))   { $scope = 'all'; $scopeId = 0; }

            $data = array(
                'name'      => $this->request->str('name'),
                'type'      => $this->request->str('type', 'percent') === 'amount' ? 'amount' : 'percent',
                'value'     => Money::round($this->request->float('value')),
                'scope'     => $scope,
                'scope_id'  => $scope === 'all' ? null : $scopeId,
                'starts_at' => $this->dateOrNull('starts_at'),
                'ends_at'   => $this->dateOrNull('ends_at'),
                'is_active' => $this->request->bool('is_active') ? 1 : 0,
            );
            $id = $this->request->int('id', 0);
            if ($id > 0 && $this->own('promotions', $id)) {
                DB::update('promotions', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
            } else {
                $data['restaurant_id'] = $this->rid();
                $id = DB::insert('promotions', $data);
            }
            Audit::log('promotion_saved', 'promotion', $id, array('name' => $data['name']));
            Session::flash('success', 'Promoción guardada.');
            return $this->redirect('/panel/menu/promociones');
        }

        return $this->view('admin/menu/promotions', array(
            'promotions' => DB::all('SELECT * FROM promotions WHERE restaurant_id = :r ORDER BY is_active DESC, id DESC', array('r' => $this->rid())),
            'categories' => DB::all('SELECT id, name FROM categories WHERE restaurant_id = :r ORDER BY sort', array('r' => $this->rid())),
            'products'   => DB::all('SELECT id, name FROM products WHERE restaurant_id = :r ORDER BY name', array('r' => $this->rid())),
        ));
    }

    public function deletePromotion(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        DB::delete('promotions', 'id = :id AND restaurant_id = :r', array('id' => (int)$params['id'], 'r' => $this->rid()));
        Session::flash('success', 'Promoción eliminada.');
        return $this->redirect('/panel/menu/promociones');
    }

    /* ================= Combos ================= */

    public function combos()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $v = new Validator($this->request->post);
            $v->required('name', 'El nombre')->numeric('price', 'El precio', 0, 999999);
            if ($v->fails()) {
                Session::flash('error', $v->firstError());
                return $this->redirect('/panel/menu/combos');
            }
            $items = array();
            foreach ($this->request->arr('items') as $pid) {
                $p = $this->own('products', (int)$pid);
                if ($p) { $items[] = array('id' => (int)$p['id'], 'name' => $p['name']); }
            }
            $data = array(
                'name'        => $this->request->str('name'),
                'description' => $this->request->str('description'),
                'price'       => Money::round($this->request->float('price')),
                'items'       => json_encode($items, JSON_UNESCAPED_UNICODE),
                'is_active'   => $this->request->bool('is_active') ? 1 : 0,
                'starts_at'   => $this->dateOrNull('starts_at'),
                'ends_at'     => $this->dateOrNull('ends_at'),
            );
            $id = $this->request->int('id', 0);
            if ($id > 0 && $this->own('combos', $id)) {
                DB::update('combos', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
            } else {
                $data['restaurant_id'] = $this->rid();
                $id = DB::insert('combos', $data);
            }
            if (!empty($this->request->files['image']['name'])) {
                try {
                    $base = Image::store($this->request->files['image'], $this->rid(), 'combos', 1600);
                    DB::update('combos', array('image' => $base), 'id = :id', array('id' => $id));
                } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); }
            }
            Audit::log('combo_saved', 'combo', $id, array('name' => $data['name']));
            Session::flash('success', 'Combo guardado.');
            return $this->redirect('/panel/menu/combos');
        }

        $combos = DB::all('SELECT * FROM combos WHERE restaurant_id = :r ORDER BY sort, id', array('r' => $this->rid()));
        foreach ($combos as $i => $c) { $combos[$i]['items_list'] = Str::json($c['items']); }
        return $this->view('admin/menu/combos', array(
            'combos'   => $combos,
            'products' => DB::all('SELECT id, name, price FROM products WHERE restaurant_id = :r AND is_active = 1 ORDER BY name', array('r' => $this->rid())),
        ));
    }

    public function deleteCombo(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        $c = $this->own('combos', (int)$params['id']);
        if ($c && $c['image'] !== '') { Image::remove($c['image']); }
        DB::delete('combos', 'id = :id AND restaurant_id = :r', array('id' => (int)$params['id'], 'r' => $this->rid()));
        Session::flash('success', 'Combo eliminado.');
        return $this->redirect('/panel/menu/combos');
    }

    /* ================= Importación ================= */

    public function importTemplate()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $rows = array(
            array('Categoria', 'Nombre', 'Descripcion', 'Precio', 'Etiquetas', 'Minutos', 'Activo'),
            array('Cortes', 'Rib eye 400 g', 'Maduración 45 días, mantequilla de hierbas', 285, 'popular,recomendado', 22, 1),
            array('Entradas', 'Tuétano al carbón', 'Con pan de masa madre y sal de gusano', 95, 'nuevo', 12, 1),
            array('Postres', 'Tres leches de café', 'Crema quemada y cacao', 65, '', 8, 1),
        );
        return Xlsx::response(array('Menú' => $rows), 'plantilla-menu.xlsx');
    }

    public function import()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if (!$this->request->isPost()) {
            return $this->view('admin/menu/import', array('result' => null));
        }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        if (empty($this->request->files['file']['tmp_name']) || (int)$this->request->files['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Selecciona un archivo Excel o CSV.');
            return $this->redirect('/panel/menu/importar');
        }

        try {
            $rows = Xlsx::read($this->request->files['file']['tmp_name']);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/panel/menu/importar');
        }

        $created = 0; $updated = 0; $skipped = 0;
        $errors = array();
        $cats = array();
        foreach (DB::all('SELECT id, name FROM categories WHERE restaurant_id = :r', array('r' => $this->rid())) as $c) {
            $cats[mb_strtolower($c['name'])] = (int)$c['id'];
        }

        foreach ($rows as $n => $row) {
            if ($n === 0) { continue; }                       // encabezados
            if (count(array_filter($row, 'strlen')) === 0) { continue; }

            $catName = isset($row[0]) ? trim($row[0]) : '';
            $name    = isset($row[1]) ? trim($row[1]) : '';
            if ($name === '') { $skipped++; continue; }
            if (mb_strlen($name) > 160) { $name = mb_substr($name, 0, 160); }

            $price = isset($row[3]) ? (float)str_replace(array(',', 'Q', ' '), array('.', '', ''), $row[3]) : 0;
            if ($price < 0) { $price = 0; }

            $catId = null;
            if ($catName !== '') {
                $key = mb_strtolower($catName);
                if (!isset($cats[$key])) {
                    $sort = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM categories WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
                    $cats[$key] = DB::insert('categories', array(
                        'restaurant_id' => $this->rid(), 'name' => mb_substr($catName, 0, 120),
                        'roman' => mg_roman($sort), 'sort' => $sort, 'is_active' => 1,
                    ));
                }
                $catId = $cats[$key];
            }

            $tags = isset($row[4]) ? preg_replace('/[^a-z_,]/', '', mb_strtolower(trim($row[4]))) : '';
            $data = array(
                'category_id'  => $catId,
                'name'         => $name,
                'description'  => isset($row[2]) ? mb_substr(trim($row[2]), 0, 2000) : '',
                'price'        => Money::round($price),
                'tags'         => $tags,
                'prep_minutes' => isset($row[5]) && $row[5] !== '' ? max(0, min(240, (int)$row[5])) : 15,
                'is_active'    => isset($row[6]) ? ((int)$row[6] === 0 ? 0 : 1) : 1,
            );

            $existing = DB::value('SELECT id FROM products WHERE restaurant_id = :r AND name = :n LIMIT 1',
                array('r' => $this->rid(), 'n' => $name));
            if ($existing) {
                DB::update('products', $data, 'id = :id AND restaurant_id = :r', array('id' => (int)$existing, 'r' => $this->rid()));
                $updated++;
            } else {
                if (Restaurant::limitReached($this->rid(), 'products')) {
                    $errors[] = 'Se alcanzó el límite de platillos del plan en la fila ' . ($n + 1) . '.';
                    break;
                }
                $data['restaurant_id'] = $this->rid();
                $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM products WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
                DB::insert('products', $data);
                $created++;
            }
        }

        Audit::log('menu_imported', 'product', 0, array('created' => $created, 'updated' => $updated));
        return $this->view('admin/menu/import', array(
            'result' => array('created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors),
        ));
    }

    /* ================= Auxiliares ================= */

    private function daysMask()
    {
        $days = $this->request->arr('days');
        if (!$days) { return 127; }
        $mask = 0;
        foreach ($days as $d) {
            $d = (int)$d;
            if ($d >= 0 && $d <= 6) { $mask |= (1 << $d); }
        }
        return $mask > 0 ? $mask : 127;
    }

    private function timeOrNull($field)
    {
        $v = $this->request->str($field, '');
        return preg_match('/^\d{1,2}:\d{2}$/', $v) ? $v . ':00' : null;
    }

    private function dateOrNull($field)
    {
        $v = $this->request->str($field, '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
