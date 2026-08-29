<?php
namespace MenuGold\Core;

/**
 * Motor de plantillas mínimo sobre PHP puro, con layouts y secciones.
 */
final class View
{
    /** @var array datos compartidos por todas las vistas */
    private static $shared = array();
    /** @var array<string,string> */
    private $sections = array();
    /** @var array pila de secciones abiertas */
    private $stack = array();
    /** @var string|null */
    private $layout = null;
    /** @var array */
    private $data = array();

    public static function share($key, $value)
    {
        self::$shared[$key] = $value;
    }

    public static function shared()
    {
        return self::$shared;
    }

    public static function render($template, array $data = array())
    {
        $v = new self();
        return $v->run($template, $data);
    }

    private function run($template, array $data)
    {
        $this->data = array_merge(self::$shared, $data);
        $content = $this->capture($template, $this->data);

        // Una plantilla puede escribir directamente (queda en $content) o
        // envolver su salida en @start('content') … @stop(). Ambas valen.
        if (isset($this->sections['content']) && trim($this->sections['content']) !== '') {
            $content = $this->sections['content'];
        }

        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] = $content;
            $content = $this->capture($layout, $this->data);
        }
        return $content;
    }

    private function path($template)
    {
        $file = MG_APP . '/Views/' . str_replace(array('..', "\0"), '', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $template);
        }
        return $file;
    }

    private function capture($template, array $data)
    {
        $__file = $this->path($template);
        $view = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    /* ---- API disponible dentro de las plantillas ---- */

    public function extend($layout)
    {
        $this->layout = $layout;
    }

    public function start($name)
    {
        $this->stack[] = $name;
        ob_start();
    }

    public function stop()
    {
        $name = array_pop($this->stack);
        $this->sections[$name] = ob_get_clean();
    }

    public function section($name, $default = '')
    {
        return isset($this->sections[$name]) ? $this->sections[$name] : $default;
    }

    public function has($name)
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    public function set($name, $value)
    {
        $this->sections[$name] = $value;
    }

    /** Incluye una parcial reutilizando los datos actuales. */
    public function partial($template, array $data = array())
    {
        echo $this->capture($template, array_merge($this->data, $data));
    }

    public function e($v)
    {
        return Security::e($v);
    }

    public function url($p = '/')
    {
        return Url::to($p);
    }

    public function asset($p)
    {
        return Url::asset($p);
    }

    public function money($amount, $currency = null)
    {
        return Money::format($amount, $currency);
    }

    public function t($key, array $repl = array())
    {
        return Lang::get($key, $repl);
    }

    public function csrf()
    {
        return Csrf::field();
    }

    /** Atributos "seguros" para JSON embebido en data-*. */
    public function json($data)
    {
        return Security::e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
