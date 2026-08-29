<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value, string $label): self
    {
        if (is_string($value) ? trim($value) === '' : empty($value)) {
            $this->errors[$field] = "{$label} es obligatorio.";
        }
        return $this;
    }

    public function email(string $field, string $value, string $label = 'El correo'): self
    {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} no tiene un formato válido.";
        }
        return $this;
    }

    public function max(string $field, string $value, int $len, string $label): self
    {
        if (mb_strlen($value) > $len) {
            $this->errors[$field] = "{$label} no puede exceder {$len} caracteres.";
        }
        return $this;
    }

    public function min(string $field, string $value, int $len, string $label): self
    {
        if (mb_strlen(trim($value)) < $len) {
            $this->errors[$field] = "{$label} debe tener al menos {$len} caracteres.";
        }
        return $this;
    }

    public function numeric(string $field, mixed $value, string $label): self
    {
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "{$label} debe ser numérico.";
        }
        return $this;
    }

    public function in(string $field, mixed $value, array $allowed, string $label): self
    {
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = "{$label} tiene un valor no permitido.";
        }
        return $this;
    }

    public function phone(string $field, string $value, string $label = 'El teléfono'): self
    {
        if ($value !== '' && !preg_match('/^[0-9\+\-\s\(\)]{7,25}$/', $value)) {
            $this->errors[$field] = "{$label} no tiene un formato válido.";
        }
        return $this;
    }

    public function custom(string $field, bool $condition, string $message): self
    {
        if (!$condition) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): string
    {
        return (string) (reset($this->errors) ?: '');
    }
}
