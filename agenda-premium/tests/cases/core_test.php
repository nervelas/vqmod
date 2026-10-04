<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('core');

use App\Core\Db;
use App\Core\Settings;
use App\Core\Tz;
use App\Core\Str;
use App\Core\Totp;
use App\Services\HolidayService;

T::section('Base');
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM users'), 'se creó el administrador');
T::ok((int) Db::val('SELECT COUNT(*) FROM migrations') >= 1, 'migraciones registradas');
T::eq('Negocio de Prueba', Settings::get('business_name'), 'ajustes guardados');

T::section('Zonas horarias');
T::eq('2026-03-08 14:00:00', Tz::localToUtc('2026-03-08 10:00', 'America/New_York'), 'NY después del salto de verano (UTC-4)');
T::eq('2026-03-07 15:00:00', Tz::localToUtc('2026-03-07 10:00', 'America/New_York'), 'NY antes del salto (UTC-5)');
T::eq('2026-10-25 09:00:00', Tz::localToUtc('2026-10-25 10:00', 'Europe/Madrid'), 'Madrid el día que termina el horario de verano (UTC+1)');
T::eq('2026-10-04 16:00:00', Tz::localToUtc('2026-10-04 10:00', 'America/Guatemala'), 'Guatemala UTC-6 sin cambios');

T::section('Feriados');
T::eq('2026-04-05', HolidayService::easter(2026), 'Pascua 2026');
$h = Db::col("SELECT date FROM holidays WHERE date LIKE '2026-%' AND active = 1 ORDER BY date");
T::ok(in_array('2026-04-02', $h, true) && in_array('2026-04-03', $h, true) && in_array('2026-04-04', $h, true), 'Jueves, Viernes y Sábado Santo 2026');
T::ok(!in_array('2026-08-15', $h, true), '15 de agosto desactivado por defecto');
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM holidays WHERE date LIKE '2026-12-%' AND kind = 'half'"), '24 y 31 de diciembre medio día');

T::section('Utilidades');
T::eq('50251234567', Str::phone('+502 5123-4567'), 'teléfono con +502');
T::eq('50255551234', Str::phone('5555 1234'), 'teléfono de 8 dígitos recibe 502');
T::eq(null, Str::phone('123'), 'teléfono inválido');
$secret = Totp::newSecret();
T::ok(Totp::verify($secret, Totp::code($secret)), 'TOTP verifica su propio código');
T::done();
