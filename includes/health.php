<?php
/**
 * SalonEase - 簡單系統健康檢查
 * A146 運維工具
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config.php';

function get_system_health(): array
{
    $checks = [];

    // 1. 資料庫連線
    try {
        $pdo = Database::getConnection();
        $checks['database'] = [
            'status' => 'ok',
            'label' => '資料庫連線',
            'detail' => 'PDO 連線正常'
        ];
    } catch (Exception $e) {
        $checks['database'] = [
            'status' => 'error',
            'label' => '資料庫連線',
            'detail' => '連線失敗'
        ];
    }

    // 2. 關鍵資料表檢查
    $tables = ['sales', 'products', 'staff', 'customers', 'audit_logs'];
    $missing = [];
    if (isset($pdo)) {
        foreach ($tables as $table) {
            try {
                $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            } catch (Exception $e) {
                $missing[] = $table;
            }
        }
    }
    $checks['tables'] = [
        'status' => empty($missing) ? 'ok' : 'warning',
        'label' => '關鍵資料表',
        'detail' => empty($missing) ? '所有核心表正常' : '缺少：' . implode(', ', $missing)
    ];

    // 3. 備份目錄可寫 + 最近備份
    $backupDir = __DIR__ . '/../uploads/backup';
    $hasBackup = false;
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/*.gz');
        if ($files) {
            $latest = max($files);
            $hasBackup = (time() - filemtime($latest)) < 86400 * 7; // 7天內
        }
    }
    $checks['backup'] = [
        'status' => $hasBackup ? 'ok' : 'warning',
        'label' => '資料庫備份',
        'detail' => $hasBackup ? '7天內有備份' : '建議盡快手動備份'
    ];

    // 4. uploads 目錄可寫
    $uploads = __DIR__ . '/../uploads';
    $checks['uploads'] = [
        'status' => is_writable($uploads) ? 'ok' : 'error',
        'label' => '上傳目錄',
        'detail' => is_writable($uploads) ? '可寫入' : '無法寫入'
    ];

    // 5. PHP 版本
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks['php'] = [
        'status' => $phpOk ? 'ok' : 'warning',
        'label' => 'PHP 版本',
        'detail' => PHP_VERSION . ($phpOk ? '（建議 ≥ 8.0）' : '（建議升級）')
    ];

    // 6. Migration 系統就緒（簡單檢查 upgrade.php 存在）
    $checks['migration'] = [
        'status' => file_exists(__DIR__ . '/../upgrade.php') ? 'ok' : 'warning',
        'label' => '升級系統',
        'detail' => 'upgrade.php 就緒'
    ];

    return [
        'checks' => $checks,
        'overall' => count(array_filter($checks, fn($c) => $c['status'] === 'error')) > 0 ? 'error' :
                     (count(array_filter($checks, fn($c) => $c['status'] === 'warning')) > 0 ? 'warning' : 'ok'),
        'checked_at' => date('Y-m-d H:i:s')
    ];
}