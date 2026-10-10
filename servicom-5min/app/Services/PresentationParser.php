<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * Error del analizador de presentaciones. `$codigo` es uno de:
 * vacio, muy_grande, formato_no_permitido, formato_antiguo, macros,
 * protegido, corrupto, zip_sospechoso, mime_invalido.
 */
class ParserError extends \RuntimeException
{
    public string $codigo = 'corrupto';

    public static function de(string $codigo, string $mensaje): self
    {
        $e = new self($mensaje);
        $e->codigo = $codigo;
        return $e;
    }
}

/**
 * Inspección segura y extracción de texto/imágenes de PDF, PPTX y DOCX.
 *
 * - `inspect()` valida SIN extraer nada (tamaño, tipo real, macros, contraseña,
 *   estructura ZIP, XML con DOCTYPE/entidades).
 * - `extract()` lee texto e imágenes de PPTX/DOCX con límites duros
 *   (entradas, tamaño descomprimido, ratio, bytes por XML, píxeles, memoria).
 *   Los PDF no se leen localmente (los lee la IA de forma nativa), pero sus
 *   imágenes se extraen en PHP puro con PdfImageExtractor (logo/fotos).
 *
 * Los XML se parsean SIN entidades ni DTD externos (LIBXML_NONET, sin NOENT) y
 * se rechaza cualquier documento con <!DOCTYPE o <!ENTITY.
 */
class PresentationParser
{
    public const MAX_ENTRADAS = 2000;
    public const MAX_TOTAL_BYTES = 157286400;   // 150 MB descomprimidos
    public const MAX_RATIO = 100;
    public const MAX_XML_BYTES = 8388608;       // 8 MB por XML
    public const MAX_IMAGENES = 40;
    public const MIN_LADO = 400;
    public const MAX_PIXELES = 40000000;
    public const MAX_LADO_SALIDA = 1600;
    public const MAX_BYTES_IMAGEN = 15728640;   // 15 MB por imagen
    public const MAX_TEXTO = 400000;            // caracteres
    public const MAX_DIAPOS = 400;
    public const PDF_MIN_LADO = 120;
    public const PDF_SEGUNDOS = 8.0;
    /** Las entradas pequeñas pueden tener ratios altos sin ser peligrosas. */
    private const RATIO_DESDE_BYTES = 65536;

    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_P = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_PKG_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const EXT_ANTIGUAS = ['ppt', 'pps', 'pot', 'doc', 'dot', 'rtf', 'key', 'canva', 'odp', 'odt', 'pages', 'numbers', 'xls', 'ods', 'wps', 'dps', 'gslides', 'gdoc'];
    private const EXT_MACROS = ['pptm', 'ppsm', 'potm', 'docm', 'dotm', 'xlsm', 'xlsb', 'xltm', 'ppam', 'xlam'];
    private const EXT_PELIGROSAS_EN_ZIP = ['zip', 'jar', 'rar', '7z', 'gz', 'tgz', 'tar', 'exe', 'dll', 'msi', 'bat', 'cmd', 'vbs', 'ps1', 'sh', 'php', 'phtml', 'scr', 'com'];

    private const MENSAJES = [
        'vacio' => 'El archivo está vacío. Revisa que sea la presentación correcta y vuelve a subirla.',
        'muy_grande' => 'Tu archivo es demasiado pesado. Puedes subir una versión más liviana o llenar los datos manualmente.',
        'formato_no_permitido' => 'Solo podemos leer presentaciones en PDF, PowerPoint (.pptx) o Word (.docx).',
        'formato_antiguo' => 'Este formato antiguo o especial no se puede leer. Guárdala como PDF y vuelve a subirla.',
        'macros' => 'Tu archivo contiene macros y por seguridad no lo podemos leer. Guárdalo sin macros o como PDF y vuelve a subirlo.',
        'protegido' => 'Tu archivo está protegido con contraseña. Quita la contraseña o guárdalo como PDF y vuelve a subirlo.',
        'corrupto' => 'No pudimos abrir el archivo; parece dañado o incompleto. Intenta guardarlo de nuevo como PDF y subirlo otra vez.',
        'zip_sospechoso' => 'El archivo tiene una estructura inusual o demasiado compleja y por seguridad no lo podemos leer. Guárdalo como PDF y vuelve a subirlo.',
        'mime_invalido' => 'El contenido del archivo no coincide con su extensión. Sube un PDF, PPTX o DOCX válido.',
    ];

    // ------------------------------------------------------------------
    // API pública
    // ------------------------------------------------------------------

    /**
     * Valida un archivo subido sin extraer su contenido.
     *
     * @return array{ok:bool,tipo:string,codigo:string,mensaje:string}
     */
    public static function inspect(string $path, string $nombreOriginal, int $maxBytes): array
    {
        $tipo = '';
        try {
            $tipo = self::inspeccionar($path, $nombreOriginal, $maxBytes);
            return ['ok' => true, 'tipo' => $tipo, 'codigo' => 'ok', 'mensaje' => 'Archivo recibido correctamente.'];
        } catch (ParserError $e) {
            return ['ok' => false, 'tipo' => $tipo !== '' ? $tipo : self::extension($nombreOriginal), 'codigo' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'tipo' => self::extension($nombreOriginal), 'codigo' => 'corrupto', 'mensaje' => self::MENSAJES['corrupto']];
        }
    }

    /**
     * Extrae texto e imágenes. Lanza ParserError (código en ->codigo) ante
     * archivos peligrosos o ilegibles.
     *
     * En PDF cada imagen añade `orden` (int), `pagina` (?int) y `logo_cand` (bool).
     *
     * @return array{texto:string,imagenes:array<int,array{archivo:string,w:int,h:int,hash:string}>,paginas:int}
     */
    public static function extract(string $path, string $tipo, string $workdir): array
    {
        $tipo = strtolower($tipo);
        if ($tipo === 'pdf') {
            $imagenes = [];
            try {
                $imagenes = self::extraerImagenesPdf($path, $workdir);
            } catch (\Throwable $e) {
                $imagenes = []; // una imagen o un PDF raro nunca rompe el análisis
            }
            return ['texto' => '', 'imagenes' => $imagenes, 'paginas' => self::contarPaginasPdf($path)];
        }
        if ($tipo !== 'pptx' && $tipo !== 'docx') {
            throw self::err('formato_no_permitido');
        }
        $zip = self::abrirZipSeguro($path, $tipo);
        try {
            if (!is_dir($workdir) && !@mkdir($workdir, 0775, true) && !is_dir($workdir)) {
                throw self::err('corrupto');
            }
            $r = $tipo === 'pptx' ? self::extraerPptx($zip, $workdir) : self::extraerDocx($zip, $workdir);
        } finally {
            $zip->close();
        }
        $r['texto'] = TextClean::limpiar($r['texto'], self::MAX_TEXTO, true);
        return $r;
    }

