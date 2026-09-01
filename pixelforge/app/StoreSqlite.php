<?php
declare(strict_types=1);

/** Implementación con SQLite (PDO). */
final class StoreSqlite extends Store
{
    private PDO $pdo;

    public function __construct(string $file)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $this->pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
        @chmod($file, 0640);
    }

    public function driver(): string
    {
        return 'sqlite';
    }

    private function migrate(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT "")');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS images (
            id TEXT PRIMARY KEY,
            created_at TEXT NOT NULL,
            created_ts INTEGER NOT NULL,
            batch_id TEXT NOT NULL DEFAULT "",
            prompt TEXT NOT NULL DEFAULT "",
            negative TEXT NOT NULL DEFAULT "",
            provider TEXT NOT NULL DEFAULT "",
            provider_label TEXT NOT NULL DEFAULT "",
            model TEXT NOT NULL DEFAULT "",
            width INTEGER NOT NULL DEFAULT 0,
            height INTEGER NOT NULL DEFAULT 0,
            source_width INTEGER NOT NULL DEFAULT 0,
            source_height INTEGER NOT NULL DEFAULT 0,
            seed INTEGER NOT NULL DEFAULT 0,
            format TEXT NOT NULL DEFAULT "png",
            realism INTEGER NOT NULL DEFAULT 0,
            file TEXT NOT NULL DEFAULT "",
            thumb TEXT NOT NULL DEFAULT "",
            bytes INTEGER NOT NULL DEFAULT 0,
            meta TEXT NOT NULL DEFAULT "{}")');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_images_ts ON images (created_ts DESC)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS presets (
            id TEXT PRIMARY KEY, type TEXT NOT NULL, name TEXT NOT NULL,
            data TEXT NOT NULL DEFAULT "{}", created_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS usage_daily (
            day TEXT NOT NULL, provider TEXT NOT NULL, hits INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (day, provider))');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS rate_hits (
            bucket TEXT NOT NULL, ts INTEGER NOT NULL)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate ON rate_hits (bucket, ts)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS kv (
            key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT "")');
    }

    public function settingsAll(): array
    {
        $out = [];
        foreach ($this->pdo->query('SELECT key, value FROM settings') as $row) {
            $out[(string) $row['key']] = (string) $row['value'];
        }
        return $out;
    }

    public function settingSet(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)
            ON CONFLICT(key) DO UPDATE SET value = :v2');
        $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    }

    public function imageInsert(array $row): string
    {
        $id = (string) ($row['id'] ?? Support::uuid());
        $stmt = $this->pdo->prepare('INSERT INTO images
            (id, created_at, created_ts, batch_id, prompt, negative, provider, provider_label, model,
             width, height, source_width, source_height, seed, format, realism, file, thumb, bytes, meta)
            VALUES (:id, :created_at, :created_ts, :batch_id, :prompt, :negative, :provider, :provider_label,
             :model, :width, :height, :source_width, :source_height, :seed, :format, :realism, :file, :thumb, :bytes, :meta)');
        $stmt->execute([
            ':id' => $id,
            ':created_at' => (string) ($row['created_at'] ?? $this->nowIso()),
            ':created_ts' => (int) ($row['created_ts'] ?? time()),
            ':batch_id' => (string) ($row['batch_id'] ?? ''),
            ':prompt' => (string) ($row['prompt'] ?? ''),
            ':negative' => (string) ($row['negative'] ?? ''),
            ':provider' => (string) ($row['provider'] ?? ''),
            ':provider_label' => (string) ($row['provider_label'] ?? ''),
            ':model' => (string) ($row['model'] ?? ''),
            ':width' => (int) ($row['width'] ?? 0),
            ':height' => (int) ($row['height'] ?? 0),
            ':source_width' => (int) ($row['source_width'] ?? 0),
            ':source_height' => (int) ($row['source_height'] ?? 0),
            ':seed' => (int) ($row['seed'] ?? 0),
            ':format' => (string) ($row['format'] ?? 'png'),
            ':realism' => (int) ($row['realism'] ?? 0),
            ':file' => (string) ($row['file'] ?? ''),
            ':thumb' => (string) ($row['thumb'] ?? ''),
            ':bytes' => (int) ($row['bytes'] ?? 0),
            ':meta' => json_encode($row['meta'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        return $id;
    }

    public function imageGet(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM images WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function imageList(int $limit, int $offset = 0, string $search = ''): array
    {
        if ($search !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM images WHERE prompt LIKE :q
                ORDER BY created_ts DESC, rowid DESC LIMIT :lim OFFSET :off');
            $stmt->bindValue(':q', '%' . $search . '%');
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM images ORDER BY created_ts DESC, rowid DESC LIMIT :lim OFFSET :off');
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function imageCount(string $search = ''): int
    {
        if ($search !== '') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM images WHERE prompt LIKE :q');
            $stmt->execute([':q' => '%' . $search . '%']);
        } else {
            $stmt = $this->pdo->query('SELECT COUNT(*) AS c FROM images');
        }
        $row = $stmt->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public function imageDelete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM images WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function imagesByBatch(string $batchId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM images WHERE batch_id = :b ORDER BY created_ts ASC, rowid ASC');
        $stmt->execute([':b' => $batchId]);
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function imagesSince(int $timestamp): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM images WHERE created_ts >= :t ORDER BY created_ts ASC, rowid ASC');
        $stmt->execute([':t' => $timestamp]);
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function presetInsert(array $row): string
    {
        $id = (string) ($row['id'] ?? Support::uuid());
        $stmt = $this->pdo->prepare('INSERT INTO presets (id, type, name, data, created_at)
            VALUES (:id, :type, :name, :data, :created_at)');
        $stmt->execute([
            ':id' => $id,
            ':type' => (string) ($row['type'] ?? 'prompt'),
            ':name' => (string) ($row['name'] ?? 'Sin nombre'),
            ':data' => json_encode($row['data'] ?? [], JSON_UNESCAPED_UNICODE),
            ':created_at' => $this->nowIso(),
        ]);
        return $id;
    }

    public function presetList(string $type = ''): array
    {
        if ($type !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM presets WHERE type = :t ORDER BY created_at DESC');
            $stmt->execute([':t' => $type]);
        } else {
            $stmt = $this->pdo->query('SELECT * FROM presets ORDER BY created_at DESC');
        }
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['data'] = json_decode((string) $row['data'], true) ?: [];
            $out[] = $row;
        }
        return $out;
    }

    public function presetDelete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM presets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function usageIncrement(string $provider): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO usage_daily (day, provider, hits) VALUES (:d, :p, 1)
            ON CONFLICT(day, provider) DO UPDATE SET hits = hits + 1');
        $stmt->execute([':d' => date('Y-m-d'), ':p' => $provider]);
    }

    public function usageByDay(int $days): array
    {
        $from = date('Y-m-d', time() - ($days * 86400));
        $stmt = $this->pdo->prepare('SELECT day, provider, hits FROM usage_daily WHERE day >= :f ORDER BY day DESC');
        $stmt->execute([':f' => $from]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['day']][(string) $row['provider']] = (int) $row['hits'];
        }
        return $out;
    }

    public function rateHit(string $key, int $windowSeconds): int
    {
        $this->pdo->prepare('DELETE FROM rate_hits WHERE ts < :t')->execute([':t' => time() - 86400]);
        $stmt = $this->pdo->prepare('INSERT INTO rate_hits (bucket, ts) VALUES (:b, :t)');
        $stmt->execute([':b' => $key, ':t' => time()]);
        return $this->rateCount($key, $windowSeconds);
    }

    public function rateCount(string $key, int $windowSeconds): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM rate_hits WHERE bucket = :b AND ts >= :t');
        $stmt->execute([':b' => $key, ':t' => time() - $windowSeconds]);
        $row = $stmt->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public function rateReset(string $key): void
    {
        $this->pdo->prepare('DELETE FROM rate_hits WHERE bucket = :b')->execute([':b' => $key]);
    }

    public function kvGet(string $key, string $default = ''): string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM kv WHERE key = :k');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : $default;
    }

    public function kvSet(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO kv (key, value) VALUES (:k, :v)
            ON CONFLICT(key) DO UPDATE SET value = :v2');
        $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    }

    private function hydrate(array $row): array
    {
        $row['meta'] = json_decode((string) ($row['meta'] ?? '{}'), true) ?: [];
        foreach (['width', 'height', 'source_width', 'source_height', 'seed', 'bytes', 'created_ts', 'realism'] as $k) {
            $row[$k] = (int) ($row[$k] ?? 0);
        }
        return $row;
    }
}
