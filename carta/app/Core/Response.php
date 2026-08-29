<?php
namespace MenuGold\Core;

final class Response
{
    /** @var int */
    public $status = 200;
    /** @var array<string,string> */
    public $headers = array();
    /** @var string */
    public $body = '';

    public static function make($body = '', $status = 200, array $headers = array())
    {
        $r = new self();
        $r->body = (string)$body;
        $r->status = (int)$status;
        $r->headers = $headers;
        return $r;
    }

    public static function html($body, $status = 200)
    {
        return self::make($body, $status, array('Content-Type' => 'text/html; charset=UTF-8'));
    }

    public static function json($data, $status = 200)
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        return self::make(json_encode($data, $flags), $status, array('Content-Type' => 'application/json; charset=UTF-8'));
    }

    public static function redirect($to, $status = 302)
    {
        return self::make('', $status, array('Location' => $to));
    }

    public static function text($body, $status = 200)
    {
        return self::make($body, $status, array('Content-Type' => 'text/plain; charset=UTF-8'));
    }

    public function header($k, $v)
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function send()
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $this->body;
    }
}
