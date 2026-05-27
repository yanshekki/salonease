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
            $sale_id = db_transaction(function($pdo) use ($customer_id, $items, $discount, $payment_method, $amount_received, $notes) {
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += (float)$item['unit_price'] * (int)$item['qty'];
                }
                $total = max(0, $subtotal - $discount);

                // 建立銷售單
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

                // 插入銷售明細
                $item_stmt = $pdo->prepare("
                    INSERT INTO sale_items (sale_id, item_type, ref_id, name, qty, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($items as $item) {
                    $line_total = (float)$item['unit_price'] * (int)$item['qty'];
                    $item_stmt->execute([
                        $sale_id,
                        $item['type'],
                        $item['ref_id'],
                        $item['name'],
                        (int)$item['qty'],
                        (float)$item['unit_price'],
                        $line_total
                    ]);
                }

                // TODO: 處理套票扣減、更新客戶消費統計、產生佣金等
                // 這些會在後續逐步完善

                return (int)$sale_id;
            });

            json_success(['id' => $sale_id], '結帳成功');

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
