<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Uploader;
use App\Models\AttributeDef;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;

final class CatalogAdminController extends Controller
{
    // ------------------------------------------------------------ productos
    public function products(array $params = []): void
    {
        [$u, $c] = $this->panel();
        $cid = (int) $c['id'];
        [$page, $per, $offset] = Request::page(25);
        $active = Request::str('estado');
        [$rows, $total] = Product::search($cid, [
            'q'           => Request::str('q'),
            'category_id' => Request::int('categoria'),
            'brand_id'    => Request::int('marca'),
            'active'      => $active === '' ? '' : (int) $active,
            'sort'        => Request::str('orden') ?: 'nombre',
            'limit'       => $per,
            'offset'      => $offset,
        ]);
        $usage  = Company::usage($cid);
        $limits = Company::limits($cid);
        $this->view('panel/products', [
            'title'      => 'Catálogo de productos',
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'pages'      => (int) ceil($total / $per),
            'categories' => Category::options($cid),
            'brands'     => Brand::all($cid),
            'usage'      => $usage,
            'limits'     => $limits,
        ], 'layout/panel');
    }

    public function productForm(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $cid = (int) $c['id'];
        $id  = (int) ($params['id'] ?? 0);
        $p   = $id ? Product::find($cid, $id) : null;
        if ($id && !$p) {
            ErrorHandler::render(404);
        }

        if (Request::isPost()) {
            Csrf::verify();
            if (!$id && !Company::withinLimit($cid, 'products')) {
                Flash::error('Alcanzó el límite de productos de su plan (' . Company::limits($cid)['products'] . '). Contacte al administrador de la plataforma.');
                redirect('/panel/productos');
            }
            $name = mb_substr(Request::str('name'), 0, 200);
            if ($name === '') {
                Flash::error('El nombre del producto es obligatorio.');
                Flash::keep($_POST);
                redirect($id ? '/panel/productos/' . $id : '/panel/productos/nuevo');
            }
            $catId   = Request::int('category_id') ?: null;
            $brandId = Request::int('brand_id') ?: null;
            if ($catId && !Category::find($cid, $catId)) {
                $catId = null;
            }
            if ($brandId && !Brand::find($cid, $brandId)) {
                $brandId = null;
            }
            $vis = Request::str('price_visibility');
            if (!in_array($vis, ['heredar', 'publico', 'clientes', 'oculto'], true)) {
                $vis = 'heredar';
            }
            $code = Request::str('code') ?: strtoupper(substr(slugify($name), 0, 12)) . '-' . random_int(100, 999);
            $data = [
                'company_id'       => $cid,
                'category_id'      => $catId,
                'brand_id'         => $brandId,
                'code'             => Product::uniqueCode($cid, $code, $id ?: null),
                'name'             => $name,
                'slug'             => Product::uniqueSlug($cid, Request::str('slug') ?: $name, $id ?: null),
                'short_desc'       => mb_substr(Request::str('short_desc'), 0, 300) ?: null,
                'description'      => mb_substr(Request::str('description'), 0, 12000) ?: null,
                'application'      => mb_substr(Request::str('application'), 0, 255) ?: null,
                'unit'             => mb_substr(Request::str('unit') ?: 'unidad', 0, 20),
                'price'            => max(0, Request::float('price')),
                'cost'             => max(0, Request::float('cost')),
                'price_visibility' => $vis,
                'min_qty'          => max(0.01, Request::float('min_qty', 1)),
                'lead_time'        => mb_substr(Request::str('lead_time'), 0, 60) ?: null,
                'stock_note'       => mb_substr(Request::str('stock_note'), 0, 60) ?: null,
                'featured'         => Request::bool('featured') ? 1 : 0,
                'active'           => Request::bool('active') ? 1 : 0,
                'seo_title'        => mb_substr(Request::str('seo_title'), 0, 190) ?: null,
                'seo_description'  => mb_substr(Request::str('seo_description'), 0, 300) ?: null,
                'updated_at'       => nowSql(),
            ];
            if ($id) {
                $oldPrice = (float) $p['price'];
                DB::update('products', $data, 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
                if (abs($oldPrice - (float) $data['price']) > 0.001) {
                    Audit::log('producto.precio', 'product', $id, ['de' => $oldPrice, 'a' => $data['price'], 'code' => $data['code']], $cid);
                }
                Audit::log('producto.editar', 'product', $id, ['code' => $data['code']], $cid);
            } else {
                $data['created_at'] = nowSql();
                $id = DB::insert('products', $data);
                Audit::log('producto.crear', 'product', $id, ['code' => $data['code']], $cid);
            }

            // Atributos técnicos.
            DB::delete('product_attributes', 'product_id = :p AND company_id = :c', ['p' => $id, 'c' => $cid]);
            foreach (Request::arr('attr') as $aid => $val) {
                $aid = (int) $aid;
                $val = is_string($val) ? mb_substr(trim($val), 0, 190) : '';
                if ($val === '' || !AttributeDef::find($cid, $aid)) {
                    continue;
                }
                DB::insert('product_attributes', ['company_id' => $cid, 'product_id' => $id, 'attribute_id' => $aid, 'value' => $val]);
            }

            // Precios por lista de clientes.
            foreach (Request::arr('plist') as $plid => $val) {
                $plid = (int) $plid;
                $val  = (float) str_replace(',', '', (string) $val);
                if (!DB::one('SELECT id FROM price_lists WHERE id = ? AND company_id = ? LIMIT 1', [$plid, $cid])) {
                    continue;
                }
                if ($val > 0) {
                    DB::run('INSERT INTO product_prices (company_id, product_id, price_list_id, price) VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE price = VALUES(price)', [$cid, $id, $plid, $val]);
                } else {
                    DB::delete('product_prices', 'product_id = :p AND price_list_id = :l AND company_id = :c', ['p' => $id, 'l' => $plid, 'c' => $cid]);
                }
            }

            // Imágenes (recomprimidas a WebP + JPG).
            $sort = (int) DB::value('SELECT COALESCE(MAX(sort),0) FROM product_images WHERE product_id = ? AND company_id = ?', [$id, $cid], 0);
            $bad = 0;
            foreach (Uploader::files('images') as $f) {
                $res = Uploader::image($f, $cid, 'productos');
                if (!$res) {
                    $bad++;
                    continue;
                }
                DB::insert('product_images', [
                    'company_id' => $cid,
                    'product_id' => $id,
                    'path'       => $res['path'],
                    'path_webp'  => $res['path_webp'],
                    'path_thumb' => $res['path_thumb'],
                    'width'      => $res['width'],
                    'height'     => $res['height'],
                    'blur'       => $res['blur'],
                    'alt'        => $res['alt'] ?: $name,
                    'sort'       => ++$sort,
                ]);
            }
            // Documentos PDF (fichas técnicas).
            foreach (Uploader::files('documents') as $f) {
                $res = Uploader::pdf($f, $cid, 'documentos');
                if (!$res) {
                    $bad++;
                    continue;
                }
                DB::insert('product_documents', [
                    'company_id' => $cid,
                    'product_id' => $id,
                    'name'       => $res['name'],
                    'path'       => $res['path'],
                    'size'       => $res['size'],
                    'created_at' => nowSql(),
                ]);
            }
            if ($bad > 0) {
                Flash::warn("Se rechazaron {$bad} archivo(s) por tipo o tamaño no permitido.");
            }
            Flash::ok('Producto guardado.');
            redirect('/panel/productos/' . $id);
        }

        $this->view('panel/product-form', [
            'title'      => $p ? 'Editar producto' : 'Nuevo producto',
            'p'          => $p,
            'categories' => Category::options($cid),
            'brands'     => Brand::all($cid),
            'attrs'      => AttributeDef::forCategory($cid, $p ? (int) $p['category_id'] : null),
            'allAttrs'   => AttributeDef::all($cid),
            'values'     => $p ? array_column(DB::all('SELECT attribute_id, value FROM product_attributes WHERE product_id = ? AND company_id = ?', [(int) $p['id'], $cid]), 'value', 'attribute_id') : [],
            'images'     => $p ? Product::images($cid, (int) $p['id']) : [],
            'documents'  => $p ? Product::documents($cid, (int) $p['id']) : [],
            'priceLists' => DB::all('SELECT * FROM price_lists WHERE company_id = ? ORDER BY name', [$cid]),
            'listPrices' => $p ? array_column(DB::all('SELECT price_list_id, price FROM product_prices WHERE product_id = ? AND company_id = ?', [(int) $p['id'], $cid]), 'price', 'price_list_id') : [],
        ], 'layout/panel');
    }

    public function productDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $cid = (int) $c['id'];
        $id = (int) $params['id'];
        $p = Product::find($cid, $id);
        if (!$p) {
            ErrorHandler::render(404);
        }
        foreach (Product::images($cid, $id) as $img) {
            Uploader::deleteImage($img);
        }
        DB::delete('product_images', 'product_id = :p AND company_id = :c', ['p' => $id, 'c' => $cid]);
        DB::delete('product_documents', 'product_id = :p AND company_id = :c', ['p' => $id, 'c' => $cid]);
        DB::delete('product_attributes', 'product_id = :p AND company_id = :c', ['p' => $id, 'c' => $cid]);
        DB::delete('product_prices', 'product_id = :p AND company_id = :c', ['p' => $id, 'c' => $cid]);
        DB::delete('products', 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
        Audit::log('producto.eliminar', 'product', $id, ['code' => $p['code']], $cid);
        Flash::ok('Producto eliminado.');
        redirect('/panel/productos');
    }

    public function productDuplicate(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $cid = (int) $c['id'];
        $p = Product::find($cid, (int) $params['id']);
        if (!$p) {
            ErrorHandler::render(404);
        }
        if (!Company::withinLimit($cid, 'products')) {
            Flash::error('Alcanzó el límite de productos de su plan.');
            redirect('/panel/productos');
        }
        $new = $p;
        unset($new['id']);
        $new['name']       = mb_substr($p['name'] . ' (copia)', 0, 200);
        $new['code']       = Product::uniqueCode($cid, $p['code'] . '-C');
        $new['slug']       = Product::uniqueSlug($cid, $new['name']);
        $new['active']     = 0;
        $new['views']      = 0;
        $new['quote_count'] = 0;
        $new['created_at'] = nowSql();
        $new['updated_at'] = nowSql();
        $newId = DB::insert('products', $new);
        foreach (DB::all('SELECT * FROM product_attributes WHERE product_id = ? AND company_id = ?', [(int) $p['id'], $cid]) as $a) {
            DB::insert('product_attributes', ['company_id' => $cid, 'product_id' => $newId, 'attribute_id' => (int) $a['attribute_id'], 'value' => (string) $a['value']]);
        }
        Audit::log('producto.duplicar', 'product', $newId, ['origen' => (int) $p['id']], $cid);
        Flash::ok('Producto duplicado. Está inactivo hasta que lo revise.');
        redirect('/panel/productos/' . $newId);
    }

    public function imageDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $cid = (int) $c['id'];
        $img = DB::one('SELECT * FROM product_images WHERE id = ? AND company_id = ? LIMIT 1', [(int) $params['id'], $cid]);
        if ($img) {
            Uploader::deleteImage($img);
            DB::delete('product_images', 'id = :id AND company_id = :c', ['id' => (int) $img['id'], 'c' => $cid]);
        }
        $this->back('/panel/productos');
    }

