<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';
$loader = new App\Core\Autoloader();
$loader->addNamespace('Vendor', BASE_PATH . '/vendor');
$loader->register();

use Vendor\Pdf\Pdf;

const NAVY = '#0B1F3A';
const GOLD = '#C9A961';
const TINTA = '#1B2430';

class Manual extends Pdf
{
    public bool $portada = false;

    protected function encabezado(): void
    {
        if ($this->paginaActual() <= 1) { return; }
        $this->setFuente('Times', 'B', 12);
        $this->setColorHex(NAVY, 'texto');
        $this->setXY(16, 11);
        $this->celda(100, 6, 'EduPortal', 0, 0, 'L');
        $this->setFuente('Helvetica', '', 8);
        $this->setColorTexto(130, 130, 130);
        $this->setXY($this->w - 116, 12);
        $this->celda(100, 5, 'Manual de usuario', 0, 0, 'R');
        $this->setColorHex(GOLD, 'trazo');
        $this->setGrosor(0.7);
        $this->linea(16, 19, $this->w - 16, 19);
        $this->setGrosor(0.2);
        $this->setColorTexto(0);
        $this->setXY(16, 26);
    }

    protected function pie(): void
    {
        if ($this->paginaActual() <= 1) { return; }
        $this->setY($this->h - 14, true);
        $this->setColorTrazo(228, 228, 228);
        $this->linea(16, $this->h - 15, $this->w - 16, $this->h - 15);
        $this->setFuente('Helvetica', '', 7.5);
        $this->setColorTexto(140, 140, 140);
        $this->setX(16);
        $this->celda(($this->w - 32) / 2, 6, 'EduPortal · Manual de usuario', 0, 0, 'L');
        $this->celda(($this->w - 32) / 2, 6, 'Página ' . $this->paginaActual(), 0, 0, 'R');
    }
}

$p = new Manual();
$p->setMargenes(16, 26, 16, 20);
$p->setMeta('EduPortal - Manual de usuario', 'EduPortal');

$W = $p->anchoUtil();

// ------------------------------------------------------------ helpers
function titulo(Manual $p, string $n, string $t): void
{
    $p->saltoSiNecesario(26);
    $p->ln(3);
    $y = $p->getY();
    $p->setColorHex(GOLD, 'relleno');
    $p->rect(16, $y, 3.2, 9, 'F');
    $p->setFuente('Times', 'B', 15);
    $p->setColorHex(NAVY, 'texto');
    $p->setXY(21.5, $y - 0.6);
    $p->celda(0, 10, $n . '. ' . $t, 0, 1, 'L');
    $p->setColorTexto(0);
    $p->ln(1.5);
    $p->setX(16);
}

function sub(Manual $p, string $t): void
{
    $p->saltoSiNecesario(16);
    $p->ln(2);
    $p->setFuente('Helvetica', 'B', 10);
    $p->setColorHex(NAVY, 'texto');
    $p->setX(16);
    $p->celda(0, 5.5, $t, 0, 1, 'L');
    $p->setColorTexto(0);
    $p->ln(0.8);
    $p->setX(16);
}

function parrafo(Manual $p, string $t, float $tam = 9.3): void
{
    $p->setFuente('Helvetica', '', $tam);
    $p->setColorHex(TINTA, 'texto');
    $p->setX(16);
    $p->multiCelda($p->anchoUtil(), 4.9, $t);
    $p->setColorTexto(0);
    $p->ln(1);
    $p->setX(16);
}

function vinetas(Manual $p, array $items): void
{
    foreach ($items as $it) {
        $p->saltoSiNecesario(8);
        $p->setFuente('Helvetica', 'B', 9.3);
        $p->setColorHex(GOLD, 'texto');
        $p->setX(18);
        $p->celda(4, 4.9, '•', 0, 0, 'L');
        $p->setFuente('Helvetica', '', 9.3);
        $p->setColorHex(TINTA, 'texto');
        $y = $p->getY();
        $p->setXY(22, $y);
        $p->multiCelda($p->anchoUtil() - 6, 4.9, $it);
        $p->setX(16);
    }
    $p->setColorTexto(0);
    $p->ln(1.2);
    $p->setX(16);
}

