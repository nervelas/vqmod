<?php
declare(strict_types=1);

namespace App\Core;

/** Menú lateral del panel. Cada elemento se muestra solo si el rol tiene acceso al área. */
final class AdminNav
{
    /** @return array<int,array{title:string,items:array<int,array{path:string,label:string,icon:string,area:string,keywords?:string}>}> */
    public static function sections(): array
    {
        return [
            ['title' => 'Principal', 'items' => [
                ['path' => '/admin', 'label' => 'Inicio', 'icon' => 'home', 'area' => 'dashboard'],
                ['path' => '/admin/calendario', 'label' => 'Calendario', 'icon' => 'calendar', 'area' => 'calendar'],
                ['path' => '/admin/citas', 'label' => 'Citas', 'icon' => 'list', 'area' => 'bookings'],
                ['path' => '/admin/mensajes', 'label' => 'Mensajes de hoy', 'icon' => 'whatsapp', 'area' => 'messages', 'keywords' => 'whatsapp recordatorios enviar'],
                ['path' => '/admin/clientes', 'label' => 'Clientes', 'icon' => 'users', 'area' => 'clients', 'keywords' => 'crm fichas pacientes'],
            ]],
            ['title' => 'Agenda', 'items' => [
                ['path' => '/admin/eventos', 'label' => 'Tipos de evento', 'icon' => 'layers', 'area' => 'events', 'keywords' => 'servicios citas reuniones'],
                ['path' => '/admin/horarios', 'label' => 'Horarios', 'icon' => 'clock', 'area' => 'schedules', 'keywords' => 'disponibilidad'],
                ['path' => '/admin/ausencias', 'label' => 'Ausencias', 'icon' => 'sun', 'area' => 'schedules', 'keywords' => 'vacaciones permisos'],
                ['path' => '/admin/feriados', 'label' => 'Feriados', 'icon' => 'flag', 'area' => 'schedules', 'keywords' => 'guatemala festivos'],
                ['path' => '/admin/calendarios', 'label' => 'Calendarios externos', 'icon' => 'link', 'area' => 'calendars', 'keywords' => 'ics google outlook apple'],
                ['path' => '/admin/recursos', 'label' => 'Recursos y salas', 'icon' => 'building', 'area' => 'schedules'],
                ['path' => '/admin/encuestas', 'label' => 'Encuestas de horarios', 'icon' => 'check', 'area' => 'polls', 'keywords' => 'votar fechas'],
                ['path' => '/admin/espera', 'label' => 'Lista de espera', 'icon' => 'clock', 'area' => 'waitlist'],
            ]],
            ['title' => 'Equipo', 'items' => [
                ['path' => '/admin/anfitriones', 'label' => 'Anfitriones', 'icon' => 'user', 'area' => 'team', 'keywords' => 'profesionales'],
                ['path' => '/admin/equipos', 'label' => 'Equipos', 'icon' => 'users', 'area' => 'team'],
                ['path' => '/admin/usuarios', 'label' => 'Usuarios y roles', 'icon' => 'shield', 'area' => 'team', 'keywords' => 'invitar permisos'],
            ]],
            ['title' => 'Ventas', 'items' => [
                ['path' => '/admin/pagos', 'label' => 'Pagos', 'icon' => 'dollar', 'area' => 'payments', 'keywords' => 'cobros comprobantes'],
                ['path' => '/admin/paquetes', 'label' => 'Paquetes de sesiones', 'icon' => 'package', 'area' => 'payments'],
                ['path' => '/admin/cupones', 'label' => 'Cupones', 'icon' => 'tag', 'area' => 'payments', 'keywords' => 'descuentos'],
                ['path' => '/admin/certificados', 'label' => 'Certificados de regalo', 'icon' => 'gift', 'area' => 'payments'],
            ]],
            ['title' => 'Automatización', 'items' => [
                ['path' => '/admin/flujos', 'label' => 'Flujos y recordatorios', 'icon' => 'zap', 'area' => 'workflows', 'keywords' => 'automatizaciones plantillas'],
                ['path' => '/admin/enrutamiento', 'label' => 'Formularios de enrutamiento', 'icon' => 'route', 'area' => 'routing'],
                ['path' => '/admin/webhooks', 'label' => 'Webhooks', 'icon' => 'code', 'area' => 'api', 'keywords' => 'zapier make'],
                ['path' => '/admin/api', 'label' => 'API y claves', 'icon' => 'key', 'area' => 'api'],
            ]],
            ['title' => 'Crecimiento', 'items' => [
                ['path' => '/admin/analitica', 'label' => 'Analítica', 'icon' => 'chart', 'area' => 'analytics', 'keywords' => 'reportes embudo ingresos'],
                ['path' => '/admin/resenas', 'label' => 'Reseñas', 'icon' => 'star', 'area' => 'reviews'],
                ['path' => '/admin/insertar', 'label' => 'Insertar y compartir', 'icon' => 'qr', 'area' => 'embed', 'keywords' => 'qr wordpress iframe boton'],
            ]],
            ['title' => 'Sistema', 'items' => [
                ['path' => '/admin/ajustes', 'label' => 'Marca y ajustes', 'icon' => 'settings', 'area' => 'settings', 'keywords' => 'logo colores negocio'],
                ['path' => '/admin/legal', 'label' => 'Privacidad y términos', 'icon' => 'lock', 'area' => 'settings', 'keywords' => 'legal consentimiento retencion'],
                ['path' => '/admin/comunicaciones', 'label' => 'Correo y WhatsApp', 'icon' => 'mail', 'area' => 'settings', 'keywords' => 'smtp'],
                ['path' => '/admin/sistema', 'label' => 'Estado del sistema', 'icon' => 'info', 'area' => 'settings', 'keywords' => 'cron errores php'],
                ['path' => '/admin/actividad', 'label' => 'Actividad', 'icon' => 'bell', 'area' => 'settings', 'keywords' => 'auditoria bitacora'],
                ['path' => '/admin/respaldo', 'label' => 'Respaldo e importación', 'icon' => 'download', 'area' => 'settings', 'keywords' => 'backup json'],
                ['path' => '/admin/asistente', 'label' => 'Asistente de inicio', 'icon' => 'sparkle', 'area' => 'settings'],
            ]],
        ];
    }

    /** Elementos visibles para el rol actual. */
    public static function visible(): array
    {
        $out = [];
        foreach (self::sections() as $s) {
            $items = array_values(array_filter($s['items'], static fn (array $i): bool => Auth::can($i['area'])));
            if ($items) {
                $out[] = ['title' => $s['title'], 'items' => $items];
            }
        }
        return $out;
    }
}
