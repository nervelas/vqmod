<?php
declare(strict_types=1);

namespace App\Core;

final class Validador
{
    private array $errores = [];

    public function requerido(string $campo, mixed $valor, string $etiqueta): self
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '') || (is_array($valor) && $valor === [])) {
            $this->errores[$campo] = $etiqueta . ' es obligatorio.';
        }
        return $this;
    }

    public function correo(string $campo, string $valor, string $etiqueta, bool $opcional = false): self
    {
        if ($valor === '' && $opcional) {
            return $this;
        }
        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->errores[$campo] = $etiqueta . ' no tiene un formato válido.';
        }
        return $this;
    }

    public function largoMax(string $campo, string $valor, int $max, string $etiqueta): self
    {
        if (mb_strlen($valor) > $max) {
            $this->errores[$campo] = $etiqueta . ' no puede superar ' . $max . ' caracteres.';
        }
        return $this;
    }

    public function numero(string $campo, mixed $valor, string $etiqueta, float $min = 0, ?float $max = null): self
    {
        if (!is_numeric($valor)) {
            $this->errores[$campo] = $etiqueta . ' debe ser un número.';
            return $this;
        }
        $n = (float) $valor;
        if ($n < $min) {
            $this->errores[$campo] = $etiqueta . ' no puede ser menor que ' . $min . '.';
        } elseif ($max !== null && $n > $max) {
            $this->errores[$campo] = $etiqueta . ' no puede ser mayor que ' . $max . '.';
        }
        return $this;
    }

    public function fecha(string $campo, string $valor, string $etiqueta, bool $opcional = false): self
    {
        if ($valor === '' && $opcional) {
            return $this;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $valor);
        if (!$d || $d->format('Y-m-d') !== $valor) {
            $this->errores[$campo] = $etiqueta . ' no es una fecha válida.';
        }
        return $this;
    }

    public function en(string $campo, mixed $valor, array $opciones, string $etiqueta): self
    {
        if (!in_array((string) $valor, array_map('strval', $opciones), true)) {
            $this->errores[$campo] = $etiqueta . ' no es una opción válida.';
        }
        return $this;
    }

    public function agregar(string $campo, string $mensaje): self
    {
        $this->errores[$campo] = $mensaje;
        return $this;
    }

    public function ok(): bool
    {
        return $this->errores === [];
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function primerError(): string
    {
        return (string) (reset($this->errores) ?: 'Revise los datos ingresados.');
    }

    public function todosTexto(): string
    {
        return implode(' ', $this->errores);
    }
}
