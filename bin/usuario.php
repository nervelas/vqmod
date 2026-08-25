<?php
/**
 * Alta y cambio de contrasena de usuarios.
 *
 *   php bin/usuario.php crear   <usuario> "<nombre>" <clave> [admin|operador]
 *   php bin/usuario.php clave   <usuario> <clave>
 *   php bin/usuario.php listar
 *   php bin/usuario.php baja    <usuario>
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Core\Db;

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
        $rol = $argv[5] ?? 'operador';

        if ($usuario === '' || $clave === '') {
            exit("Uso: php bin/usuario.php crear <usuario> \"<nombre>\" <clave> [admin|operador]\n");
        }
        if (strlen($clave) < 10) {
            exit("La contrasena debe tener al menos 10 caracteres.\n");
        }

        $sentencia = $pdo->prepare(
            'INSERT INTO fel_usuarios (usuario, clave_hash, nombre, rol, creado_en)
             VALUES (:usuario, :hash, :nombre, :rol, :creado_en)'
        );
        $sentencia->execute([
            'usuario'   => $usuario,
            'hash'      => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]),
            'nombre'    => $nombre !== '' ? $nombre : $usuario,
            'rol'       => in_array($rol, ['admin', 'operador'], true) ? $rol : 'operador',
            'creado_en' => date('Y-m-d H:i:s'),
        ]);

        echo "Usuario '{$usuario}' creado.\n";
        break;

    case 'clave':
        [$usuario, $clave] = [$argv[2] ?? '', $argv[3] ?? ''];

        if ($usuario === '' || strlen($clave) < 10) {
            exit("Uso: php bin/usuario.php clave <usuario> <clave de 10+ caracteres>\n");
        }

        $sentencia = $pdo->prepare('UPDATE fel_usuarios SET clave_hash = :hash WHERE usuario = :usuario');
        $sentencia->execute([
            'hash'    => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]),
            'usuario' => $usuario,
        ]);

        echo $sentencia->rowCount() > 0
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
        printf("%-4s %-20s %-30s %-10s %s\n", 'ID', 'USUARIO', 'NOMBRE', 'ROL', 'ACTIVO');
        foreach ($pdo->query('SELECT * FROM fel_usuarios ORDER BY id') as $fila) {
            printf(
                "%-4d %-20s %-30s %-10s %s\n",
                $fila['id'],
                $fila['usuario'],
                mb_substr((string) $fila['nombre'], 0, 30),
                $fila['rol'],
                $fila['activo'] ? 'si' : 'no'
            );
        }
}
