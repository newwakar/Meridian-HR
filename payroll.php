<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();
$pdo = db();

$pageKey = 'payroll';
$pageTitle = 'Payroll';
$pageSub = $user['role'] === 'admin' ? 'Salaries, bonuses, deductions and attendance ledger' : 'Your salary breakdown';

include __DIR__ . '/includes/header.php';

if ($user['role'] === 'admin') {
    $employees = get_employees();
    $totalNet = array_sum(array_map('net_pay', $employees));
    $totalBase = array_sum(array_column($employees, 'base_salary'));
    $totalBonus = array_sum(array_column($employees, 'bonus'));
    $ledger = $pdo->query("
        SELECT l.*, e.name FROM payroll_ledger l JOIN employees e ON e.id = l.employee_id
        ORDER BY l.entry_date DESC, l.id DESC LIMIT 20
    ")->fetchAll();
    ?>
    <div class="grid g-4" style="margin-bottom:18px;">
      <div class="card stat-card"><div class="label">Employees</div><div class="val"><?= count($employees) ?></div></div>
      <div class="card stat-card"><div class="label">Total base salary</div><div class="val"><?= fmt_money($totalBase) ?></div></div>
      <div class="card stat-card"><div class="label">Total bonuses</div><div class="val" style="color:var(--teal)"><?= fmt_money($totalBonus) ?></div></div>
      <div class="card stat-card"><div class="label">Net payroll</div><div class="val"><?= fmt_money($totalNet) ?></div></div>
    </div>

    <div class="card card-pad">
      <div class="card-head"><h3>Salary sheet</h3><span style="font-size:12px;color:var(--muted);">Attendance adj. = (shifts attended × ₹50) − (shifts missed × ₹50)</span></div>
      <div style="overflow-x:auto;">
      <table>
        <thead><tr><th>Employee</th><th>Base</th><th>Bonus</th><th>Deduction</th><th>Attendance adj.</th><th>Net pay</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($employees as $e): $adj = attendance_adjustment($e['id']); ?>
          <tr>
            <td><div class="emp-cell"><div class="avatar" style="width:28px;height:28px;font-size:11px;background:<?= h($e['color']) ?>"><?= h(initials($e['name'])) ?></div><div><div class="name"><?= h($e['name']) ?></div><div class="role"><?= h($e['department']) ?></div></div></div></td>
            <td class="mono"><?= fmt_money($e['base_salary']) ?></td>
            <td class="mono" style="color:var(--teal)"><?= fmt_money($e['bonus']) ?></td>
            <td class="mono" style="color:var(--coral)">-<?= fmt_money($e['other_deduction']) ?></td>
            <td class="mono" style="color:<?= $adj >= 0 ? 'var(--teal)' : 'var(--coral)' ?>"><?= $adj >= 0 ? '+' : '' ?><?= fmt_money($adj) ?></td>
            <td class="mono" style="font-weight:700;"><?= fmt_money(net_pay($e)) ?></td>
            <td><button class="btn btn-ghost" style="padding:6px 12px;font-size:12px;" onclick='openPayrollEdit(<?= (int)$e['id'] ?>, <?= json_encode($e['name']) ?>, <?= (int)$e['base_salary'] ?>, <?= (int)$e['bonus'] ?>, <?= (int)$e['other_deduction'] ?>)'>Edit</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>

    <div class="section-title">Attendance ledger (latest 20)</div>
    <div class="card card-pad">
      <table>
        <thead><tr><th>Date</th><th>Employee</th><th>Reason</th><th>Amount</th></tr></thead>
        <tbody>
        <?php if (empty($ledger)): ?>
          <tr><td colspan="4"><div class="empty-state">No ledger entries yet. Run a payroll sync from the Attendance page.</div></td></tr>
        <?php else: foreach ($ledger as $l): ?>
          <tr>
            <td class="mono" style="font-size:12px;"><?= $l['entry_date'] ?></td>
            <td><?= h($l['name']) ?></td>
            <td style="color:var(--muted);font-size:12.5px;"><?= h($l['reason']) ?></td>
            <td class="mono" style="color:<?= $l['amount'] >= 0 ? 'var(--teal)' : 'var(--coral)' ?>;font-weight:600;"><?= $l['amount'] >= 0 ? '+' : '' ?><?= fmt_money($l['amount']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    $pageScript = <<<JS
function openPayrollEdit(id, name, base, bonus, deduction) {
  openOverlay(`
    <div class="modal">
      <h3>Edit pay components</h3>
      <div class="sub">\${name}</div>
      <div class="field"><label>Base salary (₹/mo)</label><input type="number" id="edit-base" value="\${base}"></div>
      <div class="field"><label>Bonus (₹)</label><input type="number" id="edit-bonus" value="\${bonus}"></div>
      <div class="field"><label>Other deduction (₹)</label><input type="number" id="edit-deduction" value="\${deduction}"></div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeOverlay()">Cancel</button>
        <button class="btn btn-primary" onclick="savePayrollEdit(\${id})">Save</button>
      </div>
    </div>
  `);
}
async function savePayrollEdit(id) {
  const base = document.getElementById('edit-base').value;
  const bonus = document.getElementById('edit-bonus').value;
  const deduction = document.getElementById('edit-deduction').value;
  const res = await apiPost('api/save_payroll.php', { employee_id: id, base_salary: base, bonus, other_deduction: deduction });
  if (res.ok) { toast('Pay components updated', 'pos'); closeOverlay(); location.reload(); }
  else { toast(res.error || 'Could not save', 'neg'); }
}
JS;
} else {
    $e = get_employee($user['employee_id']);
    $adj = attendance_adjustment($e['id']);
    $ledgerStmt = $pdo->prepare("SELECT * FROM payroll_ledger WHERE employee_id=? ORDER BY entry_date DESC");
    $ledgerStmt->execute([$e['id']]);
    $myLedger = $ledgerStmt->fetchAll();
    $attended = count(array_filter($myLedger, fn($l) => $l['amount'] > 0));
    $missed = count(array_filter($myLedger, fn($l) => $l['amount'] < 0));
    ?>
    <div class="grid g-2" style="margin-bottom:18px;">
      <div class="card card-pad">
        <div class="card-head"><h3>Salary breakdown</h3></div>
        <table>
          <tbody>
            <tr><td>Base salary</td><td class="mono" style="text-align:right;"><?= fmt_money($e['base_salary']) ?></td></tr>
            <tr><td>Bonus</td><td class="mono" style="text-align:right;color:var(--teal);">+<?= fmt_money($e['bonus']) ?></td></tr>
            <tr><td>Deductions</td><td class="mono" style="text-align:right;color:var(--coral);">-<?= fmt_money($e['other_deduction']) ?></td></tr>
            <tr><td>Attendance adjustment</td><td class="mono" style="text-align:right;color:<?= $adj >= 0 ? 'var(--teal)' : 'var(--coral)' ?>;"><?= $adj >= 0 ? '+' : '' ?><?= fmt_money($adj) ?></td></tr>
            <tr><td style="font-weight:700;">Net pay</td><td class="mono" style="text-align:right;font-weight:700;font-size:16px;"><?= fmt_money(net_pay($e)) ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="card card-pad">
        <div class="card-head"><h3>How attendance affects pay</h3></div>
        <div style="font-size:13px;color:var(--muted);line-height:1.7;">
          Every scheduled shift you attend and check in for adds <b style="color:var(--teal)">+₹50</b> to your pay.<br>
          Every scheduled shift you miss deducts <b style="color:var(--coral)">−₹50</b>.<br>
          This cycle: <b><?= $attended ?></b> shifts attended, <b><?= $missed ?></b> missed.
        </div>
      </div>
    </div>
    <div class="section-title">My attendance ledger</div>
    <div class="card card-pad">
      <table>
        <thead><tr><th>Date</th><th>Reason</th><th>Amount</th></tr></thead>
        <tbody>
        <?php if (empty($myLedger)): ?>
          <tr><td colspan="3"><div class="empty-state">No ledger entries yet.</div></td></tr>
        <?php else: foreach ($myLedger as $l): ?>
          <tr>
            <td class="mono" style="font-size:12px;"><?= $l['entry_date'] ?></td>
            <td style="color:var(--muted);font-size:12.5px;"><?= h($l['reason']) ?></td>
            <td class="mono" style="color:<?= $l['amount'] >= 0 ? 'var(--teal)' : 'var(--coral)' ?>;font-weight:600;"><?= $l['amount'] >= 0 ? '+' : '' ?><?= fmt_money($l['amount']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}

include __DIR__ . '/includes/footer.php';
