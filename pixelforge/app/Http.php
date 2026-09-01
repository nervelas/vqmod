<?php
declare(strict_types=1);

/** Respuesta HTTP simplificada. */
final class HttpResponse
{
    public int $status;
    public string $body;
    public string $contentType;
    public string $error;
    public int $attempts;

    public function __construct(int $status, string $body, string $contentType = '', string $error = '', int $attempts = 1)
    {
        $this->status = $status;
        $this->body = $body;
        $this->contentType = $contentType;
        $this->error = $error;
        $this->attempts = $attempts;
    }

    public function ok(): bool
    {
        return $this->error === '' && $this->status >= 200 && $this->status < 300;
    }
}

/** Cliente HTTP con timeout y reintentos. Usa cURL y, si falta, flujos de PHP. */
final class Http
{
    /**
     * @param array{headers?:array<int,string>,body?:string,method?:string,timeout?:int,retries?:int} $options
     */
    public static function request(string $url, array $options = []): HttpResponse
    {
        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        $headers = (array) ($options['headers'] ?? []);
        $body = (string) ($options['body'] ?? '');
        $timeout = max(10, (int) ($options['timeout'] ?? 90));
        $retries = max(1, min(5, (int) ($options['retries'] ?? 3)));

        $last = new HttpResponse(0, '', '', 'No se realizó ninguna petición.');
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $last = function_exists('curl_init')
                ? self::viaCurl($url, $method, $headers, $body, $timeout)
                : self::viaStreams($url, $method, $headers, $body, $timeout);
            $last->attempts = $attempt;

            if ($last->ok()) {
                return $last;
            }
            $retryable = $last->error !== '' || $last->status === 429 || $last->status >= 500 || $last->status === 0;
            if (!$retryable || $attempt === $retries) {
                return $last;
            }
            Logger::write('http', sprintf(
                'Intento %d/%d falló (%s) en %s',
                $attempt,
                $retries,
                $last->error !== '' ? $last->error : 'HTTP ' . $last->status,
                self::safeUrl($url)
            ));
            // Espera progresiva: 2s, 4s, 8s (máx. 8s).
            sleep(min(8, 2 ** $attempt));
        }
        return $last;
    }

    private static function viaCurl(string $url, string $method, array $headers, string $body, int $timeout): HttpResponse
    {
        $ch = curl_init();
        if ($ch === false) {
            return new HttpResponse(0, '', '', 'No se pudo inicializar cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'PixelForge/' . PF_VERSION,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = $result === false ? self::translateCurlError(curl_errno($ch), (string) curl_error($ch)) : '';
        curl_close($ch);
        return new HttpResponse($status, $result === false ? '' : (string) $result, $type, $error);
    }

    private static function viaStreams(string $url, string $method, array $headers, string $body, int $timeout): HttpResponse
    {
        if (!ini_get('allow_url_fopen')) {
            return new HttpResponse(0, '', '', 'Este servidor no tiene cURL ni allow_url_fopen activos.');
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", array_merge($headers, ['User-Agent: PixelForge/' . PF_VERSION])),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $result = @file_get_contents($url, false, $context);
        $status = 0;
        $type = '';
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            } elseif (stripos($line, 'content-type:') === 0) {
                $type = trim(substr($line, 13));
            }
        }
        if ($result === false) {
            return new HttpResponse($status, '', $type, 'No se pudo conectar con el proveedor.');
        }
        return new HttpResponse($status, (string) $result, $type);
    }

    private static function translateCurlError(int $code, string $message): string
    {
        return match ($code) {
            CURLE_OPERATION_TIMEOUTED => 'El proveedor tardó demasiado en responder.',
            CURLE_COULDNT_RESOLVE_HOST => 'No se pudo resolver el dominio del proveedor.',
            CURLE_COULDNT_CONNECT => 'No se pudo conectar con el proveedor.',
            CURLE_SSL_CACERT, CURLE_SSL_PEER_CERTIFICATE => 'Error de certificado SSL al conectar con el proveedor.',
            default => 'Fallo de red: ' . ($message !== '' ? $message : 'error ' . $code),
        };
    }

    /** Oculta claves cuando la URL se escribe al log. */
    public static function safeUrl(string $url): string
    {
        $clean = preg_replace('/([?&](key|token|apikey)=)[^&]+/i', '$1***', $url);
        $clean = (string) $clean;
        return strlen($clean) > 180 ? substr($clean, 0, 180) . '…' : $clean;
    }
}
