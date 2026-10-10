<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * Extractor local de imágenes de PDF en PHP puro (GD + zlib; Imagick opcional).
 *
 * No ejecuta nada del PDF ni sigue enlaces: solo localiza objetos
 * `N G obj << ... /Subtype /Image ... >> stream`, decodifica sus filtros con
 * límites duros (bombas de descompresión, píxeles, memoria, tiempo) y entrega
 * imágenes GD. Cualquier fallo interno de una imagen devuelve null.
 *
 * Soporta: DCTDecode (JPEG, CMYK con Imagick o con /Decode invertido),
 * FlateDecode (+ predictores PNG 10-15 y TIFF 2 en 8 bits), ASCII85, ASCIIHex,
 * RunLength; DeviceGray/RGB, CalGray/CalRGB, ICCBased (N 1/3), Indexed (1/2/4/8
 * bits, ref. indirectas y ObjStm), 16 bits, /Decode invertido, /SMask (alfa).
 * No soporta: JBIG2, CCITT, JPX, LZW, ImageMask, CMYK sin comprimir, Separation.
 */
final class PdfImageExtractor
{
    public const MAX_LECTURA = 67108864;      // bytes del PDF que se leen
    public const MAX_PIXELES = 40000000;
    public const MAX_STREAM = 15728640;       // 15 MB de salida por flujo genérico
    public const MAX_OBJSTM = 8388608;        // 8 MB por ObjStm
    public const MAX_OBJSTM_TOTAL = 33554432; // 32 MB entre todos los ObjStm
    public const MAX_OBJETOS = 300000;

    private const KR = "\x01r";
    private const KS = "\x01s";
    private const WS = " \n\r\t\f\0";
    private const DELIM = " \n\r\t\f\0()<>[]{}/%";

    private string $d = '';
    /** @var array<int,int> número de objeto => posición tras "obj" */
    private array $pos = [];
    /** @var int[] offsets de cabeceras de objeto (ordenados) */
    private array $starts = [];
    /** @var int[] posición tras "obj" por cabecera */
    private array $after = [];
    /** @var int[] número por cabecera */
    private array $nums = [];
    /** @var int[] */
    private array $stmNums = [];
    /** @var array<int,array{0:int,1:int}>|null */
    private ?array $inStm = null;
    /** @var array<int,string> */
    private array $stmData = [];
    private int $stmBytes = 0;
    /** @var array<int,array> */
    private array $cache = [];
    private float $deadline;
    /** @var array<int,array>|null */
    private ?array $metas = null;
    /** @var array<int,bool> */
    private array $maskObjs = [];

    public function __construct(string $path, float $deadline = 0.0)
    {
        $this->deadline = $deadline > 0 ? $deadline : microtime(true) + 8.0;
        try {
            $d = @file_get_contents($path, false, null, 0, self::MAX_LECTURA);
            if (is_string($d) && $d !== '' && strncmp($d, '%PDF-', 5) === 0 && !preg_match('#/Encrypt\s*(?:\d+\s+\d+\s+R|<<)#', $d)) {
                $this->d = $d;
            }
        } catch (\Throwable $e) {
            $this->d = '';
        }
    }

    // ------------------------------------------------------------------
    // Listado
    // ------------------------------------------------------------------

    /**
     * @return array<int,array{obj:int,w:int,h:int,orden:int,pagina:?int}>
     */
    public function imagenes(): array
    {
        if ($this->metas !== null) {
            return $this->metas;
        }
        $this->metas = [];
        try {
            $this->listar();
        } catch (\Throwable $e) {
            // se devuelve lo que se haya reunido
        }
        return $this->metas;
    }

