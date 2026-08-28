<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Validador simple y explicito, con mensajes en espanol.
 */
final class Validator
{
    private array $errores = [];
    private array $datos;

    public function __construct(array $datos) { $this->datos = $datos; }

    public static function make(array $datos): self { return new self($datos); }

    public function requerido(string $campo, string $etiqueta): self
    {
        $v = $this->datos[$campo] ?? '';
        if ($v === '' || $v === null || (is_array($v) && !$v)) {
            $this->errores[$campo] = $etiqueta . ' es obligatorio.';
        }
        return $this;
    }

    public function min(string $campo, int $n, string $etiqueta): self
    {
        if (isset($this->datos[$campo]) && mb_strlen((string)$this->datos[$campo]) < $n) {
            $this->errores[$campo] = $etiqueta . " debe tener al menos {$n} caracteres.";
        }
        return $this;
    }

    public function max(string $campo, int $n, string $etiqueta): self
    {
        if (isset($this->datos[$campo]) && mb_strlen((string)$this->datos[$campo]) > $n) {
            $this->errores[$campo] = $etiqueta . " no puede superar {$n} caracteres.";
        }
        return $this;
    }

    public function email(string $campo, string $etiqueta = 'El correo'): self
    {
        $v = (string)($this->datos[$campo] ?? '');
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errores[$campo] = $etiqueta . ' no tiene un formato válido.';
        }
        return $this;
    }

    public function numerico(string $campo, string $etiqueta, float $min = -INF, float $max = INF): self
    {
        $v = $this->datos[$campo] ?? null;
        if ($v === null || $v === '') return $this;
        if (!is_numeric($v)) {
            $this->errores[$campo] = $etiqueta . ' debe ser un número.';
        } elseif ((float)$v < $min || (float)$v > $max) {
            $this->errores[$campo] = $etiqueta . ' está fuera del rango permitido.';
        }
        return $this;
    }

    public function en(string $campo, array $opciones, string $etiqueta): self
    {
        $v = $this->datos[$campo] ?? null;
        if ($v !== null && $v !== '' && !in_array($v, $opciones, true)) {
            $this->errores[$campo] = $etiqueta . ' no es una opción válida.';
        }
        return $this;
    }

    public function password(string $campo, string $etiqueta = 'La contrasena'): self
    {
        $v = (string)($this->datos[$campo] ?? '');
        if ($v === '') return $this;
        if (mb_strlen($v) < 8) {
            $this->errores[$campo] = $etiqueta . ' debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $v) || !preg_match('/\d/', $v)) {
            $this->errores[$campo] = $etiqueta . ' debe combinar letras y números.';
        }
        return $this;
    }

    public function iguales(string $a, string $b, string $etiqueta): self
    {
        if (($this->datos[$a] ?? null) !== ($this->datos[$b] ?? null)) {
            $this->errores[$b] = $etiqueta . ' no coinciden.';
        }
        return $this;
    }

    public function telefono(string $campo, string $etiqueta = 'El telefono'): self
    {
        $v = (string)($this->datos[$campo] ?? '');
        if ($v !== '' && !preg_match('/^[\d\s()+\-]{7,20}$/', $v)) {
            $this->errores[$campo] = $etiqueta . ' no es válido.';
        }
        return $this;
    }

    public function slug(string $campo, string $etiqueta = 'La direccion web'): self
    {
        $v = (string)($this->datos[$campo] ?? '');
        if ($v !== '' && !preg_match('/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/', $v)) {
            $this->errores[$campo] = $etiqueta . ' solo admite minúsculas, números y guiones.';
        }
        return $this;
    }

    public function personalizado(string $campo, bool $condicion, string $mensaje): self
    {
        if (!$condicion) $this->errores[$campo] = $mensaje;
        return $this;
    }

    public function pasa(): bool { return !$this->errores; }
    public function falla(): bool { return (bool)$this->errores; }
    public function errores(): array { return $this->errores; }
    public function primerError(): string { return (string)(reset($this->errores) ?: ''); }
}
