<?php
declare(strict_types=1);
/** Genera las escenas SVG originales de assets/img/scenes (uso: php tests/make-scenes.php). */
require __DIR__ . '/../app/Core/Guilloche.php';
use App\Core\Guilloche;

$dir = __DIR__ . '/../assets/img/scenes/';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$gold3 = ['#7A5420', '#C9A050', '#F0D9A0'];

/** Marco común: fondo medianoche, resplandor, guilloché, suelo y filete dorado. */
function scene(string $name, string $inner, array $o = []): void
{
    global $dir, $gold3;
    $rx = $o['rx'] ?? 1180;
    $ry = $o['ry'] ?? 430;
    $rr = $o['rr'] ?? 520;
    $floor = $o['floor'] ?? 790;
    $seed = $o['seed'] ?? 3;
    $ros = Guilloche::rosette($rx, $ry, $rr, ['seed' => $seed, 'curves' => $o['curves'] ?? 13, 'petals' => 12, 'depth' => 0.2, 'colors' => $gold3, 'opacity' => 0.36, 'stroke' => 0.7]);
    $liss = Guilloche::lissajous(1600, 1000, ['seed' => $seed + 8, 'curves' => 3, 'points' => 200, 'colors' => $gold3, 'opacity' => 0.14, 'stroke' => 0.8]);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000" width="1600" height="1000" fill="none" stroke-linecap="round" stroke-linejoin="round">'
        . '<defs>'
        . '<linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#06080D"/><stop offset="1" stop-color="#121826"/></linearGradient>'
        . '<radialGradient id="gl" cx="0.74" cy="0.42" r="0.6"><stop offset="0" stop-color="#C9A050" stop-opacity="0.26"/><stop offset="1" stop-color="#C9A050" stop-opacity="0"/></radialGradient>'
        . '<linearGradient id="au" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7A5420"/><stop offset="0.55" stop-color="#C9A050"/><stop offset="1" stop-color="#F0D9A0"/></linearGradient>'
        . '<linearGradient id="ob" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a2131"/><stop offset="1" stop-color="#0b0e15"/></linearGradient>'
        . '<linearGradient id="fl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#C9A050" stop-opacity="0.22"/><stop offset="1" stop-color="#C9A050" stop-opacity="0"/></linearGradient>'
        . '</defs>'
        . '<rect width="1600" height="1000" fill="url(#bg)"/><rect width="1600" height="1000" fill="url(#gl)"/>'
        . $liss . $ros
        . '<rect y="' . $floor . '" width="1600" height="' . (1000 - $floor) . '" fill="url(#fl)"/>'
        . '<path d="M0 ' . $floor . 'H1600" stroke="#C9A050" stroke-opacity="0.55" stroke-width="1.5"/>'
        . $inner
        . '<rect x="28" y="28" width="1544" height="944" rx="6" stroke="#C9A050" stroke-opacity="0.35" stroke-width="1.5"/>'
        . '<rect x="44" y="44" width="1512" height="912" rx="3" stroke="#C9A050" stroke-opacity="0.15" stroke-width="1"/>'
        . '</svg>';
    file_put_contents($dir . $name . '.svg', $svg);
}

$L = 'stroke="url(#au)" stroke-width="2.5"';   // línea de oro
$T = 'stroke="url(#au)" stroke-width="1.5"';   // línea fina
$F = 'fill="url(#ob)"';                          // silueta obsidiana

// 1 · Consultorio médico
scene('consultorio-medico', <<<SVG
<path d="M980 790V330a200 200 0 0 1 400 0v460" $T/><path d="M1000 790V332a180 180 0 0 1 360 0v458" $T opacity=".5"/>
<path d="M1180 150v640M980 400H1380" $T opacity=".4"/>
<circle cx="1180" cy="330" r="76" $L/><path d="M1180 290v80M1140 330h80" stroke="url(#au)" stroke-width="10"/>
<g $F $L><rect x="260" y="600" width="520" height="190" rx="10"/><path d="M290 600v-70a40 40 0 0 1 40-40h380a40 40 0 0 1 40 40v70"/></g>
<g $T><path d="M300 640h440M300 700h440M300 750h300"/></g>
<g $F $L><rect x="160" y="500" width="40" height="290" rx="8"/><path d="M180 500c-60-60-30-150 40-170"/><path d="M180 540c70-40 90-110 50-170"/><path d="M180 580c-70-30-100-90-70-150"/></g>
<g $L><path d="M880 790l-30-140h-90l-30 140"/><rect x="740" y="610" width="140" height="30" rx="6" $F/></g>
<g $L><circle cx="560" cy="440" r="48"/><path d="M560 392c-80-40-140 20-140 90v40M560 392c80-40 140 20 140 90"/><circle cx="420" cy="540" r="16" $F/></g>
SVG, ['rx' => 1180, 'ry' => 430, 'seed' => 2]);

