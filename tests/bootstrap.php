<?php
/**
 * SalonEase API 測試系統 - Bootstrap
 *
 * 專業級測試環境初始化
 *
 * 用法：
 *   php tests/run_tests.php --bootstrap
 *   或直接 require 進 run_tests.php
 *
 * 功能：
 * - 環境檢查（PHP 版本、必要 extension）
 * - 可選自動執行 seed（--seed 或環境變數）
 * - 設定錯誤處理與時區
 * - 提供全域測試常數
 */

declare(strict_types=1);

// ====================== 環境設定 ======================
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Hong_Kong');

// ====================== 測試常數 ======================
define('SALONEASE_TEST_MODE', true);
define('SALONEASE_TEST_START_TIME', microtime(true));

// ====================== 環境檢查 ======================
$requiredPhpVersion = '8.0.0';
if (version_compare(PHP_VERSION, $requiredPhpVersion, '<')) {
    fwrite(STDERR, "[Bootstrap] 錯誤：需要 PHP {$requiredPhpVersion} 或以上，目前版本 " . PHP_VERSION . "\n");
    exit(1);
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
$missing = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (!empty($missing)) {
    fwrite(STDERR, "[Bootstrap] 錯誤：缺少必要 PHP 擴充功能：" . implode(', ', $missing) . "\n");
    exit(1);
}

// 推薦 bcmath（用於精準金錢比較）
if (!extension_loaded('bcmath')) {
    fwrite(STDERR, "[Bootstrap] 警告：未安裝 bcmath 擴充，建議安裝以獲得最佳金錢計算精度。\n");
}

// ====================== 自動 Seed 支援 ======================
$shouldSeed = false;

// 透過參數 --seed
if (in_array('--seed', $GLOBALS['argv'] ?? [], true)) {
    $shouldSeed = true;
}

// 透過環境變數
if (getenv('SALONEASE_AUTO_SEED') === '1') {
    $shouldSeed = true;
}

if ($shouldSeed) {
    echo "[Bootstrap] 偵測到自動 seed 需求，正在執行種子資料...\n";
    $seedFile = __DIR__ . '/fixtures/seed_test_data.php';
    if (file_exists($seedFile)) {
        require_once $seedFile;
        echo "[Bootstrap] Seed 執行完成。\n";
    } else {
        fwrite(STDERR, "[Bootstrap] 警告：找不到 seed 腳本 {$seedFile}\n");
    }
}

// ====================== 測試就緒訊息 ======================
if (php_sapi_name() === 'cli') {
    // 只在直接執行 bootstrap 時顯示
    if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
        echo "SalonEase API 測試系統 Bootstrap 已就緒\n";
        echo "PHP " . PHP_VERSION . " | 時區：Asia/Hong_Kong\n";
        echo "建議執行：php tests/run_tests.php --report=html\n\n";
    }
}