<?php
declare(strict_types=1);

namespace Aurea\Core;

final class Response
{
    public int $status = 200;
    public array $headers = [];
    public string $body = '';
    public ?string $file = null;

    public static function html(string $body, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = $body;
        $r->headers['Content-Type'] = 'text/html; charset=utf-8';
        return $r;
    }

    public static function json($data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
        $r->headers['Content-Type'] = 'application/json; charset=utf-8';
        $r->headers['Cache-Control'] = 'no-store';
        return $r;
    }

    public static function text(string $body, string $type = 'text/plain', int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = $body;
        $r->headers['Content-Type'] = $type . '; charset=utf-8';
        return $r;
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = $to;
        return $r;
    }

    /** Descarga de contenido en memoria. */
    public static function download(string $content, string $filename, string $mime): self
    {
        $r = new self();
        $r->body = $content;
        $r->headers['Content-Type'] = $mime;
        $r->headers['Content-Disposition'] = 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"';
        $r->headers['X-Content-Type-Options'] = 'nosniff';
        return $r;
    }

    /** Entrega un archivo del disco (se lee al enviar). */
    public static function sendFile(string $path, string $mime, string $name, bool $inline = false): self
    {
        $r = new self();
        $r->file = $path;
        $r->headers['Content-Type'] = $mime;
        $r->headers['Content-Disposition'] = ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"';
        $r->headers['X-Content-Type-Options'] = 'nosniff';
        $r->headers['Cache-Control'] = 'private, no-store';
        $r->headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        return $r;
    }

    public function header(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        if ($this->file !== null && is_file($this->file)) {
            header('Content-Length: ' . filesize($this->file));
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
