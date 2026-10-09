<?php
add_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('custom-logo'); register_nav_menu('primary', 'Principal'); });
