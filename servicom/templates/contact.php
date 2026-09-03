<?php
declare(strict_types=1);
/** @var array $page @var array $FORM */
$headTitle  = (string) $page['title'];
$headSub    = (string) $page['subtitle'];
$headCrumbs = [['name' => $headTitle]];
require __DIR__ . '/layout/pagehead.php';
?>
<?php require __DIR__ . '/sections/contact.php'; ?>
<?php $limitFaqs = 6; require __DIR__ . '/sections/faq.php'; ?>
