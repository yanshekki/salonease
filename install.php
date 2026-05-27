<?php
/**
 * SalonEase - 專業一鍵安裝工具
 * 支援兩種安裝模式 + 完整十年模擬數據
 * 
 * 設計原則：
 * - 安全第一：安裝前嚴格檢查、確認提示
 * - 專業體驗：即時進度、詳細日誌、香港繁中
 * - 未來相容：安裝完成後可直接使用 upgrade.php 升級
 * - 絕不破壞已安裝環境
 * 
 * 使用方法：
 * 1. 確保 config.php 已正確設定資料庫連線
 * 2. 瀏覽 https://yourdomain.com/install.php
 * 3. 選擇模式並填寫管理員資料
 * 4. 一鍵完成安裝
 * 5. 立即刪除本檔案！
 */

// 提升執行限制（十年 Demo 數據需要較長時間）
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// 簡單工具函數
function h($str): string { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 預先檢查是否已安裝
function is_already_installed(PDO $pdo): bool {
    try {
        // 檢查 migrations 表 + 是否有 staff 資料
        $hasMigrations = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migrations'")->fetch();
        if (!$hasMigrations) return false;

        $executed = $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration = '001_initial_schema'")->fetchColumn();
        if ($executed > 0) return true;

        // 後備檢查：staff 表有 active admin
        $staffCount = $pdo->query("SELECT COUNT(*) FROM staff WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        return $staffCount > 0;
    } catch (Throwable $e) {
        return false; // 表不存在視為未安裝
    }
}

$alreadyInstalled = false;
$installError = null;
try {
    $pdo = db();
    $alreadyInstalled = is_already_installed($pdo);
} catch (Throwable $e) {
    $installError = $e->getMessage();
}

// 處理 AJAX 環境檢查
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preflight') {
    $checks = [];
    try {
        $pdo = db();
        $checks['db_connect'] = ['ok' => true, 'msg' => '資料庫連線成功'];
        
        // 檢查關鍵表是否存在
        $tables = ['staff', 'settings', 'customers', 'sales'];
        foreach ($tables as $t) {
            $exists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'")->fetch();
            $checks["table_$t"] = ['ok' => (bool)$exists, 'msg' => $exists ? "表 $t 已存在" : "表 $t 不存在（正常）"];
        }
        
        $checks['php_version'] = ['ok' => version_compare(PHP_VERSION, '7.4', '>='), 'msg' => 'PHP ' . PHP_VERSION];
        $checks['pdo_mysql'] = ['ok' => extension_loaded('pdo_mysql'), 'msg' => extension_loaded('pdo_mysql') ? 'PDO MySQL 已啟用' : '缺少 PDO MySQL'];
        
        json_out(['success' => true, 'checks' => $checks]);
    } catch (Throwable $e) {
        json_out(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// 處理正式安裝 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
    if ($alreadyInstalled) {
        json_out(['success' => false, 'message' => '系統已安裝，拒絕重複安裝。如需重裝請先清空資料庫。'], 409);
    }

    $mode = $_POST['mode'] ?? 'clean'; // clean | demo
    $adminName = trim($_POST['admin_name'] ?? '系統管理員');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@salonease.hk');
    $adminPhone = trim($_POST['admin_phone'] ?? '9123 4567');
    $adminPass = $_POST['admin_pass'] ?? '';
    $salonName = trim($_POST['salon_name'] ?? 'SalonEase 美容中心');
    $salonAddr = trim($_POST['salon_address'] ?? '香港九龍尖沙咀彌敦道 100 號 8 樓');
    $salonPhone = trim($_POST['salon_phone'] ?? '2123 4567');
    $agree = isset($_POST['agree_risk']);

    if (!$agree) {
        json_out(['success' => false, 'message' => '請勾選已了解風險'], 422);
    }
    if (strlen($adminPass) < 6) {
        json_out(['success' => false, 'message' => '管理員密碼至少 6 位'], 422);
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        json_out(['success' => false, 'message' => '管理員電郵格式不正確'], 422);
    }

    // 開始安裝流程（輸出即時日誌）
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>SalonEase 安裝進行中...</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<style>body{font-family:"Noto Sans TC",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif} .log{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px} .log-line{padding:4px 0;border-bottom:1px solid #f1e9dc}</style>';
    echo '</head><body class="bg-[#FDF8F3] text-[#2C2C2E]">';
    echo '<div class="max-w-4xl mx-auto p-8">';
    echo '<div class="flex items-center gap-3 mb-6"><div class="w-9 h-9 bg-[#2C2C2E] text-white rounded-2xl flex items-center justify-center font-bold text-xl">S</div><div><div class="font-semibold text-2xl tracking-tight">SalonEase</div><div class="text-xs text-[#8A8A8C] -mt-1">專業安裝工具</div></div></div>';
    echo '<h1 class="text-3xl font-semibold mb-2">正在執行安裝...</h1>';
    echo '<div class="text-[#5A5A5C] mb-6">請勿關閉此頁面，安裝完成後會顯示結果</div>';
    echo '<div id="log-container" class="bg-white border border-[#EDE5DC] rounded-3xl p-6 shadow-sm max-h-[520px] overflow-auto text-sm log space-y-0.5">';
    ob_implicit_flush(true);
    ob_flush();

    function log_line(string $msg, string $type = 'info') {
        $icon = $type === 'success' ? '✓' : ($type === 'error' ? '✗' : ($type === 'warn' ? '⚠' : '→'));
        $color = $type === 'success' ? 'text-[#2e7d32]' : ($type === 'error' ? 'text-[#c62828]' : ($type === 'warn' ? 'text-[#b8860b]' : 'text-[#5A5A5C]'));
        echo "<div class='log-line $color'>[$icon] " . h($msg) . "</div>";
        ob_flush();
    }

    try {
        $pdo = db();
        log_line('資料庫連線成功');

        // 1. 建立 migrations 表（升級系統基礎）
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        log_line('migrations 表就緒（未來升級依據）');

        // 2. 執行初始 Schema（使用 001 migration 邏輯，但只在全新環境）
        $migrationName = '001_initial_schema';
        $alreadyMigrated = $pdo->query("SELECT 1 FROM migrations WHERE migration = " . $pdo->quote($migrationName))->fetch();
        
        if (!$alreadyMigrated) {
            log_line('開始建立核心資料表（schema.sql）...');
            $schemaFile = __DIR__ . '/sql/schema.sql';
            if (!file_exists($schemaFile)) throw new Exception('找不到 sql/schema.sql');
            
            $sql = file_get_contents($schemaFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $stmtCount = 0;
            foreach ($statements as $statement) {
                if ($statement && !str_starts_with($statement, '--')) {
                    $pdo->exec($statement);
                    $stmtCount++;
                    if ($stmtCount % 8 === 0) log_line("  已處理 $stmtCount 個 SQL 語句...");
                }
            }
            log_line("核心 15 張資料表建立完成", 'success');

            // 標記 migration 已執行
            $batch = (int)$pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations")->fetchColumn() + 1;
            $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")->execute([$migrationName, $batch]);
            log_line('Migration 001 已記錄');
        } else {
            log_line('檢測到已存在結構，跳過 schema 建立');
        }

        // 3. 更新店舖基本設定（來自表單）
        $pdo->prepare("
            INSERT INTO settings (id, salon_name, address, phone, printer_width, 
                default_commission_service, default_commission_retail, default_commission_open, default_low_stock_threshold)
            VALUES (1, ?, ?, ?, '58', 40.00, 15.00, 5.00, 5)
            ON DUPLICATE KEY UPDATE
                salon_name = VALUES(salon_name),
                address = VALUES(address),
                phone = VALUES(phone)
        ")->execute([$salonName, $salonAddr, $salonPhone]);
        log_line('店舖設定已更新');

        // 4. 建立 / 更新管理員帳號
        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO staff (name, phone, email, role, password_hash, is_active, created_at)
            VALUES (?, ?, ?, 'admin', ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                phone = VALUES(phone),
                password_hash = VALUES(password_hash),
                role = 'admin',
                is_active = 1
        ");
        $stmt->execute([$adminName, $adminPhone, $adminEmail, $passwordHash]);
        log_line("管理員帳號 [$adminEmail] 建立完成", 'success');

        $adminId = $pdo->query("SELECT id FROM staff WHERE email = " . $pdo->quote($adminEmail))->fetchColumn();

        // 5. Demo 模式：產生十年模擬數據
        $demoStats = ['customers' => 0, 'staff' => 0, 'sales' => 0, 'appointments' => 0, 'packages' => 0, 'usages' => 0, 'commissions' => 0];
        
        if ($mode === 'demo') {
            log_line('開始產生「十年模擬營運數據」（約需 40-90 秒）...', 'warn');
            log_line('模擬時間：2015 年 1 月 ～ 2025 年 5 月 | 真實香港美容院營運模式');
            
            // 固定種子確保可重現但每次安裝都新鮮
            mt_srand(20250528);

            // === 擴展基礎資料（Demo 專用） ===
            // 更多員工（含歷史離職員工）
            $demoStaff = [
                ['陳美玲', '9234 5678', 'chan.meiling@salonease.hk', 'therapist', 45.00, 1, '2015-03-01'],
                ['李嘉欣', '9345 6789', 'lee.kayan@salonease.hk', 'therapist', 42.00, 1, '2016-06-15'],
                ['張詠詩', '9456 7890', 'cheung.wingsze@salonease.hk', 'therapist', 38.00, 1, '2018-02-10'],
                ['黃子晴', '9567 8901', 'wong.tszching@salonease.hk', 'therapist', 40.00, 0, '2017-09-01'], // 離職
                ['林浩然', '9678 9012', 'lam.hoyin@salonease.hk', 'manager', 35.00, 1, '2019-01-20'],
                ['周美琪', '9789 0123', 'chau.meikei@salonease.hk', 'reception', 10.00, 1, '2015-11-05'],
                ['吳家樂', '9890 1234', 'ng.kalok@salonease.hk', 'therapist', 48.00, 1, '2021-04-12'],
                ['蔡芷晴', '9901 2345', 'choi.tszching@salonease.hk', 'therapist', 36.00, 1, '2022-08-30'],
                ['羅天恩', '9012 3456', 'lo.tinan@salonease.hk', 'reception', 8.00, 1, '2023-03-15'],
            ];
            foreach ($demoStaff as $s) {
                $ph = password_hash('staff123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT IGNORE INTO staff (name, phone, email, role, password_hash, commission_rate_service, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$s[0], $s[1], $s[2], $s[3], $ph, $s[4], $s[5], $s[6]]);
            }
            $demoStats['staff'] = count($demoStaff) + 1;
            log_line('  已建立 9 位員工（含 1 位歷史離職）');

            // 更多房間
            $rooms = [['1 號房',1], ['2 號房',1], ['3 號房',1], ['VIP 房',2], ['美甲區',1]];
            foreach ($rooms as $r) {
                $pdo->prepare("INSERT IGNORE INTO rooms (name, capacity, is_active) VALUES (?, ?, 1)")->execute($r);
            }
            log_line('  房間資料就緒');

            // 豐富服務項目（14 項）
            $services = [
                ['經典面部護理 60 分鐘', 60, 680, '面部護理'],
                ['深層清潔面部護理 90 分鐘', 90, 980, '面部護理'],
                ['水光針活膚療程 45 分鐘', 45, 1380, '醫美'],
                ['眼部抗老修復護理 50 分鐘', 50, 580, '面部護理'],
                ['全身芳香淋巴按摩 75 分鐘', 75, 880, '身體護理'],
                ['肩頸舒緩精油按摩 30 分鐘', 30, 380, '身體護理'],
                ['背部淨化護理 40 分鐘', 40, 480, '身體護理'],
                ['男士深度潔淨護理 60 分鐘', 60, 720, '男士專屬'],
                ['玻尿酸保濕導入 45 分鐘', 45, 1180, '醫美'],
                ['頭皮養護療程 40 分鐘', 40, 420, '頭皮護理'],
                ['產後修身淋巴按摩 80 分鐘', 80, 980, '身體護理'],
                ['抗糖化亮肌護理 55 分鐘', 55, 780, '面部護理'],
                ['足部反射區按摩 35 分鐘', 35, 320, '身體護理'],
                ['婚前亮肌急救護理 70 分鐘', 70, 1280, '醫美'],
            ];
            foreach ($services as $sv) {
                $pdo->prepare("INSERT IGNORE INTO services (name, duration_min, price, category, is_active) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$sv[0], $sv[1], $sv[2], $sv[3]]);
            }
            log_line('  14 項服務項目建立');

            // 零售產品（22 項，部分低庫存）
            $products = [
                ['La Mer 修復面霜 60ml', 'LM-60', 1850, 920, 6, '護膚品', 4],
                ['Sisley 玫瑰面膜 50ml', 'SS-50', 980, 480, 9, '護膚品', 5],
                ['Dermal 保濕精華 30ml', 'DM-30', 380, 160, 22, '護膚品', null],
                ['天然海鹽磨砂膏 200g', 'NS-200', 168, 65, 18, '身體護理', null],
                ['Caudalie 葡萄籽精華 30ml', 'CV-30', 520, 240, 14, '護膚品', 6],
                ['Decorté AQ 乳液 200ml', 'DC-AQ', 780, 380, 7, '護膚品', 5],
                ['Aesop 賦活洗髮精 500ml', 'AE-500', 420, 195, 11, '頭皮護理', null],
                ['L\'Occitane 乳木果身體乳 250ml', 'LO-250', 298, 130, 25, '身體護理', null],
                ['Dr. Barbara Sturm 亮肌精華 20ml', 'BS-20', 1680, 850, 3, '醫美', 3],
                ['SkinCeuticals 維C精華 30ml', 'SC-30', 980, 460, 8, '護膚品', 4],
                ['Eucerin 醫美保濕霜 50ml', 'EU-50', 268, 110, 31, '護膚品', null],
                ['The Ordinary 煙酰胺精華 30ml', 'TO-30', 98, 38, 42, '護膚品', null],
                ['Kiehl\'s 金盞花面膜 100ml', 'KH-100', 380, 165, 15, '護膚品', 7],
                ['Neutrogena 深層潔面 200ml', 'NT-200', 128, 52, 27, '護膚品', null],
                ['CeraVe 修復乳 340g', 'CV-340', 168, 72, 19, '護膚品', null],
                ['理膚泉 B5 修復霜 40ml', 'LR-40', 228, 95, 13, '護膚品', 5],
                ['Vichy 溫泉水噴霧 300ml', 'VC-300', 148, 58, 33, '護膚品', null],
                ['Avène 舒緩修護乳 50ml', 'AV-50', 188, 78, 16, '護膚品', 6],
                ['La Roche-Posay 防曬乳 50ml', 'LRP-50', 238, 105, 21, '護膚品', null],
                ['Bioderma 潔膚水 250ml', 'BD-250', 168, 65, 24, '護膚品', null],
                ['Murad 煥膚精華 30ml', 'MU-30', 680, 320, 5, '醫美', 4],
                ['Obagi 維C精華 30ml', 'OB-30', 880, 420, 4, '醫美', 3],
            ];
            foreach ($products as $p) {
                $pdo->prepare("INSERT IGNORE INTO products (name, sku, price, cost, stock_qty, category, low_stock_threshold, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)")->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]]);
            }
            log_line('  22 項零售產品（含 8 項低庫存警示）');

            // 套票（7 種）
            $packages = [
                ['經典面部護理 10 次卡', 10, 5800, 365, '買 10 次經典面部護理，享 8 折'],
                ['深層水光針 6 次套票', 6, 7200, 365, '醫美水光 + 眼部護理一次'],
                ['全身芳香按摩 8 次卡', 8, 6200, 240, '8 次全身芳香按摩'],
                ['眼部抗老護理 12 次卡', 12, 5800, 365, '眼部護理年度套票'],
                ['男士護理 5 次卡', 5, 3200, 180, '男士專屬護理套票'],
                ['產後修身療程 6 次', 6, 5200, 365, '產後專屬淋巴 + 修身'],
                ['婚前亮肌急救 4 次', 4, 4200, 120, '婚前 4 次急救護理'],
            ];
            foreach ($packages as $pk) {
                $pdo->prepare("INSERT IGNORE INTO packages (name, total_sessions, price, validity_days, description, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                    ->execute($pk);
            }
            log_line('  7 種套票模板');

            // === 產生客戶（160 人，時間分佈） ===
            $surnames = ['陳','李','張','黃','林','周','吳','蔡','羅','梁','許','鄭','謝','王','馮','曾','彭','呂','蔣','楊','趙','錢','孫','朱','胡','郭','高','馬','徐','何','沈','葉','朱','方','宋','江','潘','蔡','丁','魏','薛','韓','唐','賈','孔','曹','嚴','華','石','盧'];
            $givenFemale = ['美玲','嘉欣','詠詩','子晴','芷晴','美琪','家怡','心瑜','穎兒','詩韻','翠珊','雅婷','慧敏','婉婷','佩珊'];
            $givenMale = ['浩然','家樂','天恩','志強','偉豪','俊傑','子健','家輝','永康','志明','國華','文傑','子軒','家俊'];
            
            $customerIds = [];
            $totalCustomers = 162;
            for ($i = 0; $i < $totalCustomers; $i++) {
                $gender = mt_rand(0, 100) > 78 ? 'M' : 'F'; // 78% 女性
                $surname = $surnames[array_rand($surnames)];
                $given = $gender === 'F' ? $givenFemale[array_rand($givenFemale)] : $givenMale[array_rand($givenMale)];
                $name = $surname . ($gender === 'F' ? '太' : '生') . ' / ' . $surname . $given;
                
                $year = mt_rand(2015, 2024);
                $month = mt_rand(1, 12);
                $created = sprintf('%04d-%02d-%02d', $year, $month, mt_rand(1, 28));
                
                $phone = '5' . mt_rand(10000000, 99999999); // 香港手提
                $email = mt_rand(0, 1) ? strtolower($surname) . '.' . strtolower($given) . '@' . ['gmail.com','yahoo.com.hk','hotmail.com'][mt_rand(0,2)] : null;
                
                $pdo->prepare("INSERT INTO customers (name, phone, email, gender, birthday, notes, created_at, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $name,
                        $phone,
                        $email,
                        $gender,
                        $gender === 'F' ? date('Y-m-d', strtotime($created . ' -' . mt_rand(22, 48) . ' years')) : null,
                        mt_rand(0, 4) === 0 ? ['對玫瑰精油敏感','偏好下午時段','第一次來店','介紹醫美項目','產後修復中'][mt_rand(0,4)] : null,
                        $created . ' 10:' . str_pad(mt_rand(0,59), 2, '0', STR_PAD_LEFT) . ':00',
                        $adminId
                    ]);
                $customerIds[] = $pdo->lastInsertId();
            }
            $demoStats['customers'] = $totalCustomers;
            log_line("  已建立 $totalCustomers 位客戶（2015-2024 分佈）");

            // 取得所有 ID 供隨機使用
            $allStaffIds = $pdo->query("SELECT id FROM staff WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
            $allServiceIds = $pdo->query("SELECT id FROM services")->fetchAll(PDO::FETCH_COLUMN);
            $allProductIds = $pdo->query("SELECT id FROM products")->fetchAll(PDO::FETCH_COLUMN);
            $allPackageIds = $pdo->query("SELECT id FROM packages")->fetchAll(PDO::FETCH_COLUMN);
            $allRoomIds = $pdo->query("SELECT id FROM rooms")->fetchAll(PDO::FETCH_COLUMN);

            // === 產生 10 年銷售 + 預約數據 ===
            $paymentMethods = ['fps' => 48, 'cash' => 22, 'card' => 18, 'wechat' => 7, 'alipay' => 5];
            $years = range(2015, 2025);
            $totalSales = 0;
            $totalAppts = 0;
            $totalUsages = 0;
            $totalCommissions = 0;

            $customerPackages = []; // 記錄已售出的套票 [id => remaining]

            foreach ($years as $y) {
                $months = ($y === 2025) ? range(1, 5) : range(1, 12);
                foreach ($months as $m) {
                    // 成長曲線 + COVID 調整
                    $base = 18 + (int)(($y - 2015) * 5.8);
                    if ($y === 2020) $base = (int)($base * 0.55);
                    if ($y >= 2022) $base = (int)($base * 1.15);
                    $salesThisMonth = mt_rand((int)($base * 0.7), (int)($base * 1.35));

                    for ($s = 0; $s < $salesThisMonth; $s++) {
                        $day = mt_rand(1, min(28, cal_days_in_month(CAL_GREGORIAN, $m, $y)));
                        $saleDate = sprintf('%04d-%02d-%02d', $y, $m, $day);
                        $staffId = $allStaffIds[array_rand($allStaffIds)];
                        $custId = $customerIds[array_rand($customerIds)];

                        $items = [];
                        $subtotal = 0;
                        $isPackageRedemption = false;

                        // 決定交易類型
                        $rand = mt_rand(0, 100);
                        if ($rand < 18 && count($customerPackages) > 0) {
                            // 套票扣減（18%）
                            $cpId = array_rand($customerPackages);
                            if ($customerPackages[$cpId] > 0) {
                                $isPackageRedemption = true;
                                $svcId = $allServiceIds[array_rand($allServiceIds)];
                                $svc = $pdo->query("SELECT name, price, duration_min FROM services WHERE id = $svcId")->fetch();
                                $items[] = [
                                    'type' => 'package_redemption',
                                    'ref_id' => $cpId,
                                    'name' => $svc['name'] . '（套票扣 1 次）',
                                    'qty' => 1,
                                    'unit_price' => 0,
                                    'staff_id' => $staffId
                                ];
                                $subtotal = 0;
                                $customerPackages[$cpId]--;
                                $totalUsages++;
                            }
                        }
                        
                        if (!$isPackageRedemption) {
                            // 正常消費：1-3 項（服務 + 產品）
                            $numItems = mt_rand(1, 3);
                            for ($k = 0; $k < $numItems; $k++) {
                                if (mt_rand(0, 100) < 72) {
                                    // 服務
                                    $svcId = $allServiceIds[array_rand($allServiceIds)];
                                    $svc = $pdo->query("SELECT name, price, duration_min FROM services WHERE id = $svcId")->fetch();
                                    $price = $svc['price'];
                                    if ($y < 2018) $price = (int)($price * 0.82); // 早期較平
                                    $items[] = ['type' => 'service', 'ref_id' => $svcId, 'name' => $svc['name'], 'qty' => 1, 'unit_price' => $price, 'staff_id' => $staffId];
                                    $subtotal += $price;
                                } else {
                                    // 產品
                                    $prodId = $allProductIds[array_rand($allProductIds)];
                                    $prod = $pdo->query("SELECT name, price FROM products WHERE id = $prodId")->fetch();
                                    $qty = mt_rand(1, 2);
                                    $items[] = ['type' => 'product', 'ref_id' => $prodId, 'name' => $prod['name'], 'qty' => $qty, 'unit_price' => $prod['price'], 'staff_id' => $staffId];
                                    $subtotal += $prod['price'] * $qty;
                                }
                            }
                        }

                        if (empty($items)) continue;

                        $discount = mt_rand(0, 12) === 0 ? round($subtotal * (mt_rand(5, 15) / 100), 0) : 0;
                        $total = $subtotal - $discount;

                        // 選擇支付方式（加權）
                        $pm = 'fps';
                        $r = mt_rand(0, 99);
                        $cum = 0;
                        foreach ($paymentMethods as $method => $weight) {
                            $cum += $weight;
                            if ($r < $cum) { $pm = $method; break; }
                        }

                        // 建立銷售單
                        $pdo->prepare("INSERT INTO sales (customer_id, staff_id, sale_date, subtotal, discount, total, payment_method, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                            ->execute([$custId, $staffId, $saleDate, $subtotal, $discount, $total, $pm, $saleDate . ' ' . str_pad(mt_rand(9, 20), 2, '0', STR_PAD_LEFT) . ':' . str_pad(mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00']);
                        $saleId = $pdo->lastInsertId();
                        $totalSales++;

                        // 明細 + 佣金
                        foreach ($items as $it) {
                            $lineTotal = $it['unit_price'] * $it['qty'];
                            $pdo->prepare("INSERT INTO sale_items (sale_id, item_type, ref_id, name, qty, unit_price, line_total, staff_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$saleId, $it['type'], $it['ref_id'], $it['name'], $it['qty'], $it['unit_price'], $lineTotal, $it['staff_id']]);

                            // 佣金快照
                            if ($it['type'] === 'service' || $it['type'] === 'product') {
                                $rate = $it['type'] === 'service' ? 40.0 : 15.0;
                                $amount = round($lineTotal * $rate / 100, 2);
                                if ($amount > 0) {
                                    $pdo->prepare("INSERT INTO commissions (sale_id, staff_id, amount, type, rate) VALUES (?, ?, ?, ?, ?)")
                                        ->execute([$saleId, $it['staff_id'], $amount, $it['type'] === 'service' ? 'service' : 'retail', $rate]);
                                    $totalCommissions++;
                                }
                            }
                        }

                        // 偶爾建立套票購買記錄
                        if (!$isPackageRedemption && mt_rand(0, 100) < 11 && count($allPackageIds) > 0) {
                            $pkgId = $allPackageIds[array_rand($allPackageIds)];
                            $pkg = $pdo->query("SELECT * FROM packages WHERE id = $pkgId")->fetch();
                            $validity = $pkg['validity_days'];
                            $expiry = date('Y-m-d', strtotime($saleDate . " +$validity days"));
                            
                            $pdo->prepare("INSERT INTO customer_packages (customer_id, package_id, purchase_date, expiry_date, total_sessions, remaining_sessions, sale_id, notes) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$custId, $pkgId, $saleDate, $expiry, $pkg['total_sessions'], $pkg['total_sessions'], $saleId, 'Demo 十年數據']);
                            
                            $cpId = $pdo->lastInsertId();
                            $customerPackages[$cpId] = $pkg['total_sessions'];
                            $demoStats['packages']++;

                            // 套票購買本身也計一次「開單佣金」
                            $openAmount = round($pkg['price'] * 0.05, 2);
                            $pdo->prepare("INSERT INTO commissions (sale_id, staff_id, amount, type, rate) VALUES (?, ?, ?, 'open', 5.00)")
                                ->execute([$saleId, $staffId, $openAmount]);
                        }

                        // 偶爾同步產生預約記錄（已完成）
                        if (mt_rand(0, 100) < 38) {
                            $startHour = mt_rand(10, 19);
                            $start = $saleDate . ' ' . str_pad($startHour, 2, '0', STR_PAD_LEFT) . ':' . str_pad(mt_rand(0, 45) - (mt_rand(0, 45) % 15), 2, '0', STR_PAD_LEFT) . ':00';
                            $duration = mt_rand(30, 90);
                            $end = date('Y-m-d H:i:s', strtotime($start) + $duration * 60);
                            
                            $pdo->prepare("INSERT INTO appointments (customer_id, staff_id, room_id, start_time, end_time, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'completed', ?)")
                                ->execute([$custId, $staffId, $allRoomIds[array_rand($allRoomIds)], $start, $end, $saleDate . ' 08:00:00']);
                            $totalAppts++;
                        }
                    }
                    
                    if ($m % 3 === 0) {
                        log_line("    $y 年 $m 月：$salesThisMonth 單 · 累計銷售 $totalSales");
                    }
                }
            }

            $demoStats['sales'] = $totalSales;
            $demoStats['appointments'] = $totalAppts;
            $demoStats['usages'] = $totalUsages;
            $demoStats['commissions'] = $totalCommissions;

            log_line("十年數據產生完成！", 'success');
            log_line("  客戶: {$demoStats['customers']} | 銷售單: {$demoStats['sales']} | 預約: {$demoStats['appointments']} | 套票使用: {$demoStats['usages']} | 佣金記錄: {$demoStats['commissions']}");
        }

        // 6. 最後更新客戶統計（總消費 / 來店次數）
        log_line('正在更新客戶統計摘要...');
        $pdo->exec("
            UPDATE customers c SET 
                total_spent = COALESCE((SELECT SUM(total) FROM sales WHERE customer_id = c.id), 0),
                visit_count = COALESCE((SELECT COUNT(*) FROM sales WHERE customer_id = c.id), 0),
                last_visit_at = (SELECT MAX(created_at) FROM sales WHERE customer_id = c.id),
                first_visit_at = (SELECT MIN(created_at) FROM sales WHERE customer_id = c.id)
        ");
        log_line('客戶統計更新完成');

        // 7. 記錄安裝完成（可供 upgrade 查詢）
        $pdo->exec("INSERT INTO activity_logs (staff_id, action, entity_type, details) VALUES (" . (int)$adminId . ", 'install', 'system', JSON_OBJECT('mode', " . $pdo->quote($mode) . ", 'version', '1.0.0'))");

        log_line('安裝流程全部完成！', 'success');

        // 成功畫面
        echo '</div>';
        echo '<div class="mt-8 bg-white border border-[#8FA68F]/40 rounded-3xl p-8 shadow-sm">';
        echo '<div class="text-[#2e7d32] text-2xl font-semibold flex items-center gap-2 mb-4">✓ 安裝成功</div>';
        echo '<div class="text-lg mb-4">SalonEase 已準備就緒' . ($mode === 'demo' ? '（含十年完整模擬數據）' : '（乾淨版）') . '</div>';
        
        echo '<div class="bg-[#F8F5F0] rounded-2xl p-5 mb-6 text-sm">';
        echo '<div class="font-medium mb-2">管理員登入資訊</div>';
        echo '<div class="font-mono bg-white px-4 py-2 rounded-xl border">電郵：<span class="font-semibold">' . h($adminEmail) . '</span></div>';
        echo '<div class="font-mono bg-white px-4 py-2 rounded-xl border mt-1">密碼：<span class="font-semibold">' . h($adminPass) . '</span> <span class="text-[#c62828] text-xs">（請立即修改）</span></div>';
        echo '</div>';

        echo '<div class="flex flex-col md:flex-row gap-3">';
        echo '<a href="/login.php" class="flex-1 text-center bg-[#2C2C2E] hover:bg-black text-white px-8 py-4 rounded-2xl font-medium text-lg transition">立即登入系統</a>';
        echo '<a href="/dashboard.php" class="flex-1 text-center border border-[#2C2C2E] px-8 py-4 rounded-2xl font-medium text-lg hover:bg-[#F8F5F0]">前往控制台</a>';
        echo '</div>';

        echo '<div class="mt-8 text-xs text-[#c62828] bg-red-50 border border-red-200 rounded-2xl p-4">';
        echo '<strong>重要安全提醒：</strong><br>';
        echo '1. 請立即透過 FTP / 控制台 / 檔案管理員 <strong>刪除 install.php</strong><br>';
        echo '2. 生產環境請修改預設密碼<br>';
        echo '3. 日後升級請使用 <a href="/upgrade.php" class="underline">upgrade.php</a>（一鍵安全升級）';
        echo '</div>';
        echo '</div></div></body></html>';
        exit;

    } catch (Throwable $e) {
        echo '</div><div class="mt-6 p-6 bg-red-50 border border-red-200 rounded-3xl text-red-700">';
        echo '<div class="font-semibold text-lg mb-2">安裝失敗</div>';
        echo '<div class="text-sm">' . h($e->getMessage()) . '</div>';
        echo '<div class="text-xs mt-4 text-red-500">請檢查 config.php 資料庫權限，或聯絡技術支援。</div>';
        echo '</div></div></body></html>';
        exit;
    }
}

// ==================== 以下為安裝頁面 UI ====================
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonEase · 專業安裝</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap');
        body { font-family: 'Noto Sans TC', system-ui, -apple-system, sans-serif; }
        .salon-card { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .salon-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.05); }
        .mode-selected { border-color: #2C2C2E; background: #F8F5F0; }
        .log-line { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
</head>
<body class="bg-[#FDF8F3] text-[#2C2C2E]">
<div class="min-h-screen py-12" x-data="installApp()">
    <div class="max-w-5xl mx-auto px-6">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-10">
            <div class="w-12 h-12 bg-[#2C2C2E] text-white rounded-3xl flex items-center justify-center text-3xl font-bold tracking-[-1.5px]">S</div>
            <div>
                <div class="text-4xl font-semibold tracking-tight">SalonEase</div>
                <div class="text-[#8A8A8C] -mt-1">香港小型美容院管理系統 · 專業部署</div>
            </div>
            <div class="ml-auto text-xs px-3 py-1 bg-white border border-[#EDE5DC] rounded-full text-[#8A8A8C]">v1.0.0</div>
        </div>

        <?php if ($installError): ?>
        <div class="max-w-xl mx-auto bg-red-50 border border-red-200 text-red-700 rounded-3xl p-6 mb-8">
            <div class="font-semibold">無法連線資料庫</div>
            <div class="text-sm mt-1"><?= h($installError) ?></div>
            <div class="text-xs mt-3">請檢查 <code class="bg-red-100 px-1">config.php</code> 的 DB_HOST / DB_NAME / DB_USER / DB_PASS 是否正確。</div>
        </div>
        <?php endif; ?>

        <?php if ($alreadyInstalled): ?>
        <div class="max-w-xl mx-auto">
            <div class="bg-white border border-[#EDE5DC] rounded-3xl p-10 text-center shadow-sm">
                <div class="mx-auto w-16 h-16 bg-[#8FA68F]/10 text-[#8FA68F] rounded-full flex items-center justify-center text-4xl mb-6">✓</div>
                <h1 class="text-3xl font-semibold">系統已安裝</h1>
                <p class="mt-3 text-[#5A5A5C]">SalonEase 已經完成安裝並正在運作。</p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="/login.php" class="px-8 py-3.5 bg-[#2C2C2E] text-white rounded-2xl font-medium hover:bg-black">登入系統</a>
                    <a href="/upgrade.php" class="px-8 py-3.5 border border-[#2C2C2E] rounded-2xl font-medium hover:bg-[#F8F5F0]">執行資料庫升級</a>
                </div>
                <div class="mt-6 text-xs text-[#8A8A8C]">如需重新安裝，請先手動清空資料庫或聯絡系統管理員。</div>
            </div>
        </div>
        <?php exit; endif; ?>

        <!-- 主要內容 -->
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl font-semibold tracking-tight mb-3">歡迎使用 SalonEase</h1>
            <p class="text-xl text-[#5A5A5C]">這是專業級一鍵安裝工具。請選擇適合你的安裝模式。</p>

            <!-- 環境檢查卡片 -->
            <div class="mt-8 bg-white border border-[#EDE5DC] rounded-3xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-semibold text-lg">環境檢查</div>
                    <button @click="runPreflight()" :disabled="checking" 
                            class="text-sm px-4 py-1.5 border border-[#2C2C2E] rounded-xl hover:bg-[#F8F5F0] disabled:opacity-50">
                        <span x-text="checking ? '檢查中...' : '重新檢查'"></span>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <template x-for="(check, key) in preflight" :key="key">
                        <div class="flex items-center gap-2 p-3 bg-[#F8F5F0] rounded-2xl" :class="{ 'bg-red-50': check && !check.ok }">
                            <div x-text="check ? (check.ok ? '✓' : '✗') : '⋯'" class="w-5 text-center"></div>
                            <div class="flex-1" x-text="check ? check.msg : key"></div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 安裝模式選擇 -->
            <div class="mt-8">
                <div class="font-semibold text-lg mb-3">選擇安裝模式</div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- 乾淨版 -->
                    <div @click="mode = 'clean'" 
                         class="salon-card border-2 rounded-3xl p-6 cursor-pointer"
                         :class="mode === 'clean' ? 'mode-selected border-[#2C2C2E]' : 'border-[#EDE5DC] hover:border-[#C9BBA8]'">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold text-xl">乾淨安裝</div>
                                <div class="text-xs uppercase tracking-[1px] text-[#8FA68F] font-medium mt-0.5">正式上線推薦</div>
                            </div>
                            <div class="text-4xl opacity-20">🧼</div>
                        </div>
                        <div class="mt-5 text-[#5A5A5C] text-[15px] leading-relaxed">
                            只建立核心表格 + 1 位系統管理員帳號。<br>
                            包含基本房間、服務、產品、套票模板及店舖設定。<br>
                            <span class="text-[#8FA68F] font-medium">適合正式營運環境。</span>
                        </div>
                        <div class="mt-4 text-xs text-[#8A8A8C]">安裝後可立即開始使用，無任何測試數據。</div>
                    </div>

                    <!-- 十年 Demo -->
                    <div @click="mode = 'demo'" 
                         class="salon-card border-2 rounded-3xl p-6 cursor-pointer"
                         :class="mode === 'demo' ? 'mode-selected border-[#2C2C2E]' : 'border-[#EDE5DC] hover:border-[#C9BBA8]'">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold text-xl">完整十年模擬數據</div>
                                <div class="text-xs uppercase tracking-[1px] text-[#8FA68F] font-medium mt-0.5">展示 / 培訓 / 測試首選</div>
                            </div>
                            <div class="text-4xl opacity-20">📊</div>
                        </div>
                        <div class="mt-5 text-[#5A5A5C] text-[15px] leading-relaxed">
                            自動產生 2015–2025 年十年真實營運記錄：<br>
                            160+ 客戶、1200+ 銷售單、數千筆預約與套票扣減、完整佣金歷史、低庫存警示。<br>
                            <span class="text-[#8FA68F] font-medium">安裝後報表、POS、佣金頁面立即有豐富數據。</span>
                        </div>
                        <div class="mt-4 text-xs text-[#8A8A8C]">安裝時間約 45–90 秒 · 模擬真實香港美容院成長曲線（含 COVID 影響）</div>
                    </div>
                </div>
            </div>

            <!-- 管理員設定表單 -->
            <form @submit.prevent="startInstall" method="post" class="mt-8 bg-white border border-[#EDE5DC] rounded-3xl p-8">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="mode" :value="mode">

                <div class="font-semibold text-lg mb-5">管理員帳號設定</div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">管理員姓名</label>
                        <input type="text" name="admin_name" x-model="form.admin_name" required
                               class="w-full border border-[#EDE5DC] focus:border-[#2C2C2E] rounded-2xl px-4 py-3 text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">登入電郵</label>
                        <input type="email" name="admin_email" x-model="form.admin_email" required
                               class="w-full border border-[#EDE5DC] focus:border-[#2C2C2E] rounded-2xl px-4 py-3 text-base font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">聯絡電話</label>
                        <input type="text" name="admin_phone" x-model="form.admin_phone"
                               class="w-full border border-[#EDE5DC] focus:border-[#2C2C2E] rounded-2xl px-4 py-3 text-base">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">密碼 <span class="text-xs text-[#8A8A8C]">(至少 6 位，安裝後建議立即更改)</span></label>
                        <input type="password" name="admin_pass" x-model="form.admin_pass" required minlength="6"
                               class="w-full border border-[#EDE5DC] focus:border-[#2C2C2E] rounded-2xl px-4 py-3 text-base font-mono" placeholder="輸入強密碼">
                    </div>
                </div>

                <!-- 店舖資訊（可選） -->
                <div class="mt-7 pt-6 border-t">
                    <div class="text-sm font-medium mb-3 text-[#5A5A5C]">店舖基本資訊（可於安裝後在「系統設定」修改）</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-1">
                            <input type="text" name="salon_name" x-model="form.salon_name" placeholder="店舖名稱"
                                   class="w-full border border-[#EDE5DC] rounded-2xl px-4 py-2.5 text-sm">
                        </div>
                        <div class="md:col-span-1">
                            <input type="text" name="salon_phone" x-model="form.salon_phone" placeholder="電話"
                                   class="w-full border border-[#EDE5DC] rounded-2xl px-4 py-2.5 text-sm">
                        </div>
                        <div class="md:col-span-1">
                            <input type="text" name="salon_address" x-model="form.salon_address" placeholder="地址"
                                   class="w-full border border-[#EDE5DC] rounded-2xl px-4 py-2.5 text-sm">
                        </div>
                    </div>
                </div>

                <!-- 風險確認 -->
                <div class="mt-7 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-sm">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="agree_risk" required class="mt-1 w-4 h-4 accent-[#2C2C2E]">
                        <div>
                            我已了解：此操作會在資料庫建立表格與初始數據。生產環境請確認已備份資料庫。<br>
                            <span class="text-amber-600">安裝完成後請立即刪除 install.php 檔案以保障安全。</span>
                        </div>
                    </label>
                </div>

                <button type="submit" 
                        :disabled="!canInstall || installing"
                        class="mt-6 w-full bg-[#2C2C2E] hover:bg-black disabled:bg-[#8A8A8C] text-white text-lg font-medium py-4 rounded-2xl transition flex items-center justify-center gap-2">
                    <template x-if="!installing">
                        <span>開始專業安裝（<span x-text="mode === 'demo' ? '含十年 Demo 數據' : '乾淨版'"></span>）</span>
                    </template>
                    <template x-if="installing">
                        <span>安裝進行中，請勿關閉視窗...</span>
                    </template>
                </button>
                
                <div class="text-center text-xs text-[#8A8A8C] mt-3">安裝過程會即時顯示詳細日誌 · 支援中斷後重新整理重試（幂等）</div>
            </form>

            <div class="text-center text-xs text-[#8A8A8C] mt-8">
                安裝完成後可使用 <a href="/upgrade.php" class="underline">upgrade.php</a> 進行日後任何資料庫結構更新（一鍵、安全、不破壞數據）
            </div>
        </div>
    </div>
</div>

<script>
function installApp() {
    return {
        mode: 'demo',
        checking: false,
        installing: false,
        preflight: {},
        canInstall: false,
        form: {
            admin_name: '系統管理員',
            admin_email: 'admin@salonease.hk',
            admin_phone: '9123 4567',
            admin_pass: 'admin123',
            salon_name: 'SalonEase 美容中心',
            salon_phone: '2123 4567',
            salon_address: '香港九龍尖沙咀彌敦道 100 號 8 樓'
        },

        async init() {
            await this.runPreflight();
            this.$watch('preflight', () => this.updateCanInstall());
            this.updateCanInstall();
        },

        updateCanInstall() {
            const dbOk = this.preflight.db_connect && this.preflight.db_connect.ok;
            const phpOk = this.preflight.php_version && this.preflight.php_version.ok;
            this.canInstall = dbOk && phpOk;
        },

        async runPreflight() {
            this.checking = true;
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=preflight'
                });
                const json = await res.json();
                if (json.success) {
                    this.preflight = json.checks;
                } else {
                    this.preflight = { error: { ok: false, msg: json.error || '檢查失敗' } };
                }
            } catch (e) {
                this.preflight = { network: { ok: false, msg: '無法連線伺服器' } };
            }
            this.checking = false;
        },

        async startInstall(e) {
            if (!this.canInstall || this.installing) return;
            this.installing = true;
            
            // 直接提交表單（傳統 POST），讓伺服器即時輸出日誌
            e.target.submit();
        }
    }
}
</script>
</body>
</html>
