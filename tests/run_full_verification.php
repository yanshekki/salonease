<?php
/**
 * SalonEase API 測試系統
 * 
 * 一鍵完整驗證腳本（推薦用於伺服器上傳後）
 * 
 * 功能：
 * - 自動執行 seed（豐富測試資料）
 * - 執行所有測試 + 產生 HTML 報告
 * - 輸出清晰總結
 * 
 * 用法：
 *   php tests/run_full_verification.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   SalonEase 完整驗證（Seed + 所有測試 + 報告）               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$overallStart = microtime(true);

echo "環境檢查...\n";
echo "PHP 版本：" . PHP_VERSION . "\n";
echo "開始時間：" . date('Y-m-d H:i:s') . "\n\n";

// 1. 執行 Seed（確保有豐富測試資料）
$seedStart = microtime(true);
echo ">>> 步驟 1/3：準備測試資料（seed）\n";
$seedFile = __DIR__ . '/fixtures/seed_test_data.php';
if (file_exists($seedFile)) {
    require_once $seedFile;
} else {
    echo "    [警告] 找不到 seed 腳本\n";
}
$seedDuration = round(microtime(true) - $seedStart, 1);
echo "    Seed 完成（耗時 {$seedDuration} 秒）\n\n";

// 2. 執行所有測試 + HTML 報告
$testStart = microtime(true);
echo ">>> 步驟 2/3：執行所有測試並產生報告\n";
$cmd = 'php ' . __DIR__ . '/run_tests.php --report=html';
echo "    執行指令：{$cmd}\n\n";

passthru($cmd, $exitCode);

$testDuration = round(microtime(true) - $testStart, 1);
echo "\n    測試執行完成（耗時 {$testDuration} 秒）\n\n";

// 3. 總結
$overallDuration = round(microtime(true) - $overallStart, 1);
echo ">>> 步驟 3/3：驗證完成\n";
echo "    總耗時：{$overallDuration} 秒\n";
echo "    退出碼：{$exitCode}\n\n";

if ($exitCode === 0) {
    echo "★ 完整驗證通過！\n";
} else {
    echo "⚠ 驗證過程中有失敗，請查看報告詳情。\n";
}

echo "\n下一步建議：\n";
echo "  1. 打開 tests/reports/ 下的最新 HTML 報告\n";
echo "  2. 特別關注紅色失敗項目（尤其是佣金相關 E2E 測試）\n";
echo "  3. 確認所有自動驗證（含佣金費率變更 E2E）全部通過\n\n";

echo "如需重新執行：php tests/run_full_verification.php\n";
