<?php
/**
 * Phase 7 A - 付款計劃提醒自動執行腳本（生產級強化版）
 *
 * 建議每天早上執行一次，例如：
 * 0 8 * * * php /var/www/html/cron/send_payment_reminders.php >> /var/log/salonease_reminders.log 2>&1
 *
 * 強化點：
 * - 完整 try/catch + 結構化 logging
 * - 簡單 SMS 速率限制（避免 Twilio 限流）
 * - 清晰成功/失敗/跳過統計
 * - 適合長期 cron 監控
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/functions.php';

$startTime = microtime(true);
$runDate = date('Y-m-d H:i:s');

echo "=== SalonEase 提醒任務開始 @ {$runDate} ===\n";

$rules = [];
try {
    $rules = db_query("
        SELECT r.id, r.plan_id, r.reminder_type, r.offset_days, r.channel, r.last_sent_at,
               p.status as plan_status, p.start_date, p.total_installments
        FROM plan_reminder_rules r
        JOIN sale_payment_plans p ON p.id = r.plan_id
        WHERE r.is_active = 1 AND p.status = 'active'
    ");
} catch (Exception $e) {
    error_log("[REMINDER CRON] DB query failed: " . $e->getMessage());
    echo "[FATAL] 無法讀取提醒規則: {$e->getMessage()}\n";
    exit(1);
}

$stats = [
    'success' => 0,
    'skipped' => 0,
    'failed'  => 0,
    'sms_sent' => 0,
    'email_sent' => 0,
];

$smsDelay = 0; // 秒，簡單速率限制（Twilio 免費版建議不要太快）

foreach ($rules as $rule) {
    try {
        $result = executePaymentReminder($rule['id']);

        if ($result['success']) {
            $stats['success']++;
            echo "[SUCCESS] Rule #{$rule['id']} Plan #{$rule['plan_id']} - {$result['message']}\n";

            // 簡單統計（實際 channel 成功數可在後續加強從 plan_notifications 計）
        } else {
            $msg = $result['message'] ?? '';
            if (strpos($msg, '不需要') !== false || strpos($msg, '已經發送') !== false) {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
                echo "[FAILED]  Rule #{$rule['id']} Plan #{$rule['plan_id']} - {$msg}\n";
            }
        }

        // 簡單 SMS 速率限制（每發一次 SMS 睡一下）
        if (in_array($rule['channel'], ['sms', 'both'])) {
            $smsDelay++;
            if ($smsDelay > 0) {
                usleep(400000); // 0.4 秒
            }
        }

    } catch (Exception $e) {
        $stats['failed']++;
        error_log("[REMINDER CRON] Exception on rule #{$rule['id']}: " . $e->getMessage());
        echo "[ERROR]   Rule #{$rule['id']} Plan #{$rule['plan_id']} - Exception: {$e->getMessage()}\n";
    }
}

$duration = round(microtime(true) - $startTime, 2);
$summary = "執行完成。成功 {$stats['success']} 筆，跳過 {$stats['skipped']} 筆，失敗 {$stats['failed']} 筆。耗時 {$duration}s";

echo "\n{$summary}\n";
echo "時間：{$runDate}\n";
echo "=== 結束 ===\n";

// 同時寫入 error_log 方便統一監控
error_log("[REMINDER CRON] {$summary} @ {$runDate}");