    // ------------------------------------------------------------------
    // inspect
    // ------------------------------------------------------------------

    private static function err(string $codigo, ?string $mensaje = null): ParserError
    {
        return ParserError::de($codigo, $mensaje ?? self::MENSAJES[$codigo]);
    }

    private static function extension(string $nombre): string
    {
        $n = basename(str_replace('\\', '/', $nombre));
        $p = strrpos($n, '.');
        return $p === false ? '' : strtolower(substr($n, $p + 1));
    }

    private static function inspeccionar(string $path, string $nombre, int $maxBytes): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw self::err('corrupto');
        }
        clearstatcache(true, $path);
        $size = (int)filesize($path);
        if ($size <= 0) {
            throw self::err('vacio');
        }
        if ($maxBytes > 0 && $size > $maxBytes) {
            $mb = rtrim(rtrim(number_format($maxBytes / 1048576, 1, '.', ''), '0'), '.');
            throw self::err('muy_grande', 'Tu archivo pesa más de ' . $mb . ' MB. Puedes subir una versión más liviana o llenar los datos manualmente.');
        }
        $ext = self::extension($nombre);
        if (in_array($ext, self::EXT_MACROS, true)) {
            throw self::err('macros');
        }
        if (in_array($ext, self::EXT_ANTIGUAS, true)) {
            throw self::err('formato_antiguo');
        }
        if (!in_array($ext, ['pdf', 'pptx', 'docx'], true)) {
            throw self::err('formato_no_permitido');
        }

        $fh = @fopen($path, 'rb');
        if (!$fh) {
            throw self::err('corrupto');
        }
        $head = (string)fread($fh, 1024);
        fclose($fh);
        $esPdf = strncmp($head, '%PDF-', 5) === 0;
        $esZip = strncmp($head, "PK\x03\x04", 4) === 0;
        $esOle = strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0;

        if ($esOle) {
            // Contenedor OLE: OOXML cifrado o formato Office antiguo.
            if ($ext !== 'pdf' && self::ole_cifrado($path)) {
                throw self::err('protegido');
            }
            throw self::err($ext === 'pdf' ? 'mime_invalido' : 'formato_antiguo');
        }

        $mime = self::mimeReal($path);
        if ($ext === 'pdf') {
            if (!$esPdf) {
                throw self::err('mime_invalido');
            }
            if ($mime !== null && !in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
                throw self::err('mime_invalido');
            }
            self::validarPdf($path, $size);
            return 'pdf';
        }

        // pptx / docx
        if (!$esZip) {
            throw self::err('mime_invalido');
        }
        if ($mime !== null && !self::mimeZipOk($mime)) {
            throw self::err('mime_invalido');
        }
        $zip = self::abrirZipSeguro($path, $ext, true);
        $zip->close();
        return $ext;
    }

    private static function mimeReal(string $path): ?string
    {
        if (!class_exists('finfo')) {
            return null;
        }
        $f = @new \finfo(FILEINFO_MIME_TYPE);
        $m = @$f->file($path);
        return is_string($m) ? strtolower($m) : null;
    }

    private static function mimeZipOk(string $mime): bool
    {
        return $mime === 'application/zip' || $mime === 'application/x-zip-compressed' || $mime === 'application/x-zip'
            || $mime === 'application/octet-stream' || $mime === 'application/encrypted'
            || str_starts_with($mime, 'application/vnd.openxmlformats-officedocument.');
    }

    /** Busca en un OLE los nombres UTF-16LE EncryptedPackage/EncryptionInfo. */
    private static function ole_cifrado(string $path): bool
    {
        $a = self::utf16('EncryptedPackage');
        $b = self::utf16('EncryptionInfo');
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return false;
        }
        $prev = '';
        $leidos = 0;
        while (!feof($fh) && $leidos < 20971520) {
            $chunk = (string)fread($fh, 1048576);
            if ($chunk === '') {
                break;
            }
            $leidos += strlen($chunk);
            $buf = $prev . $chunk;
            if (strpos($buf, $a) !== false || strpos($buf, $b) !== false) {
                fclose($fh);
                return true;
            }
            $prev = substr($buf, -64);
        }
        fclose($fh);
        return false;
    }

    private static function utf16(string $s): string
    {
        $o = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $o .= $s[$i] . "\0";
        }
        return $o;
    }

    /** PDF: cierre %%EOF, /Encrypt (por bloques en todo el archivo). */
    private static function validarPdf(string $path, int $size): void
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            throw self::err('corrupto');
        }
        fseek($fh, max(0, $size - 4096));
        $cola = (string)fread($fh, 4096);
        if (strpos($cola, '%%EOF') === false) {
            fclose($fh);
            throw self::err('corrupto');
        }
        rewind($fh);
        $prev = '';
        $viste = false;
        while (!feof($fh)) {
            $chunk = (string)fread($fh, 1048576);
            if ($chunk === '') {
                break;
            }
            $buf = $prev . $chunk;
            if (preg_match('#/Encrypt\s*(?:\d+\s+\d+\s+R|<<)#', $buf)) {
                fclose($fh);
                throw self::err('protegido');
            }
            if (!$viste && (strpos($buf, 'obj') !== false)) {
                $viste = true;
            }
            $prev = substr($buf, -64);
        }
        fclose($fh);
        if (!$viste) {
            throw self::err('corrupto');
        }
    }

    /** Estimación de páginas de un PDF (conteo de /Type /Page y /Count). */
    public static function contarPaginasPdf(string $path): int
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return 0;
        }
        $n = 0;
        $max = 0;
        $prev = '';
        $leidos = 0;
        while (!feof($fh) && $leidos < 67108864) {
            $chunk = (string)fread($fh, 1048576);
            if ($chunk === '') {
                break;
            }
            $leidos += strlen($chunk);
            $buf = $prev . $chunk;
            $off = strlen($prev);
            if (preg_match_all('#/Type\s*/Page(?![A-Za-z])#', $buf, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $mm) {
                    if ($mm[1] >= max(0, $off - 16)) {
                        $n++;
                    }
                }
            }
            if (preg_match_all('#/Type\s*/Pages\b[^>]{0,400}?/Count\s+(\d{1,5})|/Count\s+(\d{1,5})[^>]{0,400}?/Type\s*/Pages\b#', $buf, $c)) {
                foreach ([1, 2] as $k) {
                    foreach ($c[$k] as $v) {
                        if ($v !== '') {
                            $max = max($max, (int)$v);
                        }
                    }
                }
            }
            $prev = substr($buf, -64);
        }
        fclose($fh);
        return min(5000, max($n, $max, 1));
    }

    // ------------------------------------------------------------------
    // ZIP seguro
    // ------------------------------------------------------------------

    /**
     * Abre un ZIP y revisa el directorio central ANTES de extraer nada.
     */
    private static function abrirZipSeguro(string $path, string $tipo, bool $escanearXml = false): \ZipArchive
    {
        if (!class_exists('ZipArchive')) {
            throw self::err('corrupto');
        }
        $size = (int)@filesize($path);
        // Antes de que libzip cargue el directorio central, leer solo el EOCD.
        $cuenta = self::entradasDeclaradas($path, $size);
        if ($cuenta !== null && $cuenta > self::MAX_ENTRADAS) {
            throw self::err('zip_sospechoso');
        }
        $zip = new \ZipArchive();
        $r = @$zip->open($path, \ZipArchive::RDONLY);
        if ($r !== true) {
            throw self::err('corrupto');
        }
        try {
            $n = $zip->numFiles;
            if ($n > self::MAX_ENTRADAS) {
                throw self::err('zip_sospechoso');
            }
            if ($n < 1) {
                throw self::err('corrupto');
            }
            $total = 0;
            $macros = false;
            $nombres = [];
            $xmlGrandes = [];
            $cifrado = false;
            for ($i = 0; $i < $n; $i++) {
                $st = $zip->statIndex($i);
                if ($st === false) {
                    throw self::err('corrupto');
                }
                $name = (string)$st['name'];
                $lname = strtolower($name);
                if ($name === '' || strpos($name, "\0") !== false || $name[0] === '/' || $name[0] === '\\'
                    || preg_match('#^[A-Za-z]:#', $name) || strlen($name) > 400) {
                    throw self::err('zip_sospechoso');
                }
                foreach (preg_split('#[\\\\/]#', $name) as $seg) {
                    if ($seg === '..') {
                        throw self::err('zip_sospechoso');
                    }
                }
                $sz = (int)$st['size'];
                $cs = (int)$st['comp_size'];
                $total += $sz;
                if ($total > self::MAX_TOTAL_BYTES) {
                    throw self::err('zip_sospechoso');
                }
                if ($sz >= self::RATIO_DESDE_BYTES && ($cs <= 0 || $sz / $cs > self::MAX_RATIO)) {
                    throw self::err('zip_sospechoso');
                }
                // Enlaces simbólicos (atributos UNIX).
                $op = 0;
                $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $op, $attr) && $op === \ZipArchive::OPSYS_UNIX) {
                    if ((($attr >> 16) & 0170000) === 0120000) {
                        throw self::err('zip_sospechoso');
                    }
                }
                if (isset($st['encryption_method']) && (int)$st['encryption_method'] !== 0) {
                    $cifrado = true;
                }
                $ext = pathinfo($lname, PATHINFO_EXTENSION);
                if (substr($name, -1) !== '/' && in_array($ext, self::EXT_PELIGROSAS_EN_ZIP, true)) {
                    throw self::err('zip_sospechoso');
                }
                if (strpos($lname, 'vbaproject') !== false || strpos($lname, 'vbadata') !== false
                    || strncmp($lname, 'macros/', 7) === 0 || strpos($lname, '/macros/') !== false || $ext === 'vba') {
                    $macros = true;
                }
                $nombres[$lname] = true;
                if ($ext === 'xml' || $ext === 'rels' || $ext === 'vml') {
                    if ($sz > self::MAX_XML_BYTES) {
                        $xmlGrandes[] = $name;
                    } elseif ($escanearXml) {
                        $xmlGrandes[] = '+' . $name; // marcador: escanear
                    }
                }
            }
            if ($size > 0 && $total / max(1, $size) > self::MAX_RATIO && $total > 1048576) {
                throw self::err('zip_sospechoso');
            }
            if ($cifrado) {
                throw self::err('protegido');
            }
            if ($macros) {
                throw self::err('macros');
            }
            foreach ($xmlGrandes as $x) {
                if ($x[0] !== '+') {
                    throw self::err('zip_sospechoso');
                }
            }
            // Estructura mínima
            if (!isset($nombres['[content_types].xml'])) {
                if (isset($nombres['mimetype'])) {
                    throw self::err('formato_antiguo');
                }
                throw self::err('corrupto');
            }
            $principal = $tipo === 'pptx' ? 'ppt/presentation.xml' : 'word/document.xml';
            $otro = $tipo === 'pptx' ? 'word/document.xml' : 'ppt/presentation.xml';
            if (!isset($nombres[$principal])) {
                throw self::err(isset($nombres[$otro]) || isset($nombres['xl/workbook.xml']) ? 'mime_invalido' : 'corrupto');
            }
            // Content-types con macros declaradas.
            $ct = strtolower(self::leerBytes($zip, '[Content_Types].xml', 1048576));
            if (strpos($ct, 'macroenabled') !== false || strpos($ct, 'vbaproject') !== false) {
                throw self::err('macros');
            }
            if ($escanearXml) {
                foreach ($xmlGrandes as $x) {
                    self::escanearXml($zip, substr($x, 1));
                    self::memoriaOk();
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            throw $e;
        }
        return $zip;
    }

    /**
     * Número de entradas declarado en el fin del directorio central (ZIP/ZIP64),
     * leyendo solo la cola del archivo. null si no se encuentra.
     */
    private static function entradasDeclaradas(string $path, int $size): ?int
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return null;
        }
        $len = min($size, 65557 + 20);
        fseek($fh, $size - $len);
        $cola = (string)fread($fh, $len);
        $p = strrpos($cola, "PK\x05\x06");
        if ($p === false || strlen($cola) < $p + 22) {
            fclose($fh);
            return null;
        }
        $n = unpack('v', substr($cola, $p + 10, 2))[1];
        $cdSize = unpack('V', substr($cola, $p + 12, 4))[1];
        if ($n === 0xFFFF || $cdSize === 0xFFFFFFFF) {
            $loc = strrpos(substr($cola, 0, $p), "PK\x06\x07");
            if ($loc !== false && strlen($cola) >= $loc + 20) {
                $off = unpack('P', substr($cola, $loc + 8, 8))[1];
                if ($off >= 0 && $off < $size) {
                    fseek($fh, $off);
                    $z = (string)fread($fh, 56);
                    if (strlen($z) >= 56 && strncmp($z, "PK\x06\x06", 4) === 0) {
                        $n = unpack('P', substr($z, 32, 8))[1];
                        $cdSize = unpack('P', substr($z, 40, 8))[1];
                    }
                }
            }
        }
        fclose($fh);
        if ($cdSize > 4194304) {
            return PHP_INT_MAX;
        }
        return (int)$n;
    }

    /** Lee una entrada como máximo $limite bytes; si excede, error. */
    private static function leerBytes(\ZipArchive $zip, string $name, int $limite): string
    {
        $h = $zip->getStream($name);
        if (!$h) {
            throw self::err('corrupto');
        }
        $buf = '';
        while (!feof($h)) {
            $c = fread($h, 65536);
            if ($c === false || $c === '') {
                break;
            }
            $buf .= $c;
            if (strlen($buf) > $limite) {
                fclose($h);
                throw self::err('zip_sospechoso');
            }
        }
        fclose($h);
        return $buf;
    }

    /** Recorre un XML por bloques buscando DOCTYPE/ENTITY o UTF-16. */
    private static function escanearXml(\ZipArchive $zip, string $name): void
    {
        $h = $zip->getStream($name);
        if (!$h) {
            throw self::err('corrupto');
        }
        $total = 0;
        $prev = '';
        $primero = true;
        while (!feof($h)) {
            $c = fread($h, 65536);
            if ($c === false || $c === '') {
                break;
            }
            $total += strlen($c);
            if ($total > self::MAX_XML_BYTES) {
                fclose($h);
                throw self::err('zip_sospechoso');
            }
            if ($primero) {
                $primero = false;
                $b4 = substr($c, 0, 4);
                if (strpos($b4, "\0") !== false || strncmp($c, "\xFF\xFE", 2) === 0 || strncmp($c, "\xFE\xFF", 2) === 0) {
                    fclose($h);
                    throw self::err('zip_sospechoso');
                }
            }
            $buf = $prev . $c;
            if (stripos($buf, '<!doctype') !== false || stripos($buf, '<!entity') !== false) {
                fclose($h);
                throw self::err('zip_sospechoso');
            }
            $prev = substr($buf, -16);
        }
        fclose($h);
    }

    /** XML leído con tope duro, sin DOCTYPE, como DOMDocument sin red ni entidades. */
    private static function cargarXml(\ZipArchive $zip, string $name): ?\DOMDocument
    {
        if ($zip->locateName($name) === false) {
            return null;
        }
        $xml = self::leerBytes($zip, $name, self::MAX_XML_BYTES);
        $b4 = substr($xml, 0, 4);
        if (strpos($b4, "\0") !== false || strncmp($xml, "\xFF\xFE", 2) === 0 || strncmp($xml, "\xFE\xFF", 2) === 0
            || stripos($xml, '<!doctype') !== false || stripos($xml, '<!entity') !== false) {
            throw self::err('zip_sospechoso');
        }
        self::memoriaOk();
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        unset($xml);
        if (!$ok || !$dom->documentElement) {
            throw self::err('corrupto');
        }
        return $dom;
    }

    /** ¿Cabe `$extra` bytes más sin acercarse al límite de memoria? */
    public static function memoriaDisponible(int $extra): bool
    {
        return memory_get_usage() + $extra <= (int)(self::limiteMemoria() * 0.8);
    }

    private static function limiteMemoria(): int
    {
        $v = trim((string)ini_get('memory_limit'));
        if ($v === '' || $v === '-1') {
            return 536870912;
        }
        $n = (int)$v;
        $u = strtolower(substr($v, -1));
        if ($u === 'g') {
            $n *= 1073741824;
        } elseif ($u === 'm') {
            $n *= 1048576;
        } elseif ($u === 'k') {
            $n *= 1024;
        }
        return $n > 0 ? min($n, 1073741824) : 536870912;
    }

    /** Aborta con zip_sospechoso si el proceso se acerca al límite de memoria. */
    private static function memoriaOk(int $extra = 0): void
    {
        if (memory_get_usage() + $extra > (int)(self::limiteMemoria() * 0.8)) {
            throw self::err('zip_sospechoso');
        }
    }

    // ------------------------------------------------------------------
    // Rutas y relaciones OPC
    // ------------------------------------------------------------------

    private static function resolver(string $baseParte, string $target): ?string
    {
        if ($target === '' || preg_match('#^[a-z]+:#i', $target)) {
            return null;
        }
        if ($target[0] === '/') {
            $full = ltrim($target, '/');
        } else {
            $dir = dirname($baseParte);
            $full = ($dir === '.' ? '' : $dir . '/') . $target;
        }
        $out = [];
        foreach (explode('/', str_replace('\\', '/', $full)) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if (!$out) {
                    return null;
                }
                array_pop($out);
                continue;
            }
            $out[] = $seg;
        }
        return $out ? implode('/', $out) : null;
    }

    /** @return array<string,array{type:string,target:string}> */
    private static function relaciones(\ZipArchive $zip, string $parte): array
    {
        $dir = dirname($parte);
        $rels = ($dir === '.' ? '' : $dir . '/') . '_rels/' . basename($parte) . '.rels';
        $dom = self::cargarXml($zip, $rels);
        $o = [];
        if (!$dom) {
            return $o;
        }
        foreach ($dom->getElementsByTagNameNS(self::NS_PKG_REL, 'Relationship') as $r) {
            /** @var \DOMElement $r */
            if (strtolower($r->getAttribute('TargetMode')) === 'external') {
                continue;
            }
            $id = $r->getAttribute('Id');
            $t = self::resolver($parte, $r->getAttribute('Target'));
            if ($id !== '' && $t !== null) {
                $o[$id] = ['type' => $r->getAttribute('Type'), 'target' => $t];
            }
        }
        return $o;
    }

    // ------------------------------------------------------------------
    // PPTX
    // ------------------------------------------------------------------

    /** @return array{texto:string,imagenes:array,paginas:int} */
    private static function extraerPptx(\ZipArchive $zip, string $workdir): array
    {
        $pres = self::cargarXml($zip, 'ppt/presentation.xml');
        if (!$pres) {
            throw self::err('corrupto');
        }
        $rels = self::relaciones($zip, 'ppt/presentation.xml');
        $slides = [];
        foreach ($pres->getElementsByTagNameNS(self::NS_P, 'sldId') as $s) {
            /** @var \DOMElement $s */
            $rid = $s->getAttributeNS(self::NS_R, 'id');
            if ($rid !== '' && isset($rels[$rid])) {
                $slides[] = $rels[$rid]['target'];
            }
        }
        unset($pres);
        if (!$slides) {
            // Respaldo: ordenar ppt/slides/slideN.xml por número.
            $tmp = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nm = (string)$zip->getNameIndex($i);
                if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $nm, $m)) {
                    $tmp[(int)$m[1]] = $nm;
                }
            }
            ksort($tmp);
            $slides = array_values($tmp);
        }
        $slides = array_slice($slides, 0, self::MAX_DIAPOS);

        $texto = '';
        $imgRutas = [];
        $n = 0;
        foreach ($slides as $slidePath) {
            $n++;
            $dom = self::cargarXml($zip, $slidePath);
            if (!$dom) {
                continue;
            }
            $lineas = [];
            self::walkDrawing($dom->documentElement, $lineas, false);
            $srels = self::relaciones($zip, $slidePath);
            $notas = [];
            foreach ($srels as $r) {
                $t = substr($r['type'], strrpos($r['type'], '/') + 1);
                if ($t === 'notesSlide') {
                    $nd = self::cargarXml($zip, $r['target']);
                    if ($nd) {
                        self::walkDrawing($nd->documentElement, $notas, false);
                    }
                } elseif ($t === 'image') {
                    $imgRutas[] = $r['target'];
                }
            }
            unset($dom);
            $bloque = '[Diapositiva ' . $n . "]\n" . implode("\n", $lineas);
            if ($notas) {
                $bloque .= "\nNotas: " . implode(' ', $notas);
            }
            $texto .= $bloque . "\n\n";
            if (strlen($texto) > self::MAX_TEXTO * 3) {
                break;
            }
            self::memoriaOk();
        }
        return [
            'texto' => $texto,
            'imagenes' => self::procesarImagenes($zip, $imgRutas, $workdir),
            'paginas' => max(1, count($slides)),
        ];
    }

    /** Recorre nodos DrawingML recogiendo párrafos y tablas en orden. */
    private static function walkDrawing(\DOMNode $nodo, array &$lineas, bool $titulo): void
    {
        foreach ($nodo->childNodes as $c) {
            if (!($c instanceof \DOMElement)) {
                continue;
            }
            $ln = $c->localName;
            $ns = $c->namespaceURI;
            if ($ns === self::NS_A && $ln === 'p') {
                $t = self::parrafoA($c);
                if ($t !== '') {
                    $lineas[] = ($titulo ? 'Título: ' : '') . $t;
                }
                continue;
            }
            if ($ns === self::NS_A && $ln === 'tbl') {
                foreach ($c->childNodes as $tr) {
                    if ($tr instanceof \DOMElement && $tr->localName === 'tr') {
                        $celdas = [];
                        foreach ($tr->childNodes as $tc) {
                            if ($tc instanceof \DOMElement && $tc->localName === 'tc') {
                                $partes = [];
                                self::walkDrawing($tc, $partes, false);
                                $celdas[] = trim(implode(' ', $partes));
                            }
                        }
                        $fila = trim(implode(' | ', $celdas), " |");
                        if ($fila !== '') {
                            $lineas[] = $fila;
                        }
                    }
                }
                continue;
            }
            if ($ns === self::NS_P && $ln === 'sp') {
                $ph = self::tipoPlaceholder($c);
                if (in_array($ph, ['sldNum', 'dt', 'ftr', 'hdr', 'sldImg'], true)) {
                    continue;
                }
                self::walkDrawing($c, $lineas, $ph === 'title' || $ph === 'ctrTitle');
                continue;
            }
            self::walkDrawing($c, $lineas, $titulo);
        }
    }

    private static function tipoPlaceholder(\DOMElement $sp): string
    {
        foreach ($sp->getElementsByTagNameNS(self::NS_P, 'ph') as $ph) {
            /** @var \DOMElement $ph */
            return $ph->getAttribute('type');
        }
        return '';
    }

    private static function parrafoA(\DOMElement $p): string
    {
        $s = '';
        foreach ($p->childNodes as $c) {
            if (!($c instanceof \DOMElement) || $c->namespaceURI !== self::NS_A) {
                continue;
            }
            if ($c->localName === 'r') {
                foreach ($c->childNodes as $t) {
                    if ($t instanceof \DOMElement && $t->localName === 't') {
                        $s .= $t->textContent;
                    }
                }
            } elseif ($c->localName === 'br') {
                $s .= ' ';
            } elseif ($c->localName === 'fld') {
                $tipo = strtolower($c->getAttribute('type'));
                if (strpos($tipo, 'slidenum') === false && strpos($tipo, 'datetime') === false) {
                    foreach ($c->childNodes as $t) {
                        if ($t instanceof \DOMElement && $t->localName === 't') {
                            $s .= $t->textContent;
                        }
                    }
                }
            }
        }
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    // ------------------------------------------------------------------
    // DOCX
    // ------------------------------------------------------------------

    /** @return array{texto:string,imagenes:array,paginas:int} */
    private static function extraerDocx(\ZipArchive $zip, string $workdir): array
    {
        $doc = self::cargarXml($zip, 'word/document.xml');
        if (!$doc) {
            throw self::err('corrupto');
        }
        $estilos = self::estilosDocx($zip);
        $lineas = [];
        $body = $doc->getElementsByTagNameNS(self::NS_W, 'body')->item(0);
        $paginas = 1;
        if ($body) {
            self::walkWord($body, $lineas, $estilos);
        }
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('w', self::NS_W);
        $xp->registerNamespace('a', self::NS_A);
        $xp->registerNamespace('r', self::NS_R);
        $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
        $saltos = (int)$xp->evaluate('count(//w:br[@w:type="page"]) + count(//w:lastRenderedPageBreak)');
        $paginas = max(1, $saltos + 1);
        $rids = [];
        foreach ($xp->query('//a:blip/@r:embed | //v:imagedata/@r:id') as $at) {
            $rids[] = $at->nodeValue;
        }
        unset($doc, $xp);

        // Encabezados y pies de página (suelen traer datos de contacto).
        $extra = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nm = (string)$zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer)\d*\.xml$#', $nm)) {
                $hd = self::cargarXml($zip, $nm);
                if ($hd) {
                    $tmp = [];
                    self::walkWord($hd->documentElement, $tmp, $estilos);
                    foreach ($tmp as $l) {
                        $extra[mb_strtolower($l, 'UTF-8')] = $l;
                    }
                }
            }
            if (count($extra) > 100) {
                break;
            }
        }
        $texto = implode("\n", $lineas);
        if ($extra) {
            $texto .= "\n\n[Encabezado/Pie]\n" . implode("\n", array_values($extra));
        }

        $rels = self::relaciones($zip, 'word/document.xml');
        $imgRutas = [];
        foreach ($rids as $rid) {
            if (isset($rels[$rid]) && substr($rels[$rid]['type'], -6) === '/image') {
                $imgRutas[] = $rels[$rid]['target'];
            }
        }
        // Páginas declaradas por Word, si existen.
        $app = null;
        try {
            $app = self::cargarXml($zip, 'docProps/app.xml');
        } catch (ParserError $e) {
            $app = null;
        }
        if ($app) {
            $pg = $app->getElementsByTagName('Pages')->item(0);
            if ($pg && (int)$pg->textContent > 0) {
                $paginas = min(5000, (int)$pg->textContent);
            }
        }
        return [
            'texto' => $texto,
            'imagenes' => self::procesarImagenes($zip, $imgRutas, $workdir),
            'paginas' => $paginas,
        ];
    }

    /** @return array<string,string> id de estilo => nombre en minúsculas */
    private static function estilosDocx(\ZipArchive $zip): array
    {
        $o = [];
        try {
            $d = self::cargarXml($zip, 'word/styles.xml');
        } catch (ParserError $e) {
            return $o;
        }
        if (!$d) {
            return $o;
        }
        foreach ($d->getElementsByTagNameNS(self::NS_W, 'style') as $st) {
            /** @var \DOMElement $st */
            $id = $st->getAttributeNS(self::NS_W, 'styleId');
            $nm = '';
            foreach ($st->childNodes as $c) {
                if ($c instanceof \DOMElement && $c->localName === 'name') {
                    $nm = strtolower($c->getAttributeNS(self::NS_W, 'val'));
                }
            }
            if ($id !== '') {
                $o[$id] = $nm;
            }
        }
        return $o;
    }

    private static function walkWord(\DOMNode $nodo, array &$lineas, array $estilos): void
    {
        foreach ($nodo->childNodes as $c) {
            if (!($c instanceof \DOMElement) || $c->namespaceURI !== self::NS_W) {
                continue;
            }
            switch ($c->localName) {
                case 'p':
                    $t = self::parrafoW($c, $estilos);
                    if ($t !== '') {
                        $lineas[] = $t;
                    }
                    break;
                case 'tbl':
                    foreach ($c->childNodes as $tr) {
                        if ($tr instanceof \DOMElement && $tr->localName === 'tr') {
                            $celdas = [];
                            foreach ($tr->childNodes as $tc) {
                                if ($tc instanceof \DOMElement && $tc->localName === 'tc') {
                                    $partes = [];
                                    self::walkWord($tc, $partes, []);
                                    $celdas[] = trim(implode(' ', $partes));
                                }
                            }
                            $fila = trim(implode(' | ', $celdas), ' |');
                            if ($fila !== '') {
                                $lineas[] = $fila;
                            }
                        }
                    }
                    break;
                case 'sdt':
                case 'sdtContent':
                case 'customXml':
                case 'body':
                case 'hdr':
                case 'ftr':
                    self::walkWord($c, $lineas, $estilos);
                    break;
            }
        }
    }

    private static function parrafoW(\DOMElement $p, array $estilos): string
    {
        $s = '';
        $stack = [$p];
        // Recorrido en orden de documento limitado a elementos de texto.
        $it = function (\DOMNode $n) use (&$it, &$s): void {
            foreach ($n->childNodes as $c) {
                if (!($c instanceof \DOMElement)) {
                    continue;
                }
                if ($c->namespaceURI === self::NS_W) {
                    $ln = $c->localName;
                    if ($ln === 't') {
                        $s .= $c->textContent;
                        continue;
                    }
                    if ($ln === 'tab' || $ln === 'br' || $ln === 'cr') {
                        $s .= ' ';
                        continue;
                    }
                    if ($ln === 'pPr' || $ln === 'rPr' || $ln === 'delText' || $ln === 'instrText') {
                        continue;
                    }
                }
                $it($c);
            }
        };
        $it($p);
        unset($stack);
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if ($s === '') {
            return '';
        }
        $estilo = '';
        $lista = false;
        foreach ($p->childNodes as $c) {
            if ($c instanceof \DOMElement && $c->localName === 'pPr') {
                foreach ($c->childNodes as $x) {
                    if ($x instanceof \DOMElement) {
                        if ($x->localName === 'pStyle') {
                            $estilo = $x->getAttributeNS(self::NS_W, 'val');
                        } elseif ($x->localName === 'numPr') {
                            $lista = true;
                        }
                    }
                }
            }
        }
        $nombre = $estilos[$estilo] ?? '';
        $nivel = 0;
        if (preg_match('/^(?:heading|t[ií]tulo|encabezado)\s*(\d)$/u', $nombre, $m) || preg_match('/^(?:heading|ttulo|titulo)(\d)$/i', $estilo, $m)) {
            $nivel = max(1, min(4, (int)$m[1])) + 1;
        } elseif ($nombre === 'title' || $nombre === 'título' || $nombre === 'titulo' || preg_match('/^(title|ttulo)$/i', $estilo)) {
            $nivel = 1;
        } elseif ($nombre === 'subtitle' || $nombre === 'subtítulo' || preg_match('/^subt/i', $estilo)) {
            $nivel = 3;
        }
        if ($nivel > 0) {
            return str_repeat('#', $nivel) . ' ' . $s;
        }
        return ($lista ? '- ' : '') . $s;
    }

    // ------------------------------------------------------------------
    // Imágenes
    // ------------------------------------------------------------------

    /**
     * Procesa imágenes (en orden, sin duplicados) hacia $workdir.
     *
     * @param string[] $rutas rutas dentro del ZIP
     * @return array<int,array{archivo:string,w:int,h:int,hash:string}>
     */
    private static function procesarImagenes(\ZipArchive $zip, array $rutas, string $workdir): array
    {
        $out = [];
        $sha = [];
        $hashes = [];
        $leidos = 0;
        $vistos = [];
        foreach ($rutas as $ruta) {
            if (count($out) >= self::MAX_IMAGENES || $leidos > 120000000) {
                break;
            }
            if (isset($vistos[$ruta])) {
                continue;
            }
            $vistos[$ruta] = true;
            $idx = $zip->locateName($ruta);
            if ($idx === false) {
                continue;
            }
            $st = $zip->statIndex($idx);
            if ($st === false || $st['size'] > self::MAX_BYTES_IMAGEN || $st['size'] < 2000) {
                continue;
            }
            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue; // emf, wmf, svg, gif, tiff...
            }
            self::memoriaOk((int)$st['size'] * 3);
            $bytes = self::leerBytes($zip, $ruta, self::MAX_BYTES_IMAGEN);
            $leidos += strlen($bytes);
            $h = sha1($bytes);
            if (isset($sha[$h])) {
                continue;
            }
            $sha[$h] = true;
            $r = self::imagen($bytes, $workdir, count($out) + 1, $hashes);
            unset($bytes);
            if ($r !== null) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * @param array<int,array{0:string,1:array}> $hashes dHash + color de las ya aceptadas
     * @return array{archivo:string,w:int,h:int,hash:string}|null
     */
    private static function imagen(string $bytes, string $workdir, int $num, array &$hashes): ?array
    {
        $info = @getimagesizefromstring($bytes);
        if (!$info || !isset($info[2])) {
            return null;
        }
        [$w, $h, $tipo] = [(int)$info[0], (int)$info[1], (int)$info[2]];
        if (!in_array($tipo, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELES) {
            return null;
        }
        if (min($w, $h) < self::MIN_LADO) {
            return null; // iconos, viñetas y logos diminutos
        }
        if (max($w, $h) / min($w, $h) > 6) {
            return null; // líneas, banners finos
        }
        $est = $w * $h * 4 * 2 + 4194304;
        if (memory_get_usage() + $est > (int)(self::limiteMemoria() * 0.8)) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return null;
        }
        $alpha = ($tipo === IMAGETYPE_PNG || $tipo === IMAGETYPE_WEBP);
        return self::guardarGd($src, $w, $h, $alpha, $workdir, $num, $hashes, false);
    }

    /**
     * Dedupe perceptual (estricto para logos) + reducción + recompresión de una imagen GD (se destruye).
     *
     * @param mixed $src GdImage
     * @param array<int,array{0:string,1:array}> $hashes
     * @return array{archivo:string,w:int,h:int,hash:string}|null
     */
    private static function guardarGd($src, int $w, int $h, bool $alpha, string $workdir, int $num, array &$hashes, bool $estricto): ?array
    {
        // Firma perceptual: dHash 9x8 + color medio.
        $th = imagecreatetruecolor(9, 8);
        imagecopyresampled($th, $src, 0, 0, 0, 0, 9, 8, $w, $h);
        $gris = [];
        $rs = $gs = $bs = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 9; $x++) {
                $c = imagecolorat($th, $x, $y);
                $rr = ($c >> 16) & 255;
                $gg = ($c >> 8) & 255;
                $bb = $c & 255;
                $gris[$y][$x] = (int)(0.299 * $rr + 0.587 * $gg + 0.114 * $bb);
                $rs += $rr;
                $gs += $gg;
                $bs += $bb;
            }
        }
        unset($th);
        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $gris[$y][$x] > $gris[$y][$x + 1] ? '1' : '0';
            }
        }
        $col = [(int)($rs / 72), (int)($gs / 72), (int)($bs / 72)];
        $maxD = $estricto ? 0 : 3;
        $maxC = $estricto ? 6 : 30;
        foreach ($hashes as $hh) {
            $d = 0;
            for ($i = 0; $i < 64; $i++) {
                if ($hh[0][$i] !== $bits[$i]) {
                    $d++;
                }
            }
            $dc = abs($hh[1][0] - $col[0]) + abs($hh[1][1] - $col[1]) + abs($hh[1][2] - $col[2]);
            if ($d <= $maxD && $dc <= $maxC) {
                imagedestroy($src);
                return null; // duplicado (incluye versiones reescaladas)
            }
        }
        $hashes[] = [$bits, $col];
        $hex = '';
        for ($i = 0; $i < 64; $i += 4) {
            $hex .= dechex(bindec(substr($bits, $i, 4)));
        }
        $hex .= sprintf('%02x%02x%02x', $col[0], $col[1], $col[2]);

        // Reducción y recompresión.
        $nw = $w;
        $nh = $h;
        $m = max($w, $h);
        if ($m > self::MAX_LADO_SALIDA) {
            $f = self::MAX_LADO_SALIDA / $m;
            $nw = max(1, (int)round($w * $f));
            $nh = max(1, (int)round($h * $f));
        }
        $dst = imagecreatetruecolor($nw, $nh);
        $usaWebp = function_exists('imagewebp');
        if ($alpha && $usaWebp) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $ext = $usaWebp ? 'webp' : 'jpg';
        $file = rtrim($workdir, '/') . '/' . sprintf('img_%02d.%s', $num, $ext);
        $ok = $usaWebp ? @imagewebp($dst, $file, 82) : @imagejpeg($dst, $file, 85);
        imagedestroy($dst);
        if (!$ok || !is_file($file)) {
            return null;
        }
        return ['archivo' => $file, 'w' => $nw, 'h' => $nh, 'hash' => $hex];
    }

    // ------------------------------------------------------------------
    // Imágenes de PDF
    // ------------------------------------------------------------------

    /**
     * Imágenes de un PDF (logo y fotos). Acepta también imágenes pequeñas (>=120 px)
     * que parecen un logo (`logo_cand`). Ordena por página y aparición.
     *
     * @return array<int,array{archivo:string,w:int,h:int,hash:string,orden:int,pagina:?int,logo_cand:bool}>
     */
    private static function extraerImagenesPdf(string $path, string $workdir): array
    {
        if (!extension_loaded('gd') || !function_exists('inflate_init')) {
            return [];
        }
        if (!is_dir($workdir) && !@mkdir($workdir, 0775, true) && !is_dir($workdir)) {
            return [];
        }
        $ex = new PdfImageExtractor($path, microtime(true) + self::PDF_SEGUNDOS);
        $metas = $ex->imagenes();
        if (!$metas) {
            return [];
        }
        $mayor = 0;
        foreach ($metas as $m) {
            $mayor = max($mayor, $m['w'] * $m['h']);
        }
        $cands = [];
        foreach ($metas as $m) {
            $w = $m['w'];
            $h = $m['h'];
            if ($w < self::PDF_MIN_LADO || $h < self::PDF_MIN_LADO || max($w, $h) / min($w, $h) > 8 || $w * $h > self::MAX_PIXELES) {
                continue; // iconos, líneas decorativas
            }
            if (min($w, $h) < self::MIN_LADO && !self::formaLogo($w, $h, $mayor)) {
                continue;
            }
            $cands[] = $m;
        }
        usort($cands, static function (array $a, array $b): int {
            return [$a['pagina'] ?? PHP_INT_MAX, $a['orden']] <=> [$b['pagina'] ?? PHP_INT_MAX, $b['orden']];
        });
        $solo = count($cands) === 1;
        $out = [];
        $hashes = [];
        $sha = [];
        $lectura = 0;
        $uniformes = [];
        foreach ($cands as $m) {
            if (count($out) >= self::MAX_IMAGENES) {
                break;
            }
            $r = self::procesarImagenPdf($ex, $m, $workdir, count($out) + 1, $hashes, $sha, $mayor, false);
            if ($r === 'uniforme') {
                $uniformes[] = $m;
            } elseif (is_array($r)) {
                $out[] = $r;
            }
        }
        if (!$out && $solo && $uniformes) {
            $r = self::procesarImagenPdf($ex, $uniformes[0], $workdir, 1, $hashes, $sha, $mayor, true);
            if (is_array($r)) {
                $out[] = $r;
            }
        }
        return $out;
    }

    private static function formaLogo(int $w, int $h, int $mayor): bool
    {
        $asp = $w / $h;
        return $asp >= 0.25 && $asp <= 5 && ($w * $h <= 360000 || $w * $h <= 0.4 * $mayor);
    }

    /**
     * @param array{obj:int,w:int,h:int,orden:int,pagina:?int} $m
     * @param array<int,array{0:string,1:array}> $hashes
     * @param array<string,bool> $sha
     * @return array|string|null array = imagen, 'uniforme' = fondo casi uniforme, null = descartada
     */
    private static function procesarImagenPdf(PdfImageExtractor $ex, array $m, string $workdir, int $num, array &$hashes, array &$sha, int $mayor, bool $forzar)
    {
        try {
            $c = $ex->cargar($m);
            if ($c === null) {
                return null;
            }
            if (isset($sha[$c['sha']])) {
                imagedestroy($c['img']);
                return null;
            }
            $sha[$c['sha']] = true;
            $w = (int)$c['w'];
            $h = (int)$c['h'];
            $e = self::muestraPdf($c['img'], $w, $h);
            if (!$forzar && $e['std'] < 4.0 && $e['transp'] < 0.02) {
                imagedestroy($c['img']);
                unset($sha[$c['sha']]);
                return 'uniforme';
            }
            $pocos = $e['dominantes'] >= 0.85 || $e['transp'] >= 0.1;
            $logo = $pocos && self::formaLogo($w, $h, $mayor);
            if (min($w, $h) < self::MIN_LADO && !$logo) {
                imagedestroy($c['img']);
                return null;
            }
            $r = self::guardarGd($c['img'], $w, $h, (bool)$c['alpha'], $workdir, $num, $hashes, $logo);
            if ($r === null) {
                return null;
            }
            $r['orden'] = (int)$m['orden'];
            $r['pagina'] = $m['pagina'];
            $r['logo_cand'] = $logo;
            return $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Estadística sobre ~4000 píxeles: fracción cubierta por los 8 colores más
     * frecuentes (cuantizados a 5 bits; alta = pocos colores, típico de un logo),
     * fracción transparente y desviación de luminosidad.
     *
     * @param mixed $img
     * @return array{dominantes:float,transp:float,std:float}
     */
    private static function muestraPdf($img, int $w, int $h): array
    {
        $paso = max(1, (int)floor(sqrt($w * $h / 4000)));
        $col = [];
        $n = 0;
        $tr = 0;
        $s = 0.0;
        $s2 = 0.0;
        $op = 0;
        for ($y = (int)($paso / 2); $y < $h; $y += $paso) {
            for ($x = (int)($paso / 2); $x < $w; $x += $paso) {
                $c = imagecolorat($img, $x, $y);
                $n++;
                if ((($c >> 24) & 0x7F) >= 64) {
                    $tr++;
                    continue;
                }
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;
                $k = (($r >> 3) << 10) | (($g >> 3) << 5) | ($b >> 3);
                $col[$k] = ($col[$k] ?? 0) + 1;
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $s += $l;
                $s2 += $l * $l;
                $op++;
            }
        }
        $std = 0.0;
        if ($op > 0) {
            $med = $s / $op;
            $std = sqrt(max(0.0, $s2 / $op - $med * $med));
        }
        rsort($col);
        $top = array_sum(array_slice($col, 0, 8));
        return ['dominantes' => $op > 0 ? $top / $op : 0.0, 'transp' => $n > 0 ? $tr / $n : 0.0, 'std' => $std];
    }
}
