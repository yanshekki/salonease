<?php
/**
 * SalonEase - 銷售 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'checkout':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $customer_id = (int)post('customer_id');
        $items = $_POST['items'] ?? [];
        $discount = (float)post('discount', 0);
        $payment_method = post('payment_method', 'cash');
        $amount_received = (float)post('amount_received', 0);
        $notes = sanitize_string(post('notes', ''));

        // Phase 1 驗證強化
        if ($err = validate_money($discount, '折扣')) json_error($err);
        if ($err = validate_length($notes, '備註', 500)) json_error($err);

        $allowedPayments = ['cash', 'card', 'fps', 'other'];
        if (!in_array($payment_method, $allowedPayments, true)) {
            json_error('付款方式無效');
        }

        if (empty($items) || !is_array($items)) {
            json_error('購物車不能為空');
        }

        // 驗證每個 item 基本結構
        foreach ($items as $idx => $item) {
            if (!isset($item['type'], $item['ref_id'], $item['name'], $item['unit_price'], $item['qty'])) {
                json_error("購物車第 " . ($idx+1) . " 項資料不完整");
            }
            if ($err = validate_positive_int($item['qty'], "第 " . ($idx+1) . " 項數量", 1)) json_error($err);
            if ($err = validate_money($item['unit_price'], "第 " . ($idx+1) . " 項單價")) json_error($err);
        }

        // Phase 2 A1：銷售前檢查產品庫存是否足夠
        foreach ($items as $item) {
            if ($item['type'] === 'product') {
                $currentStock = db_query_one("SELECT stock_qty FROM products WHERE id = ?", [(int)$item['ref_id']]);
                $available = (int)($currentStock['stock_qty'] ?? 0);
                $needed = (int)$item['qty'];
                if ($available < $needed) {
                    json_error("產品「{$item['name']}」庫存不足（僅剩 {$available} 件）");
                }
            }
        }

        try {
            $result = db_transaction(function($pdo) use ($customer_id, $items, $discount, $payment_method, $amount_received, $notes) {
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += (float)$item['unit_price'] * (int)$item['qty'];
                }
                $total = max(0, $subtotal - $discount);

                // 1. 建立銷售單
                $stmt = $pdo->prepare("
                    INSERT INTO sales (customer_id, staff_id, sale_date, subtotal, discount, total, payment_method, notes)
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $customer_id ?: null,
                    $_SESSION['staff_id'],
                    $subtotal,
                    $discount,
                    $total,
                    $payment_method,
                    $notes
                ]);
                $sale_id = $pdo->lastInsertId();

                // === A 選擇：讀取佣金率（全域預設 + 員工個人覆蓋） ===
                $globalRates = $pdo->query("SELECT default_commission_service, default_commission_retail, default_commission_open FROM settings WHERE id = 1")->fetch();
                $staffRates = $pdo->prepare("SELECT commission_rate_service, commission_rate_retail, commission_rate_open FROM staff WHERE id = ?");
                $staffRates->execute([$_SESSION['staff_id']]);
                $staffRateRow = $staffRates->fetch();

                $service_rate = $staffRateRow['commission_rate_service'] ?? $globalRates['default_commission_service'] ?? 40.00;
                $retail_rate  = $staffRateRow['commission_rate_retail']  ?? $globalRates['default_commission_retail']  ?? 15.00;
                $open_rate    = $staffRateRow['commission_rate_open']    ?? $globalRates['default_commission_open']    ?? 5.00;

                // 2. 插入銷售明細 + 處理套票扣減
                $item_stmt = $pdo->prepare("
                    INSERT INTO sale_items (sale_id, item_type, ref_id, name, qty, unit_price, line_total, staff_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $package_usage_stmt = $pdo->prepare("
                    INSERT INTO package_usages (customer_package_id, sale_id, sessions_used, staff_id)
                    VALUES (?, ?, ?, ?)
                ");

                $update_package_stmt = $pdo->prepare("
                    UPDATE customer_packages 
                    SET remaining_sessions = remaining_sessions - ? 
                    WHERE id = ? AND remaining_sessions >= ?
                ");

                // Phase 2 A1：產品庫存扣減
                $update_stock_stmt = $pdo->prepare("
                    UPDATE products 
                    SET stock_qty = stock_qty - ? 
                    WHERE id = ? AND stock_qty >= ?
                ");

                // 按員工分拆佣金的累計器（A 改善）
                $staff_commission = [];

                foreach ($items as $item) {
                    $line_total = (float)$item['unit_price'] * (int)$item['qty'];
                    $ref_id = (int)$item['ref_id'];

                    // 使用前端傳來的指派員工，否則預設為開單人
                    $item_staff_id = !empty($item['staff_id']) ? (int)$item['staff_id'] : $_SESSION['staff_id'];

                    $item_stmt->execute([
                        $sale_id,
                        $item['type'],
                        $ref_id,
                        $item['name'],
                        (int)$item['qty'],
                        (float)$item['unit_price'],
                        $line_total,
                        $item_staff_id
                    ]);

                    // 處理套票扣減
                    if ($item['type'] === 'package') {
                        // 這裡 ref_id 應該是 customer_packages.id
                        $sessions_used = (int)$item['qty'];

                        // 扣減剩餘次數
                        $update_package_stmt->execute([$sessions_used, $ref_id, $sessions_used]);

                        if ($update_package_stmt->rowCount() === 0) {
                            // 再查一次，看是次數不足還是已過期或不屬於該客戶
                            $check = $pdo->prepare("
                                SELECT cp.remaining_sessions, cp.expiry_date, cp.customer_id, p.name 
                                FROM customer_packages cp 
                                JOIN packages p ON cp.package_id = p.id 
                                WHERE cp.id = ?
                            ");
                            $check->execute([$ref_id]);
                            $pkgInfo = $check->fetch();

                            if (!$pkgInfo) {
                                throw new Exception("找不到該套票記錄");
                            }

                            if ($pkgInfo['customer_id'] != $customer_id) {
                                throw new Exception("此套票不屬於目前選擇的客戶");
                            }

                            if ($pkgInfo['remaining_sessions'] < $sessions_used) {
                                throw new Exception("套票「{$pkgInfo['name']}」剩餘次數不足（只剩 {$pkgInfo['remaining_sessions']} 次）");
                            }

                            if ($pkgInfo['expiry_date'] < date('Y-m-d')) {
                                throw new Exception("套票「{$pkgInfo['name']}」已於 {$pkgInfo['expiry_date']} 過期");
                            }

                            throw new Exception("套票扣減失敗，請確認套票狀態");
                        }

                        // 寫入使用記錄
                        $package_usage_stmt->execute([
                            $ref_id,
                            $sale_id,
                            $sessions_used,
                            $item_staff_id
                        ]);

                        log_activity('package.redeemed', $ref_id, 'customer_package', [
                            'sessions_used' => $sessions_used,
                            'sale_id' => $sale_id
                        ]);
                    }

                    // Phase 2 A1：扣減產品庫存
                    if ($item['type'] === 'product') {
                        $qty = (int)$item['qty'];
                        $update_stock_stmt->execute([$qty, $ref_id, $qty]);

                        if ($update_stock_stmt->rowCount() === 0) {
                            throw new Exception("產品「{$item['name']}」庫存不足或已被其他銷售扣減");
                        }

                        log_activity('product.stock_deducted', $ref_id, 'product', [
                            'qty' => $qty,
                            'sale_id' => $sale_id
                        ]);
                    }

                    // 按員工分拆佣金計算（使用設定頁的比率）
                    // 服務 / 零售佣金依 sale_items 儲存的 staff_id 分配
                    if ($item['type'] === 'service' || $item['type'] === 'product') {
                        $comm_staff = $item_staff_id;
                        if (!isset($staff_commission[$comm_staff])) {
                            $staff_commission[$comm_staff] = ['service' => 0, 'retail' => 0];
                        }
                        $rate = ($item['type'] === 'service') ? ($service_rate / 100) : ($retail_rate / 100);
                        $type_key = ($item['type'] === 'service') ? 'service' : 'retail';
                        $staff_commission[$comm_staff][$type_key] += $line_total * $rate;
                    }
                }

                // 3. 更新客戶統計
                if ($customer_id) {
                    $update_customer = $pdo->prepare("
                        UPDATE customers 
                        SET 
                            total_spent = total_spent + ?,
                            visit_count = visit_count + 1,
                            last_visit_at = NOW()
                        WHERE id = ?
                    ");
                    $update_customer->execute([$total, $customer_id]);

                    // Phase 2 A4：忠誠度積分累積（每 $10 = 1 點）
                    $pointsEarned = (int) floor($total / 10);
                    if ($pointsEarned > 0) {
                        $updatePoints = $pdo->prepare("
                            UPDATE customers SET points = points + ? WHERE id = ?
                        ");
                        $updatePoints->execute([$pointsEarned, $customer_id]);

                        log_activity('customer.points_earned', $customer_id, 'customer', [
                            'points' => $pointsEarned,
                            'sale_id' => $sale_id,
                            'spent' => $total
                        ]);
                    }
                }

                // 4. 產生佣金（使用設定頁的比率 + 按員工分拆）
                $commission_stmt = $pdo->prepare("
                    INSERT INTO commissions (sale_id, staff_id, amount, type, rate)
                    VALUES (?, ?, ?, ?, ?)
                ");

                // 開單佣金（固定給開單人）
                $open_commission = $total * ($open_rate / 100);
                if ($open_commission > 0) {
                    $commission_stmt->execute([
                        $sale_id,
                        $_SESSION['staff_id'],
                        $open_commission,
                        'open',
                        $open_rate
                    ]);
                }

                // 服務 / 零售佣金按實際執行員工分拆
                foreach ($staff_commission as $s_id => $comm) {
                    if ($comm['service'] > 0) {
                        $commission_stmt->execute([
                            $sale_id,
                            $s_id,
                            $comm['service'],
                            'service',
                            $service_rate
                        ]);
                    }
                    if ($comm['retail'] > 0) {
                        $commission_stmt->execute([
                            $sale_id,
                            $s_id,
                            $comm['retail'],
                            'retail',
                            $retail_rate
                        ]);
                    }
                }

                return [
                    'sale_id' => (int)$sale_id,
                    'total' => $total
                ];
            });

            // 記錄審計日誌
            log_activity('sale.created', $result['sale_id'], 'sale', [
                'total' => $result['total'],
                'payment_method' => $payment_method,
                'item_count' => count($items),
                'customer_id' => $customer_id ?: null
            ]);

            json_success(['id' => $result['sale_id']], '結帳成功');

        } catch (Exception $e) {
            json_error('結帳失敗：' . $e->getMessage());
        }
        break;

    case 'print_receipt':
        $id = (int)get('id');
        if (!$id) {
            die('缺少銷售單 ID');
        }

        $format = get('format', '58'); // 58（預設熱感紙）、80、a4
        $isA4 = ($format === 'a4');
        $is80 = ($format === '80');

        // 從 settings 表讀取店舖資訊（單一資料列 id=1）
        $shopRow = db_query_one("SELECT salon_name, address, phone FROM settings WHERE id = 1");
        $shop = [
            'name'    => $shopRow['salon_name'] ?? 'SalonEase 美容中心',
            'address' => $shopRow['address'] ?? '',
            'phone'   => $shopRow['phone'] ?? '',
            'footer'  => '專業 · 貼心 · 值得信賴'
        ];

        $sale = db_query_one("SELECT * FROM sales WHERE id = ?", [$id]);
        if (!$sale) {
            die('找不到該銷售單');
        }

        $items = db_query("SELECT * FROM sale_items WHERE sale_id = ?", [$id]);

        // 客戶資料
        $customer = null;
        if ($sale['customer_id']) {
            $customer = db_query_one("SELECT name, phone FROM customers WHERE id = ?", [$sale['customer_id']]);
        }

        // 開單員工
        $staff = db_query_one("SELECT name FROM staff WHERE id = ?", [$sale['staff_id']]);
        $staffName = $staff ? $staff['name'] : '員工';

        // 套票扣減記錄 + 扣減後剩餘次數
        $packageUsages = db_query("
            SELECT 
                pu.sessions_used,
                p.name as package_name,
                cp.remaining_sessions as remaining_after
            FROM package_usages pu
            JOIN customer_packages cp ON pu.customer_package_id = cp.id
            JOIN packages p ON cp.package_id = p.id
            WHERE pu.sale_id = ?
            ORDER BY pu.id
        ", [$id]);

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>收據 #<?= $id ?> - SalonEase</title>
            <link rel="stylesheet" href="/assets/css/print.css">
            <style>
                /* 收據專用覆蓋樣式（確保 thermal 窄版正常） */
                @media print {
                    .receipt-thermal, .receipt-a4 {
                        box-shadow: none;
                        border: none;
                    }
                }
                @media screen {
                    body { background: #f5f5f5; padding: 20px; }
                    .receipt-thermal { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
                    .receipt-a4 { box-shadow: 0 4px 20px rgba(0,0,0,.1); background: #fff; }
                }
            </style>
        </head>
        <body class="<?= $isA4 ? 'receipt-a4' : 'receipt-thermal' ?>">

        <?php if ($isA4): ?>
            <!-- ==================== A4 正式收據 / 合約版 ==================== -->
            <div class="receipt-a4" style="max-width: 210mm; margin: 0 auto; padding: 12mm 15mm; font-family: system-ui, 'Noto Sans TC', sans-serif; font-size: 11.5pt; line-height: 1.5; color: #222;">
                <div style="text-align: center; border-bottom: 3px solid #2C2C2E; padding-bottom: 8mm; margin-bottom: 8mm;">
                    <div style="font-size: 22pt; font-weight: 700; letter-spacing: 1px;"><?= e($shop['name']) ?></div>
                    <div style="margin-top: 2mm; font-size: 10pt; color: #555;"><?= e($shop['address']) ?>　Tel: <?= e($shop['phone']) ?></div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 6mm; font-size: 10.5pt;">
                    <div>
                        <div><strong>收據編號：</strong> #<?= $id ?></div>
                        <div><strong>結帳日期：</strong> <?= $sale['sale_date'] ?> <?= date('H:i') ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div><strong>付款方式：</strong> <?= strtoupper($sale['payment_method']) ?></div>
                        <div><strong>服務員工：</strong> <?= e($staffName) ?></div>
                    </div>
                </div>

                <?php if ($customer): ?>
                <div style="background: #f8f8f8; padding: 3mm 4mm; margin-bottom: 5mm; border-radius: 2mm;">
                    <strong>客戶：</strong> <?= e($customer['name']) ?>　　<?= e($customer['phone']) ?>
                </div>
                <?php endif; ?>

                <table style="width:100%; border-collapse: collapse; margin-bottom: 6mm;">
                    <thead>
                        <tr style="border-bottom: 2px solid #2C2C2E;">
                            <th style="text-align:left; padding:2mm 3mm;">項目</th>
                            <th style="text-align:center; padding:2mm 3mm; width:18%;">數量</th>
                            <th style="text-align:right; padding:2mm 3mm; width:22%;">單價</th>
                            <th style="text-align:right; padding:2mm 3mm; width:22%;">小計</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php if ($item['item_type'] === 'package'): ?>
                            <tr style="background:#f0f0f0; font-weight:600;">
                                <td style="padding:3mm 3mm;" colspan="4">
                                    【套票扣減】<?= e($item['name']) ?><br>
                                    <span style="font-size:9.5pt; font-weight:400; color:#555;">扣除 <?= (int)$item['qty'] ?> 次服務</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td style="padding:2mm 3mm; border-bottom:1px solid #ddd;"><?= e($item['name']) ?></td>
                                <td style="padding:2mm 3mm; text-align:center; border-bottom:1px solid #ddd;"><?= (int)$item['qty'] ?></td>
                                <td style="padding:2mm 3mm; text-align:right; border-bottom:1px solid #ddd;">HK$ <?= number_format($item['unit_price'], 0) ?></td>
                                <td style="padding:2mm 3mm; text-align:right; border-bottom:1px solid #ddd;">HK$ <?= number_format($item['line_total'], 0) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($packageUsages)): ?>
                <div style="margin: 4mm 0; padding: 3mm 4mm; background:#f8f5f0; border-left: 4px solid #8FA68F;">
                    <div style="font-weight:600; margin-bottom:2mm;">套票扣減明細</div>
                    <?php foreach ($packageUsages as $pu): ?>
                        <div style="font-size:10pt; margin:1mm 0;">
                            • <?= e($pu['package_name']) ?>　扣 <?= (int)$pu['sessions_used'] ?> 次　<span style="color:#8FA68F;">（扣後剩餘 <?= (int)$pu['remaining_after'] ?> 次）</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top: 8mm; text-align:right; font-size: 11pt;">
                    <div>小計：HK$ <?= number_format($sale['subtotal'], 2) ?></div>
                    <?php if ($sale['discount'] > 0): ?>
                    <div>折扣：-HK$ <?= number_format($sale['discount'], 2) ?></div>
                    <?php endif; ?>
                    <div style="font-size:13pt; font-weight:700; margin-top:2mm; border-top:1px solid #2C2C2E; padding-top:2mm;">
                        總計：HK$ <?= number_format($sale['total'], 2) ?>
                    </div>
                </div>

                <div style="margin-top: 12mm; display:flex; gap: 20mm;">
                    <div>
                        <div style="width: 55mm; border-top: 1px solid #888; padding-top: 2mm; font-size: 9.5pt;">客戶簽名</div>
                    </div>
                    <div>
                        <div style="width: 55mm; border-top: 1px solid #888; padding-top: 2mm; font-size: 9.5pt;">服務確認</div>
                    </div>
                </div>

                <div style="margin-top: 6mm; font-size: 8.5pt; text-align: center; color: #777;">
                    此為客戶副本　店舖存根請保留備查
                </div>

                <div style="margin-top: 10mm; text-align: center; font-size: 9.5pt; color: #666;">
                    感謝惠顧！如有任何疑問，歡迎隨時聯絡我們。<br>
                    <?= e($shop['name']) ?> · <?= e($shop['footer']) ?>
                </div>
            </div>

        <?php else: ?>
            <!-- ==================== 熱感紙收據（58mm / 80mm） ==================== -->
            <div class="receipt-thermal <?= $is80 ? 'receipt-thermal-80' : '' ?>" style="margin: 0 auto; width: <?= $is80 ? '80mm' : '58mm' ?>; max-width: <?= $is80 ? '80mm' : '58mm' ?>; font-family: 'Courier New', monospace; font-size: <?= $is80 ? '12px' : '10.5px' ?>; line-height: 1.32; color: #000; padding: <?= $is80 ? '4mm 3mm' : '3mm 2.5mm 5mm' ?>;">
                <div style="text-align:center; margin-bottom: 2mm;">
                    <div style="font-size: <?= $is80 ? '14px' : '13px' ?>; font-weight:700;"><?= e($shop['name']) ?></div>
                    <div style="font-size:8.5px; margin-top:1px;"><?= e($shop['address']) ?></div>
                    <div style="font-size:8.5px;">Tel: <?= e($shop['phone']) ?>　　收據 #<?= $id ?></div>
                    <div style="font-size:7.5px; color:#888;">打印機設定：<?= $shopRow['printer_width'] ?? '58' ?>mm</div>
                </div>

                <div style="border-top:1px dashed #333; margin:2mm 0;"></div>

                <div style="font-size:9.5px; margin-bottom:2mm;">
                    日期：<?= $sale['sale_date'] ?>　　員工：<?= e($staffName) ?><br>
                    <?php if ($customer): ?>客戶：<?= e($customer['name']) ?>（<?= e($customer['phone']) ?>）<?php endif; ?>
                </div>

                <table style="width:100%; border-collapse:collapse; font-size:inherit;">
                    <?php foreach ($items as $item): ?>
                        <?php if ($item['item_type'] === 'package'): ?>
                            <tr>
                                <td colspan="2" style="padding:1.5mm 0; font-weight:700; background:#f2f2f2;">
                                    【套票扣減】<?= e($item['name']) ?><br>
                                    <span style="font-size:9px; font-weight:400;">扣 <?= (int)$item['qty'] ?> 次</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td style="padding:0.6mm 0; width:58%;"><?= e($item['name']) ?></td>
                                <td style="padding:0.6mm 0; text-align:right; width:42%;"><?= (int)$item['qty'] ?>×<?= number_format($item['unit_price'],0) ?> = <?= number_format($item['line_total'],0) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>

                <?php if (!empty($packageUsages)): ?>
                <div style="margin:2mm 0; padding:1.5mm 2mm; background:#f8f5f0; font-size:9px; border-left:3px solid #8FA68F;">
                    <?php foreach ($packageUsages as $pu): ?>
                        套票：<?= e($pu['package_name']) ?>　扣<?= (int)$pu['sessions_used'] ?>次　剩<?= (int)$pu['remaining_after'] ?>次<br>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="border-top:1px dashed #333; margin:2mm 0;"></div>

                <div style="text-align:right; font-size:<?= $is80 ? '12px' : '10.5px' ?>;">
                    <?php if ($sale['discount'] > 0): ?>
                    小計：<?= number_format($sale['subtotal'], 2) ?><br>
                    折扣：-<?= number_format($sale['discount'], 2) ?><br>
                    <?php endif; ?>
                    <span style="font-weight:700;">總計 HK$ <?= number_format($sale['total'], 2) ?></span>
                </div>

                <div style="border-top:1px dashed #333; margin:2.5mm 0;"></div>

                <div style="text-align:center; font-size:9px; line-height:1.4;">
                    感謝惠顧！<br>
                    付款方式：<?= strtoupper($sale['payment_method']) ?><br>
                    <span style="font-size:8px;"><?= e($shop['footer']) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <script>
            window.onload = () => {
                // 熱感紙默認自動喚起打印（方便日常操作）
                <?php if (!$isA4): ?>
                setTimeout(() => {
                    window.print();
                    // 打印後可選自動關閉視窗（部分瀏覽器）
                    // window.close();
                }, 450);
                <?php else: ?>
                // A4 版給用戶手動按 Ctrl+P 或使用瀏覽器打印按鈕
                console.log('%c[A4收據] 已就緒，請按 Ctrl+P 打印', 'color:#8FA68F');
                <?php endif; ?>
            };
        </script>
        </body>
        </html>
        <?php
        break;

    case 'list':
        // 給命令面板用：列出最近銷售單（可按客戶過濾）
        $limit = min(20, max(5, (int)get('limit', 10)));
        $customer_id = (int)get('customer_id', 0);

        $sql = "
            SELECT s.id, s.sale_date, s.total, s.payment_method,
                   c.name as customer_name, c.phone as customer_phone,
                   st.name as staff_name,
                   (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN staff st ON s.staff_id = st.id
            WHERE 1=1
        ";
        $params = [];

        if ($customer_id > 0) {
            $sql .= " AND s.customer_id = ?";
            $params[] = $customer_id;
        }

        $sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT ?";
        $params[] = $limit;

        $sales = db_query($sql, $params);
        json_success($sales);
        break;

    case 'get_items':
        $sale_id = (int)get('sale_id', 0);
        if (!$sale_id) json_error('缺少 sale_id');

        $items = db_query("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id", [$sale_id]);
        json_success($items);
        break;

    default:
        json_error('未知的操作', 400);
}
