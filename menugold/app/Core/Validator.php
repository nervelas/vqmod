<?php
namespace MenuGold\Core;

final class Validator
{
    /** @var array<string,string> */
    private $errors = array();
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function value($field, $default = null)
    {
        return array_key_exists($field, $this->data) ? $this->data[$field] : $default;
    }

    public function required($field, $label)
    {
        $v = $this->value($field);
        if ($v === null || (is_string($v) && trim($v) === '') || (is_array($v) && !$v)) {
            $this->errors[$field] = $label . ' es obligatorio.';
        }
        return $this;
    }

    public function email($field, $label)
    {
        $v = trim((string)$this->value($field, ''));
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $label . ' no tiene un formato válido.';
        }
        return $this;
    }

    public function min($field, $label, $len)
    {
        $v = (string)$this->value($field, '');
        if ($v !== '' && strlen($v) < $len) {
            $this->errors[$field] = $label . ' debe tener al menos ' . $len . ' caracteres.';
        }
        return $this;
    }

    public function max($field, $label, $len)
    {
        $v = (string)$this->value($field, '');
        if (strlen($v) > $len) {
            $this->errors[$field] = $label . ' no puede exceder ' . $len . ' caracteres.';
        }
        return $this;
    }

    public function numeric($field, $label, $min = null, $max = null)
    {
        $raw = $this->value($field, '');
        if ($raw === '' || $raw === null) { return $this; }
        if (!is_numeric($raw)) {
            $this->errors[$field] = $label . ' debe ser un número.';
            return $this;
        }
        $n = (float)$raw;
        if ($min !== null && $n < $min) { $this->errors[$field] = $label . ' no puede ser menor que ' . $min . '.'; }
        if ($max !== null && $n > $max) { $this->errors[$field] = $label . ' no puede ser mayor que ' . $max . '.'; }
        return $this;
    }

    public function in($field, $label, array $allowed)
    {
        $v = $this->value($field);
        if ($v !== null && $v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field] = $label . ' tiene un valor no permitido.';
        }
        return $this;
    }

    public function slug($field, $label)
    {
        $v = (string)$this->value($field, '');
        if ($v !== '' && !preg_match('/^[a-z0-9]([a-z0-9\-]{1,60})[a-z0-9]$/', $v)) {
            $this->errors[$field] = $label . ' solo admite minúsculas, números y guiones.';
        }
        return $this;
    }

    public function add($field, $message)
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function fails()
    {
        return !empty($this->errors);
    }

    public function errors()
    {
        return $this->errors;
    }

    public function firstError()
    {
        foreach ($this->errors as $e) { return $e; }
        return '';
    }
}
