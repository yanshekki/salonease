<?php
/**
 * SalonEase - 付款記錄 API（Phase 2）
 *
 * GET  /api/payments.php?action=list_by_sale&sale_id=xxx
 * POST /api/payments.php?action=record
 * POST /api/payments.php?action=refund
 *
 * 所有操作需登入，寫入操作需 admin/manager 或開單人（視業務而定）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

/**
 * 計算手續費（可重用 payment_methods 的邏輯）
 */
function calculate_payment_fee(int $paymentMethodId, float $baseAmount): array
{
    $method = db_query_one("SELECT * FROM payment_methods WHERE id = ?", [$paymentMethodId]);
    if (!$method || !$method['is_active']) {
        return ['fee' => 0, 'error' => '付款方法無效或已停用'];
    }

    $model = $method['fee_model'];
    $fixed = (float)$method['fee_fixed'];
    $percent = (float)$method['fee_percent'];

    $fee = 0;
    if ($model === 'fixed') {
        $fee = $fixed;
    } elseif ($model === 'percent') {
        $fee = round($baseAmount * $percent / 100, 2);
    } elseif ($model === 'fixed_plus_percent') {
        $fee = round($fixed + ($baseAmount * $percent / 100), 2);
    }

    return ['fee' => max(0, $fee)];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list_by_sale':
        $saleId = (int)get('sale_id');
        if ($saleId <= 0) {
            json_error('缺少 sale_id');
        }

        $payments = db_query("
            SELECT p.*, pm.name as payment_method_name, pm.code as payment_method_code, s.name as staff_name
            FROM payments p
            JOIN payment_methods pm ON p.payment_method_id = pm.id
            JOIN staff s ON p.staff_id = s.id
            WHERE p.sale_id = ?
            ORDER BY p.paid_at ASC, p.id ASC
        ", [$saleId]);

        json_success($payments);
        break;

    case 'record':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $saleId = (int)post('sale_id');
        $paymentMethodId = (int)post('payment_method_id');
        $amount = (float)post('amount');
        $feeAmount = post('fee_amount') !== '' ? (float)post('fee_amount') : null;
        $feeBorneBy = post('fee_borne_by', 'merchant');
        $refNumber = sanitize_string(post('ref_number', ''), 120);
        $notes = sanitize_string(post('notes', ''), 500);
        $installmentNo = post('installment_no') ? (int)post('installment_no') : null;
        $planId = post('plan_id') ? (int)post('plan_id') : null;

        if ($saleId <= 0) json_error('缺少 sale_id');
        if ($paymentMethodId <= 0) json_error('缺少付款方法');
        if ($amount <= 0) json_error('付款金額必須大於 0');

        // 驗證銷售單
        $sale = db_query_one("SELECT id, total, amount_paid, payment_status FROM sales WHERE id = ?", [$saleId]);
        if (!$sale) json_error('銷售單不存在');

        // 計算建議手續費（若前端未傳）
        if ($feeAmount === null) {
            $calc = calculate_payment_fee($paymentMethodId, $amount);
            $feeAmount = $calc['fee'] ?? 0;
        }

        if (!in_array($feeBorneBy, ['merchant', 'customer'])) {
            $feeBorneBy = 'merchant';
        }

        try {
            $result = db_transaction(function($pdo) use ($saleId, $paymentMethodId, $amount, $feeAmount, $feeBorneBy, $refNumber, $notes, $installmentNo, $sale) {
                // 插入付款記錄
                $pdo->prepare("
                    INSERT INTO payments (sale_id, payment_method_id, amount, fee_amount, fee_borne_by, paid_at, staff_id, ref_number, notes, installment_no, plan_id)
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
                ")->execute([
                    $saleId,
                    $paymentMethodId,
                    $amount,
                    $feeAmount,
                    $feeBorneBy,
                    $_SESSION['staff_id'],
                    $refNumber ?: null,
                    $notes ?: null,
                    $installmentNo,
                    $planId
                ]);

                $paymentId = (int)$pdo->lastInsertId();

                // 更新 sales 的 amount_paid 和 payment_status
                $newAmountPaid = (float)$sale['amount_paid'] + $amount;
                $total = (float)$sale['total'];

                $newStatus = 'partial';
                if ($newAmountPaid >= $total) {
                    $newStatus = ($newAmountPaid > $total) ? 'overpaid' : 'paid';
                }

                $pdo->prepare("
                    UPDATE sales 
                    SET amount_paid = ?, payment_status = ?, primary_payment_method_id = ?
                    WHERE id = ?
                ")->execute([$newAmountPaid, $newStatus, $paymentMethodId, $saleId]);

                log_activity('payment.recorded', $paymentId, 'payment', [
                    'sale_id' => $saleId,
                    'amount' => $amount,
                    'payment_method_id' => $paymentMethodId,
                    'plan_id' => $planId,
                    'installment_no' => $installmentNo
                ]);

                return [
                    'payment_id' => $paymentId,
                    'new_amount_paid' => $newAmountPaid,
                    'new_payment_status' => $newStatus,
                    'plan_id' => $planId,
                    'installment_no' => $installmentNo
                ];
            });

            json_success($result, '付款記錄成功');

        } catch (Throwable $e) {
            json_error('記錄付款失敗：' . $e->getMessage());
        }
        break;

    case 'refund':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        // 只有 admin / manager 可以退款
        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以執行退款', 403);
        }

        $paymentId = (int)post('payment_id');
        $refundAmount = (float)post('amount');
        $notes = sanitize_string(post('notes', ''), 500);

        if ($paymentId <= 0 || $refundAmount <= 0) {
            json_error('參數錯誤');
        }

        try {
            $result = db_transaction(function($pdo) use ($paymentId, $refundAmount, $notes) {
                $originalPayment = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE")->execute([$paymentId]);
                $originalPayment = $pdo->query("SELECT * FROM payments WHERE id = $paymentId")->fetch();

                if (!$originalPayment) {
                    throw new Exception('原付款記錄不存在');
                }
                if ($originalPayment['is_refund']) {
                    throw new Exception('此筆為退款記錄，無法再次退款');
                }

                // 建立退款記錄（負數或 is_refund=1）
                $pdo->prepare("
                    INSERT INTO payments (sale_id, payment_method_id, amount, fee_amount, fee_borne_by, paid_at, staff_id, notes, is_refund, refund_of_payment_id)
                    VALUES (?, ?, ?, 0, 'merchant', NOW(), ?, ?, 1, ?)
                ")->execute([
                    $originalPayment['sale_id'],
                    $originalPayment['payment_method_id'],
                    -$refundAmount,
                    $_SESSION['staff_id'],
                    $notes ?: '退款',
                    $paymentId
                ]);

                $refundId = (int)$pdo->lastInsertId();

                // 更新 sales amount_paid
                $pdo->prepare("
                    UPDATE sales 
                    SET amount_paid = GREATEST(0, amount_paid - ?), 
                        payment_status = CASE 
                            WHEN amount_paid - ? >= total THEN 'paid'
                            WHEN amount_paid - ? > 0 THEN 'partial'
                            ELSE 'unpaid'
                        END
                    WHERE id = ?
                ")->execute([$refundAmount, $refundAmount, $refundAmount, $originalPayment['sale_id']]);

                log_activity('payment.refunded', $refundId, 'payment', [
                    'original_payment_id' => $paymentId,
                    'refund_amount' => $refundAmount
                ]);

                return ['refund_id' => $refundId];
            });

            json_success($result, '退款成功');

        } catch (Throwable $e) {
            json_error('退款失敗：' . $e->getMessage());
        }
        break;

    // Phase 8: 客戶自助 Portal 記錄付款（token 保護）
    case 'record_portal':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $portalToken = trim(post('token', ''));
        $planId = (int)post('plan_id');
        $paymentMethodId = (int)post('payment_method_id');
        $amount = (float)post('amount');
        $notes = sanitize_string(post('notes', ''), 500);

        if (empty($portalToken)) json_error('缺少存取 token', 401);
        if ($planId <= 0) json_error('缺少計劃 ID');
        if ($amount <= 0) json_error('付款金額必須大於 0');

        $portalCustomer = validateCustomerPortalToken($portalToken);
        if (!$portalCustomer) {
            json_error('Portal 存取連結已失效或無效', 401);
        }

        // 驗證計劃屬於該客戶
        $plan = db_query_one("
            SELECT spp.id, spp.sale_id, s.customer_id, spp.installment_amount
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
            WHERE spp.id = ? AND s.customer_id = ?
        ", [$planId, $portalCustomer['customer_id']]);

        if (!$plan) {
            json_error('找不到該計劃或無權限');
        }

        $saleId = (int)$plan['sale_id'];

        // 如果沒選付款方式，用第一個活躍的（現金或預設）
        if ($paymentMethodId <= 0) {
            $defaultMethod = db_query_one("SELECT id FROM payment_methods WHERE is_active = 1 ORDER BY id LIMIT 1");
            $paymentMethodId = $defaultMethod ? (int)$defaultMethod['id'] : 1;
        }

        // 驗證銷售單
        $sale = db_query_one("SELECT id, total, amount_paid, payment_status FROM sales WHERE id = ?", [$saleId]);
        if (!$sale) json_error('銷售單不存在');

        try {
            $result = db_transaction(function($pdo) use ($saleId, $paymentMethodId, $amount, $notes, $planId, $portalCustomer) {
                // 插入付款記錄（staff_id 留空或用 0 表示 Portal 自助）
                $pdo->prepare("
                    INSERT INTO payments (sale_id, payment_method_id, amount, fee_amount, fee_borne_by, paid_at, staff_id, ref_number, notes, plan_id)
                    VALUES (?, ?, ?, 0, 'merchant', NOW(), NULL, ?, ?, ?)
                ")->execute([
                    $saleId,
                    $paymentMethodId,
                    $amount,
                    'Portal-' . date('YmdHis'),
                    $notes ?: '客戶自助 Portal 記錄',
                    $planId
                ]);

                $paymentId = (int)$pdo->lastInsertId();

                // 更新 sales
                $saleInfo = $pdo->query("SELECT amount_paid, total FROM sales WHERE id = $saleId FOR UPDATE")->fetch();
                $newAmountPaid = (float)$saleInfo['amount_paid'] + $amount;
                $total = (float)$saleInfo['total'];

                $newStatus = 'partial';
                if ($newAmountPaid >= $total) {
                    $newStatus = ($newAmountPaid > $total) ? 'overpaid' : 'paid';
                }

                $pdo->prepare("
                    UPDATE sales 
                    SET amount_paid = ?, payment_status = ?, primary_payment_method_id = ?
                    WHERE id = ?
                ")->execute([$newAmountPaid, $newStatus, $paymentMethodId, $saleId]);

                log_activity('payment.recorded_via_portal', $paymentId, 'payment', [
                    'sale_id' => $saleId,
                    'plan_id' => $planId,
                    'amount' => $amount,
                    'customer_id' => $portalCustomer['customer_id'],
                    'via' => 'customer_portal'
                ]);

                return [
                    'payment_id' => $paymentId,
                    'new_amount_paid' => $newAmountPaid,
                    'new_payment_status' => $newStatus
                ];
            });

            json_success($result, '付款已成功記錄，感謝！');

        } catch (Throwable $e) {
            json_error('記錄付款失敗：' . $e->getMessage());
        }
        break;

    default:
        json_error('未知的操作', 400);
}
