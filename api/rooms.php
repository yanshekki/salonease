<?php
/**
 * SalonEase - 房間管理 API
 * 路徑：/api/rooms.php?action=...
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $status = get('status', '');

        $sql = "SELECT id, name, capacity, is_active, created_at FROM rooms WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$status;
        }

        $sql .= " ORDER BY is_active DESC, name ASC";

        $rooms = db_query($sql, $params);
        json_success($rooms);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $name = trim(post('name'));
        $capacity = (int)post('capacity', 1);

        if (!$name) {
            json_error('房間名稱為必填');
        }

        db_exec(
            "INSERT INTO rooms (name, capacity, is_active) VALUES (?, ?, 1)",
            [$name, $capacity]
        );

        $newId = db_last_id();
        log_activity('room.created', $newId, 'room', [
            'name' => $name,
            'capacity' => $capacity
        ]);

        json_success(['id' => (int)$newId], '房間新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $name = trim(post('name'));
        $capacity = (int)post('capacity', 1);

        if (!$id || !$name) {
            json_error('缺少必要資料');
        }

        db_exec(
            "UPDATE rooms SET name = ?, capacity = ? WHERE id = ?",
            [$name, $capacity, $id]
        );

        log_activity('room.updated', $id, 'room', [
            'name' => $name,
            'capacity' => $capacity
        ]);

        json_success(null, '房間資料已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $newStatus = (int)post('status');

        db_exec("UPDATE rooms SET is_active = ? WHERE id = ?", [$newStatus, $id]);

        log_activity('room.toggled', $id, 'room', [
            'new_status' => $newStatus ? 'active' : 'inactive'
        ]);

        json_success(null, $newStatus ? '房間已啟用' : '房間已停用');
        break;

    default:
        json_error('未知的操作', 400);
}