// 2 · Odontología
scene('odontologia', <<<SVG
<path d="M1060 250c-70-60-190-30-190 90 0 90 50 130 60 230 8 60 20 180 60 180 40 0 40-90 60-130 20-40 40-40 60 0 20 40 20 130 60 130 40 0 52-120 60-180 10-100 60-140 60-230 0-120-120-150-190-90z" transform="translate(-20 -20) scale(1.05)" $L/>
<path d="M980 330c30-30 80-30 110 0" $T opacity=".6"/>
<g $F $L><path d="M300 790v-100c0-60 40-100 100-100h200c60 0 100 40 100 100v100z"/><path d="M400 590v-130c0-40 30-70 70-70h60"/><rect x="330" y="450" width="140" height="40" rx="18"/></g>
<g $L><path d="M720 790v-300M720 490l120-120M840 370l30 50"/><circle cx="880" cy="440" r="26" $F/><path d="M720 790h-70M790 790h-70"/></g>
<g $T><circle cx="1200" cy="620" r="150"/><circle cx="1200" cy="620" r="110" opacity=".5"/></g>
<path d="M200 200c80 0 140 40 140 120v40" $T opacity=".6"/><circle cx="200" cy="200" r="14" $L/>
SVG, ['rx' => 1150, 'ry' => 430, 'seed' => 5]);

// 3 · Psicología / terapia
scene('psicologia', <<<SVG
<g $F $L><path d="M320 790V600c0-60 40-100 100-100h160c60 0 100 40 100 100v190"/><rect x="290" y="620" width="400" height="90" rx="40"/></g>
<g $F $L><path d="M1280 790V600c0-60-40-100-100-100h-160c-60 0-100 40-100 100v190"/><rect x="910" y="620" width="400" height="90" rx="40"/></g>
<g $L><path d="M800 790V480"/><path d="M730 480h140l-30-110h-80z" $F/></g>
<circle cx="800" cy="300" r="170" $T opacity=".6"/>
<path d="M740 300c0-50 40-80 80-70 40 10 60 60 30 90-20 20-60 10-60-20 0-25 30-35 40-15" $L/>
<path d="M1280 360c-20-60-80-80-110-50M1300 400c-40-70-110-90-150-50" $T opacity=".5"/>
<g $L><path d="M170 790v-90M170 700c-50-30-60-90-20-130M170 700c50-30 60-90 20-130M170 690c0-70 0-100 0-140"/></g>
<g $T><ellipse cx="800" cy="790" rx="360" ry="16"/><ellipse cx="800" cy="790" rx="260" ry="10" opacity=".6"/></g>
SVG, ['rx' => 800, 'ry' => 300, 'rr' => 420, 'seed' => 8]);

// 4 · Legal / notarial
scene('legal-notarial', <<<SVG
<g $L><path d="M1180 230V790M1180 230l-300 90M1180 230l300 90M880 320l-80 190h160zM1480 320l-80 190h160z" $T/><path d="M800 510a80 40 0 0 0 160 0M1400 510a80 40 0 0 0 160 0" $L/><circle cx="1180" cy="214" r="22" $F/><path d="M1090 790h180M1110 770h140" $L/></g>
<g $F $L><path d="M240 790V300h60v490M360 790V300h60v490M480 790V300h60v490"/><path d="M210 300h360l-180-90z"/><path d="M210 790h360"/></g>
<g $T><path d="M240 400h60M360 400h60M480 400h60" opacity=".4"/></g>
<g $F $L><rect x="640" y="700" width="360" height="40" rx="6"/><rect x="670" y="660" width="300" height="40" rx="6"/><rect x="700" y="620" width="240" height="40" rx="6"/></g>
<g $T><path d="M690 720h260M720 680h200M750 640h140" opacity=".6"/></g>
<g $L><path d="M1020 760l120-70" stroke-width="14"/><path d="M1090 665l70 70" stroke-width="20"/></g>
SVG, ['rx' => 1180, 'ry' => 420, 'seed' => 12]);

