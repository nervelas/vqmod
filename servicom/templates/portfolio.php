<?php
declare(strict_types=1);
/** @var array $page */
$headTitle  = (string) $page['title'];
$headSub    = (string) $page['subtitle'];
$headCrumbs = [['name' => $headTitle]];
require __DIR__ . '/layout/pagehead.php';
$limitProjects = null;
$showBtn = false;
?>
<?php require __DIR__ . '/sections/portfolio.php'; ?>
<?php require __DIR__ . '/sections/services.php'; ?>
<?php require __DIR__ . '/sections/cta.php'; ?>
