<?php
/**
 * SalonEase - 預約管理 API
 * 包含時間衝突檢查（員工 + 房間）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // 取得預約列表
    case 'list':
        $date_from = get('date_from', date('Y-m-d'));
        $date_to   = get('date_to', date('Y-m-d', strtotime('+7 days')));
        $staff_id  = (int)get('staff_id', 0);
        $status    = get('status', '');

        $sql = "SELECT a.*, 
                       c.name AS customer_name, c.phone AS customer_phone,
                       s.name AS staff_name,
                       r.name AS room_name
                FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN staff s ON a.staff_id = s.id
                LEFT JOIN rooms r ON a.room_id = r.id
                WHERE DATE(a.start_time) BETWEEN ? AND ?";

        $params = [$date_from, $date_to];

        if ($staff_id > 0) {
            $sql .= " AND a.staff_id = ?";
            $params[] = $staff_id;
        }
        if ($status !== '') {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.start_time ASC";

        $list = db_query($sql, $params);
        json_success($list);
        break;

    // 取得單一預約詳情（含服務項目）
    case 'get':
        $id = (int)get('id');
        if (!$id) json_error('缺少預約 ID');

        $appt = db_query_one("
            SELECT a.*, 
                   c.name AS customer_name,
                   s.name AS staff_name,
                   r.name AS room_name
            FROM appointments a
            LEFT JOIN customers c ON a.customer_id = c.id
            LEFT JOIN staff s ON a.staff_id = s.id
            LEFT JOIN rooms r ON a.room_id = r.id
            WHERE a.id = ?
        ", [$id]);

        if (!$appt) json_error('找不到該預約', 404);

        // 取得服務項目
        $items = db_query("
            SELECT ai.*, sv.name AS service_name 
            FROM appointment_items ai
            LEFT JOIN services sv ON ai.service_id = sv.id
            WHERE ai.appointment_id = ?
            ORDER BY ai.id
        ", [$id]);

        $appt['items'] = $items;
        json_success($appt);
        break;

    // 新增預約（含衝突檢查）
    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $customer_id = (int)post('customer_id');
        $staff_id    = (int)post('staff_id');
        $room_id     = (int)post('room_id') ?: null;
        $start_time  = post('start_time');
        $end_time    = post('end_time');
        $notes       = sanitize_string(post('notes', ''));
        $services    = $_POST['services'] ?? [];

        if ($err = validate_required($customer_id, '客戶')) json_error($err);
        if ($err = validate_required($staff_id, '美容師')) json_error($err);
        if ($err = validate_required($start_time, '開始時間')) json_error($err);
        if ($err = validate_required($end_time, '結束時間')) json_error($err);
        if ($err = validate_length($notes, '備註', 500)) json_error($err);
        if (!is_array($services) || count($services) === 0) {
            json_error('至少需選擇一項服務');
        }

        // 衝突檢查
        $conflict = check_appointment_conflict(0, $staff_id, $room_id, $start_time, $end_time);
        if ($conflict) {
            json_error('時間衝突：該美容師或房間在指定時間已有其他預約', 409);
        }

        // 使用交易建立預約 + 服務項目
        try {
            $new_id = db_transaction(function($pdo) use ($customer_id, $staff_id, $room_id, $start_time, $end_time, $notes, $services) {
                // 插入主表
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (customer_id, staff_id, room_id, start_time, end_time, status, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmt->execute([
                    $customer_id, $staff_id, $room_id, $start_time, $end_time, $notes,
                    $_SESSION['staff_id'] ?? null
                ]);
                $appt_id = $pdo->lastInsertId();

                // 插入服務項目
                if (is_array($services) && count($services) > 0) {
                    $item_stmt = $pdo->prepare("
                        INSERT INTO appointment_items (appointment_id, service_id, price_at_time, duration_min)
                        SELECT ?, id, price, duration_min FROM services WHERE id = ?
                    ");
                    foreach ($services as $sid) {
                        $item_stmt->execute([$appt_id, (int)$sid]);
                    }
                }
                return (int)$appt_id;
            });

            log_activity('appointment.created', $new_id, 'appointment', [
                'customer_id' => $customer_id,
                'staff_id'    => $staff_id,
                'start_time'  => $start_time
            ]);

            json_success(['id' => $new_id], '預約已建立');
        } catch (Exception $e) {
            json_error('建立失敗：' . $e->getMessage());
        }
        break;

    // 變更狀態
    case 'change_status':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $new_status = post('status');

        $allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
        if (!$id || !in_array($new_status, $allowed)) {
            json_error('狀態不正確');
        }

        db_exec("UPDATE appointments SET status = ? WHERE id = ?", [$new_status, $id]);

        log_activity('appointment.status_changed', $id, 'appointment', [
            'new_status' => $new_status
        ]);

        json_success(null, '狀態已更新');
        break;

    // 更新預約
    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $customer_id = (int)post('customer_id');
        $staff_id    = (int)post('staff_id');
        $room_id     = (int)post('room_id') ?: null;
        $start_time  = post('start_time');
        $end_time    = post('end_time');
        $notes       = trim(post('notes'));
        $services    = $_POST['services'] ?? [];

        if (!$id || !$customer_id || !$staff_id || !$start_time || !$end_time) {
            json_error('資料不完整');
        }

        // 衝突檢查（排除自己）
        $conflict = check_appointment_conflict($id, $staff_id, $room_id, $start_time, $end_time);
        if ($conflict) {
            json_error('時間衝突：該美容師或房間在指定時間已有其他預約', 409);
        }

        try {
            db_transaction(function($pdo) use ($id, $customer_id, $staff_id, $room_id, $start_time, $end_time, $notes, $services) {
                // 更新主表
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET customer_id = ?, staff_id = ?, room_id = ?, start_time = ?, end_time = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$customer_id, $staff_id, $room_id, $start_time, $end_time, $notes, $id]);

                // 刪除舊服務項目
                $pdo->prepare("DELETE FROM appointment_items WHERE appointment_id = ?")->execute([$id]);

                // 插入新服務項目
                if (is_array($services) && count($services) > 0) {
                    $item_stmt = $pdo->prepare("
                        INSERT INTO appointment_items (appointment_id, service_id, price_at_time, duration_min)
                        SELECT ?, id, price, duration_min FROM services WHERE id = ?
                    ");
                    foreach ($services as $sid) {
                        $item_stmt->execute([$id, (int)$sid]);
                    }
                }
            });

            log_activity('appointment.updated', $id, 'appointment', [
                'customer_id' => $customer_id,
                'staff_id'    => $staff_id
            ]);

            json_success(['id' => $id], '預約已更新');
        } catch (Exception $e) {
            json_error('更新失敗：' . $e->getMessage());
        }
        break;

    default:
        json_error('未知的操作', 400);
}

/**
 * 檢查預約時間衝突
 */
function check_appointment_conflict(int $exclude_id, int $staff_id, ?int $room_id, string $start, string $end): bool
{
    $sql = "
        SELECT id FROM appointments 
        WHERE id != ?
          AND status NOT IN ('cancelled', 'no_show')
          AND (
              (staff_id = ? AND start_time < ? AND end_time > ?)
              " . ($room_id ? "OR (room_id = ? AND start_time < ? AND end_time > ?)" : "") . "
          )
        LIMIT 1
    ";

    $params = [$exclude_id, $staff_id, $end, $start];

    if ($room_id) {
        $params[] = $room_id;
        $params[] = $end;
        $params[] = $start;
    }

    $result = db_query($sql, $params);
    return count($result) > 0;
}
