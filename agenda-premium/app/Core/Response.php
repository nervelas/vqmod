<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public int $status = 200;
    public array $headers = [];
    public string $body = '';
    public ?string $file = null;
    public bool $noSecurityHeaders = false;

    public static function html(string $html, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = $html;
        $r->headers['Content-Type'] = 'text/html; charset=utf-8';
        return $r;
    }

    public static function json($data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $r->headers['Content-Type'] = 'application/json; charset=utf-8';
        $r->headers['Cache-Control'] = 'no-store';
        return $r;
    }

    public static function text(string $text, int $status = 200, string $type = 'text/plain'): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = $text;
        $r->headers['Content-Type'] = $type . '; charset=utf-8';
        return $r;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = $url;
        return $r;
    }

    /** Descarga o entrega de un archivo de disco. */
    public static function file(string $path, string $mime, string $downloadName = '', bool $inline = true): self
    {
        $r = new self();
        $r->file = $path;
        $r->headers['Content-Type'] = $mime;
        $r->headers['X-Content-Type-Options'] = 'nosniff';
        $name = $downloadName !== '' ? preg_replace('/[^A-Za-z0-9._ \-]/', '_', $downloadName) : '';
        if ($name !== '' || !$inline) {
            $r->headers['Content-Disposition'] = ($inline ? 'inline' : 'attachment') . '; filename="' . ($name ?: 'archivo') . '"';
        }
        return $r;
    }

    /** Descarga de contenido generado (CSV, ICS, JSON…). */
    public static function download(string $content, string $mime, string $filename): self
    {
        $r = new self();
        $r->body = $content;
        $r->headers['Content-Type'] = $mime . '; charset=utf-8';
        $r->headers['Content-Disposition'] = 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._\-]/', '_', $filename) . '"';
        $r->headers['Cache-Control'] = 'no-store';
        return $r;
    }

    public function header(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function send(Request $req): void
    {
        http_response_code($this->status);
        if (!$this->noSecurityHeaders) {
            foreach (Security::headers($req) as $k => $v) {
                if (!isset($this->headers[$k])) {
                    header($k . ': ' . $v);
                }
            }
        }
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        if ($req->method === 'HEAD') {
            return;
        }
        if ($this->file !== null && is_file($this->file)) {
            header('Content-Length: ' . filesize($this->file));
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
