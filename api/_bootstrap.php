<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

function api_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function api_require_login(): array {
    $user = current_user();
    if (!$user) api_fail('Not signed in.', 401);
    return $user;
}

function api_require_admin(): array {
    $user = api_require_login();
    if ($user['role'] !== 'admin') api_fail('Admins only.', 403);
    return $user;
}

function api_require_csrf(): void {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        api_fail('Your session expired — please refresh the page and try again.', 419);
    }
}
