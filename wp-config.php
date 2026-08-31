<?php
/**
 * Configuración de WordPress — AGROINCO (reconstruida y endurecida tras limpieza de malware)
 */

// ** Conexión MySQL ** //
define( 'DB_NAME', 'servicom001_AgroInco' );
define( 'DB_USER', 'servicom001_4GR0INC02026' );
define( 'DB_PASSWORD', 'chWz6,pqZ#5&.PyS' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// ** Claves y salts únicos (regenerados el 2026-08-31) ** //
define( 'AUTH_KEY',          'RTWa+Ai%CZM~U>-Xz{9{ZSfs,Z85Qx|m4N#O/qj5mjZ&d}JO{xP`Pld|I%tEpxjF' );
define( 'SECURE_AUTH_KEY',   'q;A-Nr{7=xrA<2FGG.DIeANU{)XVXHpN+L(%=LzAkv3VB2oDAHCNTBg#RL73b~FM' );
define( 'LOGGED_IN_KEY',     'ky}v6Gg.+N++ CH-J4p<twjWnWkRL@V/[-4c*WXTD;SJYQVx)aB.p.yFN+>]mkaL' );
define( 'NONCE_KEY',         'Ck(W>Uw$`#EnTt|0>`A}zpWXH[4udFF(0wE{i4/<R)u`qE59|Y@#*RH5a`eD]sZN' );
define( 'AUTH_SALT',         'uAw8z0U^d|oWg($|d5[*HhHFq=l!7]mEScgYa!UjR5<_{is35_iCVBWcr0@apdF;' );
define( 'SECURE_AUTH_SALT',  ';@eaJD*trrgY<twYjnl=_jz^BPoVG<GCKIn;yAgmMKswJ_&(6l?s{l>H+oY=-)--' );
define( 'LOGGED_IN_SALT',    'J=M^[7T>3B#esK=;6LQ{; `2bba}gkZx+3GHiwb642QQC~{VCn?i,bRP{8dH`O{a' );
define( 'NONCE_SALT',        '$TLr}p~}j<hN=EzUSGV`!{cwm$RJ#ms5VMkrAh$%*V i1bQulvRYOrc~#bqF$te(' );

// ** Prefijo de tablas (conservado) ** //
$table_prefix = 'wp_';

// ** URL del sitio ** //
define( 'WP_SITEURL', 'https://agroinco.com' );
define( 'WP_HOME', 'https://agroinco.com' );

define( 'WP_CACHE', true );  // activa wp-content/advanced-cache.php

// ** Endurecimiento ** //
define( 'DISALLOW_FILE_EDIT', true );   // sin editor de archivos en el panel
define( 'FORCE_SSL_ADMIN', true );      // admin solo por HTTPS
define( 'WP_AUTO_UPDATE_CORE', 'minor' ); // actualizaciones menores automáticas
define( 'WP_POST_REVISIONS', 5 );
define( 'EMPTY_TRASH_DAYS', 15 );

// ** Depuración desactivada en producción ** //
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );

/* === LOCAL-DEV (solo copia de trabajo; NO está en el ZIP) === */
if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) { $_SERVER['HTTPS'] = 'on'; }
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
/* === FIN === */

/* That's all, stop editing! Happy publishing. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
