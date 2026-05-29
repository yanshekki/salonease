<?php
/**
 * SalonEase - 付款計劃提醒規則 API（Phase 5）
 *
 * GET  /api/plan_reminders.php?action=list&plan_id=xxx
 * POST /api/plan_reminders.php?action=create
 * POST /api/plan_reminders.php?action=update
 * POST /api/plan_reminders.php?action=delete
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $planId = (int)get('plan_id', 0);
        if ($planId <= 0) json_error('缺少 plan_id');

        $rules = db_query("
            SELECT * FROM plan_reminder_rules 
            WHERE plan_id = ? 
            ORDER BY created_at DESC
        ", [$planId]);

        json_success($rules);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $planId = (int)post('plan_id');
        $reminderType = post('reminder_type', 'before_due');
        $offsetDays = max(1, (int)post('offset_days', 3));
        $channel = post('channel', 'email');

        if ($planId <= 0) json_error('缺少 plan_id');
        if (!in_array($reminderType, ['before_due', 'after_due'])) json_error('reminder_type 無效');
        if (!in_array($channel, ['email', 'sms', 'both'])) json_error('channel 無效');

        $id = db_exec("
            INSERT INTO plan_reminder_rules 
            (plan_id, reminder_type, offset_days, channel, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ", [$planId, $reminderType, $offsetDays, $channel]);

        log_activity('plan_reminder.rule_created', $planId, 'sale_payment_plan', [
            'reminder_type' => $reminderType,
            'offset_days' => $offsetDays,
            'channel' => $channel
        ]);

        json_success(['id' => $id], '提醒規則已建立');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $ruleId = (int)post('id');
        $offsetDays = max(1, (int)post('offset_days', 3));
        $channel = post('channel', 'email');
        $isActive = (int)post('is_active', 1);

        if ($ruleId <= 0) json_error('缺少規則 ID');
        if (!in_array($channel, ['email', 'sms', 'both'])) json_error('channel 無效');

        $affected = db_exec("
            UPDATE plan_reminder_rules 
            SET offset_days = ?, channel = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ", [$offsetDays, $channel, $isActive, $ruleId]);

        if ($affected === 0) json_error('更新失敗或無變更');

        json_success(null, '提醒規則已更新');
        break;

    case 'delete':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $ruleId = (int)post('id');
        if ($ruleId <= 0) json_error('缺少規則 ID');

        $affected = db_exec("DELETE FROM plan_reminder_rules WHERE id = ?", [$ruleId]);
        
        if ($affected === 0) json_error('刪除失敗');

        json_success(null, '提醒規則已刪除');
        break;

    case 'execute':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $ruleId = (int)post('id');
        if ($ruleId <= 0) json_error('缺少規則 ID');

        $result = executePaymentReminder($ruleId);

        if ($result['success']) {
            json_success($result, $result['message']);
        } else {
            json_error($result['message'], 400, $result);
        }
        break;

    case 'list_notifications':
        $planId = (int)get('plan_id', 0);
        if ($planId <= 0) json_error('缺少 plan_id');

        $notifications = db_query("
            SELECT * FROM plan_notifications 
            WHERE plan_id = ? 
            ORDER BY sent_at DESC 
            LIMIT 20
        ", [$planId]);

        json_success($notifications);
        break;

    case 'run_scheduled':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        // 只有 admin / manager 可以手動觸發全量執行
        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以執行此操作', 403);
        }

        $rules = db_query("
            SELECT r.id 
            FROM plan_reminder_rules r
            JOIN sale_payment_plans p ON p.id = r.plan_id
            WHERE r.is_active = 1 AND p.status = 'active'
        ");

        $results = ['success' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rules as $r) {
            $res = executePaymentReminder($r['id']);
            if ($res['success']) {
                $results['success']++;
            } else {
                $results['skipped']++;
                if (strpos($res['message'], '不需要') === false && strpos($res['message'], '已經發送') === false) {
                    $results['errors'][] = "Rule #{$r['id']}: " . $res['message'];
                }
            }
        }

        json_success($results, "全量提醒檢查完成：成功 {$results['success']} 筆，跳過 {$results['skipped']} 筆");
        break;

    default:
        json_error('未知 action');
}
