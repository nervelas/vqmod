<?php
namespace MenuGold\Core;

final class Request
{
    /** @var array */
    public $query;
    /** @var array */
    public $post;
    /** @var array */
    public $files;
    /** @var string */
    public $method;
    /** @var string ruta relativa a la base, siempre con / inicial */
    public $path;
    /** @var array cuerpo JSON decodificado */
    public $json = array();

    public static function capture()
    {
        $r = new self();
        $r->query  = $_GET;
        $r->post   = $_POST;
        $r->files  = $_FILES;
        $r->method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $uri = explode('?', $uri, 2);
        $path = rawurldecode($uri[0]);

        $base = Url::basePath();
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');
        if ($path !== '/' ) {
            $path = rtrim($path, '/');
            if ($path === '') { $path = '/'; }
        }
        $r->path = $path;

        $ctype = $r->header('Content-Type');
        if ($ctype && stripos($ctype, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $r->json = $decoded;
                }
            }
        }
        return $r;
    }

    public function header($name)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) { return $_SERVER[$key]; }
        if ($name === 'Content-Type' && isset($_SERVER['CONTENT_TYPE'])) { return $_SERVER['CONTENT_TYPE']; }
        return null;
    }

    /** Entrada unificada: JSON > POST > GET */
    public function input($key, $default = null)
    {
        if (array_key_exists($key, $this->json)) { return $this->json[$key]; }
        if (array_key_exists($key, $this->post)) { return $this->post[$key]; }
        if (array_key_exists($key, $this->query)) { return $this->query[$key]; }
        return $default;
    }

    public function str($key, $default = '')
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string)$v) : $default;
    }

    public function int($key, $default = 0)
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? (int)$v : $default;
    }

    public function float($key, $default = 0.0)
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? (float)str_replace(',', '.', (string)$v) : $default;
    }

    public function bool($key, $default = false)
    {
        $v = $this->input($key, $default);
        if (is_bool($v)) { return $v; }
        if (is_scalar($v)) { return in_array(strtolower((string)$v), array('1','true','on','yes','si','sí'), true); }
        return $default;
    }

    public function arr($key)
    {
        $v = $this->input($key, array());
        return is_array($v) ? $v : array();
    }

    public function isPost()  { return $this->method === 'POST'; }

    public function isAjax()
    {
        $x = $this->header('X-Requested-With');
        return ($x && strtolower($x) === 'xmlhttprequest')
            || (bool)$this->query('ajax');
    }

    public function query($key, $default = null)
    {
        return isset($this->query[$key]) ? $this->query[$key] : $default;
    }

    public function wantsJson()
    {
        $a = $this->header('Accept');
        return $this->isAjax() || ($a && stripos($a, 'application/json') !== false);
    }

    public function ip()
    {
        return Security::clientIp();
    }

    public function userAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 250) : '';
    }
}
