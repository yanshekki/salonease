<?php
/**
 * SalonEase - 佣金報表 API
 * GET /api/commissions.php?action=summary&from=YYYY-MM-DD&to=YYYY-MM-DD&staff_id=optional
 * GET /api/commissions.php?action=by_staff&from=...&to=...&staff_id=optional
 * GET /api/commissions.php?action=staff_details&from=...&to=...&staff_id=required
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_role(['admin', 'manager']);

$action = $_GET['action'] ?? '';
$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to'] ?? date('Y-m-d');
$staffId = (int)($_GET['staff_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$where = "c.calculated_at BETWEEN ? AND ?";
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];

if ($staffId > 0) {
    $where .= " AND c.staff_id = ?";
    $params[] = $staffId;
}

switch ($action) {

    case 'summary':
        $row = db_query_one("
            SELECT 
                COALESCE(SUM(amount), 0) AS total_commission,
                COALESCE(SUM(CASE WHEN type='service' THEN amount ELSE 0 END), 0) AS service_commission,
                COALESCE(SUM(CASE WHEN type='retail' THEN amount ELSE 0 END), 0) AS retail_commission,
                COALESCE(SUM(CASE WHEN type='open' THEN amount ELSE 0 END), 0) AS open_commission,
                COUNT(DISTINCT sale_id) AS sale_count
            FROM commissions c
            WHERE $where
        ", $params);

        json_success([
            'total_commission'   => (float)$row['total_commission'],
            'service_commission' => (float)$row['service_commission'],
            'retail_commission'  => (float)$row['retail_commission'],
            'open_commission'    => (float)$row['open_commission'],
            'sale_count'         => (int)$row['sale_count'],
            'from' => $from,
            'to'   => $to
        ]);
        break;

    case 'by_staff':
        $rows = db_query("
            SELECT 
                st.id as staff_id,
                st.name as staff_name,
                COALESCE(SUM(CASE WHEN c.type='service' THEN c.amount ELSE 0 END), 0) as service_commission,
                COALESCE(SUM(CASE WHEN c.type='retail' THEN c.amount ELSE 0 END), 0) as retail_commission,
                COALESCE(SUM(CASE WHEN c.type='open' THEN c.amount ELSE 0 END), 0) as open_commission,
                COALESCE(SUM(c.amount), 0) as total_commission,
                COUNT(DISTINCT c.sale_id) as sale_count
            FROM commissions c
            JOIN staff st ON c.staff_id = st.id
            WHERE $where
            GROUP BY st.id, st.name
            ORDER BY total_commission DESC
        ", $params);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'staff_id' => (int)$r['staff_id'],
                'staff_name' => $r['staff_name'],
                'service_commission' => (float)$r['service_commission'],
                'retail_commission' => (float)$r['retail_commission'],
                'open_commission' => (float)$r['open_commission'],
                'total_commission' => (float)$r['total_commission'],
                'sale_count' => (int)$r['sale_count']
            ];
        }
        json_success($result);
        break;

    case 'staff_details':
        // 取得某員工在期間內的佣金明細（每筆銷售的佣金記錄）
        $rows = db_query("
            SELECT 
                c.id,
                c.sale_id,
                c.amount,
                c.type,
                c.rate,
                s.sale_date,
                s.total as sale_total,
                s.payment_method,
                COALESCE(cu.name, '非會員') as customer_name
            FROM commissions c
            JOIN sales s ON c.sale_id = s.id
            LEFT JOIN customers cu ON s.customer_id = cu.id
            WHERE c.staff_id = ? 
              AND s.sale_date BETWEEN ? AND ?
            ORDER BY s.sale_date DESC, c.id DESC
        ", [$staffId ?: 0, $from, $to]);   // 如果沒傳 staff_id 就給 0（不會有結果）

        if ($staffId <= 0) {
            json_success([]);
            break;
        }

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'id' => (int)$r['id'],
                'sale_id' => (int)$r['sale_id'],
                'amount' => (float)$r['amount'],
                'type' => $r['type'],
                'rate' => (float)$r['rate'],
                'sale_date' => $r['sale_date'],
                'sale_total' => (float)$r['sale_total'],
                'payment_method' => $r['payment_method'],
                'customer_name' => $r['customer_name']
            ];
        }
        json_success($result);
        break;

    default:
        json_error('未知的佣金報表操作', 400);
}