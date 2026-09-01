<?php
declare(strict_types=1);

/** Respaldo automático sin SQLite: un archivo JSON por colección. */
final class StoreJson extends Store
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
    }

    public function driver(): string
    {
        return 'json';
    }

    private function path(string $name): string
    {
        return $this->dir . '/' . $name . '.json';
    }

    private function read(string $name): array
    {
        $file = $this->path($name);
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function write(string $name, array $data): void
    {
        $file = $this->path($name);
        $tmp = $file . '.' . getmypid() . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            Logger::write('store', 'No se pudo serializar la colección ' . $name);
            return;
        }
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            Logger::write('store', 'No se pudo escribir ' . $file);
            return;
        }
        @rename($tmp, $file);
        @chmod($file, 0640);
    }

    /** Lectura-modificación-escritura protegida con bloqueo exclusivo. */
    private function mutate(string $name, callable $fn)
    {
        $lockFile = $this->path($name) . '.lock';
        $handle = @fopen($lockFile, 'c');
        if ($handle) {
            @flock($handle, LOCK_EX);
        }
        try {
            $data = $this->read($name);
            $result = $fn($data);
            $this->write($name, $data);
            return $result;
        } finally {
            if ($handle) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }
        }
    }

    public function settingsAll(): array
    {
        $out = [];
        foreach ($this->read('settings') as $k => $v) {
            $out[(string) $k] = (string) $v;
        }
        return $out;
    }

    public function settingSet(string $key, string $value): void
    {
        $this->mutate('settings', function (array &$data) use ($key, $value): void {
            $data[$key] = $value;
        });
    }

    public function imageInsert(array $row): string
    {
        $id = (string) ($row['id'] ?? Support::uuid());
        $record = [
            'id' => $id,
            'created_at' => (string) ($row['created_at'] ?? $this->nowIso()),
            'created_ts' => (int) ($row['created_ts'] ?? time()),
            'batch_id' => (string) ($row['batch_id'] ?? ''),
            'prompt' => (string) ($row['prompt'] ?? ''),
            'negative' => (string) ($row['negative'] ?? ''),
            'provider' => (string) ($row['provider'] ?? ''),
            'provider_label' => (string) ($row['provider_label'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'width' => (int) ($row['width'] ?? 0),
            'height' => (int) ($row['height'] ?? 0),
            'source_width' => (int) ($row['source_width'] ?? 0),
            'source_height' => (int) ($row['source_height'] ?? 0),
            'seed' => (int) ($row['seed'] ?? 0),
            'format' => (string) ($row['format'] ?? 'png'),
            'realism' => (int) ($row['realism'] ?? 0),
            'file' => (string) ($row['file'] ?? ''),
            'thumb' => (string) ($row['thumb'] ?? ''),
            'bytes' => (int) ($row['bytes'] ?? 0),
            'meta' => $row['meta'] ?? [],
        ];
        $this->mutate('images', function (array &$data) use ($record): void {
            array_unshift($data, $record);
            if (count($data) > 5000) {
                $data = array_slice($data, 0, 5000);
            }
        });
        return $id;
    }

    public function imageGet(string $id): ?array
    {
        foreach ($this->read('images') as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }
        return null;
    }

    private function sorted(): array
    {
        $rows = $this->read('images');
        usort($rows, static fn (array $a, array $b): int => (int) ($b['created_ts'] ?? 0) <=> (int) ($a['created_ts'] ?? 0));
        return $rows;
    }

    private function filtered(string $search): array
    {
        $rows = $this->sorted();
        if ($search === '') {
            return $rows;
        }
        $needle = mb_strtolower($search, 'UTF-8');
        return array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            return str_contains(mb_strtolower((string) ($row['prompt'] ?? ''), 'UTF-8'), $needle);
        }));
    }

    public function imageList(int $limit, int $offset = 0, string $search = ''): array
    {
        return array_slice($this->filtered($search), $offset, $limit);
    }

    public function imageCount(string $search = ''): int
    {
        return count($this->filtered($search));
    }

    public function imageDelete(string $id): bool
    {
        return (bool) $this->mutate('images', function (array &$data) use ($id): bool {
            $before = count($data);
            $data = array_values(array_filter($data, static fn (array $r): bool => (string) ($r['id'] ?? '') !== $id));
            return count($data) < $before;
        });
    }

    public function imagesByBatch(string $batchId): array
    {
        $rows = array_values(array_filter($this->read('images'), static fn (array $r): bool => (string) ($r['batch_id'] ?? '') === $batchId));
        usort($rows, static fn (array $a, array $b): int => (int) ($a['created_ts'] ?? 0) <=> (int) ($b['created_ts'] ?? 0));
        return $rows;
    }

    public function imagesSince(int $timestamp): array
    {
        $rows = array_values(array_filter($this->read('images'), static fn (array $r): bool => (int) ($r['created_ts'] ?? 0) >= $timestamp));
        usort($rows, static fn (array $a, array $b): int => (int) ($a['created_ts'] ?? 0) <=> (int) ($b['created_ts'] ?? 0));
        return $rows;
    }

    public function presetInsert(array $row): string
    {
        $id = (string) ($row['id'] ?? Support::uuid());
        $record = [
            'id' => $id,
            'type' => (string) ($row['type'] ?? 'prompt'),
            'name' => (string) ($row['name'] ?? 'Sin nombre'),
            'data' => $row['data'] ?? [],
            'created_at' => $this->nowIso(),
        ];
        $this->mutate('presets', function (array &$data) use ($record): void {
            array_unshift($data, $record);
        });
        return $id;
    }

    public function presetList(string $type = ''): array
    {
        $rows = $this->read('presets');
        if ($type !== '') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => (string) ($r['type'] ?? '') === $type));
        }
        return $rows;
    }

    public function presetDelete(string $id): bool
    {
        return (bool) $this->mutate('presets', function (array &$data) use ($id): bool {
            $before = count($data);
            $data = array_values(array_filter($data, static fn (array $r): bool => (string) ($r['id'] ?? '') !== $id));
            return count($data) < $before;
        });
    }

    public function usageIncrement(string $provider): void
    {
        $day = date('Y-m-d');
        $this->mutate('usage', function (array &$data) use ($day, $provider): void {
            if (!isset($data[$day]) || !is_array($data[$day])) {
                $data[$day] = [];
            }
            $data[$day][$provider] = (int) ($data[$day][$provider] ?? 0) + 1;
        });
    }

    public function usageByDay(int $days): array
    {
        $from = date('Y-m-d', time() - ($days * 86400));
        $out = [];
        foreach ($this->read('usage') as $day => $providers) {
            if ((string) $day >= $from && is_array($providers)) {
                $out[(string) $day] = array_map('intval', $providers);
            }
        }
        krsort($out);
        return $out;
    }

    public function rateHit(string $key, int $windowSeconds): int
    {
        return (int) $this->mutate('rate', function (array &$data) use ($key, $windowSeconds): int {
            $now = time();
            $hits = array_values(array_filter(
                array_map('intval', $data[$key] ?? []),
                static fn (int $ts): bool => $ts >= $now - 86400
            ));
            $hits[] = $now;
            $data[$key] = $hits;
            foreach ($data as $bucket => $list) {
                $keep = array_values(array_filter(array_map('intval', (array) $list), static fn (int $ts): bool => $ts >= $now - 86400));
                if ($keep) {
                    $data[$bucket] = $keep;
                } else {
                    unset($data[$bucket]);
                }
            }
            return count(array_filter($hits, static fn (int $ts): bool => $ts >= $now - $windowSeconds));
        });
    }

    public function rateCount(string $key, int $windowSeconds): int
    {
        $now = time();
        $hits = array_map('intval', $this->read('rate')[$key] ?? []);
        return count(array_filter($hits, static fn (int $ts): bool => $ts >= $now - $windowSeconds));
    }

    public function rateReset(string $key): void
    {
        $this->mutate('rate', function (array &$data) use ($key): void {
            unset($data[$key]);
        });
    }

    public function kvGet(string $key, string $default = ''): string
    {
        $data = $this->read('kv');
        return isset($data[$key]) ? (string) $data[$key] : $default;
    }

    public function kvSet(string $key, string $value): void
    {
        $this->mutate('kv', function (array &$data) use ($key, $value): void {
            $data[$key] = $value;
        });
    }
}
