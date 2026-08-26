<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();

$pageKey = 'dashboard';
$pageTitle = 'Dashboard';
$pageSub = $user['role'] === 'admin' ? 'Organization overview' : 'Your overview';
$today = date('Y-m-d');

include __DIR__ . '/includes/header.php';

if ($user['role'] === 'admin') {
    // ---------------- Admin dashboard ----------------
    $employees = get_employees();
    $pdo = db();

    $scheduledToday = (int)$pdo->query("SELECT COUNT(*) c FROM roster WHERE shift_date = '$today'")->fetch()['c'];
    $presentToday = (int)$pdo->query("SELECT COUNT(*) c FROM attendance WHERE attendance_date = '$today' AND status='present'")->fetch()['c'];
    $totalCredits = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) t FROM payroll_ledger WHERE amount > 0")->fetch()['t'];
    $totalDebits = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) t FROM payroll_ledger WHERE amount < 0")->fetch()['t'];
    $totalNet = array_sum(array_map('net_pay', $employees));

    $weekStart = start_of_week($today);
    $days = [];
    for ($i = 0; $i < 7; $i++) $days[] = date('Y-m-d', strtotime($weekStart . " +$i days"));
    $rosterMap = get_roster_range($days[0], $days[6]);
    ?>

    <div class="grid g-4">
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--teal-soft);color:var(--teal)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg></div></div>
        <div class="label">Scheduled today</div>
        <div class="val"><?= $scheduledToday ?></div>
        <div class="delta up"><?= $presentToday ?> checked in so far</div>
      </div>
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--amber-soft);color:#B47A16"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"/><circle cx="12" cy="12.5" r="3"/></svg></div></div>
        <div class="label">Total monthly payroll</div>
        <div class="val"><?= fmt_money($totalNet) ?></div>
        <div class="delta">base + bonus − deduction ± attendance</div>
      </div>
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--teal-soft);color:var(--teal)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 6l-9.5 9.5-5-5L1 18"/></svg></div></div>
        <div class="label">Attendance credits</div>
        <div class="val"><?= fmt_money($totalCredits) ?></div>
        <div class="delta up">+₹50 per shift attended</div>
      </div>
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--coral-soft);color:var(--coral)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 18l-9.5-9.5-5 5L1 6"/></svg></div></div>
        <div class="label">Attendance deductions</div>
        <div class="val"><?= fmt_money($totalDebits) ?></div>
        <div class="delta down">−₹50 per missed shift</div>
      </div>
    </div>

    <div class="section-title">This week</div>
    <div class="grid g-2">
      <div class="card card-pad">
        <div class="card-head"><h3>Team attendance ring</h3><a class="link" href="attendance.php">View attendance →</a></div>
        <div style="display:flex;flex-wrap:wrap;gap:22px;">
          <?php foreach ($employees as $e):
              $p = (int)$pdo->query("SELECT COUNT(*) c FROM attendance WHERE employee_id={$e['id']} AND status='present'")->fetch()['c'];
              $ab = (int)$pdo->query("SELECT COUNT(*) c FROM attendance WHERE employee_id={$e['id']} AND status='absent'")->fetch()['c'];
          ?>
          <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div class="ring-wrap">
              <?= ring_svg($p, $ab, 68, 8, h($e['color'])) ?>
              <div class="center"><div class="n" style="font-size:13px;"><?= $p ?></div></div>
            </div>
            <div style="font-size:11px;color:var(--muted);max-width:70px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h(explode(' ', $e['name'])[0]) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card card-pad">
        <div class="card-head"><h3>Payroll snapshot</h3><a class="link" href="payroll.php">Open payroll →</a></div>
        <table>
          <thead><tr><th>Employee</th><th>Base</th><th>Adj.</th><th>Net</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($employees, 0, 6) as $e): $adj = attendance_adjustment($e['id']); ?>
            <tr>
              <td><div class="emp-cell"><div class="avatar" style="width:28px;height:28px;font-size:11px;background:<?= h($e['color']) ?>"><?= h(initials($e['name'])) ?></div><div class="name"><?= h($e['name']) ?></div></div></td>
              <td class="mono"><?= fmt_money($e['base_salary']) ?></td>
              <td class="mono" style="color:<?= $adj >= 0 ? 'var(--teal)' : 'var(--coral)' ?>"><?= $adj >= 0 ? '+' : '' ?><?= fmt_money($adj) ?></td>
              <td class="mono" style="font-weight:700;"><?= fmt_money(net_pay($e)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section-title">Roster this week</div>
    <div class="card card-pad" style="overflow-x:auto;">
      <table style="min-width:640px;">
        <thead><tr><th>Employee</th><?php foreach ($days as $d): ?><th><?= date('D', strtotime($d)) ?> <?= date('j', strtotime($d)) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
          <tr>
            <td><div class="emp-cell"><div class="avatar" style="width:26px;height:26px;font-size:10.5px;background:<?= h($e['color']) ?>"><?= h(initials($e['name'])) ?></div><div class="name"><?= h(explode(' ', $e['name'])[0]) ?></div></div></td>
            <?php foreach ($days as $d):
                $sh = $rosterMap[$e['id'] . '|' . $d] ?? null;
                if (!$sh): ?>
                  <td style="color:#C7CCDA;font-size:12px;">Off</td>
                <?php else: ?>
                  <td><span class="shift-chip <?= $sh['shift_type'] ?>"><?= SHIFT_TYPES[$sh['shift_type']]['label'] ?></span></td>
            <?php endif; endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
} else {
    // ---------------- Employee dashboard ----------------
    $e = get_employee($user['employee_id']);
    $pdo = db();
    $p = (int)$pdo->query("SELECT COUNT(*) c FROM attendance WHERE employee_id={$e['id']} AND status='present'")->fetch()['c'];
    $ab = (int)$pdo->query("SELECT COUNT(*) c FROM attendance WHERE employee_id={$e['id']} AND status='absent'")->fetch()['c'];
    $adj = attendance_adjustment($e['id']);
    $myNotifs = get_notifications($e['id'], 5);
    $myLedger = $pdo->prepare("SELECT * FROM payroll_ledger WHERE employee_id=? ORDER BY entry_date DESC");
    $myLedger->execute([$e['id']]);
    $myLedger = $myLedger->fetchAll();
    $todayShift = get_shift($e['id'], $today);

    $upcoming = [];
    for ($i = 0; $i < 5; $i++) {
        $d = date('Y-m-d', strtotime("$today +$i days"));
        $upcoming[] = ['date' => $d, 'shift' => get_shift($e['id'], $d)];
    }
    ?>
    <div class="grid g-3">
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--teal-soft);color:var(--teal)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg></div></div>
        <div class="label">Today's shift</div>
        <div class="val" style="font-size:18px;"><?= $todayShift ? SHIFT_TYPES[$todayShift['shift_type']]['label'] . ' · ' . short_time($todayShift['shift_start']) . '–' . short_time($todayShift['shift_end']) : 'Off today' ?></div>
        <div class="delta up"><?= date('M j', strtotime($today)) ?></div>
      </div>
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--amber-soft);color:#B47A16"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"/><circle cx="12" cy="12.5" r="3"/></svg></div></div>
        <div class="label">Estimated net pay (this cycle)</div>
        <div class="val"><?= fmt_money(net_pay($e)) ?></div>
        <div class="delta <?= $adj >= 0 ? 'up' : 'down' ?>"><?= $adj >= 0 ? '+' : '' ?><?= fmt_money($adj) ?> attendance adjustment</div>
      </div>
      <div class="card stat-card">
        <div class="top"><div class="icon" style="background:var(--coral-soft);color:var(--coral)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/></svg></div></div>
        <div class="label">Notifications</div>
        <div class="val"><?= count($myNotifs) ?></div>
        <div class="delta">recent updates</div>
      </div>
    </div>

    <div class="grid g-2" style="margin-top:18px;">
      <div class="card card-pad">
        <div class="card-head"><h3>Attendance record</h3><a class="link" href="attendance.php">Check in / out →</a></div>
        <div style="display:flex;align-items:center;gap:22px;">
          <div class="ring-wrap">
            <?= ring_svg($p, $ab, 110, 12, h($e['color'])) ?>
            <div class="center"><div class="n"><?= $p ?>/<?= $p + $ab ?></div><div class="l">shifts kept</div></div>
          </div>
          <div style="flex:1;">
            <div class="legend" style="flex-direction:column;align-items:flex-start;gap:8px;">
              <div><span class="sw" style="background:<?= h($e['color']) ?>"></span>Present — <?= $p ?> shifts <span class="mono" style="color:var(--teal)">+<?= fmt_money($p * 50) ?></span></div>
              <div><span class="sw" style="background:#EEF0F5"></span>Missed — <?= $ab ?> shifts <span class="mono" style="color:var(--coral)">-<?= fmt_money($ab * 50) ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-pad">
        <div class="card-head"><h3>Upcoming shifts</h3><a class="link" href="roster.php">Full roster →</a></div>
        <?php foreach ($upcoming as $u): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);">
            <div style="font-size:13.5px;font-weight:600;"><?= date('D, M j', strtotime($u['date'])) ?></div>
            <?php if ($u['shift']): ?>
              <span class="shift-chip <?= $u['shift']['shift_type'] ?>"><?= SHIFT_TYPES[$u['shift']['shift_type']]['label'] ?> · <?= short_time($u['shift']['shift_start']) ?>–<?= short_time($u['shift']['shift_end']) ?></span>
            <?php else: ?>
              <span class="pill gray">Off</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="section-title">Recent notifications</div>
    <div class="card card-pad">
      <?php if (empty($myNotifs)): ?>
        <div class="empty-state">No notifications yet.</div>
      <?php else: foreach ($myNotifs as $n): ?>
        <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
          <div class="pill <?= $n['kind'] === 'pos' ? 'green' : 'red' ?>"><?= $n['kind'] === 'pos' ? '+' : '−' ?>₹50</div>
          <div><div style="font-size:13.5px;font-weight:600;"><?= h($n['title']) ?></div><div style="font-size:12.5px;color:var(--muted);"><?= h($n['body']) ?></div></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php
}

include __DIR__ . '/includes/footer.php';
