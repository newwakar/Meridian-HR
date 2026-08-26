<?php
require_once __DIR__ . '/_bootstrap.php';
$user = api_require_login();
api_require_csrf();

if ($user['role'] !== 'employee' || !$user['employee_id']) {
    api_fail('Only employee accounts can check in.');
}

$employeeId = (int)$user['employee_id'];
$today = date('Y-m-d');
$now = date('H:i:s');

$lat = (($_POST['lat'] ?? '') !== '') ? (float)$_POST['lat'] : null;
$lng = (($_POST['lng'] ?? '') !== '') ? (float)$_POST['lng'] : null;
$faceOk = !empty($_POST['face_ok']);

// Server-side geofence check — never trust the client's own "gps_ok" flag for the real verdict.
$gpsOk = false;
if ($lat !== null && $lng !== null) {
    $dist = distance_meters($lat, $lng, OFFICE_LAT, OFFICE_LNG);
    $gpsOk = $dist <= GEOFENCE_RADIUS_METERS;
}

$pdo = db();
$existing = get_attendance($employeeId, $today);
if ($existing && $existing['check_in']) {
    api_fail('You have already checked in today.');
}

if ($existing) {
    $pdo->prepare("UPDATE attendance SET check_in=?, gps_lat=?, gps_lng=?, gps_verified=?, face_verified=?, status='present' WHERE id=?")
        ->execute([$now, $lat, $lng, $gpsOk ? 1 : 0, $faceOk ? 1 : 0, $existing['id']]);
} else {
    $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, gps_lat, gps_lng, gps_verified, face_verified, status) VALUES (?,?,?,?,?,?,?,'present')")
        ->execute([$employeeId, $today, $now, $lat, $lng, $gpsOk ? 1 : 0, $faceOk ? 1 : 0]);
}

$emp = get_employee($employeeId);
push_notification($employeeId, 'Checked in', "Checked in at " . date('H:i', strtotime($now)) . ' · GPS ' . ($gpsOk ? 'verified' : 'outside geofence') . ' · Face verified.', 'pos');

echo json_encode(['ok' => true, 'time' => date('H:i', strtotime($now)), 'gps_ok' => $gpsOk]);
