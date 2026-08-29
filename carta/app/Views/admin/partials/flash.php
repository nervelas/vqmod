<?php
/** Avisos de un solo uso. */
$all = isset($flashes) ? $flashes : array();
$map = array('success' => 'alert-success', 'error' => 'alert-error', 'info' => '');
foreach ($all as $type => $messages):
    foreach ((array)$messages as $m): ?>
      <div class="alert <?= isset($map[$type]) ? $map[$type] : '' ?>" role="status">
        <span><?= e($m) ?></span>
      </div>
    <?php endforeach;
endforeach;
