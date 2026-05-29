<?php
/**
 * Phase 5 - 付款計劃提醒自動執行腳本
 *
 * 建議每天早上執行一次，例如：
 * 0 8 * * * php /var/www/html/cron/send_payment_reminders.php >> /var/log/salonease_reminders.log 2>&1
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/functions.php';

// 讀取所有啟用的提醒規則
$rules = db_query("
    SELECT r.id, r.plan_id, r.reminder_type, r.offset_days, r.channel, r.last_sent_at,
           p.status as plan_status, p.start_date, p.total_installments
    FROM plan_reminder_rules r
    JOIN sale_payment_plans p ON p.id = r.plan_id
    WHERE r.is_active = 1 AND p.status = 'active'
");

$sentCount = 0;
$skippedCount = 0;

foreach ($rules as $rule) {
    $result = executePaymentReminder($rule['id']);

    if ($result['success']) {
        $sentCount++;
        echo "[SUCCESS] Rule #{$rule['id']} for Plan #{$rule['plan_id']} - {$result['message']}\n";
    } else {
        $skippedCount++;
        // 只在有意義的時候輸出
        if (strpos($result['message'], '不需要') === false && strpos($result['message'], '已經發送') === false) {
            echo "[SKIPPED] Rule #{$rule['id']} for Plan #{$rule['plan_id']} - {$result['message']}\n";
        }
    }
}

echo "\n執行完成。成功發送：{$sentCount} 筆，跳過：{$skippedCount} 筆\n";
echo "時間：" . date('Y-m-d H:i:s') . "\n";
