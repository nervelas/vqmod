<?php
declare(strict_types=1);

/**
 * Motor CRUD generico del panel: genera listados y formularios a partir de
 * una definicion declarativa (admin/includes/schema.php).
 */
final class Crud
{
    private array $def;
    private string $key;

    public function __construct(string $key, array $def)
    {
        $this->key = $key;
        $this->def = $def;
    }

    public function table(): string { return (string) $this->def['table']; }
    public function title(): string { return (string) $this->def['title']; }
    public function singular(): string { return (string) ($this->def['singular'] ?? 'registro'); }
    public function fields(): array { return (array) $this->def['fields']; }
    public function canCreate(): bool { return (bool) ($this->def['create'] ?? true); }
    public function canDelete(): bool { return (bool) ($this->def['delete'] ?? true); }
    public function hint(): string { return (string) ($this->def['hint'] ?? ''); }

    /** @return list<array<string,mixed>> */
    public function rows(): array
    {
        $order = (string) ($this->def['order'] ?? 'id ASC');
        return Database::all('SELECT * FROM ' . Database::ident($this->table()) . ' ORDER BY ' . $order);
    }

    public function row(int $id): ?array
    {
        return Database::first('SELECT * FROM ' . Database::ident($this->table()) . ' WHERE id = :id', ['id' => $id]);
    }

    /** Lee y sanea los valores enviados por el formulario. */
    public function collect(?array $current = null): array
    {
        $data = [];
        foreach ($this->fields() as $name => $f) {
            $type = (string) ($f['type'] ?? 'text');

            if (($f['readonly'] ?? false) === true) {
                continue;
            }

            if ($type === 'checkbox') {
                $data[$name] = isset($_POST[$name]) ? 1 : 0;
                continue;
            }

            if ($type === 'media') {
                $value = post($name);
                $upload = $_FILES['upload_' . $name] ?? null;
                if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $res = Media::upload($upload);
                    if ($res['ok']) {
                        $value = (string) $res['path'];
                    } else {
                        flash($res['error'] ?? 'No se pudo subir la imagen.', 'error');
                    }
                }
                $data[$name] = $value;
                continue;
            }

            if ($type === 'number') {
                $data[$name] = (int) post($name, '0');
                continue;
            }

            $value = post($name);

            if ($type === 'slug') {
                $source = (string) ($f['from'] ?? '');
                if ($value === '' && $source !== '') {
                    $value = post($source);
                }
                $value = slugify($value);
                $value = $this->uniqueSlug($name, $value, $current['id'] ?? null);
            }

            if ($type === 'password') {
                if ($value === '') {
                    continue; // no cambiar la contrasena si viene vacia
                }
                $value = password_hash($value, PASSWORD_DEFAULT);
            }

            if (isset($f['max'])) {
                $value = mb_substr($value, 0, (int) $f['max']);
            }

            $data[$name] = $value;
        }
        return $data;
    }

    private function uniqueSlug(string $column, string $slug, mixed $id): string
    {
        $slug = $slug !== '' ? $slug : 'item';
        $base = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM ' . Database::ident($this->table()) . ' WHERE ' . Database::ident($column) . ' = :s';
            $params = ['s' => $slug];
            if ($id !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = (int) $id;
            }
            if ((int) Database::value($sql, $params, 0) === 0) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    public function validate(array $data): array
    {
        $errors = [];
        foreach ($this->fields() as $name => $f) {
            if (($f['required'] ?? false) === true && trim((string) ($data[$name] ?? '')) === '') {
                $errors[$name] = 'El campo «' . ($f['label'] ?? $name) . '» es obligatorio.';
            }
            if (($f['type'] ?? '') === 'email' && ($data[$name] ?? '') !== '' && !filter_var($data[$name], FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Escriba un correo electrónico válido.';
            }
        }
        return $errors;
    }

    public function save(array $data, ?int $id): int
    {
        if (isset($this->def['timestamps']) && $this->def['timestamps'] === true) {
            $data['updated_at'] = Database::now();
        }
        if ($id !== null && $id > 0) {
            Database::update($this->table(), $data, 'id = :id', ['id' => $id]);
            return $id;
        }
        if (in_array('created_at', array_keys($this->fields()), true) === false && $this->hasColumn('created_at')) {
            $data['created_at'] = Database::now();
        }
        return Database::insert($this->table(), $data);
    }

    public function hasColumn(string $name): bool
    {
        try {
            $row = Database::first('SELECT * FROM ' . Database::ident($this->table()) . ' LIMIT 1');
            return $row !== null && array_key_exists($name, $row);
        } catch (Throwable) {
            return false;
        }
    }

    public function remove(int $id): void
    {
        Database::delete($this->table(), 'id = :id', ['id' => $id]);
    }

    public function toggle(int $id): void
    {
        $row = $this->row($id);
        if ($row !== null && array_key_exists('status', $row)) {
            Database::update($this->table(), ['status' => (int) $row['status'] === 1 ? 0 : 1], 'id = :id', ['id' => $id]);
        }
    }

    /** Mueve un registro en el orden (delta -1 sube, +1 baja). */
    public function move(int $id, int $delta): void
    {
        if (!$this->hasColumn('sort_order')) {
            return;
        }
        $rows = $this->rows();
        $ids  = array_map(static fn($r) => (int) $r['id'], $rows);
        $pos  = array_search($id, $ids, true);
        if ($pos === false) {
            return;
        }
        $new = $pos + $delta;
        if ($new < 0 || $new >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$new]] = [$ids[$new], $ids[$pos]];
        foreach ($ids as $i => $rid) {
            Database::update($this->table(), ['sort_order' => $i + 1], 'id = :id', ['id' => $rid]);
        }
    }

    /** Columnas mostradas en el listado. */
    public function listColumns(): array
    {
        return (array) ($this->def['list'] ?? ['id' => 'ID']);
    }

    public function key(): string { return $this->key; }
}
