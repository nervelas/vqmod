<?php
/**
 * Servicom Core - Reemplazo seguro de dominio en la base de datos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Descompone una URL/host en [scheme, host(con puerto)]. Ignora la ruta.
 *
 * @return array{0:string,1:string}
 */
function sc_dr_parse( string $u ): array {
	$u = trim( $u );
	$scheme = '';
	if ( preg_match( '#^(https?)://#i', $u, $m ) ) {
		$scheme = strtolower( $m[1] );
		$u      = substr( $u, strlen( $m[0] ) );
	} elseif ( 0 === strpos( $u, '//' ) ) {
		$u = substr( $u, 2 );
	}
	$u    = preg_replace( '#[/?\#].*$#', '', $u );
	$host = strtolower( (string) $u );
	return array( $scheme, $host );
}

/**
 * Construye las reglas de reemplazo.
 *
 * @return array{re:string,to_scheme:string,to_host:string,from_host:string,from_base:string,noop:bool}
 */
function sc_dr_rules( string $from, string $to ): array {
	list( $fs, $fh ) = sc_dr_parse( $from );
	list( $ts, $th ) = sc_dr_parse( $to );
	$ts   = '' !== $ts ? $ts : 'https';
	$base = preg_replace( '/^www\./', '', $fh );
	$q    = preg_quote( $base, '~' );
	$re   = '~(?<![A-Za-z0-9._-])'
		. '(?:(?:https?)(?::|%3A)(?:(?:\\\\*/){2}|%2[Ff]%2[Ff])|//)?'
		. '(?:www\.)?' . $q
		. '(?![A-Za-z0-9-]|\.[A-Za-z0-9])~i';
	return array(
		're'        => $re,
		'to_scheme' => $ts,
		'to_host'   => $th,
		'from_host' => $fh,
		'from_base' => $base,
		'noop'      => ( '' === $base || ( $fh === $th && ( '' === $fs || $fs === $ts ) ) ),
	);
}

/** Aplica el reemplazo a un texto. $n acumula los cambios reales. */
function sc_dr_str( string $s, array $r, int &$n ): string {
	if ( '' === $s || false === stripos( $s, $r['from_base'] ) ) {
		return $s;
	}
	return (string) preg_replace_callback(
		$r['re'],
		static function ( $m ) use ( $r, &$n ) {
			$t   = $m[0];
			$new = null;
			if ( preg_match( '#^https?(:|%3A)((?:\\\\*/){2}|%2[Ff]%2[Ff])#i', $t, $p ) ) {
				$origin = $r['to_scheme'] . '://' . $r['to_host'];
				if ( stripos( $p[1], '%' ) === 0 ) {
					$new = rawurlencode( $origin );
				} else {
					// Barras escapadas de JSON: cuántas "\" antes de cada "/".
					preg_match( '#^https?:((?:\\\\*/){2})#i', $t, $q );
					$slashes = $q[1];
					$bs      = strlen( $slashes ) - 2 > 0 ? (int) ( ( strlen( $slashes ) - 2 ) / 2 ) : 0;
					$new     = $r['to_scheme'] . ':' . str_repeat( str_repeat( '\\', $bs ) . '/', 2 ) . $r['to_host'];
				}
			} elseif ( 0 === strpos( $t, '//' ) ) {
				$new = '//' . $r['to_host'];
			} else {
				$new = $r['to_host'];
			}
			if ( $new !== $t ) {
				++$n;
			}
			return $new;
		},
		$s
	);
}

/** Recorre valores (serializados, arrays, objetos, cadenas). */
function sc_dr_value( $v, array $r, int &$n, int $depth = 0 ) {
	if ( $depth > 40 ) {
		return $v;
	}
	if ( is_string( $v ) ) {
		if ( is_serialized( $v ) ) {
			$un = @unserialize( $v, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $un || 'b:0;' === trim( $v ) ) {
				$c   = 0;
				$new = sc_dr_value( $un, $r, $c, $depth + 1 );
				if ( $c > 0 ) {
					$n += $c;
					return serialize( $new );
				}
				return $v;
			}
			// No se pudo deserializar: reemplazo textual y reparación de longitudes.
			$c   = 0;
			$new = sc_dr_str( $v, $r, $c );
			if ( $c > 0 ) {
				$n += $c;
				return sc_dr_fix_lengths( $new );
			}
			return $v;
		}
		return sc_dr_str( $v, $r, $n );
	}
	if ( is_array( $v ) ) {
		$out = array();
		foreach ( $v as $k => $x ) {
			$nk = is_string( $k ) ? sc_dr_str( $k, $r, $n ) : $k;
			$out[ $nk ] = sc_dr_value( $x, $r, $n, $depth + 1 );
		}
		return $out;
	}
	if ( is_object( $v ) ) {
		if ( $v instanceof __PHP_Incomplete_Class ) {
			$s   = serialize( $v );
			$c   = 0;
			$new = sc_dr_str( $s, $r, $c );
			if ( $c > 0 ) {
				$n  += $c;
				$obj = @unserialize( sc_dr_fix_lengths( $new ), array( 'allowed_classes' => false ) ); // phpcs:ignore
				return false === $obj ? $v : $obj;
			}
			return $v;
		}
		foreach ( get_object_vars( $v ) as $k => $x ) {
			$v->$k = sc_dr_value( $x, $r, $n, $depth + 1 );
		}
		return $v;
	}
	return $v;
}