// 5 · Contabilidad
scene('contabilidad', <<<SVG
<g $F $L><rect x="300" y="520" width="440" height="270" rx="12"/><rect x="340" y="560" width="360" height="70" rx="8"/></g>
<g $T><path d="M360 680h60M450 680h60M540 680h60M630 680h40M360 725h60M450 725h60M540 725h60M630 725h40"/></g>
<g $F $L><path d="M900 790l40-380 100 0 40 380z" opacity="0"/><rect x="880" y="560" width="110" height="230" rx="6"/><rect x="1020" y="440" width="110" height="350" rx="6"/><rect x="1160" y="300" width="110" height="490" rx="6"/></g>
<path d="M860 520L1050 380l120-90 150-70M1260 220h60v60" $L/>
<g $F $L><ellipse cx="480" cy="480" rx="90" ry="22"/><path d="M390 480v-30c0 12 40 22 90 22s90-10 90-22v30M390 450v-30c0 12 40 22 90 22s90-10 90-22v30M390 420v-20c0-12 40-22 90-22s90 10 90 22"/></g>
<g $T opacity=".5"><path d="M860 790V420M1000 790V300M1140 790V200" stroke-dasharray="4 10"/></g>
SVG, ['rx' => 1150, 'ry' => 420, 'seed' => 15]);

// 6 · Arquitectura / ingeniería
$grid = '';
for ($x = 100; $x <= 1500; $x += 100) { $grid .= "M$x 90V790"; }
for ($y = 190; $y <= 790; $y += 100) { $grid .= "M90 {$y}H1510"; }
scene('arquitectura-ingenieria', <<<SVG
<path d="$grid" stroke="#C9A050" stroke-opacity=".12"/>
<g $F $L><path d="M300 790V420l140-100 140 100v370"/><path d="M580 790V300h260v490"/><path d="M840 790V460h160l60-60v390"/></g>
<g $T><path d="M340 790V520h60v270M640 360h150M640 430h150M640 500h150M640 570h150M640 640h150"/><path d="M880 520h80M880 600h80M880 680h80" opacity=".6"/></g>
<g $L><path d="M1120 790L1320 180l200 610"/><path d="M1190 560h260"/><circle cx="1320" cy="180" r="18" $F/></g>
<g $T><path d="M1100 840H1540M1100 830v20M1540 830v20"/></g>
<g $L><path d="M200 650L300 450l100 200zM300 450v-120"/><circle cx="300" cy="320" r="14" $F/></g>
SVG, ['rx' => 1230, 'ry' => 450, 'rr' => 430, 'seed' => 19]);

// 7 · Spa / estética
scene('spa-estetica', <<<SVG
<g $F $L><ellipse cx="800" cy="760" rx="200" ry="34"/><ellipse cx="800" cy="710" rx="150" ry="28"/><ellipse cx="800" cy="665" rx="100" ry="22"/><ellipse cx="800" cy="628" rx="60" ry="16"/></g>
<g $L><path d="M800 560c-60-60-60-140 0-200 60 60 60 140 0 200z" $F/><path d="M800 560c-110-10-170-70-180-150 90 0 160 40 180 150zM800 560c110-10 170-70 180-150-90 0-160 40-180 150z" $F/><path d="M800 560c-150 30-250-10-290-80 100-30 220-10 290 80zM800 560c150 30 250-10 290-80-100-30-220-10-290 80z" $T/></g>
<g $T><ellipse cx="800" cy="800" rx="460" ry="26" opacity=".7"/><ellipse cx="800" cy="800" rx="600" ry="40" opacity=".4"/><ellipse cx="800" cy="800" rx="740" ry="56" opacity=".2"/></g>
<g $F $L><rect x="260" y="690" width="90" height="100" rx="12"/><path d="M305 690c-14-30 14-40 0-70 20 20 24 40 0 70z" stroke-width="2"/></g>
<g $L><path d="M1340 790V560M1340 640c60-30 90-80 80-150M1340 700c-60-30-90-80-80-150M1340 600c0-60-10-110 0-170"/></g>
SVG, ['rx' => 800, 'ry' => 430, 'rr' => 460, 'seed' => 23, 'floor' => 800]);

