<?php
/**
 * Players are now managed INSIDE each team (admin/teams.php).
 * This page redirects any old link to the teams module.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();

// If an old link targeted a specific league, keep the filter.
$league = int_input('league');
redirect(base_url('admin/teams.php' . ($league ? '?league=' . $league : '')));