/** Recalcula las longitudes s:N:"..." de un texto serializado ya modificado. */
function sc_dr_fix_lengths( string $s ): string {
	return (string) preg_replace_callback(
		'/s:\d+:"(.*?)";(?=(?:[aOsidbN}]|$))/s',
		static function ( $m ) {
			return 's:' . strlen( $m[1] ) . ':"' . $m[1] . '";';
		},
		$s
	);
}

/** Tablas/columnas a procesar: [tabla, pk, [columnas]]. */
function sc_dr_targets(): array {
	global $wpdb;
	$t = array(
		array( $wpdb->options, 'option_id', array( 'option_value' ) ),
		array( $wpdb->posts, 'ID', array( 'post_content', 'post_excerpt', 'post_title', 'post_content_filtered', 'to_ping', 'pinged' ) ), // guid NO.
		array( $wpdb->postmeta, 'meta_id', array( 'meta_value' ) ),
		array( $wpdb->usermeta, 'umeta_id', array( 'meta_value' ) ),
		array( $wpdb->termmeta, 'meta_id', array( 'meta_value' ) ),
		array( $wpdb->term_taxonomy, 'term_taxonomy_id', array( 'description' ) ),
		array( $wpdb->comments, 'comment_ID', array( 'comment_content', 'comment_author_url' ) ),
		array( $wpdb->commentmeta, 'meta_id', array( 'meta_value' ) ),
		array( $wpdb->links, 'link_id', array( 'link_url', 'link_image', 'link_description' ) ),
	);
	return (array) apply_filters( 'sc_replace_domain_targets', $t );
}

function sc_dr_table_exists( string $table ): bool {
	global $wpdb;
	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
}

/**
 * Reemplaza $from por $to en toda la base de datos de forma segura.
 *
 * @return array{ok:bool,from:string,to:string,filas:int,reemplazos:int,tablas:array,restantes:array,errores:array,css:string}
 */
