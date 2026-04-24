<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /CampusConnect/auth/login.php');
        exit;
    }
}
function require_role(string $role): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        die('Forbidden');
    }
}
function require_api_role(array $roles): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}