function pasos(Manual $p, array $items): void
{
    $n = 1;
    foreach ($items as $it) {
        $p->saltoSiNecesario(9);
        $y = $p->getY();
        $p->setColorHex(NAVY, 'relleno');
        $p->rectRedondeado(17, $y + 0.4, 5.2, 5.2, 1.2, 'F');
        $p->setFuente('Helvetica', 'B', 7.5);
        $p->setColorTexto(255, 255, 255);
        $p->setXY(17, $y + 0.5);
        $p->celda(5.2, 5, (string)$n, 0, 0, 'C');
        $p->setFuente('Helvetica', '', 9.3);
        $p->setColorHex(TINTA, 'texto');
        $p->setXY(25, $y);
        $p->multiCelda($p->anchoUtil() - 9, 5.2, $it);
        $p->setX(16);
        $n++;
    }
    $p->setColorTexto(0);
    $p->ln(1.2);
    $p->setX(16);
}

/** Tabla con encabezado navy y filas alternas. */
function tabla(Manual $p, array $cols, array $filas, array $anchos, array $alin = [], bool $mono = false): void
{
    $p->saltoSiNecesario(18);
    $p->setX(16);
    $p->setColorHex(NAVY, 'relleno');
    $p->setColorTexto(255, 255, 255);
    $p->setFuente('Helvetica', 'B', 8.4);
    foreach ($cols as $i => $c) {
        $p->celda($anchos[$i], 7, ' ' . $c, 0, 0, $alin[$i] ?? 'L', true);
    }
    $p->ln(7);
    $p->setX(16);

    $alterna = false;
    foreach ($filas as $f) {
        $p->saltoSiNecesario(9);
        if ($p->getX() < 16) { $p->setX(16); }
        $alto = 6.2;
        if ($alterna) {
            $p->setColorHex('#F4F2EC', 'relleno');
        } else {
            $p->setColorTexto(255, 255, 255);
            $p->setColorRelleno(255, 255, 255);
        }
        $p->setColorHex(TINTA, 'texto');
        foreach ($f as $i => $celda) {
            $bold = str_starts_with((string)$celda, '*');
            $txt = $bold ? substr((string)$celda, 1) : (string)$celda;
            $fam = ($mono && $i > 0) ? 'Courier' : 'Helvetica';
            $p->setFuente($fam, $bold ? 'B' : '', $mono && $i > 0 ? 8.2 : 8.6);
            $p->celda($anchos[$i], $alto, ' ' . $txt, 0, 0, $alin[$i] ?? 'L', true);
        }
        $p->ln($alto);
        $p->setX(16);
        $alterna = !$alterna;
    }
    $p->setColorTexto(0);
    $p->setColorRelleno(255, 255, 255);
    $p->ln(2);
    $p->setX(16);
}

function caja(Manual $p, string $tit, string $texto, string $color = GOLD, string $fondo = '#FBF7EC'): void
{
    $p->setFuente('Helvetica', '', 9);
    $lineas = $p->dividirTexto($texto, $p->anchoUtil() - 14);
    $alto = 9.5 + count($lineas) * 4.6;
    $p->saltoSiNecesario($alto + 4);
    $y = $p->getY();
    $p->setColorHex($fondo, 'relleno');
    $p->rectRedondeado(16, $y, $p->anchoUtil(), $alto, 2.2, 'F');
    $p->setColorHex($color, 'relleno');
    $p->rect(16, $y, 2.6, $alto, 'F');
    $p->setFuente('Helvetica', 'B', 9);
    $p->setColorHex($color === GOLD ? '#7A5310' : $color, 'texto');
    $p->setXY(22, $y + 2.2);
    $p->celda(0, 5, $tit, 0, 1, 'L');
    $p->setFuente('Helvetica', '', 9);
    $p->setColorHex(TINTA, 'texto');
    $p->setXY(22, $y + 7.4);
    $p->multiCelda($p->anchoUtil() - 14, 4.6, $texto);
    $p->setXY(16, $y + $alto + 3);
    $p->setColorTexto(0);
}

// ------------------------------------------------------------ PORTADA
$p->portada = true;
$p->agregarPagina();
$p->setColorHex(NAVY, 'relleno');
$p->rect(0, 0, $p->ancho(), 118, 'F');
$p->setColorHex(GOLD, 'relleno');
$p->rect(0, 118, $p->ancho(), 2.2, 'F');

