<?php
/**
 * SalonEase - 報表 API
 * GET /api/reports.php?action=summary&from=YYYY-MM-DD&to=YYYY-MM-DD
 * GET /api/reports.php?action=payment_breakdown&from=...&to=...
 * GET /api/reports.php?action=top_services&from=...&to=...&limit=5
 * GET /api/reports.php?action=top_products&from=...&to=...&limit=5
 * GET /api/reports.php?action=package_redemptions&from=...&to=...
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? '';
$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to'] ?? date('Y-m-d');

// 簡單日期驗證
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

switch ($action) {

    case 'summary':
        $sql = "
            SELECT 
                COALESCE(SUM(total), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(total), 0) AS avg_ticket,
                COALESCE(SUM(discount), 0) AS total_discount
            FROM sales 
            WHERE sale_date BETWEEN ? AND ?
        ";
        $row = db_query_one($sql, [$from, $to]);

        // 套票扣減次數
        $pkg = db_query_one("
            SELECT COALESCE(SUM(sessions_used), 0) AS sessions_used
            FROM package_usages pu
            JOIN sales s ON pu.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
        ", [$from, $to]);

        json_success([
            'total_sales'        => (float)$row['total_sales'],
            'total_transactions' => (int)$row['total_transactions'],
            'avg_ticket'         => (float)$row['avg_ticket'],
            'total_discount'     => (float)$row['total_discount'],
            'package_sessions'   => (int)$pkg['sessions_used'],
            'from' => $from,
            'to'   => $to
        ]);
        break;

    case 'payment_breakdown':
        $rows = db_query("
            SELECT 
                payment_method,
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as amount
            FROM sales 
            WHERE sale_date BETWEEN ? AND ?
            GROUP BY payment_method
            ORDER BY amount DESC
        ", [$from, $to]);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'method' => $r['payment_method'],
                'count'  => (int)$r['count'],
                'amount' => (float)$r['amount']
            ];
        }
        json_success($result);
        break;

    case 'top_services':
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));
        $rows = db_query("
            SELECT 
                si.name,
                SUM(si.qty) as qty,
                SUM(si.line_total) as revenue
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
              AND si.item_type = 'service'
            GROUP BY si.ref_id, si.name
            ORDER BY revenue DESC
            LIMIT $limit
        ", [$from, $to]);

        json_success($rows);
        break;

    case 'top_products':
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));
        $rows = db_query("
            SELECT 
                si.name,
                SUM(si.qty) as qty,
                SUM(si.line_total) as revenue
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
              AND si.item_type = 'product'
            GROUP BY si.ref_id, si.name
            ORDER BY revenue DESC
            LIMIT $limit
        ", [$from, $to]);

        json_success($rows);
        break;

    case 'package_redemptions':
        $rows = db_query("
            SELECT 
                p.name as package_name,
                COUNT(*) as times,
                SUM(pu.sessions_used) as total_sessions
            FROM package_usages pu
            JOIN customer_packages cp ON pu.customer_package_id = cp.id
            JOIN packages p ON cp.package_id = p.id
            JOIN sales s ON pu.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY p.id, p.name
            ORDER BY total_sessions DESC
        ", [$from, $to]);

        json_success($rows);
        break;

    default:
        json_error('未知的報表類型', 400);
}