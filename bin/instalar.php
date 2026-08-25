<?php
/**
 * Instalador por linea de comandos.
 *
 *   php bin/instalar.php
 *
 * Crea las tablas y el primer usuario administrador. Es idempotente:
 * puede ejecutarse varias veces sin perder datos.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Core\Db;

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

echo "Instalador del sistema de facturacion FEL\n";
echo str_repeat('=', 50), "\n\n";

try {
    Config::cargar();
} catch (\RuntimeException $error) {
    echo "ERROR: ", $error->getMessage(), "\n";
    exit(1);
}

date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

$driver  = (string) Config::get('db.driver', 'mysql');
$archivo = dirname(__DIR__) . ($driver === 'sqlite' ? '/db/schema.sqlite.sql' : '/db/schema.sql');

echo "1. Conectando a la base de datos ({$driver})...\n";

try {
    $pdo = Db::conexion();
} catch (\Throwable $error) {
    echo "   ERROR: ", $error->getMessage(), "\n";
    echo "   Revise la seccion 'db' de config/config.php.\n";
    exit(1);
}

echo "   Conexion establecida.\n\n";
echo "2. Creando tablas...\n";

$sql = (string) file_get_contents($archivo);

foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
    if (str_starts_with($sentencia, '--') || $sentencia === '') {
        continue;
    }

    try {
        $pdo->exec($sentencia);
    } catch (\Throwable $error) {
        echo "   ERROR en: ", substr(preg_replace('/\s+/', ' ', $sentencia) ?? '', 0, 70), "...\n";
        echo "   ", $error->getMessage(), "\n";
        exit(1);
    }
}

echo "   Tablas listas.\n\n";
echo "3. Creando directorios de almacenamiento...\n";

foreach (['almacen', 'xml', 'logs'] as $clave) {
    $ruta = (string) Config::get('rutas.' . $clave, dirname(__DIR__) . '/storage');
    if (!is_dir($ruta) && !@mkdir($ruta, 0770, true) && !is_dir($ruta)) {
        echo "   AVISO: no se pudo crear {$ruta}. Creelo manualmente con permisos de escritura.\n";
        continue;
    }
    echo "   {$ruta}\n";
}

echo "\n4. Usuario administrador\n";

$existentes = (int) $pdo->query('SELECT COUNT(*) FROM fel_usuarios')->fetchColumn();

if ($existentes > 0) {
    echo "   Ya existen {$existentes} usuario(s). Use bin/usuario.php para agregar otro.\n";
} else {
    $usuario = leer('   Usuario (ej. admin): ', 'admin');
    $nombre  = leer('   Nombre completo: ', 'Administrador');
    $clave   = leer('   Contrasena (minimo 10 caracteres): ', '');

    while (strlen($clave) < 10) {
        echo "   La contrasena debe tener al menos 10 caracteres.\n";
        $clave = leer('   Contrasena: ', '');
    }

    $sentencia = $pdo->prepare(
        'INSERT INTO fel_usuarios (usuario, clave_hash, nombre, rol, creado_en)
         VALUES (:usuario, :hash, :nombre, :rol, :creado_en)'
    );
    $sentencia->execute([
        'usuario'   => $usuario,
        'hash'      => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]),
        'nombre'    => $nombre,
        'rol'       => 'admin',
        'creado_en' => date('Y-m-d H:i:s'),
    ]);

    echo "   Usuario '{$usuario}' creado.\n";
}

echo "\n", str_repeat('=', 50), "\n";
echo "Instalacion completa.\n\n";
echo "Certificador configurado: ", (string) Config::get('certificador.proveedor', 'simulador'), "\n";

if ((string) Config::get('certificador.proveedor', 'simulador') === 'simulador') {
    echo "\nATENCION: esta usando el certificador SIMULADO. Los documentos que\n";
    echo "emita NO tienen validez fiscal. Para facturar de verdad contrate un\n";
    echo "certificador autorizado por SAT y configurelo en config/config.php.\n";
}

echo "\nAbra la aplicacion apuntando su dominio a la carpeta public/.\n";

function leer(string $mensaje, string $porDefecto): string
{
    echo $mensaje;
    $linea = trim((string) fgets(STDIN));

    return $linea === '' ? $porDefecto : $linea;
}
