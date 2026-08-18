<?php
/**
 * One-off generator for the demo visual assets (team crests, league logo,
 * banner). Run from the project root:  php scripts/generate_demo_assets.php
 *
 * Output goes to assets/demo/ which IS tracked by git (unlike uploads/), so the
 * generated images ship inside the release ZIP and never depend on the target
 * server having GD/FreeType at install time.
 */
declare(strict_types=1);

$ROOT = dirname(__DIR__);
$OUT  = $ROOT . '/assets/demo';
$CRESTS = $OUT . '/crests';
@mkdir($CRESTS, 0755, true);

$FONT_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
if (!is_file($FONT_BOLD)) { fwrite(STDERR, "Font not found\n"); exit(1); }

/* ---- helpers ------------------------------------------------------------- */
function hsl2rgb(float $h, float $s, float $l): array {
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60)      [$r,$g,$b] = [$c,$x,0];
    elseif ($h < 120) [$r,$g,$b] = [$x,$c,0];
    elseif ($h < 180) [$r,$g,$b] = [0,$c,$x];
    elseif ($h < 240) [$r,$g,$b] = [0,$x,$c];
    elseif ($h < 300) [$r,$g,$b] = [$x,0,$c];
    else              [$r,$g,$b] = [$c,0,$x];
    return [(int)round(($r+$m)*255), (int)round(($g+$m)*255), (int)round(($b+$m)*255)];
}
function relLum(array $c): float {
    $f = array_map(function ($v) { $v /= 255; return $v <= 0.03928 ? $v/12.92 : (($v+0.055)/1.055)**2.4; }, $c);
    return 0.2126*$f[0] + 0.7152*$f[1] + 0.0722*$f[2];
}
function textColor(array $bg): array { return relLum($bg) > 0.4 ? [17,20,24] : [255,255,255]; }
function darken(array $c, float $f): array { return [(int)($c[0]*$f),(int)($c[1]*$f),(int)($c[2]*$f)]; }
function initials(string $name): string {
    $skip = ['fc','cf','de','del','la','el','los','las','jr'];
    $words = array_values(array_filter(
        preg_split('/\s+/', trim($name)),
        fn($w) => $w !== '' && !in_array(mb_strtolower($w), $skip, true)
    ));
    if (count($words) >= 2) {
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }
    if (count($words) === 1) {
        return mb_strtoupper(mb_substr($words[0], 0, 3));
    }
    return mb_strtoupper(mb_substr(preg_replace('/[^A-Za-zÁÉÍÓÚÑ]/u', '', $name), 0, 2)) ?: 'FC';
}

/** Draw a football crest (shield) at high resolution and downsample for smooth edges. */
function make_crest(string $path, string $name, float $hue, string $font): void {
    $S = 3; $W = 160 * $S;                 // supersample
    $im = imagecreatetruecolor($W, $W);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    $primary   = hsl2rgb($hue, 0.62, 0.42);
    $primaryLo = darken($primary, 0.72);
    $band      = darken($primary, 0.55);
    $ring      = hsl2rgb($hue, 0.30, 0.90);      // light metallic ring
    $txt       = textColor($primary);

    $cx = $W / 2;
    // Shield polygon
    $sh = [
        0.12*$W, 0.14*$W,
        0.88*$W, 0.14*$W,
        0.88*$W, 0.52*$W,
        0.50*$W, 0.90*$W,
        0.12*$W, 0.52*$W,
    ];
    // Outer ring (border): draw a slightly larger shield in ring color first.
    $shOuter = [
        0.075*$W,0.10*$W, 0.925*$W,0.10*$W, 0.925*$W,0.545*$W,
        0.50*$W,0.945*$W, 0.075*$W,0.545*$W,
    ];
    $cRing = imagecolorallocate($im, $ring[0],$ring[1],$ring[2]);
    imagefilledpolygon($im, $shOuter, $cRing);

    // Base shield fill
    $cPri = imagecolorallocate($im, $primary[0],$primary[1],$primary[2]);
    imagefilledpolygon($im, $sh, $cPri);

    // Lower-half darker wedge for depth
    $lower = [ 0.12*$W,0.40*$W, 0.88*$W,0.40*$W, 0.88*$W,0.52*$W, 0.50*$W,0.90*$W, 0.12*$W,0.52*$W ];
    $cLo = imagecolorallocatealpha($im, $primaryLo[0],$primaryLo[1],$primaryLo[2], 40);
    imagefilledpolygon($im, $lower, $cLo);

    // Top band
    $topBand = [ 0.12*$W,0.14*$W, 0.88*$W,0.14*$W, 0.88*$W,0.255*$W, 0.12*$W,0.255*$W ];
    $cBand = imagecolorallocate($im, $band[0],$band[1],$band[2]);
    imagefilledpolygon($im, $topBand, $cBand);

    // Star on the band
    $cTxt = imagecolorallocate($im, $txt[0],$txt[1],$txt[2]);
    $starC = imagecolorallocate($im, $ring[0],$ring[1],$ring[2]);
    draw_star($im, (int)$cx, (int)(0.198*$W), (int)(0.055*$W), (int)(0.024*$W), $starC);

    // Initials
    $ini = initials($name);
    $fs = (mb_strlen($ini) >= 3 ? 0.20 : 0.26) * $W;
    $bb = imagettfbbox($fs, 0, $font, $ini);
    $tw = $bb[2] - $bb[0]; $th = $bb[1] - $bb[7];
    $tx = (int)($cx - $tw/2 - $bb[0]);
    $ty = (int)(0.52*$W + $th/2);
    // subtle shadow
    imagettftext($im, $fs, 0, $tx+2*$S, $ty+2*$S, imagecolorallocatealpha($im,0,0,0,80), $font, $ini);
    imagettftext($im, $fs, 0, $tx, $ty, $cTxt, $font, $ini);

    // Downsample
    $D = imagecreatetruecolor(160, 160);
    imagesavealpha($D, true);
    imagealphablending($D, false);
    imagefill($D, 0, 0, imagecolorallocatealpha($D, 0,0,0,127));
    imagealphablending($D, true);
    imagecopyresampled($D, $im, 0,0,0,0, 160,160, $W,$W);
    imagepng($D, $path);
    imagedestroy($im); imagedestroy($D);
}

