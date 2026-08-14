<?php
/**
 * SEO básico: meta description, Open Graph y Schema (Organization/Website).
 * Se desactiva automáticamente si Yoast o Rank Math están activos.
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CGM_SEO {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 5 );
	}

	/**
	 * ¿Debe emitir metadatos? No si hay plugin SEO dedicado.
	 *
	 * @return bool
	 */
	private function should_output() {
		if ( ! cgm_get_option( 'seo_output' ) ) {
			return false;
		}
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Emite los metadatos en <head>.
	 */
	public function output() {
		if ( ! $this->should_output() ) {
			return;
		}

		$brand   = cgm_get_option( 'brand_name', 'CGM' );
		$desc    = $this->current_description();
		$title   = wp_get_document_title();
		$url     = home_url( add_query_arg( null, null ) );
		$og_img  = cgm_get_image_url( 'seo_og_id', 'large' );
		if ( ! $og_img && is_singular() && has_post_thumbnail() ) {
			$og_img = get_the_post_thumbnail_url( null, 'large' );
		}

		echo "\n<!-- CGM SEO -->\n";
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( $brand ) . '">' . "\n";
		echo '<meta property="og:type" content="' . ( is_singular( 'product' ) ? 'product' : 'website' ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		if ( $og_img ) {
			echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

		$this->schema( $brand, $desc );
		echo "<!-- /CGM SEO -->\n";
	}

	/**
	 * Descripción contextual.
	 *
	 * @return string
	 */
	private function current_description() {
		if ( is_singular() ) {
			global $post;
			if ( $post && ! empty( $post->post_excerpt ) ) {
				return wp_strip_all_tags( $post->post_excerpt );
			}
			if ( $post ) {
				return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30 );
			}
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->description ) ) {
				return wp_strip_all_tags( $term->description );
			}
		}
		return (string) cgm_get_option( 'seo_description' );
	}

	/**
	 * Schema JSON-LD Organization + WebSite.
	 *
	 * @param string $brand Nombre.
	 * @param string $desc  Descripción.
	 */
	private function schema( $brand, $desc ) {
		if ( ! is_front_page() && ! is_home() ) {
			return;
		}
		$logo   = cgm_get_image_url( 'logo_id', 'full', get_template_directory_uri() . '/assets/images/logo-cgm.svg' );
		$social = array_filter(
			array(
				cgm_get_option( 'facebook' ),
				cgm_get_option( 'instagram' ),
				cgm_get_option( 'tiktok' ),
			)
		);

		$org = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Organization',
			'name'        => $brand,
			'url'         => home_url( '/' ),
			'logo'        => $logo,
			'description' => $desc,
			'sameAs'      => array_values( $social ),
		);
		$email = cgm_get_option( 'email' );
		$phone = cgm_get_option( 'phone' );
		if ( $email || $phone ) {
			$org['contactPoint'] = array(
				'@type'       => 'ContactPoint',
				'contactType' => 'customer service',
				'email'       => $email,
				'telephone'   => $phone,
				'areaServed'  => 'GT',
			);
		}

		$website = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => $brand,
			'url'      => home_url( '/' ),
		);
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_id' ) ) {
			$shop = wc_get_page_id( 'shop' );
			if ( $shop > 0 ) {
				$website['potentialAction'] = array(
					'@type'       => 'SearchAction',
					'target'      => get_permalink( $shop ) . '?s={search_term_string}&post_type=product',
					'query-input' => 'required name=search_term_string',
				);
			}
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $org ) . '</script>' . "\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $website ) . '</script>' . "\n";
	}
}
