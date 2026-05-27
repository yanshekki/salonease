<?php
/**
 * SalonEase - 銷售 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'checkout':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $customer_id = (int)post('customer_id');
        $items = $_POST['items'] ?? [];
        $discount = (float)post('discount', 0);
        $payment_method = post('payment_method', 'cash');
        $amount_received = (float)post('amount_received', 0);
        $notes = trim(post('notes'));

        if (empty($items)) {
            json_error('購物車不能為空');
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

                $total_commission_service = 0;
                $total_commission_retail = 0;

                foreach ($items as $item) {
                    $line_total = (float)$item['unit_price'] * (int)$item['qty'];
                    $ref_id = (int)$item['ref_id'];

                    // 預設執行人員為開單人
                    $item_staff_id = $_SESSION['staff_id'];

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
                    }

                    // 簡單佣金計算（之後可優化）
                    // 這裡先用 sales staff 計算，之後可支援 item 層級 staff
                    if ($item['type'] === 'service') {
                        $total_commission_service += $line_total * 0.4; // 暫時用 40%
                    } elseif ($item['type'] === 'product') {
                        $total_commission_retail += $line_total * 0.15; // 暫時用 15%
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
                }

                // 4. 產生佣金（簡單版本）
                $commission_stmt = $pdo->prepare("
                    INSERT INTO commissions (sale_id, staff_id, amount, type, rate)
                    VALUES (?, ?, ?, ?, ?)
                ");

                // 開單佣金（5%）
                $open_commission = $total * 0.05;
                if ($open_commission > 0) {
                    $commission_stmt->execute([
                        $sale_id,
                        $_SESSION['staff_id'],
                        $open_commission,
                        'open',
                        5.00
                    ]);
                }

                // 服務佣金（暫時寫給開單人，之後可改）
                if ($total_commission_service > 0) {
                    $commission_stmt->execute([
                        $sale_id,
                        $_SESSION['staff_id'],
                        $total_commission_service,
                        'service',
                        40.00
                    ]);
                }

                // 零售佣金
                if ($total_commission_retail > 0) {
                    $commission_stmt->execute([
                        $sale_id,
                        $_SESSION['staff_id'],
                        $total_commission_retail,
                        'retail',
                        15.00
                    ]);
                }

                return [
                    'sale_id' => (int)$sale_id,
                    'total' => $total
                ];
            });

            json_success(['id' => $result['sale_id']], '結帳成功');

        } catch (Exception $e) {
            json_error('結帳失敗：' . $e->getMessage());
        }
        break;

        } catch (Exception $e) {
            json_error('結帳失敗：' . $e->getMessage());
        }
        break;

    case 'print_receipt':
        $id = (int)get('id');
        if (!$id) {
            die('缺少銷售單 ID');
        }

        // 簡單打印頁面（之後可改為更專業的模板）
        $sale = db_query_one("SELECT * FROM sales WHERE id = ?", [$id]);
        if (!$sale) {
            die('找不到該銷售單');
        }

        $items = db_query("SELECT * FROM sale_items WHERE sale_id = ?", [$id]);

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>收據 #<?= $id ?></title>
            <style>
                body { font-family: "Courier New", monospace; font-size: 14px; width: 280px; margin: 0 auto; }
                .center { text-align: center; }
                .right { text-align: right; }
                .line { border-top: 1px dashed #000; margin: 8px 0; }
                table { width: 100%; border-collapse: collapse; }
                td { padding: 2px 0; }
            </style>
        </head>
        <body>
            <div class="center">
                <h2>SalonEase 美容中心</h2>
                <p>收據 #<?= $id ?></p>
                <p><?= $sale['sale_date'] ?></p>
            </div>
            <div class="line"></div>

            <table>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td>
                    <td class="right"><?= $item['qty'] ?> × <?= number_format($item['unit_price'], 0) ?></td>
                    <td class="right"><?= number_format($item['line_total'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div class="line"></div>

            <table>
                <tr><td>小計</td><td class="right"><?= number_format($sale['subtotal'], 2) ?></td></tr>
                <tr><td>折扣</td><td class="right">-<?= number_format($sale['discount'], 2) ?></td></tr>
                <tr><td><strong>總計</strong></td><td class="right"><strong><?= number_format($sale['total'], 2) ?></strong></td></tr>
            </table>

            <div class="line"></div>
            <p class="center">感謝惠顧！</p>

            <script>
                window.onload = () => {
                    // 自動打印（可選）
                    // window.print();
                };
            </script>
        </body>
        </html>
        <?php
        break;

    default:
        json_error('未知的操作', 400);
}
