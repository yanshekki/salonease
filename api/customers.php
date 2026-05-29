<?php
/**
 * SalonEase - 客戶管理 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $search = trim(get('search', ''));
        $sort = get('sort', 'recent');   // recent | points_desc

        $sql = "SELECT 
                    c.id, c.name, c.phone, c.email, c.gender, c.birthday, c.notes, 
                    c.total_spent, c.visit_count, c.last_visit_at, c.points,
                    (SELECT MAX(p.paid_at) 
                     FROM payments p 
                     JOIN sales s ON p.sale_id = s.id 
                     WHERE s.customer_id = c.id AND p.is_refund = 0) as last_payment_at,
                    (SELECT COUNT(*) 
                     FROM sale_payment_plans spp 
                     JOIN sales s ON spp.sale_id = s.id 
                     WHERE s.customer_id = c.id AND spp.status = 'active') as active_plans_count,
                    (SELECT CONCAT(
                        CASE spp.plan_type 
                            WHEN 'installment' THEN '分期' 
                            WHEN 'recurring' THEN '周期性' 
                            ELSE spp.plan_type 
                        END,
                        ' 每期 HK$', FORMAT(spp.installment_amount, 0),
                        CASE WHEN spp.plan_type = 'installment' 
                             THEN CONCAT(' × ', spp.total_installments, ' 期') 
                             ELSE '' END,
                        ' (已付 ',
                        COALESCE((SELECT COUNT(*) FROM payments WHERE plan_id = spp.id AND is_refund=0), 0),
                        '/', spp.total_installments, ')'
                     )
                     FROM sale_payment_plans spp 
                     JOIN sales s ON spp.sale_id = s.id 
                     WHERE s.customer_id = c.id AND spp.status = 'active'
                     ORDER BY spp.created_at DESC 
                     LIMIT 1) as active_plan_summary
                FROM customers c
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($sort === 'points_desc') {
            $sql .= " ORDER BY points DESC, name ASC";
        } else {
            $sql .= " ORDER BY last_visit_at DESC, name ASC";
        }

        $sql .= " LIMIT 100";

        $customers = db_query($sql, $params);
        json_success($customers);
        break;

    case 'get':
        $id = (int)get('id');
        if (!$id) json_error('缺少客戶 ID');

        $customer = db_query_one(
            "SELECT * FROM customers WHERE id = ?",
            [$id]
        );
        if (!$customer) json_error('找不到該客戶', 404);

        // A36：加入最近積分異動（最多 6 筆）
        $recentHistory = db_query(
            "SELECT 
                al.created_at,
                al.action,
                al.details
             FROM audit_logs al
             WHERE al.entity_type = 'customer' 
               AND al.entity_id = ?
               AND al.action IN ('customer.points_earned', 'customer.points_redeemed', 'customer.points_adjusted')
             ORDER BY al.created_at DESC
             LIMIT 6",
            [$id]
        );

        $customer['recent_points_history'] = $recentHistory ?: [];

        // Phase 3: 加入付款歷史（支援可展開更多）
        $paymentsLimit = (int)get('payments_limit', 6);
        $paymentsLimit = max(1, min(50, $paymentsLimit)); // 限制 1~50 筆

        $recentPayments = db_query(
            "SELECT 
                p.id,
                p.sale_id,
                p.amount,
                p.fee_amount,
                p.fee_borne_by,
                p.paid_at,
                p.is_refund,
                p.installment_no,
                pm.name as payment_method_name,
                s.sale_date,
                spp.plan_type,
                spp.total_installments
             FROM payments p
             JOIN sales s ON p.sale_id = s.id
             JOIN payment_methods pm ON p.payment_method_id = pm.id
             LEFT JOIN sale_payment_plans spp ON p.plan_id = spp.id
             WHERE s.customer_id = ?
             ORDER BY p.paid_at DESC
             LIMIT $paymentsLimit",
            [$id]
        );

        $customer['recent_payments'] = $recentPayments ?: [];
        $customer['payments_total_count'] = (int)db_query_one(
            "SELECT COUNT(*) as cnt 
             FROM payments p 
             JOIN sales s ON p.sale_id = s.id 
             WHERE s.customer_id = ?", 
            [$id]
        )['cnt'];

        // Phase 3 C5: 付款摘要資訊
        $paymentSummary = db_query_one(
            "SELECT 
                COALESCE(SUM(CASE WHEN p.is_refund = 0 THEN p.amount ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN p.is_refund = 1 THEN ABS(p.amount) ELSE 0 END), 0) as total_refunded
             FROM payments p 
             JOIN sales s ON p.sale_id = s.id 
             WHERE s.customer_id = ?",
            [$id]
        );

        $planSummary = db_query_one(
            "SELECT 
                COUNT(*) as total_plans,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_plans
             FROM sale_payment_plans spp
             JOIN sales s ON spp.sale_id = s.id
             WHERE s.customer_id = ?",
            [$id]
        );

        $customer['payment_summary'] = [
            'total_paid' => (float)($paymentSummary['total_paid'] ?? 0),
            'total_refunded' => (float)($paymentSummary['total_refunded'] ?? 0),
            'active_plans' => (int)($planSummary['active_plans'] ?? 0),
            'total_plans' => (int)($planSummary['total_plans'] ?? 0)
        ];

        json_success($customer);
        break;

    case 'get_active_plans':
        $customerId = (int)get('customer_id');
        if (!$customerId) json_error('缺少客戶 ID');

        $activePlans = db_query(
            "SELECT 
                spp.id,
                spp.plan_type,
                spp.total_installments,
                spp.installment_amount,
                spp.status,
                s.id as sale_id,
                s.total as sale_total,
                (SELECT COALESCE(SUM(CASE WHEN is_refund=0 THEN amount ELSE 0 END), 0) 
                 FROM payments WHERE plan_id = spp.id) as paid_amount
             FROM sale_payment_plans spp
             JOIN sales s ON spp.sale_id = s.id
             WHERE s.customer_id = ? AND spp.status = 'active'
             ORDER BY spp.created_at DESC",
            [$customerId]
        );

        json_success($activePlans);
        break;

    case 'list_sales':
        // 供計劃管理頁「為客戶快速新增計劃」使用
        $customerId = (int)get('customer_id');
        if (!$customerId) json_error('缺少客戶 ID');

        $sales = db_query("
            SELECT 
                s.id,
                s.sale_date,
                s.total,
                s.payment_status,
                (SELECT COUNT(*) FROM sale_payment_plans WHERE sale_id = s.id) as existing_plans_count
            FROM sales s
            WHERE s.customer_id = ?
            ORDER BY s.sale_date DESC
            LIMIT 10
        ", [$customerId]);

        json_success($sales);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $name = sanitize_string(post('name', ''));
        $phone = trim(post('phone', ''));
        $email = trim(post('email', ''));
        $gender = post('gender', '');
        $birthday = post('birthday', '');
        $notes = sanitize_string(post('notes', ''));

        if ($err = validate_required($name, '姓名')) json_error($err);
        if ($err = validate_required($phone, '電話')) json_error($err);
        if ($err = validate_length($name, '姓名', 50, 1)) json_error($err);
        if ($err = validate_hk_phone($phone)) json_error($err);
        if ($err = validate_email($email)) json_error($err);
        if ($err = validate_length($notes, '備註', 500)) json_error($err);

        // 檢查電話是否已存在
        $exists = db_query_one("SELECT id FROM customers WHERE phone = ?", [$phone]);
        if ($exists) {
            json_error('此電話號碼已被使用');
        }

        db_exec(
            "INSERT INTO customers (name, phone, email, gender, birthday, notes, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $phone, $email, $gender, $birthday ?: null, $notes, $_SESSION['staff_id'] ?? null]
        );

        $newId = db_last_id();
        log_activity('customer.created', $newId, 'customer', [
            'name' => $name,
            'phone' => $phone
        ]);

        json_success(['id' => (int)$newId], '客戶新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $name = sanitize_string(post('name', ''));
        $phone = trim(post('phone', ''));
        $email = trim(post('email', ''));
        $gender = post('gender', '');
        $birthday = post('birthday', '');
        $notes = sanitize_string(post('notes', ''));

        if ($err = validate_required($id, '客戶 ID')) json_error($err);
        if ($err = validate_required($name, '姓名')) json_error($err);
        if ($err = validate_required($phone, '電話')) json_error($err);
        if ($err = validate_length($name, '姓名', 50, 1)) json_error($err);
        if ($err = validate_hk_phone($phone)) json_error($err);
        if ($err = validate_email($email)) json_error($err);
        if ($err = validate_length($notes, '備註', 500)) json_error($err);

        // 檢查電話是否被其他客戶使用
        $exists = db_query_one("SELECT id FROM customers WHERE phone = ? AND id != ?", [$phone, $id]);
        if ($exists) {
            json_error('此電話號碼已被其他客戶使用');
        }

        db_exec(
            "UPDATE customers SET name = ?, phone = ?, email = ?, gender = ?, birthday = ?, notes = ? 
             WHERE id = ?",
            [$name, $phone, $email, $gender, $birthday ?: null, $notes, $id]
        );

        log_activity('customer.updated', $id, 'customer', [
            'name' => $name,
            'phone' => $phone
        ]);

        json_success(null, '客戶資料已更新');
        break;

    default:
        json_error('未知的操作', 400);
}
