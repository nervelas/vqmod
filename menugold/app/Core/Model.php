<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Modelo base. Todo modelo con $scoped = true filtra SIEMPRE por restaurant_id,
 * garantizando el aislamiento entre restaurantes.
 */
abstract class Model
{
    protected string $table = '';
    protected string $pk = 'id';
    /** Si es true, todas las consultas se filtran por restaurant_id. */
    protected bool $scoped = true;
    /** Columnas que pueden asignarse masivamente. */
    protected array $fillable = [];
    protected array $jsonFields = [];

    protected ?int $forceRestaurant = null;

    public function table(): string { return $this->table; }

    /** Fuerza el restaurante (solo para superadmin / instalador / cron). */
    public function forRestaurant(int $id): static
    {
        $this->forceRestaurant = $id;
        return $this;
    }

    /** ID del restaurante que se aplica a las consultas. */
    protected function rid(): int
    {
        if ($this->forceRestaurant !== null) return $this->forceRestaurant;
        $r = App::restaurantId();
        if ($r > 0) return $r;
        return Auth::restaurantId();
    }

    /** Fragmento WHERE con aislamiento. */
    protected function scope(string $where = '', array $params = []): array
    {
        if (!$this->scoped) {
            return [$where !== '' ? $where : '1=1', $params];
        }
        $rid = $this->rid();
        if ($rid <= 0) {
            throw HttpException::forbidden('Contexto de restaurante no definido.');
        }
        $w = '`' . DB::ident($this->table) . '`.restaurant_id = :__rid';
        if ($where !== '') $w .= ' AND (' . $where . ')';
        $params['__rid'] = $rid;
        return [$w, $params];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        [$w, $p] = $this->scope('`' . DB::ident($this->table) . '`.`' . $this->pk . '` = :__id', ['__id' => $id]);
        $row = DB::one('SELECT * FROM `' . DB::ident($this->table) . '` WHERE ' . $w . ' LIMIT 1', $p);
        return $row ? $this->cast($row) : null;
    }

    /** Busca o lanza 404. */
    public function findOrFail(int $id): array
    {
        $r = $this->find($id);
        if (!$r) throw HttpException::notFound('El registro solicitado no existe.');
        return $r;
    }

    /** @return array<int,array<string,mixed>> */
    public function where(string $where = '', array $params = [], string $order = '', int $limit = 0, int $offset = 0): array
    {
        [$w, $p] = $this->scope($where, $params);
        $sql = 'SELECT * FROM `' . DB::ident($this->table) . '` WHERE ' . $w;
        if ($order !== '') $sql .= ' ORDER BY ' . $this->safeOrder($order);
        if ($limit > 0) $sql .= ' LIMIT ' . (int)$limit . ($offset > 0 ? ' OFFSET ' . (int)$offset : '');
        return array_map([$this, 'cast'], DB::all($sql, $p));
    }

    public function first(string $where = '', array $params = [], string $order = ''): ?array
    {
        $r = $this->where($where, $params, $order, 1);
        return $r[0] ?? null;
    }

    public function all(string $order = ''): array
    {
        return $this->where('', [], $order);
    }

    public function count(string $where = '', array $params = []): int
    {
        [$w, $p] = $this->scope($where, $params);
        return DB::int('SELECT COUNT(*) FROM `' . DB::ident($this->table) . '` WHERE ' . $w, $p);
    }

    public function exists(string $where, array $params = []): bool
    {
        return $this->count($where, $params) > 0;
    }

    public function create(array $data): int
    {
        $data = $this->prepare($data);
        if ($this->scoped && empty($data['restaurant_id'])) {
            $data['restaurant_id'] = $this->rid();
        }
        if (!isset($data['creado']) && $this->hasColumn('creado')) $data['creado'] = date('Y-m-d H:i:s');
        return DB::insert($this->table, $data);
    }

    public function updateById(int $id, array $data): int
    {
        $data = $this->prepare($data);
        unset($data['restaurant_id'], $data[$this->pk]);
        if ($this->hasColumn('actualizado')) $data['actualizado'] = date('Y-m-d H:i:s');
        if (!$data) return 0;
        [$w, $p] = $this->scope('`' . $this->pk . '` = :__id', ['__id' => $id]);
        return DB::update($this->table, $data, $w, $p);
    }

    public function deleteById(int $id): int
    {
        [$w, $p] = $this->scope('`' . $this->pk . '` = :__id', ['__id' => $id]);
        return DB::delete($this->table, $w, $p);
    }

    /** Solo deja pasar las columnas permitidas y serializa JSON. */
    protected function prepare(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($this->fillable && !in_array($k, $this->fillable, true)) continue;
            if (in_array($k, $this->jsonFields, true)) {
                $v = is_string($v) ? $v : json_encode(array_values(array_filter((array)$v, static fn($x) => $x !== '' && $x !== null)), JSON_UNESCAPED_UNICODE);
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /** Decodifica campos JSON al leer. */
    protected function cast(array $row): array
    {
        foreach ($this->jsonFields as $f) {
            if (array_key_exists($f, $row)) $row[$f] = jdec($row[$f]);
        }
        return $row;
    }

    /** Valida ORDER BY contra un patron seguro (nunca entrada cruda). */
    protected function safeOrder(string $order): string
    {
        $parts = [];
        foreach (explode(',', $order) as $chunk) {
            $chunk = trim($chunk);
            if (preg_match('/^([A-Za-z0-9_]+)(\.[A-Za-z0-9_]+)?\s*(ASC|DESC)?$/i', $chunk, $m)) {
                $col = $m[1] . ($m[2] ?? '');
                $parts[] = '`' . str_replace('.', '`.`', $col) . '` ' . strtoupper($m[3] ?? 'ASC');
            }
        }
        return $parts ? implode(', ', $parts) : '`' . $this->pk . '` ASC';
    }

    protected function hasColumn(string $col): bool
    {
        static $cache = [];
        $key = $this->table;
        if (!isset($cache[$key])) {
            try {
                $cache[$key] = DB::column('SHOW COLUMNS FROM `' . DB::ident($this->table) . '`');
            } catch (\Throwable $e) {
                $cache[$key] = [];
            }
        }
        return in_array($col, $cache[$key], true);
    }

    /** Reordena registros segun una lista de IDs. */
    public function reorder(array $ids): void
    {
        $orden = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            [$w, $p] = $this->scope('`' . $this->pk . '` = :__id', ['__id' => $id]);
            DB::update($this->table, ['orden' => $orden++], $w, $p);
        }
    }

    public function maxOrder(): int
    {
        [$w, $p] = $this->scope();
        return DB::int('SELECT COALESCE(MAX(orden),0) FROM `' . DB::ident($this->table) . '` WHERE ' . $w, $p);
    }
}
