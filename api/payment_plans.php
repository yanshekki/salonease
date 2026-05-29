<?php
/**
 * SalonEase - 分期/周期性付款計劃 API（Phase 3）
 *
 * GET  /api/payment_plans.php?action=list_by_sale&sale_id=xxx
 * GET  /api/payment_plans.php?action=get&id=xxx
 * POST /api/payment_plans.php?action=create
 * POST /api/payment_plans.php?action=update_status
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'summary':
        // Plan Management UI - 頂部統計卡片
        $summary = db_query_one("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM sale_payment_plans
        ");
        json_success([
            'total' => (int)($summary['total'] ?? 0),
            'active' => (int)($summary['active'] ?? 0),
            'completed' => (int)($summary['completed'] ?? 0),
            'cancelled' => (int)($summary['cancelled'] ?? 0)
        ]);
        break;

    case 'dashboard':
        // 計劃管理頁專用 - 加強管理視野
        $dashboard = db_query_one("
            SELECT 
                COUNT(*) as total_plans,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_plans,
                COUNT(DISTINCT CASE WHEN status = 'active' THEN s.customer_id END) as customers_with_active,
                SUM(CASE WHEN status = 'active' THEN spp.installment_amount * spp.total_installments ELSE 0 END) as active_total_value,
                SUM(CASE WHEN status = 'active' THEN 
                    (SELECT COALESCE(SUM(CASE WHEN is_refund=0 THEN amount ELSE 0 END), 0) 
                     FROM payments WHERE plan_id = spp.id) 
                ELSE 0 END) as active_collected_value
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
        ");

        // 需要關注：活躍但進度低（已付期數 < 30% 且計劃已存在超過45天）
        $needsAttention = db_query_one("
            SELECT COUNT(*) as count
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
            LEFT JOIN (
                SELECT plan_id, COUNT(*) as paid_count 
                FROM payments 
                WHERE is_refund = 0 
                GROUP BY plan_id
            ) p ON p.plan_id = spp.id
            WHERE spp.status = 'active'
              AND (p.paid_count IS NULL OR (p.paid_count * 1.0 / spp.total_installments) < 0.3)
              AND DATEDIFF(CURDATE(), spp.created_at) > 45
        ");

        json_success([
            'active_plans' => (int)($dashboard['active_plans'] ?? 0),
            'customers_with_active' => (int)($dashboard['customers_with_active'] ?? 0),
            'active_total_value' => (float)($dashboard['active_total_value'] ?? 0),
            'active_collected_value' => (float)($dashboard['active_collected_value'] ?? 0),
            'needs_attention' => (int)($needsAttention['count'] ?? 0),
            'upcoming_30_days' => calculateUpcomingCollections(30),
            'oldest_active' => getOldestActivePlanInfo(),
            'most_concerning_customer' => getMostConcerningCustomer()
        ]);
        break;

    case 'list':
        // Phase 3 Plan Management UI - 管理列表（支援搜尋 + 篩選）
        $status = trim(get('status', ''));
        $planType = trim(get('plan_type', ''));
        $search = trim(get('search', ''));
        $customerId = (int)get('customer_id', 0);

        $sql = "
            SELECT 
                spp.*,
                s.id as sale_id,
                s.total as sale_total,
                s.payment_status,
                c.id as customer_id,
                c.name as customer_name,
                c.phone as customer_phone,
                (SELECT COUNT(*) FROM payments WHERE plan_id = spp.id AND is_refund = 0) as payments_made
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
            JOIN customers c ON s.customer_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if ($status !== '' && in_array($status, ['active','completed','cancelled'])) {
            $sql .= " AND spp.status = ?";
            $params[] = $status;
        }
        if ($planType !== '' && in_array($planType, ['installment','recurring'])) {
            $sql .= " AND spp.plan_type = ?";
            $params[] = $planType;
        }
        if ($customerId > 0) {
            $sql .= " AND c.id = ?";
            $params[] = $customerId;
        }
        if ($search !== '') {
            $like = "%{$search}%";
            $sql .= " AND (c.phone LIKE ? OR c.name LIKE ? OR s.id = ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = (int)$search;  // 允許直接搜銷售單號
        }

        $sql .= " ORDER BY spp.created_at DESC LIMIT 150";

        $plans = db_query($sql, $params);
        json_success($plans);
        break;

    case 'list_by_sale':
        $saleId = (int)get('sale_id');
        if ($saleId <= 0) json_error('缺少 sale_id');

        $plans = db_query("
            SELECT 
                spp.*,
                s.total as sale_total,
                s.amount_paid as sale_amount_paid,
                s.payment_status,
                (SELECT COUNT(*) FROM payments WHERE plan_id = spp.id AND is_refund = 0) as payments_made
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
            WHERE spp.sale_id = ?
            ORDER BY spp.created_at DESC
        ", [$saleId]);

        json_success($plans);
        break;

    case 'get':
        $id = (int)get('id');
        if ($id <= 0) json_error('缺少 plan id');

        $plan = db_query_one("
            SELECT 
                spp.*,
                s.total as sale_total,
                s.amount_paid as sale_amount_paid
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
            WHERE spp.id = ?
        ", [$id]);

        if (!$plan) json_error('找不到該分期計劃');

        // 取得已付款記錄
        $payments = db_query("
            SELECT p.*, pm.name as payment_method_name
            FROM payments p
            JOIN payment_methods pm ON p.payment_method_id = pm.id
            WHERE p.plan_id = ? AND p.is_refund = 0
            ORDER BY p.installment_no ASC, p.paid_at ASC
        ", [$id]);

        $plan['payments'] = $payments;

        json_success($plan);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $saleId = (int)post('sale_id');
        $planType = post('plan_type', 'installment');
        $totalInstallments = (int)post('total_installments', 1);
        $installmentAmount = (float)post('installment_amount');
        $frequency = post('frequency');
        $startDate = post('start_date');
        $notes = sanitize_string(post('notes', ''), 500);

        if ($saleId <= 0) json_error('缺少銷售單');
        if (!in_array($planType, ['installment', 'recurring'])) json_error('plan_type 無效');
        if ($totalInstallments < 1) json_error('總期數必須大於 0');
        if ($installmentAmount <= 0) json_error('每期金額必須大於 0');
        if (!$startDate) json_error('缺少開始日期');

        // 檢查銷售單是否存在
        $sale = db_query_one("SELECT id, total FROM sales WHERE id = ?", [$saleId]);
        if (!$sale) json_error('銷售單不存在');

        // 簡單驗證：分期總金額不應超過銷售總額太多（容許小誤差）
        $expectedTotal = $installmentAmount * $totalInstallments;
        if ($expectedTotal > ($sale['total'] * 1.05)) {
            json_error('分期總金額明顯超過銷售總額，請檢查');
        }

        try {
            $planId = db_transaction(function($pdo) use ($saleId, $planType, $totalInstallments, $installmentAmount, $frequency, $startDate, $notes) {
                $pdo->prepare("
                    INSERT INTO sale_payment_plans 
                    (sale_id, plan_type, total_installments, installment_amount, frequency, start_date, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $saleId,
                    $planType,
                    $totalInstallments,
                    $installmentAmount,
                    $frequency ?: null,
                    $startDate,
                    $notes ?: null,
                    $_SESSION['staff_id']
                ]);

                $newPlanId = (int)$pdo->lastInsertId();

                log_activity('payment_plan.created', $newPlanId, 'sale_payment_plan', [
                    'sale_id' => $saleId,
                    'plan_type' => $planType,
                    'total_installments' => $totalInstallments
                ]);

                return $newPlanId;
            });

            json_success(['plan_id' => $planId], '分期計劃建立成功');

        } catch (Throwable $e) {
            json_error('建立分期計劃失敗：' . $e->getMessage());
        }
        break;

    case 'update_status':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以更改計劃狀態', 403);
        }

        $planId = (int)post('plan_id');
        $newStatus = post('status');

        $allowedStatus = ['active', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatus)) {
            json_error('狀態無效');
        }

        $affected = db_exec("
            UPDATE sale_payment_plans 
            SET status = ? 
            WHERE id = ?
        ", [$newStatus, $planId]);

        if ($affected === 0) {
            json_error('找不到該計劃或狀態未改變');
        }

        log_activity('payment_plan.status_updated', $planId, 'sale_payment_plan', [
            'new_status' => $newStatus
        ]);

        json_success(null, '計劃狀態已更新');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以編輯計劃', 403);
        }

        $planId = (int)post('plan_id');
        if ($planId <= 0) json_error('缺少計劃 ID');

        // 取得現有計劃 + 已付款數量（用作保護）
        $plan = db_query_one("
            SELECT spp.*, 
                   (SELECT COUNT(*) FROM payments WHERE plan_id = spp.id AND is_refund = 0) as payments_made
            FROM sale_payment_plans spp
            WHERE spp.id = ?
        ", [$planId]);

        if (!$plan) json_error('找不到該計劃');

        $paymentsMade = (int)($plan['payments_made'] ?? 0);

        // 接收可編輯欄位
        $newStartDate = post('start_date');
        $newNotes = sanitize_string(post('notes', ''), 1000);

        // 只有「完全未收款」的計劃才允許改動財務相關欄位
        $canEditFinancial = ($paymentsMade === 0);

        $newPlanType = $plan['plan_type'];
        $newTotalInstallments = (int)$plan['total_installments'];
        $newInstallmentAmount = (float)$plan['installment_amount'];
        $newFrequency = $plan['frequency'];

        if ($canEditFinancial) {
            $newPlanType = post('plan_type', $plan['plan_type']);
            $newTotalInstallments = (int)post('total_installments', $plan['total_installments']);
            $newInstallmentAmount = (float)post('installment_amount', $plan['installment_amount']);
            $newFrequency = post('frequency', $plan['frequency']) ?: null;

            if (!in_array($newPlanType, ['installment', 'recurring'])) {
                json_error('plan_type 無效');
            }
            if ($newTotalInstallments < 1) json_error('總期數必須大於 0');
            if ($newInstallmentAmount <= 0) json_error('每期金額必須大於 0');
        }

        if (!$newStartDate) json_error('開始日期為必填');

        // 準備 before/after 用於 audit
        $before = [
            'plan_type' => $plan['plan_type'],
            'total_installments' => (int)$plan['total_installments'],
            'installment_amount' => (float)$plan['installment_amount'],
            'frequency' => $plan['frequency'],
            'start_date' => $plan['start_date'],
            'notes' => $plan['notes']
        ];
        $after = [
            'plan_type' => $newPlanType,
            'total_installments' => $newTotalInstallments,
            'installment_amount' => $newInstallmentAmount,
            'frequency' => $newFrequency,
            'start_date' => $newStartDate,
            'notes' => $newNotes ?: null
        ];

        try {
            $affected = db_exec("
                UPDATE sale_payment_plans SET
                    plan_type = ?,
                    total_installments = ?,
                    installment_amount = ?,
                    frequency = ?,
                    start_date = ?,
                    notes = ?
                WHERE id = ?
            ", [
                $newPlanType,
                $newTotalInstallments,
                $newInstallmentAmount,
                $newFrequency,
                $newStartDate,
                $newNotes ?: null,
                $planId
            ]);

            if ($affected === 0) {
                json_error('沒有任何變更');
            }

            log_activity('payment_plan.updated', $planId, 'sale_payment_plan', [
                'before' => $before,
                'after' => $after,
                'payments_made_at_edit' => $paymentsMade
            ]);

            json_success(null, '計劃已更新');

        } catch (Throwable $e) {
            json_error('更新失敗：' . $e->getMessage());
        }
        break;

    case 'append_followup':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $planId = (int)post('plan_id');
        $note = trim(post('note', ''));

        if ($planId <= 0) json_error('缺少計劃 ID');
        if ($note === '') json_error('跟進內容不能為空');

        // 取得現有 notes
        $plan = db_query_one("SELECT notes FROM sale_payment_plans WHERE id = ?", [$planId]);
        if (!$plan) json_error('找不到該計劃');

        $timestamp = date('Y-m-d H:i');
        $followup = "[跟進 {$timestamp}] " . $note;

        $currentNotes = $plan['notes'] ? $plan['notes'] . "\n\n" : '';
        $newNotes = $currentNotes . $followup;

        $affected = db_exec("
            UPDATE sale_payment_plans 
            SET notes = ? 
            WHERE id = ?
        ", [$newNotes, $planId]);

        if ($affected === 0) {
            json_error('更新失敗');
        }

        log_activity('payment_plan.followup_added', $planId, 'sale_payment_plan', [
            'note' => $note
        ]);

        json_success(['note' => $followup], '跟進記錄已儲存');
        break;

    case 'bulk_append_followup':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $planIdsJson = post('plan_ids', '[]');
        $note = trim(post('note', ''));

        $planIds = json_decode($planIdsJson, true);
        if (!is_array($planIds) || empty($planIds)) {
            json_error('請選擇至少一筆計劃');
        }
        if ($note === '') json_error('跟進內容不能為空');

        $successCount = 0;
        $failed = [];
        $timestamp = date('Y-m-d H:i');
        $followupText = "[跟進 {$timestamp}] " . $note;

        foreach ($planIds as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;

            $plan = db_query_one("SELECT notes FROM sale_payment_plans WHERE id = ?", [$pid]);
            if (!$plan) {
                $failed[] = $pid;
                continue;
            }

            $currentNotes = $plan['notes'] ? $plan['notes'] . "\n\n" : '';
            $newNotes = $currentNotes . $followupText;

            $affected = db_exec("
                UPDATE sale_payment_plans 
                SET notes = ? 
                WHERE id = ?
            ", [$newNotes, $pid]);

            if ($affected > 0) {
                $successCount++;
                log_activity('payment_plan.followup_added', $pid, 'sale_payment_plan', [
                    'note' => $note,
                    'bulk' => true
                ]);
            } else {
                $failed[] = $pid;
            }
        }

        json_success([
            'success_count' => $successCount,
            'failed' => $failed,
            'note' => $followupText
        ], "已成功記錄 {$successCount} 筆跟進");

        break;

    case 'bulk_update_status':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以批量更改計劃狀態', 403);
        }

        $planIdsJson = post('plan_ids', '[]');
        $newStatus = post('status');

        $planIds = json_decode($planIdsJson, true);
        if (!is_array($planIds) || empty($planIds)) {
            json_error('請選擇至少一筆計劃');
        }

        $allowedStatus = ['active', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatus)) {
            json_error('狀態無效');
        }

        $successCount = 0;
        $failed = [];

        foreach ($planIds as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;

            $affected = db_exec("
                UPDATE sale_payment_plans 
                SET status = ? 
                WHERE id = ?
            ", [$newStatus, $pid]);

            if ($affected > 0) {
                $successCount++;
                log_activity('payment_plan.status_updated', $pid, 'sale_payment_plan', [
                    'new_status' => $newStatus,
                    'bulk' => true
                ]);
            } else {
                $failed[] = $pid;
            }
        }

        json_success([
            'success_count' => $successCount,
            'failed' => $failed,
            'new_status' => $newStatus
        ], "已成功更新 {$successCount} 筆計劃狀態");

        break;

    default:
        json_error('未知的操作', 400);
}

/**
 * 計算未來 N 天內活躍計劃的預計回收金額（簡化但實用版本）
 */
