<?php
/**
 * SalonEase - 客戶自助服務 Portal（Phase 8）
 * 
 * 客戶可透過安全 token 連結查看自己的付款計劃、進度、歷史及即將到期提醒。
 * 無需登入，支援記錄付款（後續迭代）。
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/db.php';

$token = $_GET['token'] ?? '';
$customer = null;
$error = null;

if ($token) {
    $customer = validateCustomerPortalToken($token);
    if (!$customer) {
        $error = '連結已失效或無效。請聯絡店舖重新索取最新連結。';
    }
} else {
    $error = '缺少存取連結。請使用提醒電郵或 SMS 中的連結。';
}

$pageTitle = $customer ? ($customer['name'] . ' 的付款計劃') : '客戶自助服務 Portal';
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - SalonEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .portal-header { background: linear-gradient(135deg, #0d6efd, #6610f2); color: white; }
        .plan-card { transition: transform 0.2s; }
        .plan-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if ($error): ?>
            <div class="alert alert-danger text-center">
                <h4>存取失敗</h4>
                <p><?= e($error) ?></p>
                <p class="small text-muted">如有疑問，請聯絡店舖。</p>
            </div>
        <?php else: ?>
            <!-- Header -->
            <div class="portal-header rounded-3 p-4 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1"><?= e($customer['name']) ?> 你好</h1>
                        <p class="mb-0 opacity-75">這是你的付款計劃自助查詢頁面</p>
                    </div>
                    <div class="text-end">
                        <div class="small opacity-75">電話：<?= e($customer['phone']) ?></div>
                        <?php if ($customer['email']): ?>
                            <div class="small opacity-75"><?= e($customer['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- 活躍計劃 -->
                <div class="col-12 col-lg-8 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">我的付款計劃</h5>
                        </div>
                        <div class="card-body">
                            <div id="active-plans">
                                <div class="text-center py-4 text-muted">載入中...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 側邊資訊 -->
                <div class="col-12 col-lg-4 mb-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">即將到期提醒</h6>
                        </div>
                        <div class="card-body small" id="upcoming-reminders">
                            <div class="text-muted">載入中...</div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <p class="small text-muted mb-2">需要記錄付款或有疑問？</p>
                            <a href="https://wa.me/852<?= preg_replace('/[^0-9]/', '', $customer['phone']) ?>" 
                               class="btn btn-success btn-sm w-100" target="_blank">
                                聯絡店舖
                            </a>
                            <div class="tiny text-muted mt-2">或直接到店舖</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 歷史計劃（可展開） -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">歷史計劃</h6>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleHistory()">顯示 / 隱藏</button>
                </div>
                <div class="card-body d-none" id="history-plans">
                    <div class="text-center py-3 text-muted">載入中...</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <small class="text-muted">SalonEase 客戶自助服務 • 連結有效期有限，請妥善保管</small>
        </div>
    </div>

    <script>
    const token = '<?= e($token) ?>';
    const customerId = <?= $customer ? (int)$customer['customer_id'] : 0 ?>;

    async function loadPlans() {
        if (!customerId || !token) return;

        try {
            // 使用現有 API + token 驗證（後續會加強為專用端點）
            const res = await fetch(`/api/payment_plans.php?action=list&customer_id=${customerId}&status=active&token=${encodeURIComponent(token)}`);
            const data = await res.json();

            const container = document.getElementById('active-plans');
            if (!data.success || !data.data || data.data.length === 0) {
                container.innerHTML = `<div class="text-muted small">目前沒有進行中的付款計劃。</div>`;
                return;
            }

            let html = '';
            data.data.forEach(plan => {
                const progress = plan.progress || 0;
                html += `
                    <div class="plan-card border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>計劃 #${plan.id}</strong>
                                <span class="badge bg-${plan.status === 'active' ? 'primary' : 'secondary'} ms-1">${plan.status}</span>
                            </div>
                            <div class="text-end">
                                <div class="small">進度 <strong>${progress}%</strong></div>
                                <div class="progress" style="height:6px; width:120px;">
                                    <div class="progress-bar" style="width:${progress}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="small mt-2 text-muted">
                            每期：$${parseFloat(plan.installment_amount).toFixed(0)} × ${plan.total_installments} 期
                            <br>已付：${plan.payments_made || 0} / ${plan.total_installments}
                        </div>
                        <div class="mt-2">
                            <a href="#" class="btn btn-sm btn-outline-primary" onclick="viewPlanDetail(${plan.id}); return false;">
                                查看詳情
                            </a>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } catch (e) {
            document.getElementById('active-plans').innerHTML = `<div class="text-danger small">載入計劃失敗</div>`;
        }
    }

    async function loadUpcomingReminders() {
        // 簡易版：顯示最近的提醒記錄（後續可優化為預測下次提醒）
        const container = document.getElementById('upcoming-reminders');
        container.innerHTML = `<div class="text-muted small">如有即將到期提醒，店舖會透過電郵/SMS通知你。</div>`;
    }

    function toggleHistory() {
        const el = document.getElementById('history-plans');
        el.classList.toggle('d-none');
        if (!el.classList.contains('d-none') && el.innerHTML.includes('載入中')) {
            // TODO: 載入歷史計劃
            el.innerHTML = `<div class="text-muted small">歷史計劃功能即將推出。</div>`;
        }
    }

    function viewPlanDetail(planId) {
        // 簡單跳轉詳情（後續可做 Modal 或獨立頁）
        window.location.href = `?token=${encodeURIComponent(token)}&plan_id=${planId}`;
    }

    // 初始載入
    if (customerId && token) {
        loadPlans();
        loadUpcomingReminders();
    }
    </script>
</body>
</html>