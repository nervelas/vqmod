<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validacion estricta de entrada. Reglas: required, email, int, numeric, min:n,
 * max:n, len:min,max, in:a,b,c, date, bool, regex:/.../, confirmed
 */
final class Validator
{
    private array $errors = [];
    private array $clean  = [];

    public function __construct(private array $data) {}

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data);
        $v->validate($rules, $labels);
        return $v;
    }

    public function validate(array $rules, array $labels = []): bool
    {
        foreach ($rules as $field => $ruleset) {
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $list = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
            $required = in_array('required', $list, true);
            $nullable = in_array('nullable', $list, true);

            if ($required && ($value === null || $value === '' || $value === [])) {
                $this->errors[$field] = "El campo {$label} es obligatorio.";
                continue;
            }
            if (($value === null || $value === '') && ($nullable || !$required)) {
                $this->clean[$field] = ($value === '' ? null : $value);
                continue;
            }

            foreach ($list as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                switch ($name) {
                    case 'email':
                        if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field] = "El campo {$label} debe ser un correo valido.";
                        }
                        break;
                    case 'int':
                        if (filter_var((string)$value, FILTER_VALIDATE_INT) === false) {
                            $this->errors[$field] = "El campo {$label} debe ser un numero entero.";
                        } else {
                            $value = (int)$value;
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $this->errors[$field] = "El campo {$label} debe ser numerico.";
                        } else {
                            $value = (float)$value;
                        }
                        break;
                    case 'min':
                        if (is_numeric($value) ? ((float)$value < (float)$arg) : (mb_strlen((string)$value) < (int)$arg)) {
                            $this->errors[$field] = "El campo {$label} es demasiado corto o pequeno (minimo {$arg}).";
                        }
                        break;
                    case 'max':
                        if (is_numeric($value) ? ((float)$value > (float)$arg) : (mb_strlen((string)$value) > (int)$arg)) {
                            $this->errors[$field] = "El campo {$label} excede el maximo permitido ({$arg}).";
                        }
                        break;
                    case 'len':
                        [$mn, $mx] = array_pad(explode(',', (string)$arg), 2, null);
                        $l = mb_strlen((string)$value);
                        if ($l < (int)$mn || ($mx !== null && $l > (int)$mx)) {
                            $this->errors[$field] = "El campo {$label} debe tener entre {$mn} y {$mx} caracteres.";
                        }
                        break;
                    case 'in':
                        if (!in_array((string)$value, explode(',', (string)$arg), true)) {
                            $this->errors[$field] = "El valor de {$label} no es valido.";
                        }
                        break;
                    case 'date':
                        $d = \DateTime::createFromFormat('Y-m-d', (string)$value);
                        if (!$d || $d->format('Y-m-d') !== (string)$value) {
                            $this->errors[$field] = "El campo {$label} debe ser una fecha valida.";
                        }
                        break;
                    case 'regex':
                        if (!preg_match((string)$arg, (string)$value)) {
                            $this->errors[$field] = "El formato de {$label} no es valido.";
                        }
                        break;
                    case 'confirmed':
                        if (($this->data[$field . '_confirmacion'] ?? null) !== $value) {
                            $this->errors[$field] = "La confirmacion de {$label} no coincide.";
                        }
                        break;
                    case 'password':
                        if (mb_strlen((string)$value) < 10) {
                            $this->errors[$field] = "La contrasena debe tener al menos 10 caracteres.";
                        }
                        break;
                }
                if (isset($this->errors[$field])) {
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->clean[$field] = $value;
            }
        }
        return $this->passes();
    }

    public function passes(): bool { return $this->errors === []; }
    public function fails(): bool { return $this->errors !== []; }
    public function errors(): array { return $this->errors; }
    public function firstError(): string { return (string)(reset($this->errors) ?: ''); }
    public function clean(): array { return $this->clean; }
    public function get(string $k, mixed $d = null): mixed { return $this->clean[$k] ?? $d; }
}
