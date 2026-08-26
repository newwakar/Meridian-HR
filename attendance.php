<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();

$pageKey = 'attendance';
$pageTitle = 'Attendance';
$pageSub = 'GPS + face check-in / check-out';
$today = date('Y-m-d');
$pdo = db();

include __DIR__ . '/includes/header.php';

if ($user['role'] === 'admin') {
    $employees = get_employees();
    $rows = [];
    foreach ($employees as $e) {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id=? AND attendance_date=?");
        $stmt->execute([$e['id'], $today]);
        $rows[] = ['e' => $e, 'a' => $stmt->fetch(), 'sh' => get_shift($e['id'], $today)];
    }
    $log = $pdo->query("
        SELECT a.*, e.name FROM attendance a JOIN employees e ON e.id = a.employee_id
        ORDER BY a.attendance_date DESC LIMIT 60
    ")->fetchAll();
    ?>
    <div class="card card-pad" style="margin-bottom:16px;">
      <div class="card-head">
        <h3>Today · <?= date('M j', strtotime($today)) ?></h3>
        <button class="btn btn-primary" onclick="runSync()">Run attendance payroll sync</button>
      </div>
      <div style="font-size:12.5px;color:var(--muted);margin-bottom:10px;">Applies +₹50 for each attended shift and −₹50 for each missed shift, based on roster vs. check-in records, once a shift's end time has passed.</div>
      <table>
        <thead><tr><th>Employee</th><th>Scheduled</th><th>Check-in</th><th>Check-out</th><th>GPS</th><th>Face</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $e = $r['e']; $a = $r['a']; $sh = $r['sh']; ?>
          <tr>
            <td><div class="emp-cell"><div class="avatar" style="width:28px;height:28px;font-size:11px;background:<?= h($e['color']) ?>"><?= h(initials($e['name'])) ?></div><div><div class="name"><?= h($e['name']) ?></div><div class="role"><?= h($e['role_title']) ?></div></div></div></td>
            <td><?= $sh ? SHIFT_TYPES[$sh['shift_type']]['label'] . ' · ' . short_time($sh['shift_start']) . '–' . short_time($sh['shift_end']) : '<span class="pill gray">Off</span>' ?></td>
            <td class="mono"><?= $a && $a['check_in'] ? short_time($a['check_in']) : '—' ?></td>
            <td class="mono"><?= $a && $a['check_out'] ? short_time($a['check_out']) : '—' ?></td>
            <td><?= $a && $a['gps_verified'] ? '<span class="pill green">Verified</span>' : ($sh ? '<span class="pill amber">Pending</span>' : '—') ?></td>
            <td><?= $a && $a['face_verified'] ? '<span class="pill green">Matched</span>' : ($sh ? '<span class="pill amber">Pending</span>' : '—') ?></td>
            <td><?php if ($a): ?><span class="pill <?= $a['status'] === 'present' ? 'green' : 'red' ?>"><?= ucfirst($a['status']) ?></span><?php elseif ($sh): ?><span class="pill amber">Awaiting</span><?php else: ?><span class="pill gray">—</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="section-title">Full attendance log</div>
    <div class="card card-pad" style="max-height:420px;overflow:auto;">
      <table>
        <thead><tr><th>Date</th><th>Employee</th><th>Status</th><th>In</th><th>Out</th></tr></thead>
        <tbody>
        <?php foreach ($log as $l): ?>
          <tr>
            <td class="mono" style="font-size:12px;"><?= $l['attendance_date'] ?></td>
            <td><?= h($l['name']) ?></td>
            <td><span class="pill <?= $l['status'] === 'present' ? 'green' : 'red' ?>"><?= ucfirst($l['status']) ?></span></td>
            <td class="mono"><?= short_time($l['check_in']) ?></td>
            <td class="mono"><?= short_time($l['check_out']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    $pageScript = <<<JS
async function runSync() {
  const res = await apiPost('api/run_sync.php', {});
  if (res.ok) {
    toast(`Sync complete — \${res.credited} credited, \${res.debited} debited`, 'pos');
    location.reload();
  } else {
    toast(res.error || 'Sync failed', 'neg');
  }
}
JS;
} else {
    $e = get_employee($user['employee_id']);
    $todayShift = get_shift($e['id'], $today);
    $todayAtt = get_attendance($e['id'], $today);
    $history = $pdo->prepare("SELECT * FROM attendance WHERE employee_id=? ORDER BY attendance_date DESC LIMIT 10");
    $history->execute([$e['id']]);
    $history = $history->fetchAll();
    ?>
    <div class="card card-pad checkin-card">
      <div class="face-box" id="face-box">
        <div class="placeholder" id="face-placeholder">Camera preview appears here for face check-in</div>
        <div class="face-badge" id="face-badge">Face verified ✓</div>
      </div>
      <div style="flex:1;min-width:220px;">
        <h3 style="font-size:16px;"><?= $todayShift ? "Today's shift: " . SHIFT_TYPES[$todayShift['shift_type']]['label'] . ' (' . short_time($todayShift['shift_start']) . '–' . short_time($todayShift['shift_end']) . ')' : 'No shift scheduled today' ?></h3>
        <div style="font-size:12.5px;color:var(--muted);margin-top:4px;">Verification uses GPS geofencing (office radius <?= GEOFENCE_RADIUS_METERS ?>m) and a simulated face match.</div>
        <div class="geo-row" id="geo-row"><span class="g-pill">📍 Location: not checked</span></div>
        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
          <button class="btn btn-teal" id="checkin-btn" onclick="startCheckIn()" <?= $todayAtt && $todayAtt['check_in'] ? 'disabled' : '' ?>><?= $todayAtt && $todayAtt['check_in'] ? 'Checked in ✓' : 'Check in' ?></button>
          <button class="btn btn-coral" id="checkout-btn" onclick="startCheckOut()" <?= !$todayAtt || !$todayAtt['check_in'] || $todayAtt['check_out'] ? 'disabled' : '' ?>><?= $todayAtt && $todayAtt['check_out'] ? 'Checked out ✓' : 'Check out' ?></button>
        </div>
        <?php if (!$todayShift): ?><div style="margin-top:10px;font-size:12px;color:var(--coral);">You have no shift scheduled today — check-in is for reference only.</div><?php endif; ?>
      </div>
    </div>

    <div class="section-title">Recent attendance</div>
    <div class="card card-pad">
      <div class="timeline">
        <?php if (empty($history)): ?>
          <div class="empty-state">No attendance history yet.</div>
        <?php else: foreach ($history as $hst): ?>
          <div class="tl-item <?= $hst['status'] ?>">
            <div class="tt"><?= date('l, M j', strtotime($hst['attendance_date'])) ?> · <span class="pill <?= $hst['status'] === 'present' ? 'green' : 'red' ?>"><?= $hst['status'] === 'present' ? 'Present' : 'Absent' ?></span></div>
            <div class="td2"><?= $hst['status'] === 'present' ? 'In ' . short_time($hst['check_in']) . ' · Out ' . short_time($hst['check_out']) . ' · GPS ' . ($hst['gps_verified'] ? 'verified' : 'flagged') . ' · Face ' . ($hst['face_verified'] ? 'verified' : '—') : 'No check-in recorded for the scheduled shift' ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php
    $pageScript = <<<JS
function startCheckIn() {
  const geoRow = document.getElementById('geo-row');
  geoRow.innerHTML = '<span class="g-pill">📍 Locating…</span>';

  const finish = (lat, lng, gpsOk, coordsText) => {
    geoRow.innerHTML = `<span class="g-pill">📍 \${coordsText}</span> <span class="g-pill">\${gpsOk ? '✓ Within office geofence' : '⚠ Outside geofence — flagged'}</span>`;
    startFaceCapture(async (faceOk) => {
      const res = await apiPost('api/checkin.php', { lat: lat ?? '', lng: lng ?? '', gps_ok: gpsOk ? 1 : 0, face_ok: faceOk ? 1 : 0 });
      if (res.ok) {
        toast(`Checked in at \${res.time}`, 'pos');
        document.getElementById('checkin-btn').disabled = true;
        document.getElementById('checkin-btn').textContent = 'Checked in ✓';
        document.getElementById('checkout-btn').disabled = false;
      } else {
        toast(res.error || 'Check-in failed', 'neg');
      }
    });
  };

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => finish(pos.coords.latitude, pos.coords.longitude, true, `\${pos.coords.latitude.toFixed(3)}, \${pos.coords.longitude.toFixed(3)}`),
      () => finish(null, null, false, 'Location permission denied'),
      { timeout: 6000 }
    );
  } else {
    finish(null, null, false, 'Geolocation unavailable');
  }
}

async function startCheckOut() {
  const res = await apiPost('api/checkout.php', {});
  if (res.ok) {
    toast(`Checked out at \${res.time}`, 'pos');
    document.getElementById('checkout-btn').disabled = true;
    document.getElementById('checkout-btn').textContent = 'Checked out ✓';
  } else {
    toast(res.error || 'Check-out failed', 'neg');
  }
}
JS;
}

include __DIR__ . '/includes/footer.php';
