<?php
declare(strict_types=1);
namespace S5\Provision;

/** Operaciones de cPanel que usa el sistema. Implementaciones: CpanelHttpApi (real) y SimCpanelApi (pruebas). */
interface CpanelApi
{
    /** Verifica credenciales. @return array{ok:bool,message:string,user?:string} */
    public function ping(): array;

    /** Crea subdominio $sub.$root con su propio docroot ABSOLUTO $dir. */
    public function addSubdomain(string $sub, string $root, string $dir): void;

    public function delSubdomain(string $sub, string $root): void;

    public function subdomainExists(string $sub, string $root): bool;

    /** Nombre real que cPanel dará (con prefijo de cuenta) a una BD/usuario cortos. */
    public function dbRealName(string $short): string;

    /** @return array{db:int,user:int} longitudes máximas (incluyendo el prefijo de la cuenta) */
    public function maxLengths(): array;

    public function createDatabase(string $short): string;

    public function createDbUser(string $short, string $pass): string;

    public function grantAll(string $realUser, string $realDb): void;

    public function deleteDatabase(string $realDb): void;

    public function deleteDbUser(string $realUser): void;

    public function databaseExists(string $realDb): bool;

    /** Conecta un dominio propio (.com) al docroot $dir. */
    public function addAddonDomain(string $domain, string $sub, string $dir): void;

    public function delAddonDomain(string $domain, string $sub, string $root): void;

    /** Host de MySQL a poner en wp-config. */
    public function dbHost(): string;
}