$p->setFuente('Helvetica', '', 9.5);
$p->setColorHex(GOLD, 'texto');
$p->setXY(24, 34);
$p->celda(0, 6, 'SISTEMA INTEGRAL DE GESTIÓN ESCOLAR', 0, 1, 'L');

$p->setFuente('Times', 'B', 40);
$p->setColorTexto(255, 255, 255);
$p->setXY(24, 46);
$p->celda(0, 18, 'EduPortal', 0, 1, 'L');

$p->setFuente('Times', '', 19);
$p->setColorHex('#C8D2E0', 'texto');
$p->setXY(24, 68);
$p->celda(0, 10, 'Manual de usuario', 0, 1, 'L');

$p->setFuente('Helvetica', '', 9.5);
$p->setColorHex('#8FA0B6', 'texto');
$p->setXY(24, 86);
$p->multiCelda(120, 5, "Enlaces de acceso, usuarios y contraseñas,\nque hace cada rol y las tareas del día a día.");

$p->setColorTexto(0);
$p->setXY(24, 132);
$p->setFuente('Helvetica', 'B', 10);
$p->setColorHex(NAVY, 'texto');
$p->celda(0, 6, 'Contenido de este manual', 0, 1, 'L');
$p->ln(2);
$idx = [
    '1  Enlaces de acceso',
    '2  Usuarios y contraseñas',
    '3  Qué hace cada rol',
    '4  Primeros pasos del administrador',
    '5  Cobros mensuales, paso a paso',
    '6  Notas y asistencia (docentes)',
    '7  El portal de los padres',
    '8  Avisos, tareas y mensajes',
    '9  Tareas automáticas y respaldos',
    '10 Seguridad de las cuentas',
];
$p->setFuente('Helvetica', '', 9.5);
foreach ($idx as $i => $linea) {
    $y = 141 + $i * 6.2;
    $p->setColorHex(GOLD, 'texto');
    $p->setXY(24, $y);
    $p->setFuente('Helvetica', 'B', 9.5);
    $p->celda(9, 5.5, substr($linea, 0, 2), 0, 0, 'L');
    $p->setFuente('Helvetica', '', 9.5);
    $p->setColorHex(TINTA, 'texto');
    $p->celda(0, 5.5, trim(substr($linea, 2)), 0, 0, 'L');
}

$p->setColorHex('#E6E2D8', 'trazo');
$p->linea(24, 212, $p->ancho() - 24, 212);
$p->setFuente('Helvetica', '', 8.5);
$p->setColorTexto(140, 140, 140);
$p->setXY(24, 216);
$p->multiCelda(150, 4.8, "Guarde este documento junto con las credenciales del colegio.\nGenerado el " . date('d/m/Y') . '.');

// ------------------------------------------------------------ 1. ENLACES
$p->portada = false;
$p->agregarPagina();

titulo($p, '1', 'Enlaces de acceso');
parrafo($p, 'Sustituya SUDOMINIO por el dominio real del colegio. El sistema reconoce solo quien entra y lo envía automáticamente al lugar que le corresponde: administración, secretaria y docentes al panel; padres y encargados al portal.');
tabla(
    $p,
    ['Para que sirve', 'Dirección'],
    [
        ['*Ingresar (pantalla de acceso)', 'https://SUDOMINIO/ingresar'],
        ['Sitio público del colegio', 'https://SUDOMINIO/'],
        ['Panel de administración', 'https://SUDOMINIO/panel'],
        ['Portal de padres y encargados', 'https://SUDOMINIO/portal'],
        ['Recuperar contraseña', 'https://SUDOMINIO/recuperar'],
        ['Cerrar sesión', 'https://SUDOMINIO/salir'],
        ['Pre-inscripción en linea (público)', 'https://SUDOMINIO/preinscripcion'],
        ['Instalador (solo la primera vez)', 'https://SUDOMINIO/install/'],
    ],
    [78, $W - 78],
    ['L', 'L'],
    true
);
caja($p, 'Instálelo como aplicación', 'EduPortal funciona como app en el celular. Abra el sitio en Chrome (Android) o Safari (iPhone) y use la opción del navegador "Agregar a la pantalla de inicio". Queda con icono propio, a pantalla completa y sigue mostrando la información ya cargada aunque se caiga el internet.');

