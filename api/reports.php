<?php
/**
 * SalonEase - 報表 API
 * GET /api/reports.php?action=summary&from=YYYY-MM-DD&to=YYYY-MM-DD
 * GET /api/reports.php?action=payment_breakdown&from=...&to=...
 * GET /api/reports.php?action=top_services&from=...&to=...&limit=5
 * GET /api/reports.php?action=top_products&from=...&to=...&limit=5
 * GET /api/reports.php?action=package_redemptions&from=...&to=...
 * GET /api/reports.php?action=staff_sales_ranking&from=...&to=&staff_id= (optional)
 * GET /api/reports.php?action=daily_sales&from=...&to=...   （A140 新增）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_role(['admin', 'manager']);

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

    case 'daily_sales':
        // A140：提供每日銷售數據（用於真實趨勢圖表）
        // A142 加強：支援 staff_id 篩選
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $where = "sale_date BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($staffId > 0) {
            $where .= " AND staff_id = ?";
            $params[] = $staffId;
        }

        $sql = "
            SELECT 
                sale_date,
                COALESCE(SUM(total), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(total), 0) AS avg_ticket
            FROM sales 
            WHERE $where
            GROUP BY sale_date
            ORDER BY sale_date ASC
        ";
        $rows = db_query($sql, $params);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'date'               => $r['sale_date'],
                'total_sales'        => (float)$r['total_sales'],
                'total_transactions' => (int)$r['total_transactions'],
                'avg_ticket'         => (float)$r['avg_ticket'],
            ];
        }

        json_success([
            'data' => $result,
            'from' => $from,
            'to'   => $to,
            'staff_id' => $staffId
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

    case 'staff_sales_ranking':
        $staffId = (int)($_GET['staff_id'] ?? 0);

        $where = "s.sale_date BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($staffId > 0) {
            $where .= " AND s.staff_id = ?";
            $params[] = $staffId;
        }

        $rows = db_query("
            SELECT 
                st.id as staff_id,
                st.name as staff_name,
                COUNT(s.id) as transaction_count,
                COALESCE(SUM(s.total), 0) as total_sales,
                COALESCE(AVG(s.total), 0) as avg_ticket
            FROM sales s
            JOIN staff st ON s.staff_id = st.id
            WHERE $where
            GROUP BY st.id, st.name
            ORDER BY total_sales DESC
        ", $params);

        // 額外計算每位員工的套票扣減次數
        $packageStats = db_query("
            SELECT 
                s.staff_id,
                COALESCE(SUM(pu.sessions_used), 0) as package_sessions
            FROM package_usages pu
            JOIN sales s ON pu.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
            " . ($staffId > 0 ? "AND s.staff_id = ?" : "") . "
            GROUP BY s.staff_id
        ", $staffId > 0 ? [$from, $to, $staffId] : [$from, $to]);

        $pkgMap = [];
        foreach ($packageStats as $p) {
            $pkgMap[$p['staff_id']] = (int)$p['package_sessions'];
        }

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'staff_id' => (int)$r['staff_id'],
                'staff_name' => $r['staff_name'],
                'transaction_count' => (int)$r['transaction_count'],
                'total_sales' => (float)$r['total_sales'],
                'avg_ticket' => (float)$r['avg_ticket'],
                'package_sessions' => $pkgMap[$r['staff_id']] ?? 0
            ];
        }

        json_success($result);
        break;

    default:
        json_error('未知的報表類型', 400);
}