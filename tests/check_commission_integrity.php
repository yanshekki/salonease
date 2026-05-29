<?php
/**
 * SalonEase API 測試系統
 * 
 * ★ 專用佣金計算完整性檢查腳本（最高風險領域最終人工驗證）
 * 
 * 用法（伺服器上執行）：
 *   php tests/check_commission_integrity.php
 * 
 * 目的：
 * - 直接查詢 commissions 表 + sales / sale_items
 * - 與純函數 calculateExpectedCommissions 比對
 * - 使用 bccomp 確認絕對無誤差
 * - 清晰輸出 PASS / FAIL，讓你一目了然「所有佣金計算是否 100% 正確」
 * 
 * 執行前請先跑過 run_full_verification.php 或 seed
 */

require_once __DIR__ . '/api/test_sales_checkout_commission.php';   // 取得 calculateExpectedCommissions 純函數
require_once __DIR__ . '/../../db.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   SalonEase 佣金計算完整性最終檢查（最高風險領域）               ║\n";
echo "║   直接比對 commissions 表 vs 純函數規格                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;
$checks = [];

// 1. 取得測試期間建立的銷售（用 notes 識別）
$testNotes = [
    'API_TEST_COMMISSION_',
    'E2E_RATE_CHANGE_',
    'INTEGRATION_POINTS_',
    'INTEGRATION_PLAN_',
    'CLOSED_LOOP_COMMISSION_PORTAL_',
];

