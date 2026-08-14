<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (!defined('FL_NOT_INSTALLED')) {
    Security::startSession();
    Audit::log('logout', 'auth');
    Auth::logout();
}
redirect(base_url('admin/login.php'));
