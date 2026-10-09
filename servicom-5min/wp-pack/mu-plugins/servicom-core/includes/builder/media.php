<?php
/**
 * Servicom builder: paso "media". Importa assets a la biblioteca de medios.
 * Idempotente (meta _sc_key = "asset:<id>"), reanudable por lotes (more:true).
 */
if (!defined('ABSPATH')) {
	exit;
}

class SC_Build_Media
{
	public static function run(array $args)
	{
		$m = SC_Builder::manifest();
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$assets = $m['assets'];
		$known = SC_Builder::keymap('asset:');
		$up = wp_upload_dir();
		if (!empty($up['error'])) {
			throw new SC_Builder_Fatal('uploads no escribible: ' . $up['error'], true);
		}
		$map = array();
		$created = 0;
		$skipped = 0;
		$failed = array();
		$more = false;
		$thumbs = 0;

		foreach ($assets as $a) {
			$aid = (string) ($a['id'] ?? '');
			if ($aid === '' || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $aid)) {
				continue;
			}
			$key = 'asset:' . $aid;
			$existing = $known[$key] ?? 0;
			if ($existing && get_post_meta($existing, '_sc_done', true) === '1' && get_attached_file($existing) && file_exists(get_attached_file($existing))) {
				$map[$aid] = $existing;
				$skipped++;
				continue;
			}
			if (SC_Builder::out_of_time(4)) {
				$more = true;
				continue;
			}
			$rel = (string) ($a['path'] ?? '');
			$src = self::safe_path($rel);
			if ($src === null) {
				$failed[$aid] = 'ruta de asset inválida o inexistente';
				continue;
			}
			try {
				$id = self::import($a, $src, $existing);
			} catch (Throwable $e) {
				$failed[$aid] = substr($e->getMessage(), 0, 120);
				continue;
			}
			$map[$aid] = $id;
			$created++;
			$known[$key] = $id;
		}

		$logo = SC_Builder::apply_logo();
		$s = SC_Builder::state();
		$s['media'] = array('map' => $map + (array) ($s['media']['map'] ?? array()), 'total' => count($assets), 'failed' => $failed);
		if (!$more) {
			$s['steps']['media'] = array('done' => true, 'at' => time());
		}
		SC_Builder::save_state($s);
		if ($failed) {
			SC_Builder::note('assets con error: ' . implode(',', array_keys($failed)));
		}
		return array('data' => array(
			'map' => $map, 'created' => $created, 'skipped' => $skipped, 'failed' => $failed,
			'remaining' => $more ? max(0, count($assets) - count($map) - count($failed)) : 0, 'logo' => $logo,
		), 'more' => $more);
	}

	/** Resuelve una ruta relativa del manifiesto dentro de assets/ (sin escapar del job). */
	private static function safe_path($rel)
	{
		$rel = str_replace('\\', '/', $rel);
		if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) {
			return null;
		}
		$p = SC_Builder::$dir . '/' . $rel;
		$real = realpath($p);
		$root = realpath(SC_Builder::$dir);
		if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
			return null;
		}
		return $real;
	}

	private static function import(array $a, $src, $existingId)
	{
		$aid = (string) $a['id'];
		$mime = (string) ($a['mime'] ?? '');
		$check = wp_check_filetype(basename($src), null);
		if (!$check['type'] || strpos($check['type'], 'image/') !== 0) {
			throw new RuntimeException('tipo de archivo no permitido');
		}
		$mime = $check['type'];
		$info = @getimagesize($src);
		if (!$info) {
			throw new RuntimeException('imagen ilegible');
		}
		$up = wp_upload_dir();
		$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
		$name = 'sc-' . sanitize_file_name($aid) . '.' . $ext; // nombre determinista: un huérfano se sobrescribe
		$dest = null;
		$id = (int) $existingId;
		if ($id) {
			// reintento de un adjunto a medias: reutilizar su archivo si existe
			$cur = get_attached_file($id);
			if ($cur && file_exists($cur)) {
				$dest = $cur;
			}
		}
		if (!$dest) {
			$dest = $up['path'] . '/' . $name;
			if (!@copy($src, $dest)) {
				throw new RuntimeException('no se pudo copiar el archivo');
			}
			@chmod($dest, 0644);
		}
		$alt = (string) ($a['alt'] ?? '');
		if (!$id) {
			$title = $alt !== '' ? mb_substr($alt, 0, 80) : $aid;
			$id = wp_insert_attachment(wp_slash(array(
				'post_mime_type' => $mime,
				'post_title' => $title,
				'post_name' => 'sc-asset-' . sanitize_title($aid),
				'post_status' => 'inherit',
				'post_content' => '',
				'meta_input' => array('_sc_key' => 'asset:' . $aid),
			)), $dest, 0, true);
			if (is_wp_error($id) || !$id) {
				@unlink($dest);
				throw new RuntimeException('wp_insert_attachment falló');
			}
		} else {
			update_attached_file($id, $dest);
		}
		update_post_meta($id, '_wp_attachment_image_alt', wp_slash($alt));
		$meta = wp_generate_attachment_metadata($id, $dest);
		if (is_wp_error($meta) || empty($meta)) {
			// GD/Imagick sin soporte para el formato: se usa solo el original.
			SC_Builder::note('sin miniaturas para ' . $aid);
			$meta = array('width' => (int) $info[0], 'height' => (int) $info[1], 'file' => _wp_relative_upload_path($dest), 'sizes' => array());
		}
		wp_update_attachment_metadata($id, $meta);
		update_post_meta($id, '_sc_done', '1');
		return (int) $id;
	}
}
