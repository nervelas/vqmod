<?php
/**
 * CorreoRadar - Prefijos telefónicos internacionales.
 *
 * Mapa "prefijo => [código ISO, país, longitud mínima, longitud máxima]".
 * Las longitudes son las del número NACIONAL (sin prefijo) y sirven para
 * descartar números imposibles. El motor busca siempre la coincidencia de
 * prefijo más larga (por ejemplo 1787 antes que 1).
 *
 * @return array<string,array{0:string,1:string,2:int,3:int}>
 */
declare(strict_types=1);

return [
    // --- América ---------------------------------------------------------
    '1'    => ['US', 'Estados Unidos / Canadá', 10, 10],
    '1242' => ['BS', 'Bahamas', 7, 7],      '1246' => ['BB', 'Barbados', 7, 7],
    '1264' => ['AI', 'Anguila', 7, 7],      '1268' => ['AG', 'Antigua y Barbuda', 7, 7],
    '1284' => ['VG', 'Islas Vírgenes Británicas', 7, 7],
    '1340' => ['VI', 'Islas Vírgenes de EE. UU.', 7, 7],
    '1441' => ['BM', 'Bermudas', 7, 7],     '1473' => ['GD', 'Granada', 7, 7],
    '1649' => ['TC', 'Islas Turcas y Caicos', 7, 7],
    '1664' => ['MS', 'Montserrat', 7, 7],   '1670' => ['MP', 'Islas Marianas del Norte', 7, 7],
    '1671' => ['GU', 'Guam', 7, 7],         '1684' => ['AS', 'Samoa Americana', 7, 7],
    '1721' => ['SX', 'San Martín', 7, 7],   '1758' => ['LC', 'Santa Lucía', 7, 7],
    '1767' => ['DM', 'Dominica', 7, 7],     '1784' => ['VC', 'San Vicente y las Granadinas', 7, 7],
    '1787' => ['PR', 'Puerto Rico', 7, 7],  '1809' => ['DO', 'República Dominicana', 7, 7],
    '1829' => ['DO', 'República Dominicana', 7, 7], '1849' => ['DO', 'República Dominicana', 7, 7],
    '1868' => ['TT', 'Trinidad y Tobago', 7, 7],    '1869' => ['KN', 'San Cristóbal y Nieves', 7, 7],
    '1876' => ['JM', 'Jamaica', 7, 7],      '1939' => ['PR', 'Puerto Rico', 7, 7],
    '52'   => ['MX', 'México', 10, 10],     '53'  => ['CU', 'Cuba', 6, 8],
    '54'   => ['AR', 'Argentina', 10, 11],  '55'  => ['BR', 'Brasil', 10, 11],
    '56'   => ['CL', 'Chile', 9, 9],        '57'  => ['CO', 'Colombia', 10, 10],
    '58'   => ['VE', 'Venezuela', 10, 10],  '51'  => ['PE', 'Perú', 9, 9],
    '591'  => ['BO', 'Bolivia', 8, 8],      '592' => ['GY', 'Guyana', 7, 7],
    '593'  => ['EC', 'Ecuador', 8, 9],      '594' => ['GF', 'Guayana Francesa', 9, 9],
    '595'  => ['PY', 'Paraguay', 9, 9],     '596' => ['MQ', 'Martinica', 9, 9],
    '597'  => ['SR', 'Surinam', 6, 7],      '598' => ['UY', 'Uruguay', 8, 8],
    '599'  => ['CW', 'Curazao', 7, 8],
    '501'  => ['BZ', 'Belice', 7, 7],       '502' => ['GT', 'Guatemala', 8, 8],
    '503'  => ['SV', 'El Salvador', 8, 8],  '504' => ['HN', 'Honduras', 8, 8],
    '505'  => ['NI', 'Nicaragua', 8, 8],    '506' => ['CR', 'Costa Rica', 8, 8],
    '507'  => ['PA', 'Panamá', 7, 8],       '508' => ['PM', 'San Pedro y Miquelón', 6, 6],
    '509'  => ['HT', 'Haití', 8, 8],

    // --- Europa ----------------------------------------------------------
    '34'   => ['ES', 'España', 9, 9],       '33'  => ['FR', 'Francia', 9, 9],
    '39'   => ['IT', 'Italia', 9, 11],      '351' => ['PT', 'Portugal', 9, 9],
    '49'   => ['DE', 'Alemania', 10, 11],   '44'  => ['GB', 'Reino Unido', 10, 10],
    '353'  => ['IE', 'Irlanda', 9, 9],      '31'  => ['NL', 'Países Bajos', 9, 9],
    '32'   => ['BE', 'Bélgica', 8, 9],      '352' => ['LU', 'Luxemburgo', 8, 9],
    '41'   => ['CH', 'Suiza', 9, 9],        '43'  => ['AT', 'Austria', 9, 11],
    '45'   => ['DK', 'Dinamarca', 8, 8],    '46'  => ['SE', 'Suecia', 9, 9],
    '47'   => ['NO', 'Noruega', 8, 8],      '358' => ['FI', 'Finlandia', 9, 10],
    '354'  => ['IS', 'Islandia', 7, 7],     '48'  => ['PL', 'Polonia', 9, 9],
    '420'  => ['CZ', 'Chequia', 9, 9],      '421' => ['SK', 'Eslovaquia', 9, 9],
    '36'   => ['HU', 'Hungría', 9, 9],      '40'  => ['RO', 'Rumanía', 9, 9],
    '359'  => ['BG', 'Bulgaria', 8, 9],     '30'  => ['GR', 'Grecia', 10, 10],
    '385'  => ['HR', 'Croacia', 8, 9],      '386' => ['SI', 'Eslovenia', 8, 8],
    '387'  => ['BA', 'Bosnia y Herzegovina', 8, 8], '381' => ['RS', 'Serbia', 8, 9],
    '382'  => ['ME', 'Montenegro', 8, 8],   '389' => ['MK', 'Macedonia del Norte', 8, 8],
    '355'  => ['AL', 'Albania', 8, 9],      '356' => ['MT', 'Malta', 8, 8],
    '357'  => ['CY', 'Chipre', 8, 8],       '370' => ['LT', 'Lituania', 8, 8],
    '371'  => ['LV', 'Letonia', 8, 8],      '372' => ['EE', 'Estonia', 7, 8],
    '373'  => ['MD', 'Moldavia', 8, 8],     '374' => ['AM', 'Armenia', 8, 8],
    '375'  => ['BY', 'Bielorrusia', 9, 9],  '376' => ['AD', 'Andorra', 6, 6],
    '377'  => ['MC', 'Mónaco', 8, 9],       '378' => ['SM', 'San Marino', 8, 10],
    '380'  => ['UA', 'Ucrania', 9, 9],      '383' => ['XK', 'Kosovo', 8, 9],
    '7'    => ['RU', 'Rusia / Kazajistán', 10, 10],
    '90'   => ['TR', 'Turquía', 10, 10],    '995' => ['GE', 'Georgia', 9, 9],
    '994'  => ['AZ', 'Azerbaiyán', 9, 9],   '350' => ['GI', 'Gibraltar', 8, 8],
    '298'  => ['FO', 'Islas Feroe', 6, 6],  '299' => ['GL', 'Groenlandia', 6, 6],
    '423'  => ['LI', 'Liechtenstein', 7, 9],

    // --- África ----------------------------------------------------------
    '212'  => ['MA', 'Marruecos', 9, 9],    '213' => ['DZ', 'Argelia', 9, 9],
    '216'  => ['TN', 'Túnez', 8, 8],        '218' => ['LY', 'Libia', 9, 9],
    '20'   => ['EG', 'Egipto', 10, 10],     '27'  => ['ZA', 'Sudáfrica', 9, 9],
    '234'  => ['NG', 'Nigeria', 10, 10],    '254' => ['KE', 'Kenia', 9, 9],
    '233'  => ['GH', 'Ghana', 9, 9],        '251' => ['ET', 'Etiopía', 9, 9],
    '256'  => ['UG', 'Uganda', 9, 9],       '255' => ['TZ', 'Tanzania', 9, 9],
    '221'  => ['SN', 'Senegal', 9, 9],      '225' => ['CI', 'Costa de Marfil', 8, 10],
    '237'  => ['CM', 'Camerún', 9, 9],      '243' => ['CD', 'R. D. del Congo', 9, 9],
    '244'  => ['AO', 'Angola', 9, 9],       '258' => ['MZ', 'Mozambique', 9, 9],
    '263'  => ['ZW', 'Zimbabue', 9, 9],     '260' => ['ZM', 'Zambia', 9, 9],
    '265'  => ['MW', 'Malaui', 9, 9],       '267' => ['BW', 'Botsuana', 8, 8],
    '230'  => ['MU', 'Mauricio', 7, 8],     '238' => ['CV', 'Cabo Verde', 7, 7],
    '239'  => ['ST', 'Santo Tomé y Príncipe', 7, 7], '240' => ['GQ', 'Guinea Ecuatorial', 9, 9],

    // --- Asia y Oceanía --------------------------------------------------
    '86'   => ['CN', 'China', 11, 11],      '81'  => ['JP', 'Japón', 10, 10],
    '82'   => ['KR', 'Corea del Sur', 9, 10], '91' => ['IN', 'India', 10, 10],
    '92'   => ['PK', 'Pakistán', 10, 10],   '880' => ['BD', 'Bangladés', 10, 10],
    '62'   => ['ID', 'Indonesia', 9, 12],   '60'  => ['MY', 'Malasia', 9, 10],
    '65'   => ['SG', 'Singapur', 8, 8],     '66'  => ['TH', 'Tailandia', 9, 9],
    '84'   => ['VN', 'Vietnam', 9, 10],     '63'  => ['PH', 'Filipinas', 10, 10],
    '852'  => ['HK', 'Hong Kong', 8, 8],    '853' => ['MO', 'Macao', 8, 8],
    '886'  => ['TW', 'Taiwán', 9, 9],       '855' => ['KH', 'Camboya', 8, 9],
    '856'  => ['LA', 'Laos', 9, 10],        '95'  => ['MM', 'Birmania', 8, 10],
    '971'  => ['AE', 'Emiratos Árabes Unidos', 9, 9], '966' => ['SA', 'Arabia Saudí', 9, 9],
    '974'  => ['QA', 'Catar', 8, 8],        '973' => ['BH', 'Baréin', 8, 8],
    '965'  => ['KW', 'Kuwait', 8, 8],       '968' => ['OM', 'Omán', 8, 8],
    '962'  => ['JO', 'Jordania', 9, 9],     '961' => ['LB', 'Líbano', 7, 8],
    '963'  => ['SY', 'Siria', 9, 9],        '964' => ['IQ', 'Irak', 10, 10],
    '972'  => ['IL', 'Israel', 9, 9],       '970' => ['PS', 'Palestina', 9, 9],
    '98'   => ['IR', 'Irán', 10, 10],       '93'  => ['AF', 'Afganistán', 9, 9],
    '994'  => ['AZ', 'Azerbaiyán', 9, 9],   '996' => ['KG', 'Kirguistán', 9, 9],
    '998'  => ['UZ', 'Uzbekistán', 9, 9],   '992' => ['TJ', 'Tayikistán', 9, 9],
    '993'  => ['TM', 'Turkmenistán', 8, 8], '976' => ['MN', 'Mongolia', 8, 8],
    '977'  => ['NP', 'Nepal', 10, 10],      '94'  => ['LK', 'Sri Lanka', 9, 9],
    '960'  => ['MV', 'Maldivas', 7, 7],     '61'  => ['AU', 'Australia', 9, 9],
    '64'   => ['NZ', 'Nueva Zelanda', 8, 10], '679' => ['FJ', 'Fiyi', 7, 7],
    '675'  => ['PG', 'Papúa Nueva Guinea', 8, 8], '685' => ['WS', 'Samoa', 7, 7],
];
