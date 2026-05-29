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
 * GET /api/reports.php?action=inventory_turnover&from=...&to=...  （A143 新增）
 * GET /api/reports.php?action=stockout_trend&from=...&to=...     （A143 新增）
 * GET /api/reports.php?action=staff_performance_trend&from=...&to=...&staff_id= (optional)  （A144 新增）
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

    case 'inventory_turnover':
        // A143：庫存周轉率報表
        // 計算期間內每個產品的銷售數量 + 目前庫存，算周轉率 = 銷量 / 目前庫存
        $rows = db_query("
            SELECT 
                p.id,
                p.name,
                p.stock_qty,
                COALESCE(SUM(si.qty), 0) AS sales_qty
            FROM products p
            LEFT JOIN sale_items si ON si.ref_id = p.id 
                AND si.item_type = 'product'
                AND si.sale_id IN (
                    SELECT id FROM sales WHERE sale_date BETWEEN ? AND ?
                )
            GROUP BY p.id, p.name, p.stock_qty
            ORDER BY sales_qty DESC
            LIMIT 20
        ", [$from, $to]);

        $result = [];
        foreach ($rows as $r) {
            $stock = max(1, (int)$r['stock_qty']); // 避免除以 0
            $turnover = round((int)$r['sales_qty'] / $stock, 2);
            $result[] = [
                'product_id'   => (int)$r['id'],
                'name'         => $r['name'],
                'stock_qty'    => (int)$r['stock_qty'],
                'sales_qty'    => (int)$r['sales_qty'],
                'turnover'     => $turnover,
                'turnover_days' => $turnover > 0 ? round(1 / $turnover * 30, 1) : null // 粗估每月周轉天數
            ];
        }

        json_success([
            'data' => $result,
            'from' => $from,
            'to'   => $to
        ]);
        break;

    case 'stockout_trend':
        // A143：缺貨趨勢（每日低庫存產品數量）
        $rows = db_query("
            SELECT 
                sale_date,
                COUNT(*) AS low_stock_count
            FROM (
                SELECT DISTINCT s.sale_date, p.id
                FROM sales s
                CROSS JOIN products p
                WHERE s.sale_date BETWEEN ? AND ?
                  AND p.stock_qty <= COALESCE(p.low_stock_threshold, 
                        (SELECT default_low_stock_threshold FROM settings LIMIT 1), 5)
            ) t
            GROUP BY sale_date
            ORDER BY sale_date ASC
        ", [$from, $to]);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'date' => $r['sale_date'],
                'low_stock_count' => (int)$r['low_stock_count']
            ];
        }

        json_success([
            'data' => $result,
            'from' => $from,
            'to'   => $to
        ]);
        break;

    case 'staff_performance_trend':
        // A144：員工表現趨勢（每日銷售數據，用於圖表）
        $staffId = (int)($_GET['staff_id'] ?? 0);

        $where = "s.sale_date BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($staffId > 0) {
            $where .= " AND s.staff_id = ?";
            $params[] = $staffId;
        }

        $rows = db_query("
            SELECT 
                s.staff_id,
                st.name as staff_name,
                s.sale_date,
                COALESCE(SUM(s.total), 0) as total_sales,
                COUNT(*) as transaction_count,
                COALESCE(AVG(s.total), 0) as avg_ticket
            FROM sales s
            JOIN staff st ON s.staff_id = st.id
            WHERE $where
            GROUP BY s.staff_id, st.name, s.sale_date
            ORDER BY s.sale_date ASC, total_sales DESC
        ", $params);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'staff_id' => (int)$r['staff_id'],
                'staff_name' => $r['staff_name'],
                'date' => $r['sale_date'],
                'total_sales' => (float)$r['total_sales'],
                'transaction_count' => (int)$r['transaction_count'],
                'avg_ticket' => (float)$r['avg_ticket']
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
        // Phase 2: 使用 payments 表做更精準的多筆付款分佈
        $rows = db_query("
            SELECT 
                pm.name as method,
                COUNT(DISTINCT p.sale_id) as count,
                COALESCE(SUM(p.amount), 0) as amount
            FROM payments p
            JOIN payment_methods pm ON p.payment_method_id = pm.id
            JOIN sales s ON p.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
              AND p.is_refund = 0
            GROUP BY pm.id, pm.name
            ORDER BY amount DESC
        ", [$from, $to]);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'method' => $r['method'],
                'count'  => (int)$r['count'],
                'amount' => (float)$r['amount']
            ];
        }
        json_success($result);
        break;

    case 'fee_cost_breakdown':
        // Phase 3: 手續費成本統計（商戶實際承擔）
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $where = "s.sale_date BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($staffId > 0) {
            $where .= " AND s.staff_id = ?";
            $params[] = $staffId;
        }

        $rows = db_query("
            SELECT 
                pm.name as method,
                COUNT(*) as count,
                COALESCE(SUM(p.fee_amount), 0) as total_fee,
                COALESCE(SUM(CASE WHEN p.fee_borne_by = 'merchant' THEN p.fee_amount ELSE 0 END), 0) as merchant_fee,
                COALESCE(SUM(CASE WHEN p.fee_borne_by = 'customer' THEN p.fee_amount ELSE 0 END), 0) as customer_fee
            FROM payments p
            JOIN payment_methods pm ON p.payment_method_id = pm.id
            JOIN sales s ON p.sale_id = s.id
            WHERE $where
              AND p.is_refund = 0
            GROUP BY pm.id, pm.name
            ORDER BY merchant_fee DESC
        ", $params);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'method' => $r['method'],
                'count' => (int)$r['count'],
                'total_fee' => (float)$r['total_fee'],
                'merchant_fee' => (float)$r['merchant_fee'],
                'customer_fee' => (float)$r['customer_fee']
            ];
        }
        json_success($result);
        break;

    case 'reminder_report':
        // Phase 5: 付款計劃提醒發送報表
        $channel = trim(get('channel', ''));
        $status = trim(get('status', ''));

        $where = "pn.sent_at BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($channel) {
            $where .= " AND pn.channel = ?";
            $params[] = $channel;
        }
        if ($status) {
            $where .= " AND pn.status = ?";
            $params[] = $status;
        }

        // Summary
        $summary = db_query_one("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email_total,
                SUM(CASE WHEN channel = 'sms' THEN 1 ELSE 0 END) as sms_total
            FROM plan_notifications pn
            WHERE $where
        ", $params);

        // List (最近 200 筆)
        $list = db_query("
            SELECT 
                pn.*,
                spp.id as plan_id,
                c.name as customer_name,
                c.phone as customer_phone,
                c.email as customer_email
            FROM plan_notifications pn
            JOIN sale_payment_plans spp ON spp.id = pn.plan_id
            JOIN sales sa ON sa.id = spp.sale_id
            JOIN customers c ON c.id = sa.customer_id
            WHERE $where
            ORDER BY pn.sent_at DESC
            LIMIT 200
        ", $params);

        json_success([
            'summary' => $summary,
            'list' => $list
        ]);
        break;

    case 'reminder_effectiveness':
        // Phase 9: 提醒系統回款成效分析
        $daysAfter = 14; // 提醒後14天內有付款算有效

        // 只看成功發送的提醒
        $reminders = db_query("
            SELECT pn.plan_id, pn.sent_at
            FROM plan_notifications pn
            JOIN sale_payment_plans spp ON spp.id = pn.plan_id
            WHERE pn.sent_at BETWEEN ? AND ?
              AND pn.status = 'sent'
              AND spp.status = 'active'
        ", [$from, $to]);

        $totalSent = count($reminders);
        $effective = 0;
        $totalCollected = 0;

        foreach ($reminders as $r) {
            $planId = $r['plan_id'];
            $sentAt = $r['sent_at'];

            // 檢查提醒後 $daysAfter 天內是否有付款
            $payment = db_query_one("
                SELECT COALESCE(SUM(amount), 0) as collected
                FROM payments 
                WHERE plan_id = ? 
                  AND paid_at > ? 
                  AND paid_at <= DATE_ADD(?, INTERVAL $daysAfter DAY)
                  AND is_refund = 0
            ", [$planId, $sentAt, $sentAt]);

            if ($payment && $payment['collected'] > 0) {
                $effective++;
                $totalCollected += (float)$payment['collected'];
            }
        }

        $successRate = $totalSent > 0 ? round(($effective / $totalSent) * 100, 1) : 0;

        json_success([
            'total_sent' => $totalSent,
            'effective' => $effective,
            'success_rate' => $successRate,
            'total_collected_after_reminders' => round($totalCollected, 2),
            'window_days' => $daysAfter
        ]);
        break;

    case 'payment_forecast':
        // Phase 6: 付款計劃現金流預測（強化版）
        $days = (int)get('days', 90);
        $plans = db_query("
            SELECT id FROM sale_payment_plans 
            WHERE status = 'active'
            ORDER BY created_at DESC
            LIMIT 300
        ");

        $forecasts = [];
        $totalExpected = 0;
        $monthlyTotal = [];

        foreach ($plans as $p) {
            $f = calculatePlanCashFlowForecast($p['id'], $days);
            if (!isset($f['error'])) {
                $forecasts[] = $f;
                $totalExpected += $f['expected_collections'];

                foreach ($f['monthly'] as $month => $amt) {
                    if (!isset($monthlyTotal[$month])) $monthlyTotal[$month] = 0;
                    $monthlyTotal[$month] += $amt;
                }
            }
        }

        ksort($monthlyTotal);

        json_success([
            'days_ahead' => $days,
            'total_expected' => round($totalExpected, 2),
            'monthly_breakdown' => $monthlyTotal,
            'plans_count' => count($forecasts)
        ]);
        break;

    case 'customer_payment_health':
        // Phase 6: 客戶付款健康分數
        $customerId = (int)get('customer_id', 0);
        if ($customerId <= 0) json_error('缺少 customer_id');

        $health = calculateCustomerPaymentHealthScore($customerId);
        json_success($health);
        break;

    case 'top_risk_customers':
        // Phase 6: 高風險客戶 Top 列表
        $limit = (int)get('limit', 10);

        // 取得有活躍計劃的客戶
        $customers = db_query("
            SELECT DISTINCT c.id, c.name, c.phone
            FROM customers c
            JOIN sales s ON s.customer_id = c.id
            JOIN sale_payment_plans spp ON spp.sale_id = s.id
            WHERE spp.status = 'active'
            LIMIT 100
        ");

        $riskList = [];
        foreach ($customers as $c) {
            $health = calculateCustomerPaymentHealthScore($c['id']);
            $riskList[] = [
                'customer_id' => $c['id'],
                'name' => $c['name'],
                'phone' => $c['phone'],
                'score' => $health['score'],
                'factors' => $health['factors'],
                'stats' => $health['stats']
            ];
        }

        // 按分數由低到高排序（分數越低風險越高）
        usort($riskList, function($a, $b) {
            return $a['score'] <=> $b['score'];
        });

        json_success(array_slice($riskList, 0, $limit));
        break;

    case 'health_score_distribution':
        // Phase 9: 客戶付款健康分數分佈
        $customers = db_query("
            SELECT DISTINCT c.id 
            FROM customers c
            JOIN sales s ON s.customer_id = c.id
            JOIN sale_payment_plans spp ON spp.sale_id = s.id
            WHERE spp.status = 'active'
        ");

        $buckets = [
            'high' => 0,   // 0-49
            'medium' => 0, // 50-69
            'good' => 0    // 70-100
        ];

        foreach ($customers as $c) {
            $h = calculateCustomerPaymentHealthScore($c['id']);
            $score = $h['score'];
            if ($score < 50) $buckets['high']++;
            elseif ($score < 70) $buckets['medium']++;
            else $buckets['good']++;
        }

        json_success([
            'high' => $buckets['high'],
            'medium' => $buckets['medium'],
            'good' => $buckets['good'],
            'total' => count($customers)
        ]);
        break;

    case 'installment_overview':
        // Phase 3: 分期計劃概覽報表
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $where = "s.sale_date BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($staffId > 0) {
            $where .= " AND s.staff_id = ?";
            $params[] = $staffId;
        }

        $rows = db_query("
            SELECT 
                spp.id as plan_id,
                spp.plan_type,
                spp.total_installments,
                spp.installment_amount,
                spp.status,
                s.id as sale_id,
                s.total as sale_total,
                COALESCE(SUM(CASE WHEN p.is_refund = 0 THEN p.amount ELSE 0 END), 0) as paid_amount,
                COUNT(CASE WHEN p.is_refund = 0 THEN 1 END) as payments_made
            FROM sale_payment_plans spp
            JOIN sales s ON spp.sale_id = s.id
            LEFT JOIN payments p ON p.plan_id = spp.id
            WHERE $where
            GROUP BY spp.id, s.id
            ORDER BY spp.created_at DESC
        ", $params);

        $result = [];
        foreach ($rows as $r) {
            $paid = (float)$r['paid_amount'];
            $expected = (float)$r['installment_amount'] * (int)$r['total_installments'];
            $progress = $expected > 0 ? round(($paid / $expected) * 100, 1) : 0;

            $result[] = [
                'plan_id' => (int)$r['plan_id'],
                'sale_id' => (int)$r['sale_id'],
                'plan_type' => $r['plan_type'],
                'total_installments' => (int)$r['total_installments'],
                'installment_amount' => (float)$r['installment_amount'],
                'status' => $r['status'],
                'paid_amount' => $paid,
                'payments_made' => (int)$r['payments_made'],
                'progress_percent' => $progress,
                'remaining_amount' => max(0, $expected - $paid)
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