    public function documentDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $cid = (int) $c['id'];
        $d = DB::one('SELECT * FROM product_documents WHERE id = ? AND company_id = ? LIMIT 1', [(int) $params['id'], $cid]);
        if ($d) {
            $abs = STORAGE_PATH . '/uploads/' . $d['path'];
            $root = realpath(STORAGE_PATH . '/uploads');
            if (is_file($abs) && $root && str_starts_with((string) realpath($abs), $root)) {
                @unlink($abs);
            }
            DB::delete('product_documents', 'id = :id AND company_id = :c', ['id' => (int) $d['id'], 'c' => $cid]);
        }
        $this->back('/panel/productos');
    }

    // ----------------------------------------------------------- categorías
    public function categories(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $cid = (int) $c['id'];
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $name = mb_substr(Request::str('name'), 0, 140);
            if ($name === '') {
                Flash::error('Escriba el nombre de la categoría.');
                redirect('/panel/categorias');
            }
            $parent = Request::int('parent_id') ?: null;
            if ($parent && (!Category::find($cid, $parent) || $parent === $id)) {
                $parent = null;
            }
            $data = [
                'company_id'  => $cid,
                'parent_id'   => $parent,
                'name'        => $name,
                'slug'        => Category::uniqueSlug($cid, Request::str('slug') ?: $name, $id ?: null),
                'code'        => mb_substr(Request::str('code'), 0, 20) ?: null,
                'description' => mb_substr(Request::str('description'), 0, 3000) ?: null,
                'active'      => Request::bool('active') ? 1 : 0,
                'seo_title'   => mb_substr(Request::str('seo_title'), 0, 190) ?: null,
                'seo_description' => mb_substr(Request::str('seo_description'), 0, 300) ?: null,
            ];
            $f = Uploader::files('image');
            if ($f) {
                $res = Uploader::image($f[0], $cid, 'categorias', 1200, 900);
                if ($res) {
                    $data['image'] = $res['path_webp'] ?: $res['path'];
                }
            }
            if ($id && Category::find($cid, $id)) {
                DB::update('categories', $data, 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
                Audit::log('categoria.editar', 'category', $id, ['nombre' => $name], $cid);
            } else {
                $data['created_at'] = nowSql();
                $data['sort'] = (int) DB::value('SELECT COALESCE(MAX(sort),0)+1 FROM categories WHERE company_id = ?', [$cid], 1);
                $id = DB::insert('categories', $data);
                Audit::log('categoria.crear', 'category', $id, ['nombre' => $name], $cid);
            }
            Flash::ok('Categoría guardada.');
            redirect('/panel/categorias');
        }
        $this->view('panel/categories', [
            'title' => 'Categorías',
            'tree'  => Category::tree($cid),
            'options' => Category::options($cid),
            'edit'  => Request::int('editar') ? Category::find($cid, Request::int('editar')) : null,
        ], 'layout/panel');
    }

    public function categoryOrder(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        Csrf::verify();
        $cid = (int) $c['id'];
        $order = Request::arr('order');
        $i = 0;
        foreach ($order as $entry) {
            $id     = (int) ($entry['id'] ?? 0);
            $parent = (int) ($entry['parent'] ?? 0) ?: null;
            if (!$id || !Category::find($cid, $id)) {
                continue;
            }
            if ($parent && (!Category::find($cid, $parent) || $parent === $id)) {
                $parent = null;
            }
            DB::update('categories', ['sort' => $i++, 'parent_id' => $parent], 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
        }
        jsonOut(['ok' => true]);
    }

    public function categoryDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $cid = (int) $c['id'];
        $id = (int) $params['id'];
        if (!Category::find($cid, $id)) {
            ErrorHandler::render(404);
        }
        DB::run('UPDATE products SET category_id = NULL WHERE category_id = ? AND company_id = ?', [$id, $cid]);
        DB::run('UPDATE categories SET parent_id = NULL WHERE parent_id = ? AND company_id = ?', [$id, $cid]);
        DB::delete('categories', 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
        Audit::log('categoria.eliminar', 'category', $id, [], $cid);
        Flash::ok('Categoría eliminada. Sus productos quedaron sin categoría.');
        redirect('/panel/categorias');
    }

    // --------------------------------------------------------------- marcas
    public function brands(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $cid = (int) $c['id'];
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $name = mb_substr(Request::str('name'), 0, 120);
            if ($name === '') {
                Flash::error('Escriba el nombre de la marca.');
                redirect('/panel/marcas');
            }
            $data = [
                'company_id' => $cid,
                'name'       => $name,
                'slug'       => slugify($name),
                'website'    => filter_var(Request::str('website'), FILTER_VALIDATE_URL) ? mb_substr(Request::str('website'), 0, 190) : null,
                'sort'       => Request::int('sort'),
                'active'     => Request::bool('active') ? 1 : 0,
            ];
            $f = Uploader::files('logo');
            if ($f) {
                $res = Uploader::image($f[0], $cid, 'marcas', 600, 400);
                if ($res) {
                    $data['logo'] = $res['path_webp'] ?: $res['path'];
                }
            }
            if ($id && Brand::find($cid, $id)) {
                DB::update('brands', $data, 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
            } else {
                $exists = DB::one('SELECT id FROM brands WHERE company_id = ? AND slug = ? LIMIT 1', [$cid, $data['slug']]);
                if ($exists) {
                    Flash::error('Ya existe una marca con ese nombre.');
                    redirect('/panel/marcas');
                }
                DB::insert('brands', $data);
            }
            Audit::log('marca.guardar', 'brand', $id, ['nombre' => $name], $cid);
            Flash::ok('Marca guardada.');
            redirect('/panel/marcas');
        }
        $this->view('panel/brands', [
            'title'  => 'Marcas que distribuye',
            'brands' => Brand::all($cid),
            'edit'   => Request::int('editar') ? Brand::find($cid, Request::int('editar')) : null,
        ], 'layout/panel');
    }

    public function brandDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $cid = (int) $c['id'];
        DB::run('UPDATE products SET brand_id = NULL WHERE brand_id = ? AND company_id = ?', [(int) $params['id'], $cid]);
        DB::delete('brands', 'id = :id AND company_id = :c', ['id' => (int) $params['id'], 'c' => $cid]);
        Flash::ok('Marca eliminada.');
        redirect('/panel/marcas');
    }

    // ---------------------------------------------------- atributos técnicos
    public function attributes(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $cid = (int) $c['id'];
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $label = mb_substr(Request::str('label'), 0, 90);
            if ($label === '') {
                Flash::error('Escriba la etiqueta del atributo (ej. Material).');
                redirect('/panel/atributos');
            }
            $type = Request::str('type');
            if (!in_array($type, ['texto', 'numero', 'lista', 'booleano'], true)) {
                $type = 'texto';
            }
            $opts = array_values(array_filter(array_map('trim', explode("\n", Request::str('options')))));
            $catId = Request::int('category_id') ?: null;
            if ($catId && !Category::find($cid, $catId)) {
                $catId = null;
            }
            $data = [
                'company_id'  => $cid,
                'category_id' => $catId,
                'code'        => mb_substr(Request::str('code') ?: slugify($label, '_'), 0, 50),
                'label'       => $label,
                'type'        => $type,
                'unit'        => mb_substr(Request::str('unit'), 0, 20) ?: null,
                'options'     => $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : null,
                'filterable'  => Request::bool('filterable') ? 1 : 0,
                'sort'        => Request::int('sort'),
            ];
            if ($id && AttributeDef::find($cid, $id)) {
                DB::update('attribute_defs', $data, 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
            } else {
                DB::insert('attribute_defs', $data);
            }
            Flash::ok('Atributo guardado.');
            redirect('/panel/atributos');
        }
        $this->view('panel/attributes', [
            'title'   => 'Atributos técnicos',
            'attrs'   => AttributeDef::all($cid),
            'options' => Category::options($cid),
            'edit'    => Request::int('editar') ? AttributeDef::find($cid, Request::int('editar')) : null,
        ], 'layout/panel');
    }

    public function attributeDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $cid = (int) $c['id'];
        DB::delete('product_attributes', 'attribute_id = :a AND company_id = :c', ['a' => (int) $params['id'], 'c' => $cid]);
        DB::delete('attribute_defs', 'id = :id AND company_id = :c', ['id' => (int) $params['id'], 'c' => $cid]);
        Flash::ok('Atributo eliminado.');
        redirect('/panel/atributos');
    }

    // ------------------------------------------------------ listas de precios
    public function priceLists(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $cid = (int) $c['id'];
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $name = mb_substr(Request::str('name'), 0, 90);
            if ($name === '') {
                Flash::error('Escriba el nombre de la lista.');
                redirect('/panel/listas-precios');
            }
            $data = [
                'company_id'   => $cid,
                'name'         => $name,
                'discount_pct' => max(0, min(90, Request::float('discount_pct'))),
                'is_default'   => Request::bool('is_default') ? 1 : 0,
            ];
            if ($data['is_default']) {
                DB::run('UPDATE price_lists SET is_default = 0 WHERE company_id = ?', [$cid]);
            }
            if ($id && DB::one('SELECT id FROM price_lists WHERE id = ? AND company_id = ?', [$id, $cid])) {
                DB::update('price_lists', $data, 'id = :id AND company_id = :c', ['id' => $id, 'c' => $cid]);
            } else {
                DB::insert('price_lists', $data);
            }
            Audit::log('lista_precios.guardar', 'price_list', $id, ['nombre' => $name], $cid);
            Flash::ok('Lista de precios guardada.');
            redirect('/panel/listas-precios');
        }
        $this->view('panel/price-lists', [
            'title' => 'Listas de precios',
            'lists' => DB::all('SELECT pl.*, (SELECT COUNT(*) FROM customers cu WHERE cu.price_list_id = pl.id) AS clientes FROM price_lists pl WHERE pl.company_id = ? ORDER BY pl.name', [$cid]),
            'edit'  => Request::int('editar') ? DB::one('SELECT * FROM price_lists WHERE id = ? AND company_id = ?', [Request::int('editar'), $cid]) : null,
        ], 'layout/panel');
    }

    public function priceListDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $cid = (int) $c['id'];
        DB::run('UPDATE customers SET price_list_id = NULL WHERE price_list_id = ? AND company_id = ?', [(int) $params['id'], $cid]);
        DB::delete('product_prices', 'price_list_id = :l AND company_id = :c', ['l' => (int) $params['id'], 'c' => $cid]);
        DB::delete('price_lists', 'id = :id AND company_id = :c', ['id' => (int) $params['id'], 'c' => $cid]);
        Flash::ok('Lista eliminada.');
        redirect('/panel/listas-precios');
    }
}
