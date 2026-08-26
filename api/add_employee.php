<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_admin();
api_require_csrf();

$name = trim($_POST['name'] ?? '');
$role = trim($_POST['role_title'] ?? '') ?: 'Team Member';
$dept = trim($_POST['department'] ?? '') ?: 'General';
$base = (float)($_POST['base_salary'] ?? 30000);

if ($name === '') api_fail('Name is required.');
if (mb_strlen($name) > 120) api_fail('Name is too long.');

$pdo = db();
$next = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 n FROM employees")->fetch()['n'];
$empCode = 'E' . str_pad((string)$next, 2, '0', STR_PAD_LEFT);
$colors = ['#0F9D8C', '#E8604C', '#EFA93A', '#4A4FA0', '#C2410C', '#0284C7', '#7C3AED', '#059669'];
$color = $colors[$next % count($colors)];

$pdo->prepare("INSERT INTO employees (emp_code, name, role_title, department, base_salary, color) VALUES (?,?,?,?,?,?)")
    ->execute([$empCode, $name, $role, $dept, $base, $color]);

echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
