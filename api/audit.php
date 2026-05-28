<?php
/**
 * SalonEase - Audit Log API
 * Phase 1 實作
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin'); // 只有管理員可以查看審計日誌

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $staff_id = (int)get('staff_id', 0);
        $log_action = trim(get('action', ''));
        $entity_type = trim(get('entity_type', ''));
        $entity_id = (int)get('entity_id', 0);
        $from = get('from', '');
        $to = get('to', '');
        $limit = min(500, max(10, (int)get('limit', 100)));  // A147 提高上限支援更好篩選

        // 共用 WHERE 條件
        $where = "WHERE 1=1";
        $params = [];

        if ($staff_id > 0) {
            $where .= " AND al.staff_id = ?";
            $params[] = $staff_id;
        }
        if ($log_action !== '') {
            $where .= " AND al.action = ?";
            $params[] = $log_action;
        }
        if ($entity_type !== '') {
            $where .= " AND al.entity_type = ?";
            $params[] = $entity_type;
        }
        if ($entity_id > 0) {
            $where .= " AND al.entity_id = ?";
            $params[] = $entity_id;
        }
        if ($from !== '') {
            $where .= " AND al.created_at >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where .= " AND al.created_at <= ?";
            $params[] = $to . ' 23:59:59';
        }

        // 先取總數（不受 LIMIT 影響）
        $totalRow = db_query_one(
            "SELECT COUNT(*) as cnt FROM audit_logs al $where",
            $params
        );
        $total = (int)($totalRow['cnt'] ?? 0);

        // 再取實際資料
        $sql = "
            SELECT 
                al.id,
                al.staff_id,
                s.name as staff_name,
                al.action,
                al.entity_type,
                al.entity_id,
                al.details,
                al.ip_address,
                al.created_at
            FROM audit_logs al
            LEFT JOIN staff s ON al.staff_id = s.id
            $where
            ORDER BY al.created_at DESC 
            LIMIT $limit
        ";

        $logs = db_query($sql, $params);
        json_success(['data' => $logs, 'total' => $total]);
        break;

    case 'actions':
        // 取得所有 action 類型 + 真實總數量（用於篩選下拉顯示完整歷史計數，不受 limit 200 影響）
        $actions = db_query("
            SELECT action, COUNT(*) as cnt 
            FROM audit_logs 
            GROUP BY action 
            ORDER BY action ASC
        ");
        json_success($actions);
        break;

    case 'export':
        // A147：CSV 匯出（尊重所有篩選，無 limit）
        $staff_id = (int)get('staff_id', 0);
        $log_action = trim(get('action', ''));
        $entity_type = trim(get('entity_type', ''));
        $entity_id = (int)get('entity_id', 0);
        $from = get('from', '');
        $to = get('to', '');

        $where = "WHERE 1=1";
        $params = [];

        if ($staff_id > 0) {
            $where .= " AND al.staff_id = ?";
            $params[] = $staff_id;
        }
        if ($log_action !== '') {
            $where .= " AND al.action = ?";
            $params[] = $log_action;
        }
        if ($entity_type !== '') {
            $where .= " AND al.entity_type = ?";
            $params[] = $entity_type;
        }
        if ($entity_id > 0) {
            $where .= " AND al.entity_id = ?";
            $params[] = $entity_id;
        }
        if ($from !== '') {
            $where .= " AND al.created_at >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where .= " AND al.created_at <= ?";
            $params[] = $to . ' 23:59:59';
        }

        $logs = db_query("
            SELECT 
                al.created_at,
                s.name as staff_name,
                al.action,
                al.entity_type,
                al.entity_id,
                al.ip_address,
                al.details
            FROM audit_logs al
            LEFT JOIN staff s ON al.staff_id = s.id
            $where
            ORDER BY al.created_at DESC
        ", $params);

        // 直接輸出 CSV（下載）
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');

        echo "\uFEFF"; // BOM for Excel
        echo "時間,員工,操作,實體類型,實體ID,IP,細節\n";

        foreach ($logs as $log) {
            $detail = $log['details'] ? json_encode(json_decode($log['details'], true), JSON_UNESCAPED_UNICODE) : '';
            echo sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $log['created_at'],
                $log['staff_name'] ?: '系統',
                $log['action'],
                $log['entity_type'] ?: '',
                $log['entity_id'] ?: '',
                $log['ip_address'] ?: '',
                str_replace('"', '""', $detail)
            );
        }
        exit;

    default:
        json_error('未知的操作', 400);
}
