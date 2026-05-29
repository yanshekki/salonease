<?php
/**
 * SalonEase API 測試系統 - 測試資料種子（豐富版）
 * 
 * 使用方法（在目標伺服器執行）：
 *   php tests/fixtures/seed_test_data.php
 * 
 * 效果：
 * - 建立/重設 4 個測試帳號（含不同個人佣金率）
 * - 設定已知佣金全球預設率 + 個人覆蓋費率
 * - 建立多位客戶 + 多個服務項目
 * - 建立多筆銷售 + 對應 commissions（供佣金自動驗證）
 * - 建立多個活躍 sale_payment_plans（含不同進度、跟進記錄、舊計劃）
 * - 建立部分付款記錄（供 payments + payment_plans 自動驗證）
 * - 確保有付款方法
 * 
 * 執行後，test_sales_checkout_commission.php、test_payments.php、test_payment_plans.php 
 * 的「自動驗證」區塊大多可以真正執行並通過/顯示有意義結果。
 * 
 * ⚠️ 僅供測試環境使用，生產環境請勿執行
 */

require_once __DIR__ . '/../../db.php';

echo "=== SalonEase 測試資料種子（豐富版）開始 ===\n";

// 1. 確保 settings 有佣金預設 + 注意門檻
db_exec("
    INSERT INTO settings (id, default_commission_service, default_commission_retail, default_commission_open, 
                          needs_attention_days_threshold, needs_attention_progress_threshold)
    VALUES (1, 40.00, 15.00, 5.00, 30, 40)
    ON DUPLICATE KEY UPDATE
        default_commission_service = VALUES(default_commission_service),
        default_commission_retail  = VALUES(default_commission_retail),
        default_commission_open    = VALUES(default_commission_open),
        needs_attention_days_threshold = VALUES(needs_attention_days_threshold),
        needs_attention_progress_threshold = VALUES(needs_attention_progress_threshold)
");
echo "✓ 全球佣金預設率 + 注意門檻已設定\n";

// 2. 建立測試員工（含個人佣金率覆蓋，用於佣金測試不同情境）
$testStaff = [
    ['name' => '測試管理員',   'email' => 'admin@salonease.test',     'role' => 'admin',     'password' => 'TestAdmin123!', 
     'service' => 42, 'retail' => 18, 'open' => 7],
    ['name' => '測試店長',     'email' => 'manager@salonease.test',   'role' => 'manager',   'password' => 'TestManager123!', 
     'service' => 45, 'retail' => 20, 'open' => 6],   // 個人覆蓋
    ['name' => '測試治療師',   'email' => 'therapist@salonease.test', 'role' => 'therapist', 'password' => 'TestTherapist123!', 
     'service' => 35, 'retail' => null, 'open' => null], // 只有 service 個人率
    ['name' => '測試前台',     'email' => 'reception@salonease.test', 'role' => 'reception', 'password' => 'TestReception123!', 
     'service' => null, 'retail' => null, 'open' => null],
];

$staffIds = [];
foreach ($testStaff as $s) {
    $hash = password_hash($s['password'], PASSWORD_DEFAULT);
    
    db_exec("
        INSERT INTO staff (name, email, phone, role, password_hash, is_active, 
                           commission_rate_service, commission_rate_retail, commission_rate_open, created_at)
        VALUES (?, ?, '99999999', ?, ?, 1, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password_hash = VALUES(password_hash),
            is_active = 1,
            commission_rate_service = VALUES(commission_rate_service),
            commission_rate_retail  = VALUES(commission_rate_retail),
            commission_rate_open    = VALUES(commission_rate_open)
    ", [
        $s['name'], $s['email'], $s['role'], $hash,
        $s['service'], $s['retail'], $s['open']
    ]);
    
    $row = db_query_one("SELECT id FROM staff WHERE email = ?", [$s['email']]);
    $staffIds[$s['email']] = $row['id'] ?? null;
    
    echo "✓ 測試帳號已建立/更新：{$s['email']} (staff_id=" . ($staffIds[$s['email']] ?? '?') . ")\n";
}

// 3. 建立多位測試客戶
$customers = [
    ['name' => '測試客戶A', 'phone' => '91234567', 'email' => 'testa@salonease.test', 'points' => 650],
    ['name' => '測試客戶B', 'phone' => '92345678', 'email' => 'testb@salonease.test', 'points' => 120],
    ['name' => '測試客戶C（高風險）', 'phone' => '93456789', 'email' => 'testc@salonease.test', 'points' => 30],
];

$customerIds = [];
foreach ($customers as $c) {
    db_exec("
        INSERT INTO customers (name, phone, email, points, total_spent, visit_count, created_at)
        VALUES (?, ?, ?, ?, 0, 0, NOW())
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            points = GREATEST(points, VALUES(points))
    ", [$c['name'], $c['phone'], $c['email'], $c['points']]);
    
    $row = db_query_one("SELECT id FROM customers WHERE email = ?", [$c['email']]);
    $customerIds[$c['email']] = $row['id'] ?? null;
}
echo "✓ 多位測試客戶已建立\n";

// 4. 建立服務項目
$services = [
    ['name' => '剪髮', 'price' => 280],
    ['name' => '染髮', 'price' => 680],
    ['name' => '護理', 'price' => 450],
];
foreach ($services as $svc) {
    db_exec("INSERT IGNORE INTO services (name, duration_minutes, price, is_active, created_at) 
             VALUES (?, 60, ?, 1, NOW())", [$svc['name'], $svc['price']]);
}
echo "✓ 服務項目已確保存在\n";

// 5. 建立付款方法
db_exec("INSERT IGNORE INTO payment_methods (name, code, is_enabled, fee_type, fee_value, created_at) 
         VALUES ('現金', 'cash', 1, 'none', 0, NOW())");
db_exec("INSERT IGNORE INTO payment_methods (name, code, is_enabled, fee_type, fee_value, created_at) 
         VALUES ('信用卡', 'credit', 1, 'percent', 2.5, NOW())");
echo "✓ 付款方法已確保存在\n";

// 6. 建立幾筆銷售 + commissions（供佣金自動驗證使用）
$managerId = $staffIds['manager@salonease.test'] ?? 2;
$therapistId = $staffIds['therapist@salonease.test'] ?? 3;

$salesData = [
    ['customer' => 'testa@salonease.test', 'staff' => $managerId,   'amount' => 500,  'service_rate' => 45],
    ['customer' => 'testb@salonease.test', 'staff' => $therapistId, 'amount' => 680,  'service_rate' => 35],
    ['customer' => 'testc@salonease.test', 'staff' => $managerId,   'amount' => 280,  'service_rate' => 45],
];

$saleIds = [];
foreach ($salesData as $idx => $sd) {
    $custId = $customerIds[$sd['customer']] ?? null;
    if (!$custId) continue;

    $saleDate = date('Y-m-d', strtotime("-" . ($idx * 2) . " days"));

    db_exec("
        INSERT INTO sales (customer_id, staff_id, sale_date, subtotal, discount, total, payment_method, notes, created_at)
        VALUES (?, ?, ?, ?, 0, ?, 'cash', ?, NOW())
    ", [$custId, $sd['staff'], $saleDate, $sd['amount'], $sd['amount'], 'SEED_SALE_' . ($idx+1)]);

    $saleId = db_last_id();
    $saleIds[] = $saleId;

    // 建立對應 commission（模擬真實結帳寫入）
    $serviceComm = round($sd['amount'] * ($sd['service_rate'] / 100), 2);
    db_exec("
        INSERT INTO commissions (sale_id, staff_id, amount, type, rate, calculated_at)
        VALUES (?, ?, ?, 'service', ?, NOW())
    ", [$saleId, $sd['staff'], $serviceComm, $sd['service_rate']]);

    $openComm = round($sd['amount'] * 0.06, 2);
    db_exec("
        INSERT INTO commissions (sale_id, staff_id, amount, type, rate, calculated_at)
        VALUES (?, ?, ?, 'open', 6, NOW())
    ", [$saleId, $sd['staff'], $openComm]);

    echo "✓ 建立銷售單 #{$saleId} + commissions（staff {$sd['staff']}）\n";
}

// 7. 建立多個付款計劃（含不同進度、跟進、舊計劃）
$planCustomer = $customerIds['testc@salonease.test'] ?? $customerIds['testa@salonease.test'];
$planSale = $saleIds[2] ?? ($saleIds[0] ?? 1);

$plans = [
    ['sale_id' => $planSale, 'installments' => 4, 'amount' => 120, 'paid' => 1, 'days_ago' => 50, 'notes' => "[跟進 " . date('Y-m-d H:i', strtotime('-10 days')) . "] 客戶暫時資金周轉有問題"],
    ['sale_id' => $saleIds[0] ?? 1, 'installments' => 3, 'amount' => 180, 'paid' => 2, 'days_ago' => 20, 'notes' => ''],
    ['sale_id' => $saleIds[1] ?? 1, 'installments' => 6, 'amount' => 120, 'paid' => 0, 'days_ago' => 8,  'notes' => ''],
];

foreach ($plans as $p) {
    $startDate = date('Y-m-d', strtotime("-{$p['days_ago']} days"));
    
    db_exec("
        INSERT INTO sale_payment_plans 
        (sale_id, plan_type, total_installments, installment_amount, frequency, start_date, status, notes, created_by, created_at)
        VALUES (?, 'installment', ?, ?, 'monthly', ?, 'active', ?, ?, NOW())
    ", [$p['sale_id'], $p['installments'], $p['amount'], $startDate, $p['notes'], $managerId]);

    $planId = db_last_id();

    // 建立部分付款記錄
    for ($i = 1; $i <= $p['paid']; $i++) {
        db_exec("
            INSERT INTO payments (sale_id, plan_id, payment_method_id, amount, fee_amount, fee_borne_by, paid_at, staff_id, installment_no)
            VALUES (?, ?, 1, ?, 0, 'merchant', DATE_SUB(NOW(), INTERVAL ? DAY), ?, ?)
        ", [$p['sale_id'], $planId, $p['amount'], ($i * 7), $managerId, $i]);
    }

    echo "✓ 建立付款計劃 #{$planId}（{$p['paid']}/{$p['installments']} 期，已付）\n";
}

echo "\n=== 測試資料種子（豐富版）完成 ===\n";
echo "現在執行以下指令，自動驗證區塊大多可以真正運作：\n";
echo "  php tests/api/test_sales_checkout_commission.php\n";
echo "  php tests/api/test_payments.php\n";
echo "  php tests/api/test_payment_plans.php\n";
echo "  php tests/api/test_permissions_matrix.php\n";