// ------------------------------------------------------------ 2. USUARIOS
titulo($p, '2', 'Usuarios y contraseñas');
caja($p, 'Antes de instalar no existe ningún usuario', 'El primer usuario (administrador) lo crea usted en el paso 3 del instalador /install/, escribiendo su nombre, su correo y su contraseña. Las cuentas de abajo solo existen si importa los datos de demostración (database_demo.sql).', '#0F6E8C', '#EAF4F8');

sub($p, 'Cuentas principales de demostración');
tabla(
    $p,
    ['Rol', 'Correo', 'Contraseña'],
    [
        ['*Administrador', 'admin@colegio.gt', 'Admin2026!'],
        ['*Secretaria', 'secretaria@colegio.gt', 'Secre2026!'],
        ['*Docente', 'docente@colegio.gt', 'Docente2026!'],
        ['*Padre / Encargado', 'padre@colegio.gt', 'Padre2026!'],
    ],
    [40, 88, $W - 128],
    ['L', 'L', 'L'],
    true
);

sub($p, 'Las demás cuentas de demostración');
tabla(
    $p,
    ['Rol', 'Correo', 'Contraseña'],
    [
        ['Docente', 'lucia.herrera@colegio.gt', 'Docente2026!'],
        ['Docente', 'marco.solis@colegio.gt', 'Docente2026!'],
        ['Encargados (24)', 'encargado2@correo.gt ... encargado25@correo.gt', 'Padre2026!'],
    ],
    [40, 88, $W - 128],
    ['L', 'L', 'L'],
    true
);
parrafo($p, 'En total son 30 usuarios: 1 administrador, 1 secretaria, 3 docentes y 25 encargados. Los primeros cinco encargados tienen dos hijos cada uno, para que pueda probar el cambio de hijo dentro del portal.');
caja($p, 'Cambie estas contraseñas', 'Las contraseñas de demostración son publicas: vienen dentro del paquete de instalación. Si deja los datos de ejemplo en un sitio en linea, cambielas de inmediato o desactive esas cuentas.', '#B3261E', '#FBEDEC');

sub($p, 'Como se crean los usuarios reales');
vinetas($p, [
    'Personal (secretaria, otros administradores): entre como administrador y vaya a Configuración > Usuarios y accesos > Nuevo. Escriba nombre, correo, rol y una contraseña inicial.',
    'Docentes: cree el usuario con rol Docente y luego asígnele materias en Académico > Asignaciones. Sin asignaciones no verá ningún grupo.',
    'Padres y encargados: se crean solos. Al registrar al encargado en la ficha del alumno, escriba su correo y marque "Crear acceso al portal": el sistema genera la cuenta y le envía sus datos por correo.',
]);

// ------------------------------------------------------------ 3. ROLES
titulo($p, '3', 'Qué hace cada rol');
parrafo($p, 'El sistema tiene cuatro tipos de usuario. Cada uno ve únicamente lo que le corresponde, y esa restricción se verifica en el servidor: no basta con cambiar la dirección en el navegador para ver información ajena.');

