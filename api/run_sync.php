<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_admin();
api_require_csrf();

$summary = run_attendance_payroll_sync();
echo json_encode(['ok' => true, 'credited' => $summary['credited'], 'debited' => $summary['debited']]);
