<?php get_header(); while (have_posts()) { the_post(); echo '<main><h1>'; the_title(); echo '</h1>'; the_content(); echo '</main>'; } get_footer();