$roles = [
    ['Administrador', '#7A3E9D', 'Acceso total.', 'Configura el colegio (nombre, logo, tema, NIT, ciclo, correo saliente), define la estructura académica, gestiona alumnos, cobros, notas, usuarios, respaldos y bitácora.'],
    ['Secretaria / Contabilidad', '#0F6E8C', 'Todo el dinero y los expedientes.', 'Alumnos y encargados, conceptos de cobro, cargos mensuales, pagos y recibos, estados de cuenta, morosidad, corte de caja, aprobación de comprobantes, avisos y pre-inscripciones. No tiene acceso a las notas.'],
    ['Docente', '#1B6B4A', 'Solo sus grupos y materias.', 'Captura de notas con guardado automático, actividades con ponderación, zona y examen, conducta, pase de asistencia, tareas, avisos y mensajes con encargados. No ve información de cobros.'],
    ['Padre / Encargado', '#A8621F', 'Solo sus propios hijos.', 'Estado de cuenta, subir comprobante de pago, descargar recibos y boletas, asistencia, tareas, avisos y mensajes con los docentes. Si tiene varios hijos, cambia entre ellos con un selector.'],
];
foreach ($roles as $r) {
    $p->setFuente('Helvetica', '', 9);
    $lin = $p->dividirTexto($r[3], $W - 14);
    $alto = 15 + count($lin) * 4.6;
    $p->saltoSiNecesario($alto + 4);
    $y = $p->getY();
    $p->setColorHex('#FAFAFA', 'relleno');
    $p->rectRedondeado(16, $y, $W, $alto, 2.4, 'F');
    $p->setColorHex($r[1], 'relleno');
    $p->rect(16, $y, 2.6, $alto, 'F');
    $p->setFuente('Helvetica', 'B', 10);
    $p->setColorHex($r[1], 'texto');
    $p->setXY(22, $y + 2.4);
    $p->celda(70, 5, $r[0], 0, 0, 'L');
    $p->setFuente('Helvetica', 'I', 8.6);
    $p->setColorTexto(120, 120, 120);
    $p->celda($W - 78, 5, $r[2], 0, 1, 'R');
    $p->setFuente('Helvetica', '', 9);
    $p->setColorHex(TINTA, 'texto');
    $p->setXY(22, $y + 9.4);
    $p->multiCelda($W - 14, 4.6, $r[3]);
    $p->setXY(16, $y + $alto + 3.5);
    $p->setColorTexto(0);
}

sub($p, 'Quién puede entrar a que');
tabla(
    $p,
    ['Modulo', 'Admin', 'Secre', 'Docente', 'Padre'],
    [
        ['Configuración del colegio', 'Si', '-', '-', '-'],
        ['Estructura académica', 'Si', '-', '-', '-'],
        ['Alumnos y encargados', 'Si', 'Si', 'Ver', '-'],
        ['Cobros, pagos y recibos', 'Si', 'Si', '-', 'Solo sus hijos'],
        ['Aprobar comprobantes', 'Si', 'Si', '-', '-'],
        ['Notas y boletas', 'Si', '-', 'Sus grupos', 'Solo sus hijos'],
        ['Asistencia', 'Si', 'Ver', 'Sus grupos', 'Solo sus hijos'],
        ['Tareas', 'Si', '-', 'Sus grupos', 'Solo sus hijos'],
        ['Avisos y calendario', 'Si', 'Si', 'Si', 'Ver'],
        ['Mensajes', 'Si', 'Si', 'Si', 'Si'],
        ['Reportes y graficas', 'Si', 'Si', '-', '-'],
        ['Usuarios, respaldos, bitácora', 'Si', '-', '-', '-'],
    ],
    [66, 22, 22, 30, $W - 140],
    ['L', 'C', 'C', 'C', 'C']
);

// ------------------------------------------------------------ 4. ADMIN
titulo($p, '4', 'Primeros pasos del administrador');
parrafo($p, 'Haga esto en orden la primera vez. Cada paso depende del anterior.');
pasos($p, [
    'Configuración > Colegio: nombre, logo, favicon, NIT, dirección, teléfono, tema de color y texto del recibo. El logo genera automáticamente los iconos de la aplicación.',
    'Configuración > Correo (SMTP): servidor, puerto, usuario y contraseña de su correo. Sin esto no salen recibos ni recordatorios. Use el boton de prueba.',
    'Académico: cree el ciclo escolar, los niveles, los grados, las secciones y las materias. Luego cree los periodos (bimestres o trimestres) y revise la escala de notas.',
    'Académico > Asignaciones: relacione cada docente con las materias y secciones que imparte.',
    'Alumnos: registre a los alumnos uno por uno o con la importación masiva desde Excel/CSV (descargue primero la plantilla).',
    'En cada ficha de alumno agregue hasta tres encargados. Marque "Crear acceso al portal" para los que deben entrar al sistema.',
    'Cobros > Conceptos: cree la colegiatura mensual y los demás conceptos (inscripción, transporte, laboratorio) con su monto y su día de vencimiento.',
    'Cobros > Generar: genere los cargos del mes o de un rango de meses. Repita esto cada mes o deje configurado el rango del ciclo completo.',
    'Configuración > Usuarios y accesos: cree las cuentas de la secretaria y de los docentes.',
    'Configuración > Tareas automáticas: copie el enlace del cron y péguelo en el cron de cPanel cada 15 minutos.',
]);