function calculateUpcomingCollections(int $days = 30): float
{
    $activePlans = db_query("
        SELECT 
            spp.id,
            spp.installment_amount,
            spp.total_installments,
            spp.frequency,
            spp.start_date,
            s.customer_id,
            (SELECT COUNT(*) FROM payments WHERE plan_id = spp.id AND is_refund = 0) as paid_count,
            (SELECT MAX(paid_at) FROM payments WHERE plan_id = spp.id AND is_refund = 0) as last_paid_at
        FROM sale_payment_plans spp
        JOIN sales s ON spp.sale_id = s.id
        WHERE spp.status = 'active'
    ");

    if (empty($activePlans)) {
        return 0.0;
    }

    $today = new DateTime();
    $endDate = (clone $today)->modify("+{$days} days");
    $totalUpcoming = 0.0;

    $frequencyMap = [
        'weekly'    => '+1 week',
        'biweekly'  => '+2 weeks',
        'monthly'   => '+1 month',
        'quarterly' => '+3 months',
    ];

    foreach ($activePlans as $plan) {
        $installmentAmount = (float)$plan['installment_amount'];
        $paidCount = (int)($plan['paid_count'] ?? 0);
        $frequency = $plan['frequency'] ?? 'monthly';
        $startDate = $plan['start_date'];

        if (!isset($frequencyMap[$frequency]) || $paidCount >= (int)$plan['total_installments']) {
            continue;
        }

        // 計算下一個預計付款日期
        try {
            $nextDue = new DateTime($startDate);

            // 推進到下一個應付期數
            for ($i = 0; $i < $paidCount; $i++) {
                $nextDue->modify($frequencyMap[$frequency]);
            }

            // 計算未來 N 天內會到期的期數
            while ($nextDue <= $endDate) {
                if ($nextDue >= $today) {
                    $totalUpcoming += $installmentAmount;
                }
                $nextDue->modify($frequencyMap[$frequency]);

                // 安全機制，避免無限循環
                if ($nextDue->diff($today)->days > 400) break;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    return round($totalUpcoming, 2);
}

/**
 * 取得目前最老的活躍計劃資訊（供管理概覽顯示）
 */
function getOldestActivePlanInfo()
{
    $oldest = db_query_one("
        SELECT 
            spp.id,
            spp.created_at,
            spp.installment_amount,
            spp.total_installments,
            s.id as sale_id,
            c.name as customer_name,
            c.phone as customer_phone,
            (SELECT COUNT(*) FROM payments WHERE plan_id = spp.id AND is_refund = 0) as paid_count
        FROM sale_payment_plans spp
        JOIN sales s ON spp.sale_id = s.id
        JOIN customers c ON s.customer_id = c.id
        WHERE spp.status = 'active'
        ORDER BY spp.created_at ASC
        LIMIT 1
    ");

    if (!$oldest) return null;

    $created = $oldest['created_at'] ? new DateTime($oldest['created_at']) : null;
    $daysOld = $created ? $created->diff(new DateTime())->days : 0;

    $paid = (int)($oldest['paid_count'] ?? 0);
    $total = (int)$oldest['total_installments'];
    $progress = $total > 0 ? round(($paid / $total) * 100) : 0;

    return [
        'id' => (int)$oldest['id'],
        'sale_id' => (int)$oldest['sale_id'],
        'customer_name' => $oldest['customer_name'],
        'customer_phone' => $oldest['customer_phone'],
        'days_old' => $daysOld,
        'progress' => $progress
    ];
}

/**
 * 取得目前「最需要關注的客戶」（活躍計劃數量最多的客戶）
 */
function getMostConcerningCustomer()
{
    $customer = db_query_one("
        SELECT 
            c.id,
            c.name,
            c.phone,
            COUNT(spp.id) as active_plans_count,
            SUM(spp.installment_amount * spp.total_installments) as active_value
        FROM customers c
        JOIN sales s ON s.customer_id = c.id
        JOIN sale_payment_plans spp ON spp.sale_id = s.id
        WHERE spp.status = 'active'
        GROUP BY c.id
        ORDER BY active_plans_count DESC, active_value DESC
        LIMIT 1
    ");

    if (!$customer) return null;

    return [
        'id' => (int)$customer['id'],
        'name' => $customer['name'],
        'phone' => $customer['phone'],
        'active_plans_count' => (int)$customer['active_plans_count'],
        'active_value' => (float)$customer['active_value']
    ];
}
