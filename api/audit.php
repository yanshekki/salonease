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
        $from = get('from', '');
        $to = get('to', '');
        $limit = min(100, max(10, (int)get('limit', 50)));

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
            WHERE 1=1
        ";
        $params = [];

        if ($staff_id > 0) {
            $sql .= " AND al.staff_id = ?";
            $params[] = $staff_id;
        }

        if ($log_action !== '') {
            $sql .= " AND al.action = ?";
            $params[] = $log_action;
        }

        if ($from !== '') {
            $sql .= " AND al.created_at >= ?";
            $params[] = $from . ' 00:00:00';
        }

        if ($to !== '') {
            $sql .= " AND al.created_at <= ?";
            $params[] = $to . ' 23:59:59';
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT $limit";

        $logs = db_query($sql, $params);
        json_success($logs);
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

    default:
        json_error('未知的操作', 400);
}
