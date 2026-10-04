<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Util;

/** Formularios de ingreso por servicio: definición, lógica condicional y validación estricta. */
final class FormService
{
    public const TYPES = ['text' => 'Texto corto', 'textarea' => 'Párrafo', 'number' => 'Número', 'date' => 'Fecha',
        'select' => 'Selección', 'checkbox' => 'Casillas', 'file' => 'Archivo', 'consent' => 'Consentimiento'];

    public static function fieldsFor(int $serviceId): array
    {
        return Db::all('SELECT * FROM form_fields WHERE active=1 AND (service_id=? OR service_id IS NULL) ORDER BY sort,id', [$serviceId]);
    }

    public static function options(array $f): array
    {
        $o = array_map('trim', explode('|', str_replace(["\r\n", "\n"], '|', (string)$f['options'])));
        return array_values(array_filter($o, static fn($x) => $x !== ''));
    }

    /**
     * @param array $input   [field_id => valor|array]
     * @param array $files   [field_id => $_FILES entry]
     * @return array{0:array,1:array,2:array} [respuestas, archivos a guardar, errores [field_id => mensaje]]
     */
    public static function validate(array $fields, array $input, array $files = []): array
    {
        $values = [];
        foreach ($fields as $f) { $values[(int)$f['id']] = $input[$f['id']] ?? null; }
        $answers = []; $toStore = []; $errors = [];
        foreach ($fields as $f) {
            $id = (int)$f['id'];
            // Lógica condicional: el campo solo aplica si el campo de control tiene el valor indicado
            if ($f['cond_field_id']) {
                $cv = $values[(int)$f['cond_field_id']] ?? null;
                $match = is_array($cv) ? in_array($f['cond_value'], $cv, true) : ((string)$cv === (string)$f['cond_value']);
                if (!$match) { continue; }
            }
            $label = (string)$f['label'];
            $req = (bool)$f['required'];
            $v = $values[$id];
            switch ($f['ftype']) {
                case 'file':
                    $file = $files[$id] ?? null;
                    $has = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                    if (!$has) { if ($req) { $errors[$id] = 'Adjunta un archivo.'; } break; }
                    try { \Aurea\Core\Upload::check($file); $toStore[$id] = $file; $answers[] = [$id, $label, '(archivo adjunto)']; }
                    catch (\RuntimeException $e) { $errors[$id] = $e->getMessage(); }
                    break;
                case 'consent':
                    $ok = $v === '1' || $v === 1 || $v === true || $v === 'on';
                    if ($req && !$ok) { $errors[$id] = 'Debes aceptar para continuar.'; break; }
                    $answers[] = [$id, $label, $ok ? 'Sí, acepta' : 'No'];
                    break;
                case 'checkbox':
                    $opts = self::options($f);
                    if ($opts) {
                        $arr = is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
                        $bad = array_diff($arr, $opts);
                        if ($bad) { $errors[$id] = 'Opción inválida.'; break; }
                        if ($req && !$arr) { $errors[$id] = 'Selecciona al menos una opción.'; break; }
                        if ($arr) { $answers[] = [$id, $label, implode(', ', $arr)]; }
                    } else {
                        $ok = $v === '1' || $v === 1 || $v === true || $v === 'on';
                        if ($req && !$ok) { $errors[$id] = 'Este campo es obligatorio.'; break; }
                        $answers[] = [$id, $label, $ok ? 'Sí' : 'No'];
                    }
                    break;
                case 'select':
                    $s = is_scalar($v) ? trim((string)$v) : '';
                    if ($s === '') { if ($req) { $errors[$id] = 'Selecciona una opción.'; } break; }
                    if (!in_array($s, self::options($f), true)) { $errors[$id] = 'Opción inválida.'; break; }
                    $answers[] = [$id, $label, $s];
                    break;
                case 'number':
                    $s = is_scalar($v) ? trim((string)$v) : '';
                    if ($s === '') { if ($req) { $errors[$id] = 'Este campo es obligatorio.'; } break; }
                    if (!preg_match('/^-?\d{1,12}([.,]\d{1,4})?$/', $s)) { $errors[$id] = 'Ingresa un número válido.'; break; }
                    $answers[] = [$id, $label, str_replace(',', '.', $s)];
                    break;
                case 'date':
                    $s = is_scalar($v) ? trim((string)$v) : '';
                    if ($s === '') { if ($req) { $errors[$id] = 'Este campo es obligatorio.'; } break; }
                    if (!Util::isDate($s)) { $errors[$id] = 'Fecha inválida.'; break; }
                    $answers[] = [$id, $label, $s];
                    break;
                default: // text, textarea
                    $s = is_scalar($v) ? trim((string)$v) : '';
                    $max = $f['ftype'] === 'textarea' ? 3000 : 255;
                    if ($s === '') { if ($req) { $errors[$id] = 'Este campo es obligatorio.'; } break; }
                    if (mb_strlen($s) > $max) { $errors[$id] = 'Máximo ' . $max . ' caracteres.'; break; }
                    $answers[] = [$id, $label, $s];
            }
        }
        return [$answers, $toStore, $errors];
    }
}