// 8 · Academia / tutoría
scene('academia-tutoria', <<<SVG
<g $F $L><path d="M800 720c-90-50-200-60-330-50V420c130-10 240 0 330 50z"/><path d="M800 720c90-50 200-60 330-50V420c-130-10-240 0-330 50z"/><path d="M800 470v250"/></g>
<g $T><path d="M510 480c80 0 150 6 250 40M510 540c80 0 150 6 250 40M510 600c80 0 150 6 250 40M1090 480c-80 0-150 6-250 40M1090 540c-80 0-150 6-250 40M1090 600c-80 0-150 6-250 40" opacity=".6"/></g>
<g $F $L><path d="M800 220L1060 320 800 420 540 320z"/><path d="M660 380v70c0 30 70 60 140 60s140-30 140-60v-70"/><path d="M1060 320v110"/><circle cx="1060" cy="450" r="10"/></g>
<g $F $L><rect x="200" y="260" width="240" height="360" rx="8"/><path d="M200 620l-30 170M440 620l30 170"/></g>
<g $T><path d="M240 330c40-30 90 0 120-20M240 400c60-30 100 20 160-10M240 470c50-20 90 20 140 0" opacity=".6"/></g>
<g $L><path d="M1300 790V560M1220 560h160l-40-110h-80z" $F/><circle cx="1300" cy="800" r="0"/></g>
SVG, ['rx' => 800, 'ry' => 380, 'rr' => 500, 'seed' => 27]);

// 9 · Reunión comercial
scene('reunion-comercial', <<<SVG
<g $T><rect x="140" y="130" width="1320" height="450" rx="8" opacity=".5"/><path d="M300 580V380h70v200M420 580V300h90v280M560 580V420h80v160M700 580V260h110v320M860 580V360h90v220M1000 580V320h100v260M1150 580V420h70v160M1270 580V240h100v340" opacity=".5"/></g>
<g $F $L><ellipse cx="800" cy="700" rx="420" ry="60"/><path d="M380 700v60c0 33 188 60 420 60s420-27 420-60v-60"/></g>
<g $F $L><rect x="640" y="610" width="140" height="70" rx="6" transform="skewX(-8)"/><path d="M628 700h170" /></g>
<g $L><circle cx="500" cy="500" r="40" $F/><path d="M430 640v-60c0-50 30-80 70-80s70 30 70 80v60" $F/></g>
<g $L><circle cx="1100" cy="500" r="40" $F/><path d="M1030 640v-60c0-50 30-80 70-80s70 30 70 80v60" $F/></g>
<g $L><circle cx="800" cy="440" r="36" $F/><path d="M740 560v-50c0-40 25-70 60-70s60 30 60 70v50" $F/></g>
SVG, ['rx' => 800, 'ry' => 380, 'rr' => 420, 'seed' => 31]);

// 10 · Tiempo y oro (genérica)
$ticks = '';
for ($i = 0; $i < 60; $i++) {
    $a = $i * M_PI / 30;
    $r1 = $i % 5 === 0 ? 292 : 306;
    $ticks .= sprintf('M%.1f %.1fL%.1f %.1f', 800 + $r1 * sin($a), 480 - $r1 * cos($a), 800 + 320 * sin($a), 480 - 320 * cos($a));
}
$dial = Guilloche::rosette(800, 480, 250, ['seed' => 9, 'curves' => 9, 'petals' => 14, 'depth' => 0.14, 'colors' => $gold3, 'opacity' => 0.55, 'stroke' => 0.7]);
scene('tiempo-y-oro', <<<SVG
<circle cx="800" cy="480" r="352" $F $L/><circle cx="800" cy="480" r="335" $T opacity=".6"/>
$dial
<path d="$ticks" stroke="url(#au)" stroke-width="2"/>
<path d="M800 480L800 270" stroke="url(#au)" stroke-width="7"/><path d="M800 480L930 560" stroke="url(#au)" stroke-width="10"/>
<circle cx="800" cy="480" r="16" fill="url(#au)"/><circle cx="800" cy="480" r="5" fill="#06080D"/>
<path d="M770 128V96h60v32M800 96V70" $L/><circle cx="800" cy="58" r="14" $L/>
<g $T opacity=".7"><path d="M310 800h980"/><path d="M420 840h760" opacity=".6"/></g>
SVG, ['rx' => 800, 'ry' => 480, 'rr' => 600, 'seed' => 35, 'floor' => 880, 'curves' => 5]);

foreach (glob($dir . '*.svg') as $f) {
    printf("%-34s %6d bytes\n", basename($f), filesize($f));
}
