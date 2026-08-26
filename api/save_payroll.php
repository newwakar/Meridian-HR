<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_admin();
api_require_csrf();

$employeeId = (int)($_POST['employee_id'] ?? 0);
$base = (float)($_POST['base_salary'] ?? -1);
$bonus = (float)($_POST['bonus'] ?? -1);
$deduction = (float)($_POST['other_deduction'] ?? -1);

if (!$employeeId || !get_employee($employeeId)) api_fail('Unknown employee.');
if ($base < 0 || $bonus < 0 || $deduction < 0) api_fail('Amounts cannot be negative.');

db()->prepare("UPDATE employees SET base_salary=?, bonus=?, other_deduction=? WHERE id=?")
    ->execute([$base, $bonus, $deduction, $employeeId]);

echo json_encode(['ok' => true]);
