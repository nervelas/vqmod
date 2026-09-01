<?php
declare(strict_types=1);

/** Almacén de datos. Usa SQLite y, si no está disponible, archivos JSON. */
abstract class Store
{
    abstract public function driver(): string;

    // Ajustes ---------------------------------------------------------------
    abstract public function settingsAll(): array;
    abstract public function settingSet(string $key, string $value): void;

    // Historial -------------------------------------------------------------
    abstract public function imageInsert(array $row): string;
    abstract public function imageGet(string $id): ?array;
    abstract public function imageList(int $limit, int $offset = 0, string $search = ''): array;
    abstract public function imageCount(string $search = ''): int;
    abstract public function imageDelete(string $id): bool;
    abstract public function imagesByBatch(string $batchId): array;
    abstract public function imagesSince(int $timestamp): array;

    // Presets ---------------------------------------------------------------
    abstract public function presetInsert(array $row): string;
    abstract public function presetList(string $type = ''): array;
    abstract public function presetDelete(string $id): bool;

    // Uso y límites ---------------------------------------------------------
    abstract public function usageIncrement(string $provider): void;
    abstract public function usageByDay(int $days): array;
    abstract public function rateHit(string $key, int $windowSeconds): int;
    abstract public function rateCount(string $key, int $windowSeconds): int;
    abstract public function rateReset(string $key): void;
    abstract public function kvGet(string $key, string $default = ''): string;
    abstract public function kvSet(string $key, string $value): void;

    /** Elige el mejor motor disponible. */
    public static function make(): Store
    {
        $forced = getenv('PIXELFORGE_STORE');
        $sqliteOk = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
        if ($sqliteOk && $forced !== 'json') {
            try {
                return new StoreSqlite(Support::vaultDir() . '/pixelforge.sqlite');
            } catch (Throwable $e) {
                Logger::write('store', 'SQLite no utilizable, se cambia a JSON: ' . $e->getMessage());
            }
        }
        return new StoreJson(Support::vaultDir());
    }

    protected function nowIso(): string
    {
        return date('Y-m-d H:i:s');
    }
}
