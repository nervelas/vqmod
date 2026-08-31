<?php
/**
 * OceanWP Customizer Inline CSS Output for Accessibility.
 *
 * @package OceanWP WordPress theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OceanWP Accessibility CSS output class.
 */
class OceanWP_Customize_A11Y_CSS {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'add_inline_css' ), 999 );
	}

	/**
	 * Add standalone keyboard focus outline CSS.
	 *
	 * This intentionally runs only when full Accessibility Mode is disabled.
	 * When full Accessibility Mode is enabled, the dedicated a11y stylesheet owns
	 * all focus styles and CSS variables.
	 *
	 * @return void
	 */
	public function add_inline_css() {

		if ( function_exists( 'oceanwp_is_accessibility_feature_enabled' )
			&& oceanwp_is_accessibility_feature_enabled( 'ocean_accessibility_mode' ) ) {
			return;
		}

		if ( true !== (bool) get_theme_mod( 'ocean_accessibility_keyboard_focus_outline', false ) ) {
			return;
		}

		$css = $this->generate_css();

		if ( empty( $css ) ) {
			return;
		}

		wp_add_inline_style( 'oceanwp-style', $css );
	}

	/**
	 * Generate standalone keyboard focus outline CSS.
	 *
	 * No CSS variables are used here because the variables stylesheet is not
	 * printed when full Accessibility Mode is disabled.
	 *
	 * @return string
	 */
	public function generate_css() {

		$focusable_selectors = 'body :where(a[href],area[href],button:not([disabled]),input:not([type="hidden"]):not([disabled]),select:not([disabled]),textarea:not([disabled]),summary,iframe,[tabindex]:not([tabindex="-1"]),[role="button"],[role="link"],[role="menuitem"],[role="tab"],[contenteditable="true"])';

		$button_selectors = 'body :where(input[type="button"]:not([disabled]),input[type="reset"]:not([disabled]),input[type="submit"]:not([disabled]),button[type="submit"]:not([disabled]),.theme-button,.button,div.wpforms-container-full .wpforms-form input[type="submit"],div.wpforms-container-full .wpforms-form button[type="submit"],div.wpforms-container-full .wpforms-form .wpforms-page-button,.wp-block-button__link,.wp-element-button)';

		$button_outline_color = $this->get_button_outline_color();

		$css  = '/* OceanWP keyboard focus outline */';
		$css .= $focusable_selectors . ':focus{outline:2px solid currentColor;outline-offset:2px;}';
		$css .= $focusable_selectors . ':focus:not(:focus-visible){outline:none;}';
		$css .= $focusable_selectors . ':focus-visible{outline:2px solid currentColor;outline-offset:2px;}';
		$css .= $button_selectors . ':focus-visible{outline-color:' . $button_outline_color . ';}';
		$css .= '@media (forced-colors: active){' . $focusable_selectors . ':focus-visible{outline-color:Highlight;}}';

		return $css;
	}

	/**
	 * Get the standalone button focus outline color.
	 *
	 * @return string
	 */
	private function get_button_outline_color() {

		$default = function_exists( 'oceanwp_get_theme_mod_default' )
			? oceanwp_get_theme_mod_default( 'ocean_primary_color', '#13aff0' )
			: '#13aff0';

		$color = get_theme_mod( 'ocean_primary_color', $default );
		$color = sanitize_hex_color( $color );

		return $color ? $color : '#13aff0';
	}
}

return new OceanWP_Customize_A11Y_CSS();