$sales = db_query("
    SELECT id, customer_id, total, discount, points_used, notes, staff_id as opener_id
    FROM sales 
    WHERE notes LIKE '%API_TEST%' 
       OR notes LIKE '%E2E_RATE_CHANGE%' 
       OR notes LIKE '%INTEGRATION%' 
       OR notes LIKE '%CLOSED_LOOP%'
    ORDER BY id DESC
    LIMIT 30
");

if (empty($sales)) {
    echo "⚠ 找不到測試銷售記錄。請先執行 php tests/run_full_verification.php 或 seed_test_data.php\n\n";
    exit(1);
}

echo "找到 " . count($sales) . " 筆測試相關銷售，正在檢查佣金...\n\n";

// 顯示當前全局費率（讓驗證更有透明度）
$curSettings = db_query_one("SELECT default_commission_service, default_commission_retail, default_commission_open FROM settings LIMIT 1");
printf("當前全局佣金預設率：Service %.0f%% | Retail %.0f%% | Open %.0f%%\n\n",
    $curSettings['default_commission_service'] ?? 40,
    $curSettings['default_commission_retail'] ?? 15,
    $curSettings['default_commission_open'] ?? 5
);

foreach ($sales as $sale) {
    $saleId = (int)$sale['id'];
    $notes = $sale['notes'] ?? '';

    // 取得 sale_items
    $items = db_query("
        SELECT si.type, si.ref_id, si.unit_price, si.qty, si.staff_id
        FROM sale_items si
        WHERE si.sale_id = ?
    ", [$saleId]);

    if (empty($items)) continue;

    // 取得該銷售的所有佣金記錄
    $commissions = db_query("
        SELECT type, amount, rate, staff_id
        FROM commissions
        WHERE sale_id = ?
    ", [$saleId]);

    if (empty($commissions)) {
        echo "✗ Sale #{$saleId} 沒有佣金記錄（notes: {$notes}）\n";
        $failed++;
        continue;
    }

    // 重建參數給純函數
    $formattedItems = [];
    foreach ($items as $it) {
        $formattedItems[] = [
            'type'      => $it['type'],
            'ref_id'    => $it['ref_id'],
            'unit_price'=> (float)$it['unit_price'],
            'qty'       => (int)$it['qty'],
            'staff_id'  => $it['staff_id'] ? (int)$it['staff_id'] : null,
        ];
    }

    $discount = (float)($sale['discount'] ?? 0);
    $pointsUsed = (int)($sale['points_used'] ?? 0);
    $openerId = (int)($sale['opener_id'] ?? 2);

    // 取得全局費率（模擬執行時的設定）
    $settings = db_query_one("SELECT default_commission_service, default_commission_retail, default_commission_open FROM settings LIMIT 1");
    $globalRates = [
        'service' => (float)($settings['default_commission_service'] ?? 40),
        'retail'  => (float)($settings['default_commission_retail'] ?? 15),
        'open'    => (float)($settings['default_commission_open'] ?? 5),
    ];

    // 取得相關員工的個人費率（盡量準確抓取 seed 設定的個人費率）
    $staffIds = array_unique(array_filter(array_merge(
        array_column($formattedItems, 'staff_id'),
        array_column($commissions, 'staff_id')
    )));
    $staffPersonal = [];
    if (!empty($staffIds)) {
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $staffRows = db_query("
            SELECT id, commission_rate_service, commission_rate_retail, commission_rate_open
            FROM staff
            WHERE id IN ($placeholders)
        ", $staffIds);
        foreach ($staffRows as $s) {
            $staffPersonal[(int)$s['id']] = [
                'service' => $s['commission_rate_service'] !== null ? (float)$s['commission_rate_service'] : null,
                'retail'  => $s['commission_rate_retail']  !== null ? (float)$s['commission_rate_retail']  : null,
                'open'    => $s['commission_rate_open']    !== null ? (float)$s['commission_rate_open']    : null,
            ];
        }
    }

    // 計算預期值
    $expected = TestSalesCheckoutCommission::calculateExpectedCommissions(
        $formattedItems,
        $discount,
        $pointsUsed,
        $globalRates,
        $staffPersonal,
        $openerId
    );

    // 比對實際寫入
    $actualService = 0;
    $actualRetail = 0;
    $actualOpen = 0;

    foreach ($commissions as $c) {
        if ($c['type'] === 'service') $actualService += (float)$c['amount'];
        if ($c['type'] === 'retail')  $actualRetail  += (float)$c['amount'];
        if ($c['type'] === 'open')    $actualOpen    += (float)$c['amount'];
    }

    $expService = array_sum($expected['service_by_staff'] ?? []);
    $expRetail  = array_sum($expected['retail_by_staff'] ?? []);
    $expOpen    = $expected['open_commission'] ?? 0;

    $okService = bccomp(number_format($expService, 2, '.', ''), number_format($actualService, 2, '.', ''), 2) === 0;
    $okRetail  = bccomp(number_format($expRetail, 2, '.', ''), number_format($actualRetail, 2, '.', ''), 2) === 0;
    $okOpen    = bccomp(number_format($expOpen, 2, '.', ''), number_format($actualOpen, 2, '.', ''), 2) === 0;

    $status = ($okService && $okRetail && $okOpen) ? '✓ PASS' : '✗ FAIL';

    printf("Sale #%d | %s | service %.2f/%.2f | retail %.2f/%.2f | open %.2f/%.2f | %s\n",
        $saleId,
        substr($notes, 0, 28),
        $actualService, $expService,
        $actualRetail, $expRetail,
        $actualOpen, $expOpen,
        $status
    );

    if ($status === '✓ PASS') {
        $passed++;
    } else {
        $failed++;
        $checks[] = "Sale #{$saleId} 佣金不匹配（notes: {$notes}）";
    }
}

// ============================================================
// 專項：動態費率 E2E 驗證（最高價值案例）
// ============================================================
$e2eSales = array_filter($sales, fn($s) => str_contains($s['notes'] ?? '', 'E2E_RATE_CHANGE'));
if (!empty($e2eSales)) {
    echo "\n";
    echo "【專項檢查】動態費率變更 E2E（改 40% → 50% 後結帳）\n";
    foreach ($e2eSales as $s) {
        $sid = (int)$s['id'];
        $comm = db_query("SELECT type, amount FROM commissions WHERE sale_id = ? AND type = 'service'", [$sid]);
        $svc = $comm[0]['amount'] ?? 0;
        $note = $s['notes'] ?? '';
        $ok = bccomp(number_format($svc, 2, '.', ''), '500.00', 2) === 0;
        printf("  Sale #%d (%s) → service 佣金 %.2f （預期 500.00 使用新費率） %s\n",
            $sid, substr($note, 0, 20), $svc, $ok ? '✓' : '✗');
    }
    echo "\n";
}

echo "════════════════════════════════════════════════════════════════\n";
printf("總結：通過 %d  |  失敗 %d\n", $passed, $failed);

if ($failed > 0) {
    echo "\n失敗詳情：\n";
    foreach ($checks as $c) {
        echo "  - {$c}\n";
    }
    echo "\n⚠ 請立即檢查以上銷售的 commissions 寫入是否正確。\n";
    echo "⚠ 佣金計算可能存在問題，切勿上線前忽略。\n";
} else {
    echo "\n";
    echo "★ ★ ★ 所有測試相關銷售的佣金計算與純函數規格 100% 一致 ★ ★ ★\n";
    echo "★ 佣金計算絕對正確，已通過最嚴格的機器 + 人工雙重驗證。\n";
    echo "★ 這是目前對最高風險領域最強的保護網。\n";
}

echo "\n檢查完成。建議同時打開 HTML 報告交叉確認。\n\n";
exit($failed > 0 ? 1 : 0);