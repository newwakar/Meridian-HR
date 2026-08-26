<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_admin();
api_require_csrf();

$date = $_POST['date'] ?? '';
$employeeId = (int)($_POST['employee_id'] ?? 0);
$shiftType = $_POST['shift_type'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) api_fail('Invalid date.');
if (!$employeeId || !get_employee($employeeId)) api_fail('Unknown employee.');
if ($shiftType !== '' && !isset(SHIFT_TYPES[$shiftType])) api_fail('Invalid shift type.');

if ($shiftType === '') {
    delete_shift($employeeId, $date);
} else {
    upsert_shift($employeeId, $date, $shiftType);
}

echo json_encode(['ok' => true]);
