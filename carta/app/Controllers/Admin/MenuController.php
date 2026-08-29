<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Money;
use MenuGold\Core\Session;
use MenuGold\Core\Validator;
use MenuGold\Core\Xlsx;

class MenuController extends BaseController
{
    protected $ability = 'menu';

    /* ================= Vista general ================= */

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $categoryId = $this->request->int('categoria', 0);
        $where = '1 = 1';
        $params = array();
        if ($categoryId > 0) { $where .= ' AND p.category_id = :c'; $params['c'] = $categoryId; }
        $q = $this->request->str('q', '');
        if ($q !== '') { $where .= ' AND p.name LIKE :q'; $params['q'] = '%' . $q . '%'; }

        return $this->view('admin/menu/index', array(
            'categories' => DB::all('SELECT c.*, (SELECT COUNT(*) FROM mg_products p WHERE p.category_id = c.id) AS products_count
                                     FROM mg_categories c ORDER BY c.sort, c.id'),
            'products'   => DB::all('SELECT p.*, c.name AS category_name FROM mg_products p
                                     LEFT JOIN mg_categories c ON c.id = p.category_id
                                     WHERE ' . $where . ' ORDER BY c.sort, p.sort, p.id', $params),
            'categoryId' => $categoryId,
            'q'          => $q,
        ));
    }

    /* ================= Categorías ================= */

    public function category(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nueva' ? 0 : (int)$params['id'];
        $category = $id > 0 ? $this->row('mg_categories', $id) : null;
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
            'roman'          => mb_substr($this->request->str('roman'), 0, 8),
            'is_active'      => $this->request->bool('is_active') ? 1 : 0,
            'days_mask'      => $this->daysMask(),
        );

        if (!empty($this->request->files['image']['name'])) {
            try {
                $data['image'] = Image::store($this->request->files['image'], 'categorias', 1600);
                if ($category && $category['image'] !== '') { Image::remove($category['image']); }
            } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); }
        }

        if ($category) {
            DB::update('mg_categories', $data, 'id = :id', array('id' => $id));
            Audit::log('category_updated', 'category', $id, array('name' => $data['name']));
            Session::flash('success', 'Categoría actualizada.');
        } else {
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_categories', array(), 0);
            if ($data['roman'] === '') { $data['roman'] = mg_roman($data['sort']); }
            $id = DB::insert('mg_categories', $data);
            Audit::log('category_created', 'category', $id, array('name' => $data['name']));
            Session::flash('success', 'Categoría creada.');
        }
        return $this->redirect('/panel/menu');
    }

    public function reorderCategories() { return $this->reorder('mg_categories'); }
    public function reorderProducts()   { return $this->reorder('mg_products'); }

    private function reorder($table)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $order = $this->request->arr('order');
        $sort = 0;
        foreach ($order as $id) {
            $sort++;
            DB::update($table, array('sort' => $sort), 'id = :id', array('id' => (int)$id));
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

        $category = $this->row('mg_categories', (int)$params['id']);
        if (!$category) { return $this->notFound('Esa categoría no existe.'); }
        $count = (int)DB::value('SELECT COUNT(*) FROM mg_products WHERE category_id = :c', array('c' => (int)$category['id']), 0);
        if ($count > 0) {
            Session::flash('error', 'Primero mueve o elimina los ' . $count . ' platillos de esa categoría.');
            return $this->redirect('/panel/menu');
        }
        if ($category['image'] !== '') { Image::remove($category['image']); }
        DB::delete('mg_categories', 'id = :id', array('id' => (int)$category['id']));
        Audit::log('category_deleted', 'category', (int)$category['id'], array('name' => $category['name']));
        Session::flash('success', 'Categoría eliminada.');
        return $this->redirect('/panel/menu');
    }

    /* ================= Platillos ================= */

    public function product(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nuevo' ? 0 : (int)$params['id'];
        $product = $id > 0 ? $this->row('mg_products', $id) : null;
        if ($id > 0 && !$product) { return $this->notFound('Ese platillo no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/menu/product', array(
                'product'    => $product,
                'categories' => DB::all('SELECT * FROM mg_categories ORDER BY sort'),
                'groups'     => DB::all('SELECT * FROM mg_modifier_groups ORDER BY sort, id'),
                'linked'     => $product ? DB::column('SELECT group_id FROM mg_product_modifier_groups WHERE product_id = :p', array('p' => $id)) : array(),
                'variants'   => $product ? DB::all('SELECT * FROM mg_variants WHERE product_id = :p ORDER BY sort, id', array('p' => $id)) : array(),
                'images'     => $product ? DB::all('SELECT * FROM mg_product_images WHERE product_id = :p ORDER BY sort, id', array('p' => $id)) : array(),
            ));
        }

        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $v = new Validator($this->request->post);
        $v->required('name', 'El nombre')->max('name', 'El nombre', 160)
          ->numeric('price', 'El precio', 0, 999999);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            return $this->back('/panel/menu');
        }

        $categoryId = $this->request->int('category_id', 0);
        if ($categoryId <= 0 || !$this->row('mg_categories', $categoryId)) {
            $categoryId = (int)DB::value('SELECT id FROM mg_categories ORDER BY sort LIMIT 1', array(), 0);
            if ($categoryId <= 0) {
                Session::flash('error', 'Crea primero una categoría.');
                return $this->redirect('/panel/menu');
            }
        }

        $tags = array();
        foreach ($this->request->arr('tags') as $t) {
            $t = preg_replace('/[^a-z_]/', '', strtolower((string)$t));
            if ($t !== '') { $tags[] = $t; }
        }

        $data = array(
            'category_id'    => $categoryId,
            'name'           => $this->request->str('name'),
            'name_en'        => $this->request->str('name_en'),
            'description'    => $this->request->str('description'),
            'description_en' => $this->request->str('description_en'),
            'price'          => Money::round($this->request->float('price')),
            'prep_minutes'   => max(0, min(240, $this->request->int('prep_minutes', 15))),
            'tags'           => implode(',', array_unique($tags)),
            'is_active'      => $this->request->bool('is_active') ? 1 : 0,
            'is_featured'    => $this->request->bool('is_featured') ? 1 : 0,
            'is_sold_out'    => $this->request->bool('is_sold_out') ? 1 : 0,
            'days_mask'      => $this->daysMask(),
            'available_from' => $this->timeOrNull('available_from'),
            'available_to'   => $this->timeOrNull('available_to'),
        );

        if ($product) {
            DB::update('mg_products', $data, 'id = :id', array('id' => $id));
        } else {
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_products', array(), 0);
            $id = DB::insert('mg_products', $data);
        }

        if (!empty($this->request->files['image']['name'])) {
            try {
                $base = Image::store($this->request->files['image'], 'platillos', 1600);
                if ($product && $product['image'] !== '') { Image::remove($product['image']); }
                DB::update('mg_products', array('image' => $base), 'id = :id', array('id' => $id));
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
                $base = Image::store($one, 'platillos', 1600);
                DB::insert('mg_product_images', array('product_id' => (int)$productId, 'image' => $base, 'sort' => $i + 1));
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
                'name'        => mb_substr($name, 0, 120),
                'price_delta' => Money::round(isset($deltas[$i]) ? (float)str_replace(',', '.', $deltas[$i]) : 0),
                'sort'        => (int)$i,
            );
            $row['is_default'] = abs((float)$row['price_delta']) < 0.001 ? 1 : 0;
            $existing = isset($ids[$i]) ? (int)$ids[$i] : 0;
            if ($existing > 0) {
                DB::update('mg_variants', $row, 'id = :id AND product_id = :p', array('id' => $existing, 'p' => (int)$productId));
                $keep[] = $existing;
            } else {
                $row['product_id'] = (int)$productId;
                $keep[] = DB::insert('mg_variants', $row);
            }
        }
        if ($keep) {
            DB::run('DELETE FROM mg_variants WHERE product_id = ? AND id NOT IN (' . DB::placeholders($keep) . ')',
                array_merge(array((int)$productId), $keep));
        } else {
            DB::delete('mg_variants', 'product_id = :p', array('p' => (int)$productId));
        }
    }

    private function syncModifierGroups($productId)
    {
        DB::delete('mg_product_modifier_groups', 'product_id = :p', array('p' => (int)$productId));
        $sort = 0;
        foreach ($this->request->arr('groups') as $gid) {
            $g = $this->row('mg_modifier_groups', (int)$gid);
            if (!$g) { continue; }
            DB::insert('mg_product_modifier_groups', array(
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

        $product = $this->row('mg_products', (int)$params['id']);
        if (!$product) { return $this->fail('Ese platillo no existe.', 404); }
        $new = (int)$product['is_sold_out'] === 1 ? 0 : 1;
        DB::update('mg_products', array('is_sold_out' => $new), 'id = :id', array('id' => (int)$product['id']));
        Audit::log('product_stock', 'product', (int)$product['id'], array('sold_out' => $new));

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

        $p = $this->row('mg_products', (int)$params['id']);
        if (!$p) { return $this->fail('Ese platillo no existe.', 404); }

        $origen = (int)$p['id'];
        unset($p['id'], $p['created_at']);
        $p['name'] = mb_substr($p['name'] . ' (copia)', 0, 160);
        $p['is_active'] = 0;
        $p['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_products', array(), 0);
        $newId = DB::insert('mg_products', $p);

        foreach (DB::all('SELECT * FROM mg_variants WHERE product_id = :p', array('p' => $origen)) as $v) {
            DB::insert('mg_variants', array('product_id' => $newId, 'name' => $v['name'],
                'price_delta' => $v['price_delta'], 'is_default' => $v['is_default'], 'sort' => $v['sort']));
        }
        foreach (DB::all('SELECT * FROM mg_product_modifier_groups WHERE product_id = :p', array('p' => $origen)) as $g) {
            DB::insert('mg_product_modifier_groups', array('product_id' => $newId, 'group_id' => $g['group_id'], 'sort' => $g['sort']));
        }
        Audit::log('product_duplicated', 'product', $newId, array('from' => $origen));
        return $this->ok(array('message' => 'Platillo duplicado. Revísalo y actívalo.', 'reload' => true));
    }

    public function deleteProduct(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $p = $this->row('mg_products', (int)$params['id']);
        if (!$p) { return $this->notFound('Ese platillo no existe.'); }
        if ($p['image'] !== '') { Image::remove($p['image']); }
        foreach (DB::column('SELECT image FROM mg_product_images WHERE product_id = :p', array('p' => (int)$p['id'])) as $img) {
            Image::remove($img);
        }
        DB::delete('mg_products', 'id = :id', array('id' => (int)$p['id']));
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

        $img = $this->row('mg_product_images', (int)$params['id']);
        if (!$img) { return $this->fail('Esa imagen no existe.', 404); }
        Image::remove($img['image']);
        DB::delete('mg_product_images', 'id = :id', array('id' => (int)$img['id']));
        return $this->ok(array('message' => 'Imagen eliminada.', 'reload' => true));
    }

    /* ================= Modificadores ================= */

    public function modifiers()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $groups = DB::all('SELECT * FROM mg_modifier_groups ORDER BY sort, id');
        foreach ($groups as $i => $g) {
            $groups[$i]['options'] = DB::all('SELECT * FROM mg_modifier_options WHERE group_id = :g ORDER BY sort, id', array('g' => (int)$g['id']));
            $groups[$i]['used'] = (int)DB::value('SELECT COUNT(*) FROM mg_product_modifier_groups WHERE group_id = :g', array('g' => (int)$g['id']), 0);
        }
        return $this->view('admin/menu/modifiers', array('groups' => $groups));
    }

    public function modifierGroup(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nuevo' ? 0 : (int)$params['id'];
        $group = $id > 0 ? $this->row('mg_modifier_groups', $id) : null;
        if ($id > 0 && !$group) { return $this->notFound('Ese grupo no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/menu/modifier', array(
                'group'   => $group,
                'options' => $group ? DB::all('SELECT * FROM mg_modifier_options WHERE group_id = :g ORDER BY sort, id', array('g' => $id)) : array(),
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
            DB::update('mg_modifier_groups', $data, 'id = :id', array('id' => $id));
        } else {
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_modifier_groups', array(), 0);
            $id = DB::insert('mg_modifier_groups', $data);
        }

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
                'sort'        => (int)$i,
                'is_active'   => 1,
            );
            $existing = isset($ids[$i]) ? (int)$ids[$i] : 0;
            if ($existing > 0) {
                DB::update('mg_modifier_options', $row, 'id = :id AND group_id = :g', array('id' => $existing, 'g' => $id));
                $keep[] = $existing;
            } else {
                $row['group_id'] = $id;
                $keep[] = DB::insert('mg_modifier_options', $row);
            }
        }
        if ($keep) {
            DB::run('DELETE FROM mg_modifier_options WHERE group_id = ? AND id NOT IN (' . DB::placeholders($keep) . ')',
                array_merge(array($id), $keep));
        } else {
            DB::delete('mg_modifier_options', 'group_id = :g', array('g' => $id));
        }

        Audit::log('modifier_saved', 'modifier_group', $id, array('name' => $data['name']));
        Session::flash('success', 'Grupo de modificadores guardado.');
        return $this->redirect('/panel/menu/modificadores');
    }

    public function deleteModifierGroup(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $g = $this->row('mg_modifier_groups', (int)$params['id']);
        if (!$g) { return $this->notFound('Ese grupo no existe.'); }
        DB::delete('mg_modifier_groups', 'id = :id', array('id' => (int)$g['id']));
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
            $targetId = $this->request->int('target_id', 0);
            if ($scope === 'category' && !$this->row('mg_categories', $targetId)) { $scope = 'all'; $targetId = 0; }
            if ($scope === 'product'  && !$this->row('mg_products', $targetId))   { $scope = 'all'; $targetId = 0; }

            $data = array(
                'name'      => $this->request->str('name'),
                'type'      => $this->request->str('type', 'percent') === 'amount' ? 'amount' : 'percent',
                'value'     => Money::round($this->request->float('value')),
                'scope'     => $scope,
                'target_id' => $scope === 'all' ? 0 : $targetId,
                'starts_at' => $this->dateOrNull('starts_at'),
                'ends_at'   => $this->dateOrNull('ends_at'),
                'days_mask' => $this->daysMask(),
                'is_active' => $this->request->bool('is_active') ? 1 : 0,
            );
            $id = $this->request->int('id', 0);
            if ($id > 0 && $this->row('mg_promotions', $id)) {
                DB::update('mg_promotions', $data, 'id = :id', array('id' => $id));
            } else {
                $id = DB::insert('mg_promotions', $data);
            }
            Audit::log('promotion_saved', 'promotion', $id, array('name' => $data['name']));
            Session::flash('success', 'Promoción guardada.');
            return $this->redirect('/panel/menu/promociones');
        }

        return $this->view('admin/menu/promotions', array(
            'promotions' => DB::all('SELECT * FROM mg_promotions ORDER BY is_active DESC, id DESC'),
            'categories' => DB::all('SELECT id, name FROM mg_categories ORDER BY sort'),
            'products'   => DB::all('SELECT id, name FROM mg_products ORDER BY name'),
        ));
    }

    public function deletePromotion(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        DB::delete('mg_promotions', 'id = :id', array('id' => (int)$params['id']));
        Session::flash('success', 'Promoción eliminada.');
        return $this->redirect('/panel/menu/promociones');
    }

    /* ================= Cupones ================= */

    public function coupons()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $code = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $this->request->str('code', '')));
            if ($code === '') {
                Session::flash('error', 'El cupón necesita un código (letras, números y guiones).');
                return $this->redirect('/panel/menu/cupones');
            }
            $data = array(
                'code'      => mb_substr($code, 0, 40),
                'type'      => $this->request->str('type', 'percent') === 'amount' ? 'amount' : 'percent',
                'value'     => Money::round($this->request->float('value')),
                'min_total' => Money::round($this->request->float('min_total')),
                'max_uses'  => max(0, min(100000, $this->request->int('max_uses', 0))),
                'starts_at' => $this->dateOrNull('starts_at'),
                'ends_at'   => $this->dateOrNull('ends_at'),
                'is_active' => $this->request->bool('is_active') ? 1 : 0,
            );
            $id = $this->request->int('id', 0);
            $clash = DB::value('SELECT id FROM mg_coupons WHERE code = :c AND id <> :i', array('c' => $data['code'], 'i' => $id));
            if ($clash) {
                Session::flash('error', 'Ya existe un cupón con ese código.');
                return $this->redirect('/panel/menu/cupones');
            }
            if ($id > 0 && $this->row('mg_coupons', $id)) {
                DB::update('mg_coupons', $data, 'id = :id', array('id' => $id));
            } else {
                $id = DB::insert('mg_coupons', $data);
            }
            Audit::log('coupon_saved', 'coupon', $id, array('code' => $data['code']));
            Session::flash('success', 'Cupón guardado.');
            return $this->redirect('/panel/menu/cupones');
        }

        return $this->view('admin/menu/coupons', array(
            'coupons' => DB::all('SELECT * FROM mg_coupons ORDER BY is_active DESC, id DESC'),
        ));
    }

    public function deleteCoupon(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        DB::delete('mg_coupons', 'id = :id', array('id' => (int)$params['id']));
        Session::flash('success', 'Cupón eliminado.');
        return $this->redirect('/panel/menu/cupones');
    }

    /* ================= Importar y exportar ================= */

    public function importTemplate()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $rows = array(
            array('Categoria', 'Nombre', 'Descripcion', 'Precio', 'Etiquetas', 'Minutos', 'Activo'),
            array('De la brasa', 'Rib eye 400 g', 'Maduración 45 días, mantequilla de hierbas', 285, 'popular,recomendado', 22, 1),
            array('Para empezar', 'Tuétano al carbón', 'Con pan de masa madre y sal de gusano', 95, 'nuevo', 12, 1),
            array('Postres', 'Tres leches de café', 'Crema quemada y cacao', 65, '', 8, 1),
        );
        return Xlsx::response(array('Menú' => $rows), 'plantilla-menu.xlsx');
    }

    public function export()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $rows = array(array('Categoria', 'Nombre', 'Descripcion', 'Precio', 'Etiquetas', 'Minutos', 'Activo'));
        foreach (DB::all('SELECT p.*, c.name AS category_name FROM mg_products p
                          LEFT JOIN mg_categories c ON c.id = p.category_id
                          ORDER BY c.sort, p.sort') as $p) {
            $rows[] = array((string)$p['category_name'], $p['name'], $p['description'],
                (float)$p['price'], $p['tags'], (int)$p['prep_minutes'], (int)$p['is_active']);
        }
        return Xlsx::response(array('Menú' => $rows), 'menu.xlsx');
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
        foreach (DB::all('SELECT id, name FROM mg_categories') as $c) {
            $cats[mb_strtolower($c['name'])] = (int)$c['id'];
        }

        foreach ($rows as $n => $row) {
            if ($n === 0) { continue; }                       // encabezados
            if (count(array_filter($row, 'strlen')) === 0) { continue; }

            $catName = isset($row[0]) ? trim($row[0]) : '';
            $name    = isset($row[1]) ? trim($row[1]) : '';
            if ($name === '') { $skipped++; continue; }
            $name = mb_substr($name, 0, 160);

            $price = isset($row[3]) ? (float)str_replace(array(',', 'Q', ' '), array('.', '', ''), $row[3]) : 0;
            if ($price < 0) { $price = 0; }

            if ($catName === '') { $catName = 'Sin categoría'; }
            $key = mb_strtolower($catName);
            if (!isset($cats[$key])) {
                $sort = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_categories', array(), 0);
                $cats[$key] = DB::insert('mg_categories', array(
                    'name' => mb_substr($catName, 0, 120), 'roman' => mg_roman($sort), 'sort' => $sort, 'is_active' => 1,
                ));
            }

            $data = array(
                'category_id'  => $cats[$key],
                'name'         => $name,
                'description'    => isset($row[2]) ? mb_substr(trim($row[2]), 0, 2000) : '',
                'description_en' => '',
                'price'        => Money::round($price),
                'tags'         => isset($row[4]) ? preg_replace('/[^a-z_,]/', '', mb_strtolower(trim($row[4]))) : '',
                'prep_minutes' => isset($row[5]) && $row[5] !== '' ? max(0, min(240, (int)$row[5])) : 15,
                'is_active'    => isset($row[6]) ? ((int)$row[6] === 0 ? 0 : 1) : 1,
            );

            $existing = DB::value('SELECT id FROM mg_products WHERE name = :n LIMIT 1', array('n' => $name));
            if ($existing) {
                DB::update('mg_products', $data, 'id = :id', array('id' => (int)$existing));
                $updated++;
            } else {
                $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_products', array(), 0);
                DB::insert('mg_products', $data);
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
