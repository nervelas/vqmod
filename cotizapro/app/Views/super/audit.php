<div class="card">
  <div class="card__head"><span class="secnum">01/</span><h2><?= e(number_format($total)) ?> registros globales</h2></div>
  <div class="card__body card__body--flush tablescroll">
    <table class="datatable" style="border:0;border-radius:0">
      <caption class="sr-only">Bitácora global</caption>
      <thead><tr><th scope="col">Fecha</th><th scope="col">Empresa</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Detalle</th><th scope="col">IP</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="small nowrap"><?= e(fechaHora((string) $r['created_at'])) ?></td>
            <td class="small"><?= e($r['company_name'] ?: 'plataforma') ?></td>
            <td class="small"><?= e($r['user_name'] ?: '—') ?></td>
            <td><span class="badge"><?= e($r['action']) ?></span></td>
            <td class="small"><?= e(str_limit((string) $r['details'], 70)) ?></td>
            <td class="small muted"><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;padding:36px;color:var(--steel)">Sin registros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Paginación">
    <?php
    $mk = static fn (int $n): string => url('/super/bitacora') . ($n > 1 ? '?p=' . $n : '');
    if ($page > 1) { echo '<a href="' . e($mk($page - 1)) . '">Anterior</a>'; }
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        echo $i === $page ? '<span class="is-cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($mk($i)) . '">' . $i . '</a>';
    }
    if ($page < $pages) { echo '<a href="' . e($mk($page + 1)) . '">Siguiente</a>'; }
    ?>
  </nav>
<?php endif; ?>