function draw_star($im, int $cx, int $cy, int $rOut, int $rIn, $color): void {
    $pts = [];
    for ($i = 0; $i < 10; $i++) {
        $r = $i % 2 === 0 ? $rOut : $rIn;
        $a = -M_PI/2 + $i * M_PI/5;
        $pts[] = $cx + $r * cos($a);
        $pts[] = $cy + $r * sin($a);
    }
    imagefilledpolygon($im, $pts, $color);
}

/* ---- team lists (fictional demo teams) ---------------------------------- */
$sets = require __DIR__ . '/demo_teams.php';

$hueStep = 360 / 44;
$i = 0;
$manifest = [];
foreach ($sets as $key => $teams) {
    foreach ($teams as $team) {
        $hue = fmod($i * 137.508, 360); // golden-angle spread → distinct hues
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$team)));
        $slug = trim($slug, '-');
        $file = "crests/$slug.png";
        make_crest("$OUT/$slug-tmp", $team, $hue, $FONT_BOLD); // placeholder to compute, then move
        @rename("$OUT/$slug-tmp", "$CRESTS/" . basename($file));
        $manifest[$team] = 'assets/demo/' . $file;
        $i++;
    }
}
file_put_contents($OUT . '/crest_manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

/* ---- league logo --------------------------------------------------------- */
make_crest($OUT . '/liga-logo.png', 'Planes', 210, $FONT_BOLD);

/* ---- banner -------------------------------------------------------------- */
make_banner($OUT . '/liga-banner.jpg', 'LIGA DE FÚTBOL PLANES', 'FÚTBOL 5', $FONT_BOLD);

function make_banner(string $path, string $title, string $sub, string $font): void {
    $W = 1600; $H = 520;
    $im = imagecreatetruecolor($W, $H);
    // vertical gradient (deep navy → teal)
    for ($y = 0; $y < $H; $y++) {
        $t = $y / $H;
        $r = (int)(10 + $t * 6);
        $g = (int)(20 + $t * 60);
        $b = (int)(38 + $t * 70);
        imagefilledrectangle($im, 0, $y, $W, $y, imagecolorallocate($im, $r, $g, $b));
    }
    // subtle diagonal light streaks
    for ($k = -2; $k < 14; $k++) {
        $x = $k * 140;
        $poly = [$x, 0, $x + 60, 0, $x + 60 - 260, $H, $x - 260, $H];
        imagefilledpolygon($im, $poly, imagecolorallocatealpha($im, 120, 200, 255, 122));
    }
    // pitch arc accent
    imagesetthickness($im, 6);
    imagearc($im, (int)($W*0.82), (int)($H*0.5), 520, 520, 0, 360, imagecolorallocatealpha($im, 130, 255, 200, 110));
    imagearc($im, (int)($W*0.82), (int)($H*0.5), 240, 240, 0, 360, imagecolorallocatealpha($im, 130, 255, 200, 118));

    // Textless premium graphic — the page overlays its own crisp title (H1),
    // so the banner stays clean: gradient + streaks + pitch arcs + accent dots.
    $c2 = imagecolorallocate($im, 130, 255, 200);
    mt_srand(7);
    for ($d = 0; $d < 26; $d++) {
        $r = mt_rand(3, 9);
        imagefilledellipse($im, mt_rand(40, $W - 40), mt_rand(30, $H - 30), $r, $r,
            imagecolorallocatealpha($im, 130, 255, 200, mt_rand(90, 118)));
    }
    imagejpeg($im, $path, 88);
    imagedestroy($im);
}

echo "Generated " . count($manifest) . " crests + logo + banner into assets/demo/\n";