    private function listar(): void
    {
        if ($this->d === '') {
            return;
        }
        $this->escanear();
        if (!$this->starts) {
            return;
        }
        if (!preg_match_all('#/Subtype\s*/Image(?![A-Za-z0-9])#', $this->d, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $vistos = [];
        $imgs = [];
        $n = 0;
        foreach ($m[0] as $mm) {
            if (++$n > 6000 || microtime(true) > $this->deadline) {
                break;
            }
            $i = $this->cabeceraDe((int)$mm[1]);
            if ($i < 0 || isset($vistos[$i])) {
                continue;
            }
            $vistos[$i] = true;
            $num = $this->nums[$i];
            if (($this->pos[$num] ?? -1) !== $this->after[$i]) {
                continue; // versión reemplazada por una posterior
            }
            try {
                $o = $this->obj($num);
            } catch (\Throwable $e) {
                continue;
            }
            $v = $o['v'] ?? null;
            if (!$this->esDict($v) || ($v['Subtype'] ?? null) !== '/Image' || $o['ss'] === null) {
                continue;
            }
            foreach (['SMask', 'Mask'] as $k) {
                $r = $this->refDe($v[$k] ?? null);
                if ($r !== null) {
                    $this->maskObjs[$r] = true;
                }
            }
            $imgs[] = [$num, $v, $i];
        }
        $orden = 0;
        $out = [];
        foreach ($imgs as [$num, $v, $i]) {
            if (isset($this->maskObjs[$num]) || ($v['ImageMask'] ?? false) === true) {
                continue;
            }
            $w = $this->entero($v['Width'] ?? null);
            $h = $this->entero($v['Height'] ?? null);
            if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELES) {
                continue;
            }
            if ($this->filtros($v) === null) {
                continue; // JBIG2, CCITT, JPX, LZW...
            }
            $out[$num] = ['obj' => $num, 'w' => $w, 'h' => $h, 'orden' => $orden++, 'pagina' => null];
        }
        if ($out) {
            try {
                $mapa = $this->mapaPaginas();
            } catch (\Throwable $e) {
                $mapa = [];
            }
            foreach ($out as $num => $_) {
                $out[$num]['pagina'] = $mapa[$num] ?? null;
            }
        }
        $this->metas = array_values($out);
    }

    private function escanear(): void
    {
        $re = '/(?<![0-9A-Za-z])(\d{1,8})[ \t\r\n\f\0]+(\d{1,5})[ \t\r\n\f\0]+obj(?![A-Za-z0-9])/';
        $off = 0;
        $len = strlen($this->d);
        $cuenta = 0;
        while ($off < $len && $cuenta < self::MAX_OBJETOS && preg_match($re, $this->d, $m, PREG_OFFSET_CAPTURE, $off) === 1) {
            $ini = (int)$m[0][1];
            $fin = $ini + strlen($m[0][0]);
            $num = (int)$m[1][0];
            $this->starts[] = $ini;
            $this->after[] = $fin;
            $this->nums[] = $num;
            $this->pos[$num] = $fin;
            $off = $fin;
            $cuenta++;
        }
        if (preg_match_all('#/Type\s*/ObjStm(?![A-Za-z0-9])#', $this->d, $mm, PREG_OFFSET_CAPTURE)) {
            foreach (array_slice($mm[0], 0, 2000) as $x) {
                $i = $this->cabeceraDe((int)$x[1]);
                if ($i >= 0 && ($this->pos[$this->nums[$i]] ?? -1) === $this->after[$i]) {
                    $this->stmNums[$this->nums[$i]] = $this->nums[$i];
                }
            }
        }
    }

    /** Índice de la cabecera de objeto que contiene la posición, o -1. */
    private function cabeceraDe(int $off): int
    {
        $lo = 0;
        $hi = count($this->starts) - 1;
        $r = -1;
        while ($lo <= $hi) {
            $mid = ($lo + $hi) >> 1;
            if ($this->starts[$mid] <= $off) {
                $r = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }
        return $r;
    }

    // ------------------------------------------------------------------
    // Árbol de páginas
    // ------------------------------------------------------------------

    /** @return array<int,int> objeto imagen => página (1-based) */
    private function mapaPaginas(): array
    {
        if (!preg_match_all('#/Root\s+(\d+)\s+\d+\s+R#', $this->d, $m) || !$m[1]) {
            return [];
        }
        $root = (int)end($m[1]);
        $cat = $this->obj($root)['v'] ?? null;
        if (!$this->esDict($cat)) {
            return [];
        }
        $raiz = $this->refDe($cat['Pages'] ?? null);
        if ($raiz === null) {
            return [];
        }
        $mapa = [];
        $pag = 0;
        $visto = [];
        $this->recorrer($raiz, null, $mapa, $pag, $visto, 0);
        return $mapa;
    }

    /**
     * @param mixed $heredado
     */
    private function recorrer(int $n, $heredado, array &$mapa, int &$pag, array &$visto, int $prof): void
    {
        if ($prof > 40 || isset($visto[$n]) || $pag > 5000 || microtime(true) > $this->deadline) {
            return;
        }
        $visto[$n] = true;
        $v = $this->obj($n)['v'] ?? null;
        if (!$this->esDict($v)) {
            return;
        }
        $res = array_key_exists('Resources', $v) ? $v['Resources'] : $heredado;
        $tipo = $v['Type'] ?? '';
        $kids = $this->deref($v['Kids'] ?? null);
        if ($tipo === '/Pages' || (is_array($kids) && $tipo !== '/Page')) {
            if (is_array($kids)) {
                foreach ($kids as $k) {
                    $r = $this->refDe($k);
                    if ($r !== null) {
                        $this->recorrer($r, $res, $mapa, $pag, $visto, $prof + 1);
                    }
                }
            }
            return;
        }
        $pag++;
        $formas = [];
        $this->recursos($res, $pag, $mapa, $formas, 0);
    }

    /**
     * @param mixed $res
     */
    private function recursos($res, int $pag, array &$mapa, array &$formas, int $prof): void
    {
        $res = $this->deref($res);
        if (!$this->esDict($res) || $prof > 3) {
            return;
        }
        $xo = $this->deref($res['XObject'] ?? null);
        if (!$this->esDict($xo)) {
            return;
        }
        $c = 0;
        foreach ($xo as $ref) {
            $r = $this->refDe($ref);
            if ($r === null || ++$c > 3000) {
                continue;
            }
            if (!isset($mapa[$r])) {
                $mapa[$r] = $pag;
            }
            if (isset($formas[$r])) {
                continue;
            }
            $f = $this->obj($r)['v'] ?? null;
            if ($this->esDict($f) && ($f['Subtype'] ?? null) === '/Form') {
                $formas[$r] = true;
                $this->recursos($f['Resources'] ?? null, $pag, $mapa, $formas, $prof + 1);
            }
        }
    }

    // ------------------------------------------------------------------
    // Carga de una imagen
    // ------------------------------------------------------------------

    /**
     * @param array{obj:int} $meta
     * @return array{img:mixed,w:int,h:int,alpha:bool,sha:string}|null img = GdImage truecolor
     */
    public function cargar(array $meta): ?array
    {
        try {
            return $this->cargarImg((int)$meta['obj'], 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{img:mixed,w:int,h:int,alpha:bool,sha:string}|null
     */
    private function cargarImg(int $n, int $prof): ?array
    {
        if (microtime(true) > $this->deadline) {
            return null;
        }
        $o = $this->obj($n);
        $v = $o['v'] ?? null;
        if (!$this->esDict($v) || ($o['ss'] ?? null) === null || ($v['ImageMask'] ?? false) === true) {
            return null;
        }
        $w = $this->entero($v['Width'] ?? null);
        $h = $this->entero($v['Height'] ?? null);
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELES) {
            return null;
        }
        $nombres = $this->filtros($v);
        if ($nombres === null) {
            return null;
        }
        $raw = substr($this->d, (int)$o['ss'], (int)$o['sl']);
        $sha = sha1($raw);
        $gd = null;
        $dct = $nombres && end($nombres) === '/DCTDecode';

        if ($dct) {
            // Cadena (p. ej. Flate -> DCT): la salida es el JPEG completo.
            $dec = $this->decodificar($raw, $v, self::MAX_STREAM);
            unset($raw);
            if ($dec === null || !$dec['dct']) {
                return null;
            }
            $jpg = $dec['data'];
            $info = @getimagesizefromstring($jpg);
            if (!$info || (int)$info[2] !== IMAGETYPE_JPEG) {
                return null;
            }
            $jw = (int)$info[0];
            $jh = (int)$info[1];
            $ch = (int)($info['channels'] ?? 3);
            if ($jw < 1 || $jh < 1 || $jw * $jh > self::MAX_PIXELES || !PresentationParser::memoriaDisponible($jw * $jh * (4 + $ch) + 8388608)) {
                return null;
            }
            if ($ch === 4) {
                $gd = $this->jpegCmyk($jpg, $v);
            } elseif ($ch === 1 || $ch === 3) {
                $gd = @imagecreatefromstring($jpg);
            }
            unset($jpg, $dec);
            if (!$gd) {
                return null;
            }
            $w = $jw;
            $h = $jh;
        } else {
            $gd = $this->crudaAGd($raw, $v, $w, $h);
            unset($raw);
            if (!$gd) {
                return null;
            }
        }
        if (!imageistruecolor($gd)) {
            imagepalettetotruecolor($gd);
        }
        if ($this->decodeInvertido($v)) {
            imagefilter($gd, IMG_FILTER_NEGATE);
        }

        $alpha = false;
        $sm = $this->refDe($v['SMask'] ?? null);
        if ($sm !== null && $prof === 0 && microtime(true) < $this->deadline) {
            $mk = null;
            try {
                $mk = $this->cargarImg($sm, 1);
            } catch (\Throwable $e) {
                $mk = null;
            }
            if ($mk !== null) {
                $gd2 = $this->aplicarMascara($gd, $mk['img'], $w, $h);
                if ($gd2 !== null) {
                    $gd = $gd2;
                    $w = imagesx($gd);
                    $h = imagesy($gd);
                    $alpha = true;
                }
                imagedestroy($mk['img']);
            }
        }
        return ['img' => $gd, 'w' => $w, 'h' => $h, 'alpha' => $alpha, 'sha' => $sha];
    }

    /**
     * Alfa desde una máscara en grises (255 = opaco). Reduce a ~1 Mpx si hace falta.
     *
     * @param mixed $base
     * @param mixed $mask
     * @return mixed|null
     */
    private function aplicarMascara($base, $mask, int $w, int $h)
    {
        $tw = $w;
        $th = $h;
        if ($w * $h > 1000000) {
            $f = sqrt(1000000 / ($w * $h));
            $tw = max(1, (int)($w * $f));
            $th = max(1, (int)($h * $f));
        }
        if (!PresentationParser::memoriaDisponible($tw * $th * 12 + 4194304)) {
            return null;
        }
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $base, 0, 0, 0, 0, $tw, $th, imagesx($base), imagesy($base));
        imagedestroy($base);
        $mk = $mask;
        $liberar = false;
        if (imagesx($mask) !== $tw || imagesy($mask) !== $th) {
            $mk = imagecreatetruecolor($tw, $th);
            imagecopyresampled($mk, $mask, 0, 0, 0, 0, $tw, $th, imagesx($mask), imagesy($mask));
            $liberar = true;
        }
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $m = imagecolorat($mk, $x, $y) & 0xFF;
                if ($m < 255) {
                    $c = imagecolorat($out, $x, $y) & 0xFFFFFF;
                    imagesetpixel($out, $x, $y, $c | ((127 - ($m >> 1)) << 24));
                }
            }
            if (($y & 63) === 0 && microtime(true) > $this->deadline) {
                break;
            }
        }
        if ($liberar) {
            imagedestroy($mk);
        }
        return $out;
    }

    /** @return mixed|null GdImage */
    private function jpegCmyk(string $jpg, array $v)
    {
        if (class_exists('Imagick')) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($jpg);
                $im->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                $im->setImageFormat('png');
                $png = $im->getImageBlob();
                $im->clear();
                $g = @imagecreatefromstring($png);
                return $g ?: null;
            } catch (\Throwable $e) {
                return null;
            }
        }
        // GD asume CMYK "invertido" (Photoshop/Adobe), que es lo que declara /Decode [1 0 ...].
        $dec = $this->deref($v['Decode'] ?? null);
        if (is_array($dec) && count($dec) >= 8 && $this->num($dec[0] ?? 0) == 1 && $this->num($dec[1] ?? 1) == 0) {
            $g = @imagecreatefromstring($jpg);
            return $g ?: null;
        }
        return null;
    }

    /** /Decode [1 0 ...] en Gray/RGB (se aplica con negativo). */
    private function decodeInvertido(array $v): bool
    {
        $dec = $this->deref($v['Decode'] ?? null);
        if (!is_array($dec) || count($dec) < 2 || count($dec) > 6) {
            return false;
        }
        $cs = $this->colorspace($v['ColorSpace'] ?? null, 0);
        if ($cs === null || $cs['t'] === 'idx' || $cs['t'] === 'cmyk') {
            return false;
        }
        for ($i = 0; $i + 1 < count($dec); $i += 2) {
            if (!($this->num($dec[$i]) == 1 && $this->num($dec[$i + 1]) == 0)) {
                return false;
            }
        }
        return count($dec) === ($cs['t'] === 'rgb' ? 6 : 2);
    }

    /**
     * Datos de muestras (Flate/ASCII/RL) a imagen GD por medio de un PNG en memoria.
     *
     * @return mixed|null
     */
    private function crudaAGd(string $raw, array $v, int $w, int $h)
    {
        $cs = $this->colorspace($v['ColorSpace'] ?? null, 0);
        if ($cs === null || $cs['t'] === 'cmyk') {
            return null;
        }
        $bpc = $this->entero($v['BitsPerComponent'] ?? 8);
        $n = $cs['t'] === 'rgb' ? 3 : 1;
        $ok = $cs['t'] === 'rgb' ? [8, 16] : ($cs['t'] === 'idx' ? [1, 2, 4, 8] : [1, 2, 4, 8, 16]);
        if (!in_array($bpc, $ok, true)) {
            return null;
        }
        $fila = intdiv($w * $n * $bpc + 7, 8);
        $esperado = $fila * $h;
        if ($esperado < 1 || !PresentationParser::memoriaDisponible($esperado * 3 + $w * $h * 4 + 4194304)) {
            return null;
        }
        $dec = $this->decodificar($raw, $v, $esperado + $h + 8192, false);
        if ($dec === null || $dec['dct']) {
            return null;
        }
        $data = $dec['data'];
        $pred = $dec['pred'];
        unset($dec);
        if ($pred !== null && $pred['p'] >= 10) {
            if ($pred['colors'] !== $n || $pred['bpc'] !== $bpc || ($pred['cols'] !== $w)) {
                return null;
            }
            $total = ($fila + 1) * $h;
            if (strlen($data) < $total) {
                return null;
            }
            $idat = strlen($data) === $total ? $data : substr($data, 0, $total);
        } else {
            if ($pred !== null && $pred['p'] === 2) {
                if ($bpc !== 8 || $pred['colors'] !== $n || $pred['cols'] !== $w) {
                    return null;
                }
                $data = $this->tiffPredictor($data, $fila, $n, $h);
            } elseif ($pred !== null && $pred['p'] !== 1) {
                return null;
            }
            if (strlen($data) < $esperado) {
                return null;
            }
            if (strlen($data) > $esperado) {
                $data = substr($data, 0, $esperado);
            }
            $idat = "\0" . implode("\0", str_split($data, $fila));
        }
        unset($data);
        $ct = $cs['t'] === 'rgb' ? 2 : ($cs['t'] === 'idx' ? 3 : 0);
        $plte = null;
        if ($ct === 3) {
            $plte = $this->paleta($cs, $bpc);
            if ($plte === null) {
                return null;
            }
        }
        $png = $this->png($w, $h, $bpc, $ct, $idat, $plte);
        unset($idat);
        $g = @imagecreatefromstring($png);
        return $g ?: null;
    }

    private function png(int $w, int $h, int $bd, int $ct, string $filtrado, ?string $plte): string
    {
        $chunk = static function (string $t, string $d): string {
            return pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
        };
        $s = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, $bd, $ct, 0, 0, 0));
        if ($plte !== null) {
            $s .= $chunk('PLTE', $plte);
        }
        $z = gzcompress($filtrado, 1);
        if ($z === false) {
            throw new \RuntimeException('deflate');
        }
        return $s . $chunk('IDAT', $z) . $chunk('IEND', '');
    }

    /** Paleta RGB (3 bytes por entrada) para PNG color 3. */
    private function paleta(array $cs, int $bpc): ?string
    {
        $base = $cs['base'] ?? null;
        $lk = $cs['lookup'] ?? null;
        if ($base === null || !is_string($lk)) {
            return null;
        }
        $max = min(256, 1 << $bpc, max(1, (int)$cs['hival'] + 1));
        $nb = $base['t'] === 'rgb' ? 3 : ($base['t'] === 'cmyk' ? 4 : 1);
        $lk = str_pad($lk, $max * $nb, "\0");
        $p = '';
        for ($i = 0; $i < $max; $i++) {
            $e = substr($lk, $i * $nb, $nb);
            if ($nb === 3) {
                $p .= $e;
            } elseif ($nb === 1) {
                $p .= $e . $e . $e;
            } else {
                $c = ord($e[0]);
                $m = ord($e[1]);
                $y = ord($e[2]);
                $k = ord($e[3]);
                $p .= chr((int)((255 - $c) * (255 - $k) / 255)) . chr((int)((255 - $m) * (255 - $k) / 255)) . chr((int)((255 - $y) * (255 - $k) / 255));
            }
        }
        return $p;
    }

    // ------------------------------------------------------------------
    // Espacios de color
    // ------------------------------------------------------------------

    /**
     * @param mixed $x
     * @return array{t:string,base?:array,hival?:int,lookup?:string}|null t: gray|rgb|cmyk|idx
     */
    private function colorspace($x, int $prof): ?array
    {
        if ($prof > 4) {
            return null;
        }
        $x = $this->deref($x);
        if (is_string($x)) {
            switch ($x) {
                case '/DeviceGray':
                case '/G':
                case '/CalGray':
                    return ['t' => 'gray'];
                case '/DeviceRGB':
                case '/RGB':
                case '/CalRGB':
                    return ['t' => 'rgb'];
                case '/DeviceCMYK':
                case '/CMYK':
                    return ['t' => 'cmyk'];
            }
            return null;
        }
        if (!is_array($x) || !isset($x[0])) {
            return null;
        }
        $head = $this->deref($x[0]);
        if (!is_string($head)) {
            return null;
        }
        if ($head === '/ICCBased') {
            $s = $this->deref($x[1] ?? null);
            if (!$this->esDict($s)) {
                return null;
            }
            $nn = $this->entero($s['N'] ?? 0);
            return $nn === 1 ? ['t' => 'gray'] : ($nn === 3 ? ['t' => 'rgb'] : ($nn === 4 ? ['t' => 'cmyk'] : null));
        }
        if ($head === '/Indexed' || $head === '/I') {
            $base = $this->colorspace($x[1] ?? null, $prof + 1);
            if ($base === null || $base['t'] === 'idx') {
                return null;
            }
            $hival = $this->entero($x[2] ?? 0);
            $lk = $this->deref($x[3] ?? null);
            $bytes = null;
            if (is_array($lk) && isset($lk[self::KS])) {
                $bytes = (string)$lk[self::KS];
            } else {
                $r = $this->refDe($x[3] ?? null);
                if ($r !== null) {
                    $bytes = $this->bytesDeFlujo($r, 65536);
                }
            }
            if ($bytes === null) {
                return null;
            }
            return ['t' => 'idx', 'base' => $base, 'hival' => max(0, min(255, $hival)), 'lookup' => $bytes];
        }
        if (count($x) === 1 || in_array($head, ['/CalGray', '/CalRGB', '/DeviceGray', '/DeviceRGB', '/DeviceCMYK'], true)) {
            return $this->colorspace($head, $prof + 1);
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    /** @return string[]|null lista de filtros normalizados o null si hay alguno no soportado */
    private function filtros(array $v): ?array
    {
        $f = $this->deref($v['Filter'] ?? null);
        if ($f === null) {
            return [];
        }
        $lista = is_array($f) && !isset($f[self::KR]) ? $f : [$f];
        $mapa = ['/FlateDecode' => '/FlateDecode', '/Fl' => '/FlateDecode', '/ASCII85Decode' => '/ASCII85Decode', '/A85' => '/ASCII85Decode',
            '/ASCIIHexDecode' => '/ASCIIHexDecode', '/AHx' => '/ASCIIHexDecode', '/RunLengthDecode' => '/RunLengthDecode', '/RL' => '/RunLengthDecode',
            '/DCTDecode' => '/DCTDecode', '/DCT' => '/DCTDecode'];
        $o = [];
        foreach ($lista as $i => $x) {
            $x = $this->deref($x);
            if (!is_string($x) || !isset($mapa[$x]) || $i > 6) {
                return null;
            }
            if ($mapa[$x] === '/DCTDecode' && $i !== count($lista) - 1) {
                return null;
            }
            $o[] = $mapa[$x];
        }
        return $o;
    }

    /**
     * Aplica la cadena de filtros. Con $aplicarPred=false el último predictor de Flate
     * se devuelve sin deshacer (['pred'] = parámetros).
     *
     * @return array{data:string,dct:bool,pred:?array}|null
     */
    private function decodificar(string $raw, array $v, int $max, bool $aplicarPred = true): ?array
    {
        $nombres = $this->filtros($v);
        if ($nombres === null) {
            return null;
        }
        $dp = $this->deref($v['DecodeParms'] ?? null);
        $parms = [];
        if (is_array($dp) && !$this->esDict($dp)) {
            foreach ($dp as $x) {
                $parms[] = $this->deref($x);
            }
        } else {
            $parms[] = $dp;
        }
        $data = $raw;
        $dct = false;
        $pred = null;
        $ultimo = count($nombres) - 1;
        foreach ($nombres as $i => $f) {
            if (microtime(true) > $this->deadline) {
                return null;
            }
            $pp = $parms[$i] ?? null;
            $pp = $this->esDict($pp) ? $pp : [];
            switch ($f) {
                case '/ASCIIHexDecode':
                    $data = $this->asciiHex($data);
                    break;
                case '/ASCII85Decode':
                    $data = $this->ascii85($data, $max);
                    break;
                case '/RunLengthDecode':
                    $data = $this->runLength($data, $max);
                    break;
                case '/DCTDecode':
                    $dct = true;
                    break;
                case '/FlateDecode':
                    $data = $this->inflar($data, $max);
                    if ($data === null) {
                        return null;
                    }
                    $p = $this->entero($pp['Predictor'] ?? 1);
                    if ($p > 1) {
                        $info = ['p' => $p, 'colors' => $this->entero($pp['Colors'] ?? 1), 'bpc' => $this->entero($pp['BitsPerComponent'] ?? 8), 'cols' => $this->entero($pp['Columns'] ?? 1)];
                        if (!isset($pp['Columns'])) {
                            $info['cols'] = -1;
                        }
                        if ($i === $ultimo && !$aplicarPred) {
                            $pred = $info;
                            // Columns ausente: lo resuelve quien llama (se toma como el ancho)
                            if ($info['cols'] === -1) {
                                $pred['cols'] = $this->entero($v['Width'] ?? 1);
                            }
                        } else {
                            $data = $this->aplicarPredictor($data, $info);
                            if ($data === null) {
                                return null;
                            }
                        }
                    }
                    break;
            }
            if ($data === null) {
                return null;
            }
        }
        return ['data' => $data, 'dct' => $dct, 'pred' => $pred];
    }

    private function aplicarPredictor(string $data, array $info): ?string
    {
        $cols = $info['cols'] < 1 ? 1 : $info['cols'];
        $n = max(1, $info['colors']);
        $bpc = max(1, $info['bpc']);
        $fila = intdiv($cols * $n * $bpc + 7, 8);
        if ($info['p'] >= 10) {
            return $this->pngUnfilter($data, $fila, max(1, intdiv($n * $bpc + 7, 8)));
        }
        if ($info['p'] === 2 && $bpc === 8) {
            return $this->tiffPredictor($data, $fila, $n, intdiv(strlen($data), max(1, $fila)));
        }
        return null;
    }

    private function tiffPredictor(string $data, int $fila, int $n, int $filas): string
    {
        $out = '';
        for ($r = 0; $r < $filas; $r++) {
            $c = array_values(unpack('C*', substr($data, $r * $fila, $fila)) ?: []);
            for ($i = $n, $l = count($c); $i < $l; $i++) {
                $c[$i] = ($c[$i] + $c[$i - $n]) & 255;
            }
            $out .= pack('C*', ...$c);
        }
        return $out;
    }

    private function pngUnfilter(string $data, int $fila, int $bpp): ?string
    {
        $filas = intdiv(strlen($data), $fila + 1);
        $out = '';
        $prev = array_fill(0, $fila, 0);
        for ($r = 0; $r < $filas; $r++) {
            $o = $r * ($fila + 1);
            $ft = ord($data[$o]);
            $c = array_values(unpack('C*', substr($data, $o + 1, $fila)) ?: []);
            if (count($c) < $fila) {
                return null;
            }
            switch ($ft) {
                case 1:
                    for ($i = $bpp; $i < $fila; $i++) {
                        $c[$i] = ($c[$i] + $c[$i - $bpp]) & 255;
                    }
                    break;
                case 2:
                    for ($i = 0; $i < $fila; $i++) {
                        $c[$i] = ($c[$i] + $prev[$i]) & 255;
                    }
                    break;
                case 3:
                    for ($i = 0; $i < $fila; $i++) {
                        $a = $i >= $bpp ? $c[$i - $bpp] : 0;
                        $c[$i] = ($c[$i] + (($a + $prev[$i]) >> 1)) & 255;
                    }
                    break;
                case 4:
                    for ($i = 0; $i < $fila; $i++) {
                        $a = $i >= $bpp ? $c[$i - $bpp] : 0;
                        $b = $prev[$i];
                        $cc = $i >= $bpp ? $prev[$i - $bpp] : 0;
                        $p = $a + $b - $cc;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $cc);
                        $pr = ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $cc);
                        $c[$i] = ($c[$i] + $pr) & 255;
                    }
                    break;
                case 0:
                    break;
                default:
                    return null;
            }
            $out .= pack('C*', ...$c);
            $prev = $c;
        }
        return $out;
    }

    /** Inflado por bloques con tope de salida; null si lo excede (bomba) o está dañado. */
    private function inflar(string $data, int $max): ?string
    {
        foreach ([[ZLIB_ENCODING_DEFLATE, 0], [ZLIB_ENCODING_RAW, 2]] as [$enc, $skip]) {
            $ctx = @inflate_init($enc);
            if ($ctx === false) {
                continue;
            }
            $out = '';
            $n = strlen($data);
            $ok = true;
            $fin = false;
            for ($i = $skip; $i < $n; $i += 8192) {
                try {
                    $r = @inflate_add($ctx, substr($data, $i, 8192), ZLIB_SYNC_FLUSH);
                } catch (\Throwable $e) {
                    $r = false;
                }
                if ($r === false) {
                    $ok = false;
                    break;
                }
                $out .= $r;
                if (strlen($out) > $max) {
                    return null;
                }
                if (inflate_get_status($ctx) === ZLIB_STREAM_END) {
                    $fin = true;
                    break;
                }
            }
            if ($ok && $out !== '' && ($fin || $enc === ZLIB_ENCODING_DEFLATE)) {
                return $out;
            }
            if ($ok && $fin) {
                return $out;
            }
        }
        return null;
    }

    private function asciiHex(string $s): string
    {
        $p = strpos($s, '>');
        if ($p !== false) {
            $s = substr($s, 0, $p);
        }
        $s = (string)preg_replace('/[^0-9A-Fa-f]/', '', $s);
        if (strlen($s) % 2) {
            $s .= '0';
        }
        $r = hex2bin($s);
        return $r === false ? '' : $r;
    }

    private function ascii85(string $s, int $max): ?string
    {
        $s = (string)preg_replace('/[\x00\t\n\f\r ]+/', '', $s);
        if (strncmp($s, '<~', 2) === 0) {
            $s = substr($s, 2);
        }
        $e = strpos($s, '~');
        if ($e !== false) {
            $s = substr($s, 0, $e);
        }
        $out = [];
        $tam = 0;
        $grupo = [];
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === 'z' && !$grupo) {
                $out[] = "\0\0\0\0";
                $tam += 4;
            } else {
                $o = ord($c) - 33;
                if ($o < 0 || $o > 84) {
                    return null;
                }
                $grupo[] = $o;
                if (count($grupo) === 5) {
                    $v = ((($grupo[0] * 85 + $grupo[1]) * 85 + $grupo[2]) * 85 + $grupo[3]) * 85 + $grupo[4];
                    $out[] = pack('N', $v & 0xFFFFFFFF);
                    $tam += 4;
                    $grupo = [];
                }
            }
            if ($tam > $max) {
                return null;
            }
        }
        $k = count($grupo);
        if ($k > 1) {
            for ($j = $k; $j < 5; $j++) {
                $grupo[] = 84;
            }
            $v = ((($grupo[0] * 85 + $grupo[1]) * 85 + $grupo[2]) * 85 + $grupo[3]) * 85 + $grupo[4];
            $out[] = substr(pack('N', $v & 0xFFFFFFFF), 0, $k - 1);
        }
        return implode('', $out);
    }

    private function runLength(string $s, int $max): ?string
    {
        $out = '';
        $n = strlen($s);
        $i = 0;
        while ($i < $n) {
            $l = ord($s[$i++]);
            if ($l === 128) {
                break;
            }
            if ($l < 128) {
                $out .= substr($s, $i, $l + 1);
                $i += $l + 1;
            } elseif ($i < $n) {
                $out .= str_repeat($s[$i++], 257 - $l);
            }
            if (strlen($out) > $max) {
                return null;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Objetos
    // ------------------------------------------------------------------

    /**
     * @return array{v:mixed,ss:?int,sl:int}|null
     */
    private function obj(int $n): ?array
    {
        if (isset($this->cache[$n])) {
            return $this->cache[$n];
        }
        $r = null;
        if (isset($this->pos[$n])) {
            $p = $this->pos[$n];
            $v = $this->val($this->d, $p, 0);
            $r = ['v' => $v, 'ss' => null, 'sl' => 0];
            if ($this->esDict($v)) {
                $this->ws($this->d, $p);
                if (substr($this->d, $p, 6) === 'stream') {
                    $b = $this->limites($v, $p + 6);
                    if ($b !== null) {
                        $r['ss'] = $b[0];
                        $r['sl'] = $b[1];
                    }
                }
            }
        } else {
            $this->construirObjStm();
            if (isset($this->inStm[$n])) {
                [$sn, $po] = $this->inStm[$n];
                $s = $this->stmData[$sn] ?? null;
                if ($s !== null) {
                    $p = $po;
                    $r = ['v' => $this->val($s, $p, 0), 'ss' => null, 'sl' => 0];
                }
            }
        }
        if ($r !== null && count($this->cache) < 4000) {
            $this->cache[$n] = $r;
        }
        return $r;
    }

    /** @return array{0:int,1:int}|null inicio y longitud de los datos del flujo */
    private function limites(array $dict, int $q): ?array
    {
        $d = $this->d;
        $len = strlen($d);
        if ($q < $len && $d[$q] === "\r" && ($d[$q + 1] ?? '') === "\n") {
            $q += 2;
        } elseif ($q < $len && ($d[$q] === "\n" || $d[$q] === "\r")) {
            $q += 1;
        }
        $l = $this->entero($dict['Length'] ?? null, -1);
        if ($l >= 0 && $q + $l <= $len && preg_match('/\G[ \r\n\t\f]*endstream/', $d, $m, 0, $q + $l) === 1) {
            return [$q, $l];
        }
        $e = strpos($d, 'endstream', $q);
        if ($e === false) {
            return null;
        }
        // quita un único salto de línea final
        if ($e > $q && $d[$e - 1] === "\n") {
            $e--;
            if ($e > $q && $d[$e - 1] === "\r") {
                $e--;
            }
        } elseif ($e > $q && $d[$e - 1] === "\r") {
            $e--;
        }
        return [$q, $e - $q];
    }

    private function construirObjStm(): void
    {
        if ($this->inStm !== null) {
            return;
        }
        $this->inStm = [];
        foreach ($this->stmNums as $sn) {
            if (microtime(true) > $this->deadline || $this->stmBytes > self::MAX_OBJSTM_TOTAL) {
                break;
            }
            try {
                $o = $this->obj($sn);
                $v = $o['v'] ?? null;
                if (!$this->esDict($v) || ($o['ss'] ?? null) === null) {
                    continue;
                }
                $data = $this->decodificar(substr($this->d, (int)$o['ss'], (int)$o['sl']), $v, self::MAX_OBJSTM);
                if ($data === null || $data['dct']) {
                    continue;
                }
                $s = $data['data'];
                $cnt = $this->entero($v['N'] ?? 0);
                $first = $this->entero($v['First'] ?? 0);
                if ($cnt < 1 || $cnt > 100000 || $first < 1 || $first > strlen($s)) {
                    continue;
                }
                $this->stmBytes += strlen($s);
                $this->stmData[$sn] = $s;
                if (preg_match_all('/\d+/', substr($s, 0, $first), $mm)) {
                    $t = $mm[0];
                    for ($i = 0; $i < $cnt && 2 * $i + 1 < count($t); $i++) {
                        $on = (int)$t[2 * $i];
                        if (!isset($this->pos[$on]) && !isset($this->inStm[$on])) {
                            $this->inStm[$on] = [$sn, $first + (int)$t[2 * $i + 1]];
                        }
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    private function bytesDeFlujo(int $n, int $max): ?string
    {
        $o = $this->obj($n);
        if (!$o || !$this->esDict($o['v']) || $o['ss'] === null) {
            return null;
        }
        $d = $this->decodificar(substr($this->d, (int)$o['ss'], (int)$o['sl']), $o['v'], $max);
        return ($d === null || $d['dct']) ? null : $d['data'];
    }

    // ------------------------------------------------------------------
    // Tokenizador de objetos PDF
    // ------------------------------------------------------------------

    /**
     * Valores: int|float|bool|null, nombre = string "/Nombre", cadena = [KS=>bytes],
     * referencia = [KR=>n], arreglo = list, diccionario = array asociativo.
     *
     * @return mixed
     */
    private function val(string $s, int &$p, int $prof)
    {
        if ($prof > 24) {
            throw new \RuntimeException('profundidad');
        }
        $this->ws($s, $p);
        $n = strlen($s);
        if ($p >= $n) {
            throw new \RuntimeException('fin');
        }
        $c = $s[$p];
        if ($c === '<') {
            if (($s[$p + 1] ?? '') === '<') {
                $p += 2;
                $d = [];
                $cnt = 0;
                while (true) {
                    $this->ws($s, $p);
                    if ($p >= $n) {
                        throw new \RuntimeException('dict');
                    }
                    if ($s[$p] === '>' && ($s[$p + 1] ?? '') === '>') {
                        $p += 2;
                        break;
                    }
                    $k = $this->val($s, $p, $prof + 1);
                    if (!is_string($k) || $k === '' || $k[0] !== '/' || ++$cnt > 4000) {
                        throw new \RuntimeException('clave');
                    }
                    $d[substr($k, 1)] = $this->val($s, $p, $prof + 1);
                }
                return $d;
            }
            $e = strpos($s, '>', $p);
            if ($e === false) {
                throw new \RuntimeException('hex');
            }
            $hex = (string)preg_replace('/[^0-9A-Fa-f]/', '', substr($s, $p + 1, $e - $p - 1));
            $p = $e + 1;
            if (strlen($hex) % 2) {
                $hex .= '0';
            }
            return [self::KS => (string)hex2bin($hex)];
        }
        if ($c === '[') {
            $p++;
            $a = [];
            while (true) {
                $this->ws($s, $p);
                if ($p >= $n) {
                    throw new \RuntimeException('array');
                }
                if ($s[$p] === ']') {
                    $p++;
                    break;
                }
                $a[] = $this->val($s, $p, $prof + 1);
                if (count($a) > 100000) {
                    throw new \RuntimeException('array grande');
                }
            }
            return $a;
        }
        if ($c === '(') {
            $p++;
            $nivel = 1;
            $o = '';
            while ($p < $n) {
                $ch = $s[$p++];
                if ($ch === '\\') {
                    $nx = $s[$p] ?? '';
                    $p++;
                    if ($nx === 'n') {
                        $o .= "\n";
                    } elseif ($nx === 'r') {
                        $o .= "\r";
                    } elseif ($nx === 't') {
                        $o .= "\t";
                    } elseif ($nx === 'b') {
                        $o .= "\x08";
                    } elseif ($nx === 'f') {
                        $o .= "\f";
                    } elseif ($nx >= '0' && $nx <= '7' && $nx !== '') {
                        $oc = $nx;
                        for ($k = 0; $k < 2 && $p < $n && $s[$p] >= '0' && $s[$p] <= '7'; $k++) {
                            $oc .= $s[$p++];
                        }
                        $o .= chr(octdec($oc) & 255);
                    } elseif ($nx === "\r") {
                        if (($s[$p] ?? '') === "\n") {
                            $p++;
                        }
                    } elseif ($nx !== "\n") {
                        $o .= $nx;
                    }
                    continue;
                }
                if ($ch === '(') {
                    $nivel++;
                } elseif ($ch === ')') {
                    if (--$nivel === 0) {
                        return [self::KS => $o];
                    }
                }
                $o .= $ch;
            }
            throw new \RuntimeException('cadena');
        }
        if ($c === '/') {
            $e = $p + 1 + strcspn($s, self::DELIM, $p + 1);
            $nom = substr($s, $p + 1, $e - $p - 1);
            $p = $e;
            if (strpos($nom, '#') !== false) {
                $nom = (string)preg_replace_callback('/#([0-9A-Fa-f]{2})/', static function (array $m): string {
                    return chr((int)hexdec($m[1]));
                }, $nom);
            }
            return '/' . $nom;
        }
        if (($c >= '0' && $c <= '9') || $c === '+' || $c === '-' || $c === '.') {
            if (preg_match('/\G([+-]?)(\d+\.?\d*|\.\d+)/', $s, $m, 0, $p) !== 1) {
                throw new \RuntimeException('número');
            }
            $p += strlen($m[0]);
            if ($m[1] === '' && ctype_digit($m[2])) {
                if (preg_match('/\G[ \t\r\n\f\0]+(\d+)[ \t\r\n\f\0]+R(?![A-Za-z0-9])/', $s, $r, 0, $p) === 1) {
                    $p += strlen($r[0]);
                    return [self::KR => (int)$m[2]];
                }
                return (int)$m[2];
            }
            return strpos($m[0], '.') === false ? (int)$m[0] : (float)$m[0];
        }
        $e = $p + strcspn($s, self::DELIM, $p);
        $kw = substr($s, $p, $e - $p);
        if ($e === $p) {
            throw new \RuntimeException('token');
        }
        $p = $e;
        if ($kw === 'true') {
            return true;
        }
        if ($kw === 'false') {
            return false;
        }
        if ($kw === 'null') {
            return null;
        }
        throw new \RuntimeException('palabra clave');
    }

    private function ws(string $s, int &$p): void
    {
        $n = strlen($s);
        while ($p < $n) {
            $p += strspn($s, self::WS, $p);
            if ($p < $n && $s[$p] === '%') {
                $p += strcspn($s, "\r\n", $p);
                continue;
            }
            break;
        }
    }

    // ------------------------------------------------------------------
    // Utilidades de valores
    // ------------------------------------------------------------------

    /** @param mixed $x */
    private function esDict($x): bool
    {
        if (!is_array($x) || isset($x[self::KR]) || isset($x[self::KS])) {
            return false;
        }
        foreach ($x as $k => $_) {
            return !is_int($k);
        }
        return true;
    }

    /** @param mixed $x */
    private function refDe($x): ?int
    {
        return (is_array($x) && isset($x[self::KR])) ? (int)$x[self::KR] : null;
    }

    /**
     * @param mixed $x
     * @return mixed
     */
    private function deref($x)
    {
        for ($i = 0; $i < 5; $i++) {
            $r = $this->refDe($x);
            if ($r === null) {
                return $x;
            }
            $o = $this->obj($r);
            $x = $o['v'] ?? null;
        }
        return null;
    }

    /** @param mixed $x */
    private function num($x): float
    {
        $x = $this->deref($x);
        return is_int($x) || is_float($x) ? (float)$x : 0.0;
    }

    /** @param mixed $x */
    private function entero($x, int $def = 0): int
    {
        $x = $this->deref($x);
        return is_int($x) ? $x : (is_float($x) ? (int)$x : $def);
    }
}