function sc_replace_domain( string $from, string $to ): array {
	global $wpdb;
	$res = array(
		'ok'         => false,
		'from'       => $from,
		'to'         => $to,
		'filas'      => 0,
		'reemplazos' => 0,
		'tablas'     => array(),
		'restantes'  => array(),
		'errores'    => array(),
		'css'        => '',
	);
	$r = sc_dr_rules( $from, $to );
	if ( '' === $r['from_base'] || '' === $r['to_host'] ) {
		$res['errores'][] = 'Dominio de origen o destino vacío.';
		return $res;
	}
	if ( ! preg_match( '/^[a-z0-9.-]+(:\d+)?$/', $r['from_host'] ) || ! preg_match( '/^[a-z0-9.-]+(:\d+)?$/', $r['to_host'] ) ) {
		$res['errores'][] = 'Dominio no válido.';
		return $res;
	}
	if ( $r['noop'] ) {
		$res['ok'] = true;
		return $res;
	}

	@set_time_limit( 0 ); // phpcs:ignore
	$like_host = preg_replace( '/:\d+$/', '', $r['from_base'] ); // Ya validado: solo a-z 0-9 . -
	$old_sup   = $wpdb->suppress_errors( true );

	foreach ( sc_dr_targets() as $target ) {
		list( $table, $pk, $cols ) = $target;
		if ( ! sc_dr_table_exists( $table ) ) {
			continue;
		}
		$where = array();
		foreach ( $cols as $c ) {
			$where[] = "`$c` LIKE '%" . $like_host . "%'";
		}
		$whereSql = '(' . implode( ' OR ', $where ) . ')';
		$engine   = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		$tx       = ( 'InnoDB' === $engine );
		if ( $tx ) {
			$wpdb->query( 'START TRANSACTION' );
		}
		$changed = 0;
		$fail    = false;
		$last    = 0;
		while ( true ) {
			$rows = $wpdb->get_results(
				"SELECT * FROM `$table` WHERE `$pk` > " . (int) $last . " AND $whereSql ORDER BY `$pk` ASC LIMIT 200",
				ARRAY_A
			);
			if ( ! $rows ) {
				if ( '' !== $wpdb->last_error ) {
					$res['errores'][] = $table . ': ' . $wpdb->last_error;
					$fail             = true;
				}
				break;
			}
			foreach ( $rows as $row ) {
				$last = (int) $row[ $pk ];
				$upd  = array();
				foreach ( $cols as $c ) {
					if ( ! isset( $row[ $c ] ) || '' === $row[ $c ] ) {
						continue;
					}
					$n   = 0;
					$new = sc_dr_value( (string) $row[ $c ], $r, $n );
					if ( $n > 0 && $new !== $row[ $c ] ) {
						$upd[ $c ]            = $new;
						$res['reemplazos']   += $n;
					}
				}
				if ( $upd ) {
					$ok = $wpdb->update( $table, $upd, array( $pk => $row[ $pk ] ) );
					if ( false === $ok ) {
						$res['errores'][] = $table . ': ' . $wpdb->last_error;
						$fail             = true;
						break 2;
					}
					++$changed;
				}
			}
		}
		if ( $tx ) {
			$wpdb->query( $fail ? 'ROLLBACK' : 'COMMIT' );
		}
		if ( $fail ) {
			$res['reemplazos'] = max( 0, $res['reemplazos'] );
			$res['tablas'][ $table ] = 'error';
			continue;
		}
		if ( $changed ) {
			$res['tablas'][ $table ] = $changed;
			$res['filas']           += $changed;
		}
	}

	// URLs base (por si estaban fuera de serie) y caché.
	foreach ( array( 'home', 'siteurl' ) as $o ) {
		$cur = (string) get_option( $o );
		$n   = 0;
		$new = sc_dr_str( $cur, $r, $n );
		if ( $n > 0 ) {
			update_option( $o, $new );
		}
	}
	wp_cache_flush();

	// Verificación: ¿queda el dominio viejo?
	foreach ( sc_dr_targets() as $target ) {
		list( $table, $pk, $cols ) = $target;
		if ( ! sc_dr_table_exists( $table ) ) {
			continue;
		}
		$left = 0;
		foreach ( $cols as $c ) {
			$vals = $wpdb->get_col( "SELECT `$c` FROM `$table` WHERE `$c` LIKE '%" . $like_host . "%'" );
			foreach ( (array) $vals as $val ) {
				if ( sc_dr_would_change( (string) $val, $r ) ) {
					++$left;
				}
			}
		}
		if ( $left ) {
			$res['restantes'][ $table ] = $left;
		}
	}
	$wpdb->suppress_errors( $old_sup );

	$res['css'] = sc_dr_clear_css();
	if ( function_exists( 'flush_rewrite_rules' ) ) {
		flush_rewrite_rules( false );
	}
	$res['ok'] = ! $res['errores'] && ! $res['restantes'];
	return $res;
}

/** ¿Este fragmento cambiaría? (evita falsos positivos cuando to contiene a from). */
function sc_dr_would_change( string $frag, array $r ): bool {
	$n = 0;
	sc_dr_str( $frag, $r, $n );
	return $n > 0;
}

/** Regenera el CSS de Elementor (que contiene URLs de imágenes). */
function sc_dr_clear_css(): string {
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		try {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			return 'elementor';
		} catch ( \Throwable $e ) {
			// Continúa con el plan B.
		}
	}
	$u   = wp_upload_dir( null, false );
	$dir = ( $u['basedir'] ?? '' ) . '/elementor/css';
	$del = 0;
	if ( is_dir( $dir ) ) {
		foreach ( (array) glob( $dir . '/*.css' ) as $f ) {
			if ( is_file( $f ) && @unlink( $f ) ) { // phpcs:ignore
				++$del;
			}
		}
	}
	delete_post_meta_by_key( '_elementor_css' );
	delete_option( '_elementor_global_css' );
	delete_option( 'elementor-custom-breakpoints-files' );
	return 'archivos:' . $del;
}
