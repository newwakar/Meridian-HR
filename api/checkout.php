<?php
require_once __DIR__ . '/_bootstrap.php';
$user = api_require_login();
api_require_csrf();

if ($user['role'] !== 'employee' || !$user['employee_id']) {
    api_fail('Only employee accounts can check out.');
}

$employeeId = (int)$user['employee_id'];
$today = date('Y-m-d');
$now = date('H:i:s');

$existing = get_attendance($employeeId, $today);
if (!$existing || !$existing['check_in']) api_fail('You need to check in before checking out.');
if ($existing['check_out']) api_fail('You have already checked out today.');

db()->prepare("UPDATE attendance SET check_out=? WHERE id=?")->execute([$now, $existing['id']]);
push_notification($employeeId, 'Checked out', 'Checked out at ' . date('H:i', strtotime($now)) . '.', 'pos');

echo json_encode(['ok' => true, 'time' => date('H:i', strtotime($now))]);
