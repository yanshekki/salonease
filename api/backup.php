<?php
/**
 * SalonEase - 手動資料庫備份 API
 * GET /api/backup.php?action=manual
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/backup.php';

require_role(['admin', 'manager']);

$action = $_GET['action'] ?? '';

if ($action === 'manual') {
    try {
        $file = create_database_backup();
        $filename = basename($file);

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file));

        readfile($file);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '備份失敗：' . $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => '未知操作']);