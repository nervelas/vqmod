<?php
if (!defined('ABSPATH')) {
	exit;
}
$uid = wp_unique_id('sc-search-');
?>
<form role="search" method="get" class="sc-searchform" action="<?php echo esc_url(home_url('/')); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr($uid); ?>">Buscar</label>
	<input type="search" id="<?php echo esc_attr($uid); ?>" class="sc-searchform__input" placeholder="Buscar…" value="<?php echo esc_attr(get_search_query()); ?>" name="s">
	<button type="submit" class="sc-searchform__btn" aria-label="Buscar"><?php echo servicom_icon('search', 20); // phpcs:ignore ?></button>
</form>
