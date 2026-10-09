<?php
declare(strict_types=1);
namespace S5\Provision;

/** Operaciones sobre un hosting (local o remoto vía agente). Todas idempotentes. */
interface HostDriver
{
    public function ping(): array;

    public function docroot(string $slug): string;

    public function subdomainCreate(string $slug, string $docroot): array;

    public function subdomainDelete(string $slug): void;

    public function subdomainExists(string $slug): bool;

    /** @return array{db:string,user:string,host:string} */
    public function dbCreate(string $shortDb, string $shortUser, string $pass): array;

    public function dbDelete(string $db, string $user): void;

    /** Copia por lotes el paquete base. @return array{done:bool,cursor:int,total:int} */
    public function copyBase(string $docroot, int $cursor, int $budgetSec = 12): array;

    public function writeFile(string $docroot, string $rel, string $content, int $mode = 0640): void;

    /** Prepara carpeta del trabajo: manifest, secreto y assets (locales o por URL firmada). */
    public function stageJob(string $docroot, string $jobId, string $manifestJson, array $assets, string $secret): void;

    /** Ejecuta un paso de sc-provision.php. @return array respuesta JSON del script */
    public function provision(string $docroot, string $jobId, string $step, array $args = []): array;

    public function deleteFile(string $docroot, string $rel): void;

    public function removeJob(string $docroot, string $jobId): void;

    public function removeProvisionScript(string $docroot): void;

    public function removeSite(string $docroot): void;

    public function attachDomain(string $domain, string $slug, string $docroot): void;

    public function detachDomain(string $domain, string $slug): void;

    public function siteExists(string $docroot): bool;
}