// ------------------------------------------------------------ 5. COBROS
titulo($p, '5', 'Cobros mensuales, paso a paso');
parrafo($p, 'La colegiatura es mensual. El sistema crea el cargo de cada alumno, calcula la mora si se vence, recibe el pago y emite el recibo numerado.');

sub($p, 'Generar los cargos del mes');
vinetas($p, [
    'Cobros > Generar: elija el mes, o un mes desde y un mes hasta para generar todo el ciclo de una vez.',
    'El sistema no duplica: si un alumno ya tiene el cargo de ese mes, lo omite y se lo informa.',
    'Solo se generan cargos a alumnos activos en el ciclo vigente.',
]);

sub($p, 'Registrar un pago en caja');
pasos($p, [
    'Cobros > Pagos > Nuevo pago. Busque al alumno por nombre o código.',
    'Marque los cargos que está cubriendo. Puede pagar varios meses o pagar solo una parte.',
    'Elija la forma de pago: efectivo, transferencia, tarjeta o depósito, y anote la referencia.',
    'Guarde. El sistema emite el recibo con su número correlativo y lo envía por correo al encargado.',
]);

sub($p, 'Cuando el padre sube su comprobante');
pasos($p, [
    'El encargado entra al portal, elige los cargos que paga y sube la foto o el PDF del depósito.',
    'El pago queda "En revisión". Todavia no descuenta el saldo.',
    'La secretaria lo ve en Cobros > Por revisar, abre el comprobante y lo aprueba o lo rechaza indicando el motivo.',
    'Al aprobarlo se aplica al saldo y sale el recibo. Si el saldo cambió desde que el padre lo envio, el sistema aplica solo lo que corresponde y se lo avisa.',
]);

sub($p, 'Reportes de cobranza');
vinetas($p, [
    'Estado de cuenta por alumno o por familia, con saldo y detalle mes a mes.',
    'Reporte de morosidad: quien debe, cuánto y desde cuando.',
    'Proyección de ingresos del ciclo y corte de caja del día.',
    'Todo se exporta a PDF y a Excel.',
]);

// ------------------------------------------------------------ 6. NOTAS
titulo($p, '6', 'Notas y asistencia (docentes)');

sub($p, 'Cargar notas');
vinetas($p, [
    'Notas > elija su grupo, su materia y el periodo. Aparece una hoja como Excel con sus alumnos.',
    'Escriba y muévase con las flechas o con Tab. Se guarda solo, no hay boton de guardar.',
    'Las notas fuera del rango permitido se marcan en rojo y no se guardan.',
    'La nota se arma con zona (60) más examen (40). El sistema calcula el total y avisa quien no llega a 60.',
    'Cuando termine, cierre el periodo: eso bloquea la edición y habilita las boletas.',
]);

sub($p, 'Pasar asistencia');
vinetas($p, [
    'Asistencia > su grupo. Todos aparecen presentes: marque solo a los ausentes y guarde. Son dos toques desde el celular.',
    'Puede marcar ausencia justificada y escribir el motivo.',
    'A la tercera ausencia sin justificar el sistema avisa automáticamente al encargado.',
    'El reporte mensual de asistencia se descarga en PDF o Excel.',
]);

// ------------------------------------------------------------ 7. PORTAL
titulo($p, '7', 'El portal de los padres');
parrafo($p, 'El encargado entra en https://SUDOMINIO/ingresar con su correo y va directo al portal. Si tiene más de un hijo, cambia de hijo con el selector de la parte superior.');
vinetas($p, [
    'Estado de cuenta: cuánto debe, de que meses y desde cuando; con el detalle de cada cargo.',
    'Pagar: elige los cargos y sube el comprobante. Si el colegio configuro un enlace de pago en linea, aparece el boton; si no, no se muestra nada.',
    'Recibos: descarga en PDF todos los recibos de pagos ya aplicados.',
    'Calificaciones: boleta del periodo con la nota de cada materia y los comentarios del docente.',
    'Asistencia: días presentes, ausentes y justificados del mes.',
    'Tareas: que se dejo, para cuando y si ya fue entregada.',
    'Avisos y mensajes: circulares del colegio y conversación directa con los docentes.',
]);

