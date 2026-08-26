<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_admin();

$pageKey = 'employees';
$pageTitle = 'Employees';
$pageSub = 'Manage the team';
$employees = get_employees();

include __DIR__ . '/includes/header.php';
?>

<div class="card card-pad" style="margin-bottom:16px;">
  <div class="card-head"><h3>Team directory</h3><button class="btn btn-primary" onclick="openAddEmployee()">+ Add employee</button></div>
  <div class="grid g-3">
    <?php foreach ($employees as $e): ?>
      <div class="card card-pad" style="box-shadow:none;border:1px solid var(--border);">
        <div style="display:flex;align-items:center;gap:12px;">
          <div class="avatar" style="width:44px;height:44px;font-size:15px;background:<?= h($e['color']) ?>"><?= h(initials($e['name'])) ?></div>
          <div>
            <div style="font-weight:700;font-size:14.5px;"><?= h($e['name']) ?></div>
            <div style="font-size:12.5px;color:var(--muted);"><?= h($e['role_title']) ?></div>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <span class="pill gray"><?= h($e['department']) ?></span>
          <span class="pill gray"><?= h($e['emp_code']) ?></span>
        </div>
        <div style="margin-top:12px;font-size:12.5px;color:var(--muted);">Net pay: <span class="mono" style="color:var(--text);font-weight:600;"><?= fmt_money(net_pay($e)) ?></span></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php
$pageScript = <<<JS
function openAddEmployee() {
  openOverlay(`
    <div class="modal">
      <h3>Add employee</h3>
      <div class="sub">Creates a new HR record. Add a login account separately once you're ready.</div>
      <div class="field"><label>Full name</label><input id="add-name" placeholder="e.g. Divya Rao"></div>
      <div class="field"><label>Role / title</label><input id="add-role" placeholder="e.g. Operations Associate"></div>
      <div class="field"><label>Department</label><input id="add-dept" placeholder="e.g. Operations"></div>
      <div class="field"><label>Base salary (₹/mo)</label><input id="add-base" type="number" value="30000"></div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeOverlay()">Cancel</button>
        <button class="btn btn-primary" onclick="saveNewEmployee()">Add employee</button>
      </div>
    </div>
  `);
}
async function saveNewEmployee() {
  const name = document.getElementById('add-name').value.trim();
  if (!name) { toast('Please enter a name', 'neg'); return; }
  const res = await apiPost('api/add_employee.php', {
    name,
    role_title: document.getElementById('add-role').value.trim(),
    department: document.getElementById('add-dept').value.trim(),
    base_salary: document.getElementById('add-base').value,
  });
  if (res.ok) { toast(`\${name} added to the team`, 'pos'); closeOverlay(); location.reload(); }
  else { toast(res.error || 'Could not add employee', 'neg'); }
}
JS;
include __DIR__ . '/includes/footer.php';
