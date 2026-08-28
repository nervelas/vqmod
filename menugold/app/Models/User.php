<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Model;
use MenuGold\Core\Security;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'restaurant_id','nombre','email','usuario','password_hash','rol','telefono',
        'avatar','tema_panel','activo','onboarding','ultimo_acceso','ultima_ip',
    ];

    public function byEmail(string $email): ?array
    {
        return DB::one('SELECT * FROM users WHERE email = :e LIMIT 1', ['e' => mb_strtolower($email)]);
    }

    public function disponible(string $campo, string $valor, int $excluirId = 0): bool
    {
        $campo = DB::ident($campo);
        if ($valor === '') return true;
        return DB::int("SELECT COUNT(*) FROM users WHERE `{$campo}` = :v AND id <> :i",
            ['v' => $valor, 'i' => $excluirId]) === 0;
    }

    /** Usuario unico sugerido a partir de un nombre. */
    public function usuarioUnico(string $base): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', str_slug($base)) ?: 'usuario';
        $u = $base;
        $i = 1;
        while (!$this->disponible('usuario', $u)) { $u = $base . (++$i); }
        return $u;
    }

    public function setPassword(int $id, string $plain): void
    {
        DB::update('users', ['password_hash' => Security::hashPassword($plain)], 'id=:i', ['i' => $id]);
    }

    public static function etiquetaRol(string $rol): string
    {
        $m = [
            'superadmin' => 'Administrador de plataforma',
            'dueno'      => 'Dueño',
            'admin'      => 'Administrador',
            'cocina'     => 'Cocina',
            'mesero'     => 'Mesero / Caja',
        ];
        return $m[$rol] ?? $rol;
    }
}
