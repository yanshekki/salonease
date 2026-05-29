<?php
/**
 * SalonEase API 測試系統 - 測試資料種子
 * 
 * 使用方法（在目標伺服器執行）：
 *   php tests/fixtures/seed_test_data.php
 * 
 * 效果：
 * - 建立/重設 4 個測試帳號（admin/manager/therapist/reception）
 * - 設定已知佣金全球預設率（service 40%、retail 15%、open 5%）
 * - 建立 1 位測試客戶（方便結帳）
 * 
 * ⚠️ 僅供測試環境使用，生產環境請勿執行
 */

require_once __DIR__ . '/../../db.php';

echo "=== SalonEase 測試資料種子開始 ===\n";

// 1. 確保 settings 有佣金預設（與 install.php 一致）
$db->exec("
    INSERT INTO settings (id, default_commission_service, default_commission_retail, default_commission_open)
    VALUES (1, 40.00, 15.00, 5.00)
    ON DUPLICATE KEY UPDATE
        default_commission_service = VALUES(default_commission_service),
        default_commission_retail  = VALUES(default_commission_retail),
        default_commission_open    = VALUES(default_commission_open)
");
echo "✓ 全球佣金預設率已設定（service 40%, retail 15%, open 5%）\n";

// 2. 建立測試員工（密碼統一 TestXXX123!）
$testStaff = [
    ['name' => '測試管理員',   'email' => 'admin@salonease.test',     'role' => 'admin',     'password' => 'TestAdmin123!'],
    ['name' => '測試店長',     'email' => 'manager@salonease.test',   'role' => 'manager',   'password' => 'TestManager123!'],
    ['name' => '測試治療師',   'email' => 'therapist@salonease.test', 'role' => 'therapist', 'password' => 'TestTherapist123!'],
    ['name' => '測試前台',     'email' => 'reception@salonease.test', 'role' => 'reception', 'password' => 'TestReception123!'],
];

$hashStmt = $db->prepare("SELECT ?");
foreach ($testStaff as $s) {
    $hash = password_hash($s['password'], PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO staff (name, email, phone, role, password_hash, is_active, commission_rate_service, commission_rate_retail, commission_rate_open, created_at)
        VALUES (?, ?, '99999999', ?, ?, 1, NULL, NULL, NULL, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password_hash = VALUES(password_hash),
            is_active = 1
    ");
    $stmt->execute([$s['name'], $s['email'], $s['role'], $hash]);
    echo "✓ 測試帳號已建立/更新：{$s['email']} ({$s['role']})\n";
}

// 3. 建立一位測試客戶
$customerStmt = $db->prepare("
    INSERT INTO customers (name, phone, email, points, total_spent, visit_count, created_at)
    VALUES ('測試客戶', '91234567', 'testcustomer@salonease.test', 500, 0, 0, NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name)
");
$customerStmt->execute();
echo "✓ 測試客戶已建立（預設 500 點，方便測試積分兌換）\n";

// 4. 確保至少有一個服務項目可供測試結帳（若完全沒有會失敗）
$serviceCheck = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();
if ($serviceCheck == 0) {
    $db->exec("INSERT INTO services (name, duration_minutes, price, is_active, created_at) 
               VALUES ('測試佣金服務（自動建立）', 60, 300.00, 1, NOW())");
    echo "✓ 自動建立一個測試服務項目（id=1 左右）\n";
}

echo "\n=== 測試資料種子完成 ===\n";
echo "現在可以執行：php tests/run_tests.php --api=sales\n";
echo "或單獨執行佣金測試：php tests/api/test_sales_checkout_commission.php\n";
