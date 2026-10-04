<?php
/** @var string $tz @var string $today @var string $tomorrow @var array $agenda @var array $pending @var int $pendingTotal @var array $upcoming @var array $kpis @var array $free @var bool $showOnboarding @var bool $canCreate @var string $userName */
use App\Controllers\Admin\A1Support;
use App\Core\Clock;
use App\Core\Fmt;
use App\Core\Tz;

$hour = (int) Tz::formatTs(Clock::now(), $tz, 'G');
$greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
$first = trim(explode(' ', $userName)[0] ?? '');
$todayLong = Fmt::dateLong(Tz::localToUtc($today . ' 12:00:00', $tz), $tz);
$back = '/admin';
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1 class="page-title serif"><?= e($greeting . ($first !== '' ? ', ' . $first : '')) ?></h1>
      <p class="page-sub"><?= e(ucfirst($todayLong)) ?></p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline" href="<?= e(url('/admin/calendario')) ?>"><?= icon('calendar') ?>Ver calendario</a>
      <?php if ($canCreate) : ?><a class="btn btn-gold" href="<?= e(url('/admin/citas/nueva')) ?>"><?= icon('plus') ?>Nueva cita</a><?php endif; ?>
    </div>
  </div>

  <?php if ($showOnboarding) : ?>
    <aside class="card card-gold onboarding-banner">
      <div class="card-body row row-between row-wrap gap-3">
        <div class="row gap-3">
          <span class="ob-mark"><?= icon('sparkle') ?></span>
          <div>
            <p class="ob-title serif">Termina de preparar tu agenda</p>
            <p class="muted">El Asistente de inicio te guía para configurar tu negocio, tus horarios y tu primer servicio en pocos minutos.</p>
          </div>
        </div>
        <a class="btn btn-gold" href="<?= e(url('/admin/asistente')) ?>">Abrir el asistente</a>
      </div>
    </aside>
  <?php endif; ?>

  <section class="kpis kpis-5" aria-label="Indicadores">
    <div class="stat">
      <span class="stat-label">Citas de hoy</span>
      <span class="stat-value serif" data-ticker="<?= (int) $kpis['today'] ?>"><?= (int) $kpis['today'] ?></span>
    </div>
    <div class="stat">
      <span class="stat-label">Citas de la semana</span>
      <span class="stat-value serif" data-ticker="<?= (int) $kpis['week'] ?>"><?= (int) $kpis['week'] ?></span>
    </div>
    <?php if ($kpis['revenue'] !== null) : ?>
    <div class="stat">
      <span class="stat-label">Ingresos del mes</span>
      <span class="stat-value serif" data-ticker="<?= e((string) round((float) $kpis['revenue'], 2)) ?>" data-format="money" data-prefix="<?= e((string) setting('currency_symbol', 'Q')) ?>" data-decimals="2"><?= e(money($kpis['revenue'])) ?></span>
    </div>
    <?php else : ?>
    <div class="stat">
      <span class="stat-label">Completadas este mes</span>
      <span class="stat-value serif" data-ticker="<?= (int) $kpis['completed'] ?>"><?= (int) $kpis['completed'] ?></span>
    </div>
    <?php endif; ?>
    <div class="stat">
      <span class="stat-label">No asistieron (mes)</span>
      <span class="stat-value serif" data-ticker="<?= (int) $kpis['noshow'] ?>"><?= (int) $kpis['noshow'] ?></span>
    </div>
    <div class="stat">
      <span class="stat-label">Ocupación (7 días)</span>
      <?php if ($kpis['occupancy'] !== null) : ?>
        <span class="stat-value serif"><span data-ticker="<?= (int) $kpis['occupancy'] ?>"><?= (int) $kpis['occupancy'] ?></span>%</span>
        <span class="progress" role="img" aria-label="<?= (int) $kpis['occupancy'] ?> por ciento de ocupación"><span <?= vars(['--p' => (int) $kpis['occupancy']]) ?>></span></span>
      <?php else : ?>
        <span class="stat-value serif muted">—</span>
        <span class="stat-delta muted">Define tus horarios para medirla</span>
      <?php endif; ?>
    </div>
  </section>

  <div class="dash-grid">
    <div class="stack dash-col">
    <section class="card dash-agenda" aria-labelledby="h-agenda">
      <div class="card-head row row-between">
        <h2 class="serif" id="h-agenda">Agenda de hoy</h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/calendario', ['vista' => 'dia'])) ?>">Ver en calendario<?= icon('arrow-right') ?></a>
      </div>
      <?php if (!$agenda) : ?>
        <div class="empty">
          <?= icon('calendar') ?>
          <p class="empty-title serif">Hoy no tienes citas</p>
          <p class="empty-text">Un día despejado. Aprovecha para ordenar tu agenda o agenda una cita manual.</p>
          <?php if ($canCreate) : ?><a class="btn btn-outline" href="<?= e(url('/admin/citas/nueva', ['fecha' => $today])) ?>">Agendar una cita</a><?php endif; ?>
        </div>
      <?php else : ?>
        <ol class="ag-list">
          <?php foreach ($agenda as $b) : ?>
            <li>
              <a class="ag-row ag-<?= e($b['status']) ?>" href="<?= e(url('/admin/citas/' . $b['id'])) ?>" <?= vars(['--c' => $b['host_color']]) ?>>
                <span class="ag-time mono"><?= e(Fmt::time($b['starts_at'], $tz)) ?></span>
                <span class="ag-main">
                  <strong><?= e($b['guest_name']) ?></strong>
                  <span class="muted"><?= e($b['event_name']) ?> · <?= e(Fmt::duration((int) $b['duration'])) ?> · <?= e($b['host_name']) ?></span>
                </span>
                <span class="badge <?= e(A1Support::statusBadge($b['status'])) ?>"><?= e(A1Support::statusLabel($b['status'])) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <section class="card dash-upcoming" aria-labelledby="h-up">
      <div class="card-head row row-between">
        <h2 class="serif" id="h-up">Próximos eventos</h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/citas', ['desde' => $tomorrow])) ?>">Ver lista</a>
      </div>
      <?php if (!$upcoming) : ?>
        <div class="empty">
          <?= icon('inbox') ?>
          <p class="empty-title serif">Aún no hay próximas citas</p>
          <p class="empty-text">Comparte tu enlace de reservas para recibir nuevas citas.</p>
        </div>
      <?php else : ?>
        <ul class="up-list">
          <?php foreach ($upcoming as $b) : ?>
            <li>
              <a class="up-row" href="<?= e(url('/admin/citas/' . $b['id'])) ?>" <?= vars(['--c' => $b['host_color']]) ?>>
                <span class="up-date mono"><?= e(Fmt::dateShort($b['starts_at'], $tz)) ?><br><?= e(Fmt::time($b['starts_at'], $tz)) ?></span>
                <span class="ag-main"><strong><?= e($b['guest_name']) ?></strong><span class="muted"><?= e($b['event_name']) ?> · <?= e($b['host_name']) ?></span></span>
                <?php if ($b['status'] === 'pending') : ?><span class="badge badge-warn">Pendiente</span><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    </div>
    <div class="stack dash-col">
    <section class="card dash-pending" aria-labelledby="h-pend">
      <div class="card-head row row-between">
        <h2 class="serif" id="h-pend">Por aprobar<?php if ($pendingTotal > 0) : ?> <span class="badge badge-warn"><?= (int) $pendingTotal ?></span><?php endif; ?></h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/citas', ['estado' => 'pending'])) ?>">Ver todas</a>
      </div>
      <?php if (!$pending) : ?>
        <div class="empty">
          <?= icon('check') ?>
          <p class="empty-title serif">Todo al día</p>
          <p class="empty-text">No hay citas esperando tu aprobación.</p>
        </div>
      <?php else : ?>
        <ul class="pend-list">
          <?php foreach ($pending as $b) : ?>
            <li class="pend-item">
              <a class="pend-info" href="<?= e(url('/admin/citas/' . $b['id'])) ?>">
                <strong><?= e($b['guest_name']) ?></strong>
                <span class="muted"><?= e($b['event_name']) ?></span>
                <span class="mono small"><?= e(Fmt::dateShort($b['starts_at'], $tz)) ?> · <?= e(Fmt::time($b['starts_at'], $tz)) ?></span>
              </a>
              <div class="pend-actions">
                <?php foreach ([['confirmed', 'Aprobar', 'btn-gold', 'check'], ['rejected', 'Rechazar', 'btn-ghost', 'x']] as [$st, $lbl, $cls, $ic]) : ?>
                  <form method="post" action="<?= e(url('/admin/citas/' . $b['id'] . '/estado')) ?>" class="inline" <?= $st === 'rejected' ? 'data-confirm="¿Rechazar esta cita? Se avisará a la persona."' : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= e($st) ?>">
                    <input type="hidden" name="volver" value="<?= e($back) ?>">
                    <button class="btn btn-sm <?= e($cls) ?>" type="submit"><?= icon($ic) ?><?= e($lbl) ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card dash-free" aria-labelledby="h-free">
      <div class="card-head"><h2 class="serif" id="h-free">Huecos libres</h2></div>
      <?php if (!$free) : ?>
        <div class="empty">
          <?= icon('clock') ?>
          <p class="empty-title serif">Sin huecos para hoy ni mañana</p>
          <p class="empty-text">Cuando haya horarios disponibles aparecerán aquí para agendar con un clic.</p>
        </div>
      <?php else : ?>
        <div class="free-list">
          <?php foreach ($free as $f) : ?>
            <div class="free-event" <?= vars(['--c' => $f['event']['color']]) ?>>
              <p class="free-name"><span class="dot-c"></span><?= e($f['event']['name']) ?></p>
              <?php foreach ([[$today, 'Hoy'], [$tomorrow, 'Mañana']] as [$d, $lbl]) : if (!$f['days'][$d]) { continue; } ?>
                <div class="free-day">
                  <span class="free-day-label muted"><?= e($lbl) ?></span>
                  <div class="chips">
                    <?php foreach ($f['days'][$d] as $s) : ?>
                      <a class="chip mono" href="<?= e(url('/admin/citas/nueva', ['evento' => $f['event']['id'], 'inicio' => $s['start'], 'anfitrion' => $s['host'] ?: null])) ?>"><?= e(Fmt::time($s['start'], $tz)) ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    </div>
  </div>
</div>
