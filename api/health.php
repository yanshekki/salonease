<?php
/**
 * SalonEase - 系統健康檢查 API
 * GET /api/health.php
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/health.php';

require_login();
$currentUser = get_logged_in_user();
if (!$currentUser || !in_array($currentUser['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => '權限不足']);
    exit;
}

$health = get_system_health();
echo json_encode($health);