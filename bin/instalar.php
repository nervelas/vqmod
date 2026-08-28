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
use Fel\Repositorio\EmpresaRepositorio;
use Fel\Repositorio\UsuarioRepositorio;

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

echo "\n4. Administrador de la plataforma\n";

$usuarios = new UsuarioRepositorio();

if ($usuarios->total() > 0) {
    echo "   Ya existen usuarios. Use bin/usuario.php para agregar otro.\n";
} else {
    $usuario = leer('   Usuario (ej. admin): ', 'admin');
    $nombre  = leer('   Nombre completo: ', 'Administrador');
    $clave   = leer('   Contrasena (minimo 10 caracteres): ', '');

    while (strlen($clave) < 10) {
        echo "   La contrasena debe tener al menos 10 caracteres.\n";
        $clave = leer('   Contrasena: ', '');
    }

    $usuarios->crear($usuario, $clave, $nombre, UsuarioRepositorio::SUPERADMIN);

    echo "   Superadministrador '{$usuario}' creado.\n";
    echo "   Con este usuario da de alta las empresas y sus usuarios.\n";
}

echo "\n5. Empresa inicial\n";

$empresas = new EmpresaRepositorio();

if ($empresas->listar() !== []) {
    echo "   Ya hay empresas registradas.\n";
} else {
    $emisor = (array) Config::get('emisor', []);

    if (($emisor['nit'] ?? '') !== '' && ($emisor['nombre'] ?? '') !== '') {
        // Instalacion que venia de la version de un solo emisor: se crea la
        // empresa a partir de config.php para no perder esa configuracion.
        $empresaId = $empresas->guardar([
            'nombre_interno'          => $emisor['nombre_comercial'] ?? $emisor['nombre'],
            'nit'                     => $emisor['nit'],
            'nombre_emisor'           => $emisor['nombre'],
            'nombre_comercial'        => $emisor['nombre_comercial'] ?? $emisor['nombre'],
            'afiliacion_iva'          => $emisor['afiliacion_iva'] ?? 'GEN',
            'codigo_establecimiento'  => $emisor['codigo_establecimiento'] ?? '1',
            'correo'                  => $emisor['correo'] ?? '',
            'telefono'                => $emisor['telefono'] ?? '',
            'direccion'               => $emisor['direccion'] ?? 'Ciudad',
            'codigo_postal'           => $emisor['codigo_postal'] ?? '01001',
            'municipio'               => $emisor['municipio'] ?? 'Guatemala',
            'departamento'            => $emisor['departamento'] ?? 'Guatemala',
            'pais'                    => $emisor['pais'] ?? 'GT',
            'ambiente'                => (string) Config::get('ambiente', 'pruebas'),
            'certificador_proveedor'  => (string) Config::get('certificador.proveedor', 'simulador'),
            'certificador_nombre'     => (string) Config::get('certificador.nombre_visible', ''),
            'certificador_nit'        => (string) Config::get('certificador.nit_visible', ''),
            'limite_consumidor_final' => (float) Config::get('reglas.limite_consumidor_final', 2500),
            'dias_maximos_anulacion'  => (int) Config::get('reglas.dias_maximos_anulacion', 30),
        ], null, (array) Config::get('certificador.infile', []));

        echo "   Empresa '", $emisor['nombre_comercial'] ?? $emisor['nombre'], "' creada desde config.php (id {$empresaId}).\n";
        echo "   Revise y complete sus credenciales desde la pantalla Empresas.\n";
    } else {
        echo "   Sin datos de emisor en config.php. Cree la primera empresa desde\n";
        echo "   la pantalla Empresas, ingresando como administrador de la plataforma.\n";
    }
}

echo "\n", str_repeat('=', 50), "\n";
echo "Instalacion completa.\n\n";
echo "Abra la aplicacion apuntando su dominio a la carpeta public/.\n";
echo "Ingrese como administrador de la plataforma y de alta a sus clientes\n";
echo "desde la pantalla Empresas.\n\n";
echo "ATENCION: mientras una empresa use el certificador SIMULADO, los\n";
echo "documentos que emita NO tienen validez fiscal.\n";

function leer(string $mensaje, string $porDefecto): string
{
    echo $mensaje;
    $linea = trim((string) fgets(STDIN));

    return $linea === '' ? $porDefecto : $linea;
}
