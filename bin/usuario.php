<?php
/**
 * Alta y cambio de contrasena de usuarios.
 *
 *   php bin/usuario.php crear  <usuario> "<nombre>" <clave> [superadmin|admin|operador] [empresa_id]
 *   php bin/usuario.php clave  <usuario> <clave>
 *   php bin/usuario.php listar
 *   php bin/usuario.php baja   <usuario>
 *
 * El rol 'superadmin' administra la plataforma y no pertenece a ninguna
 * empresa. 'admin' y 'operador' requieren el id de su empresa.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Core\Db;
use Fel\Repositorio\UsuarioRepositorio;

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

Config::cargar();
date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

$accion = $argv[1] ?? 'listar';
$pdo    = Db::conexion();

switch ($accion) {
    case 'crear':
        [$usuario, $nombre, $clave] = [$argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
        $rol       = $argv[5] ?? UsuarioRepositorio::OPERADOR;
        $empresaId = isset($argv[6]) ? (int) $argv[6] : null;

        if ($usuario === '' || $clave === '') {
            exit("Uso: php bin/usuario.php crear <usuario> \"<nombre>\" <clave> [superadmin|admin|operador] [empresa_id]\n");
        }
        if (strlen($clave) < 10) {
            exit("La contrasena debe tener al menos 10 caracteres.\n");
        }
        if ($rol !== UsuarioRepositorio::SUPERADMIN && ($empresaId === null || $empresaId <= 0)) {
            exit("Los usuarios que no son superadmin necesitan el id de su empresa.\n");
        }

        (new UsuarioRepositorio())->crear($usuario, $clave, $nombre, $rol, $empresaId);

        echo "Usuario '{$usuario}' creado con rol {$rol}.\n";
        break;

    case 'clave':
        [$usuario, $clave] = [$argv[2] ?? '', $argv[3] ?? ''];

        if ($usuario === '' || strlen($clave) < 10) {
            exit("Uso: php bin/usuario.php clave <usuario> <clave de 10+ caracteres>\n");
        }

        echo (new UsuarioRepositorio())->cambiarClave($usuario, $clave)
            ? "Contrasena actualizada.\n"
            : "No se encontro el usuario '{$usuario}'.\n";
        break;

    case 'baja':
        $sentencia = $pdo->prepare('UPDATE fel_usuarios SET activo = 0 WHERE usuario = :usuario');
        $sentencia->execute(['usuario' => $argv[2] ?? '']);
        echo "Usuario desactivado (si existia).\n";
        break;

    case 'listar':
    default:
        printf("%-4s %-18s %-26s %-24s %-11s %s\n", 'ID', 'USUARIO', 'NOMBRE', 'EMPRESA', 'ROL', 'ACTIVO');
        foreach ((new UsuarioRepositorio())->porEmpresa(null) as $fila) {
            printf(
                "%-4d %-18s %-26s %-24s %-11s %s\n",
                $fila['id'],
                $fila['usuario'],
                mb_substr((string) $fila['nombre'], 0, 26),
                mb_substr((string) ($fila['empresa'] ?? '— plataforma —'), 0, 24),
                $fila['rol'],
                $fila['activo'] ? 'si' : 'no'
            );
        }
}
