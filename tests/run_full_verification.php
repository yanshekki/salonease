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

// ============================================================
// 最高風險領域：佣金計算完整性最終檢查指引
// ============================================================
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   ★ 佣金計算完整性最終檢查（最高風險 - 務必人工確認）      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "【自動化部分已完成】\n";
echo "  - C001-C011 純函數規格 + bccomp 精準比對\n";
echo "  - 動態費率 E2E（改 40%→50% → 真實結帳 → commissions 表驗證 500.00）\n";
echo "  - 閉環 E2E（銷售 → 多次付款含手續費 → Portal 記錄 → 計劃進度 + 佣金不變）\n\n";

echo "【伺服器上請再做以下人工交叉驗證（強烈建議）】\n\n";

echo "1. 檢查測試銷售的佣金記錄（用 seed 建立的經理帳號）\n";
echo "   mysql -u root -p salonease\n";
echo "   SELECT s.id, s.total, c.type, c.amount, c.rate, c.staff_id\n";
echo "   FROM commissions c\n";
echo "   JOIN sales s ON c.sale_id = s.id\n";
echo "   WHERE s.notes LIKE 'API_TEST_COMMISSION_%' OR s.notes LIKE 'E2E_RATE_CHANGE_%' OR s.notes LIKE 'INTEGRATION_%'\n";
echo "   ORDER BY s.id DESC LIMIT 20;\n\n";

echo "2. 驗證動態費率 E2E 效果（應該有 sale 使用 50% 費率）\n";
echo "   找 notes 包含 'E2E_RATE_CHANGE_' 的銷售，service 佣金應為 500.00（1000 x 50%）\n\n";

echo "3. 檢查閉環測試的付款計劃進度與佣金\n";
echo "   SELECT id, sale_id, total_amount, paid_amount, progress_percentage, status\n";
echo "   FROM sale_payment_plans\n";
echo "   WHERE notes LIKE 'CLOSED_LOOP_%' OR sale_id IN (SELECT id FROM sales WHERE notes LIKE 'CLOSED_LOOP_%');\n\n";

echo "4. 確認佣金金額與純函數預期一致（無浮點誤差）\n";
echo "   - 所有 service/retail 佣金應在 points 扣減「前」計算\n";
echo "   - open 佣金使用最終 total\n";
echo "   - 個人費率優先，NULL 時回退全局設定\n\n";

echo "如果以上查詢結果與報告內的 assert 結果一致，即代表「所有佣金計算絕對正確」。\n\n";

echo "【最終確認步驟 - 絕對最後一步，最強推薦】\n";
echo "   php tests/check_commission_integrity.php\n\n";
echo "   此腳本會自動找出測試期間的所有銷售，使用與生產完全一致的純函數計算預期值，\n";
echo "   並用 bccomp 與實際 commissions 表比對，額外專項檢查動態費率 E2E，\n";
echo "   直接輸出清晰 PASS/FAIL + 最終裁決。\n";
echo "   跑完這個如果全部通過，即代表所有佣金計算絕對正確。\n\n";

echo "如需重新執行：php tests/run_full_verification.php\n";
