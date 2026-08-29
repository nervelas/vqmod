<?php /** Tabla compacta de platillos más o menos vendidos. */ ?>
<?php if (!empty($rows)): ?>
  <div class="table-wrap">
    <table class="data" style="min-width:0">
      <thead><tr><th>Platillo</th><th class="num">Uds.</th><th class="num">Ventas</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['name']) ?></td>
            <td class="num tabular"><?= (int)$r['qty'] ?></td>
            <td class="num tabular"><?= e(mg_money($r['revenue'], $cur)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="muted" style="font-size:var(--step--1)">Sin ventas en el periodo.</p>
<?php endif; ?>