// ------------------------------------------------------------ 8. COMUNICACION
titulo($p, '8', 'Avisos, tareas y mensajes');
vinetas($p, [
    'Avisos: escriba el aviso, adjunte archivos y elija a quien va (todo el colegio, un nivel, un grado, una seccion o un alumno). Puede programar la fecha de publicación y la de vencimiento, y ver quien ya lo leyo.',
    'Calendario: las actividades que publique aparecen también en el sitio público del colegio.',
    'Tareas: el docente publica la tarea con su fecha de entrega; el encargado ve si ya fue entregada.',
    'Mensajes: conversación directa entre docente y encargado, dentro del sistema.',
    'Todo aviso llega además como notificación en el celular, por correo, y con boton para enviarlo por WhatsApp.',
]);

// ------------------------------------------------------------ 9. CRON
titulo($p, '9', 'Tareas automáticas y respaldos');
parrafo($p, 'En cPanel > Trabajos cron agregue esta línea cada 15 minutos. El token se genera solo durante la instalación y lo encuentra en Configuración > Tareas automáticas.');
$p->setFuente('Courier', '', 8.4);
$y = $p->getY();
$p->setColorHex('#F2F4F7', 'relleno');
$p->rectRedondeado(16, $y, $W, 9, 2, 'F');
$p->setColorHex(NAVY, 'texto');
$p->setXY(20, $y + 1.6);
$p->celda($W - 8, 6, '*/15 * * * * curl -s "https://SUDOMINIO/cron/run.php?token=SU_TOKEN"', 0, 1, 'L');
$p->setXY(16, $y + 13);
$p->setColorTexto(0);
parrafo($p, 'Sin este cron el sistema funciona, pero nadie recibe recordatorios y no se calcula la mora. Cada 15 minutos el sistema:');
vinetas($p, [
    'Calcula la mora de los cargos vencidos y no pagados.',
    'Envia recordatorios de pago: 3 días antes del vencimiento, el día del vencimiento y cada 7 días mientras siga en mora.',
    'Depura intentos de acceso, enlaces de recuperación vencidos y notificaciones antiguas.',
    'Genera el respaldo automático de la base de datos los domingos.',
]);
parrafo($p, 'Ademas, en Configuración > Respaldos puede descargar en cualquier momento un respaldo completo (.sql.gz) con un clic. Guarde una copia fuera del servidor.');

// ------------------------------------------------------------ 10. SEGURIDAD
titulo($p, '10', 'Seguridad de las cuentas');
tabla(
    $p,
    ['Regla', 'Como funciona'],
    [
        ['Contraseñas', 'Mínimo 10 caracteres. Se guardan cifradas; nadie puede verlas, ni usted.'],
        ['Intentos fallidos', '5 intentos equivocados bloquean el acceso 15 minutos.'],
        ['Sesión inactiva', 'Se cierra sola a los 30 minutos sin actividad.'],
        ['Recuperar contraseña', 'Enlace de un solo uso que vence en 30 minutos.'],
        ['Verificación en dos pasos', 'Opcional, por correo, para la cuenta de administrador.'],
        ['Cerrar en todos lados', 'Boton en su perfil: cierra la sesión en cualquier dispositivo olvidado.'],
        ['Bitácora', 'Cada accion importante queda registrada con usuario, fecha y dirección IP.'],
        ['Dar de baja', 'Nunca borre un usuario: desactívelo. Asi conserva su historial de pagos y notas.'],
    ],
    [46, $W - 46],
    ['L', 'L']
);
caja($p, 'Si olvida la contraseña del administrador', 'Use https://SUDOMINIO/recuperar con el correo del administrador. Si tampoco tiene acceso a ese correo, la contraseña se puede restablecer desde la base de datos en phpMyAdmin; pida apoyo técnico antes de tocar la tabla de usuarios.');

$ruta = BASE_PATH . '/Manual-EduPortal.pdf';
file_put_contents($ruta, $p->salida());
echo 'OK ', $ruta, ' ', number_format(filesize($ruta) / 1024, 1), " KB, {$p->paginaActual()} paginas\n";
