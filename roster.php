<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();

$pageKey = 'roster';
$pageTitle = 'Roster Planning';
$pageSub = 'Schedule and manage shifts';

$weekStart = isset($_GET['week']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['week'])
    ? start_of_week($_GET['week'])
    : start_of_week(date('Y-m-d'));

$days = [];
for ($i = 0; $i < 7; $i++) $days[] = date('Y-m-d', strtotime($weekStart . " +$i days"));
$rosterMap = get_roster_range($days[0], $days[6]);

$employees = $user['role'] === 'admin' ? get_employees() : array_filter(get_employees(), fn($e) => $e['id'] == $user['employee_id']);

$prevWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' +7 days'));

include __DIR__ . '/includes/header.php';
?>

<div class="card card-pad" style="margin-bottom:16px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <a class="btn btn-ghost" href="roster.php?week=<?= $prevWeek ?>">← Prev</a>
      <div style="font-weight:700;font-family:'Space Grotesk';font-size:15px;">
        <?= date('M j', strtotime($days[0])) ?> – <?= date('M j, Y', strtotime($days[6])) ?>
      </div>
      <a class="btn btn-ghost" href="roster.php?week=<?= $nextWeek ?>">Next →</a>
    </div>
    <div class="legend">
      <span><span class="sw" style="background:var(--teal)"></span>Morning</span>
      <span><span class="sw" style="background:var(--amber)"></span>Evening</span>
      <span><span class="sw" style="background:#4A4FA0"></span>Night</span>
      <?php if ($user['role'] === 'admin'): ?><span style="color:var(--muted);font-size:12px;">· Click a cell to assign / edit a shift</span><?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="overflow-x:auto;">
  <div class="roster-grid" style="min-width:<?= 170 + 7 * 110 ?>px;">
    <div class="rhead">Employee</div>
    <?php foreach ($days as $d): ?>
      <div class="rhead"><?= date('D', strtotime($d)) ?><div class="d"><?= date('M j', strtotime($d)) ?></div></div>
    <?php endforeach; ?>

    <?php foreach ($employees as $e): ?>
      <div class="rname"><div class="avatar" style="width:26px;height:26px;font-size:10px;background:<?= h($e['color']) ?>"><?= h(initials($e['name'])) ?></div><?= h(explode(' ', $e['name'])[0]) ?></div>
      <?php foreach ($days as $d):
          $sh = $rosterMap[$e['id'] . '|' . $d] ?? null;
          $clickAttr = $user['role'] === 'admin' ? "onclick=\"openShiftModal('{$d}', {$e['id']}, '" . h($e['name']) . "', " . ($sh ? "'{$sh['shift_type']}'" : 'null') . ")\"" : '';
      ?>
        <?php if (!$sh): ?>
          <div class="rcell off" <?= $clickAttr ?>><?= $user['role'] === 'admin' ? '+ Add' : 'Off' ?></div>
        <?php else: ?>
          <div class="rcell" <?= $clickAttr ?>><div class="shift-chip <?= $sh['shift_type'] ?>"><?= SHIFT_TYPES[$sh['shift_type']]['label'] ?><div class="t"><?= short_time($sh['shift_start']) ?>–<?= short_time($sh['shift_end']) ?></div></div></div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>

<?php
$pageScript = <<<JS
function openShiftModal(dateStr, empId, empName, currentType) {
  const options = {
    morning: 'Morning (09:00–17:00)',
    evening: 'Evening (14:00–22:00)',
    night: 'Night (22:00–06:00)',
  };
  let optionsHtml = '<option value="">Off / No shift</option>';
  for (const [k, label] of Object.entries(options)) {
    optionsHtml += `<option value="\${k}" \${currentType === k ? 'selected' : ''}>\${label}</option>`;
  }
  openOverlay(`
    <div class="modal">
      <h3>Assign shift</h3>
      <div class="sub">\${empName} · \${new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US',{weekday:'long', month:'short', day:'numeric'})}</div>
      <div class="field">
        <label>Shift type</label>
        <select id="shift-type-select">\${optionsHtml}</select>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeOverlay()">Cancel</button>
        <button class="btn btn-primary" onclick="saveShift('\${dateStr}', \${empId})">Save shift</button>
      </div>
    </div>
  `);
}

async function saveShift(dateStr, empId) {
  const shiftType = document.getElementById('shift-type-select').value;
  const res = await apiPost('api/save_shift.php', { date: dateStr, employee_id: empId, shift_type: shiftType });
  if (res.ok) {
    toast('Roster updated', 'pos');
    closeOverlay();
    location.reload();
  } else {
    toast(res.error || 'Could not save shift', 'neg');
  }
}
JS;
include __DIR__ . '/includes/footer.php';
