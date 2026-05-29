<?php
/**
 * SalonEase - 記錄補款 / 多筆付款（Phase 2）
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = '記錄補款';
$pageSubtitle = '為已開立的銷售單補錄付款（支援一單多付）';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">選擇銷售單</h5>

                <div class="mb-3">
                    <label class="form-label">銷售單編號</label>
                    <div class="input-group">
                        <input type="number" id="sale-id-input" class="form-control" placeholder="輸入銷售單 ID">
                        <button class="btn btn-outline-primary" onclick="loadSale()">載入</button>
                    </div>
                </div>

                <div id="sale-info" class="d-none border rounded p-3 bg-light">
                    <div class="mb-2">
                        <strong>銷售單 #<span id="sale-id"></span></strong>
                    </div>
                    <div class="row small">
                        <div class="col-6">總金額：<span id="sale-total"></span></div>
                        <div class="col-6">已付：<span id="sale-paid" class="fw-bold"></span></div>
                    </div>
                    <div class="mt-2">
                        狀態：<span id="sale-status" class="badge"></span>
                    </div>
                </div>

                <!-- Phase 4 A：此銷售單的付款計劃入口整合 -->
                <div id="sale-plans-summary" class="d-none mt-3 border rounded p-2 bg-white">
                    <div class="small fw-semibold mb-1" title="此銷售單目前所有活躍的分期或周期性付款計劃">此銷售單的付款計劃</div>
                    <div id="sale-plans-list" class="small"></div>
                    <div class="mt-1 small text-muted" style="font-size: 0.75rem;">記錄付款後會自動更新進度。如需跟進或調整計劃，請使用快速行動按鈕。</div>
                    <div class="mt-2">
                        <a href="/payment_plans.php" class="small text-decoration-none">去計劃管理頁 →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">記錄新付款</h5>

                <div id="payment-form" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">付款方法</label>
                            <select id="payment-method-id" class="form-select">
                                <!-- 由 JS 載入 -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">付款金額</label>
                            <input type="number" id="payment-amount" class="form-control" step="0.01" placeholder="0.00">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">參考編號（可選）</label>
                            <input type="text" id="ref-number" class="form-control" placeholder="FPS 參考號 / 交易單號">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">備註（可選）</label>
                            <input type="text" id="payment-notes" class="form-control" placeholder="例如：客戶補款">
                        </div>

                        <!-- Phase 3: 連結分期計劃 -->
                        <div class="col-12" id="plan-selection-area" style="display: none;">
                            <label class="form-label">連結付款計劃（可選）</label>
                            <select id="plan-id" class="form-select" onchange="toggleInstallmentField()">
                                <option value="">不連結計劃</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="installment-area" style="display: none;">
                            <label class="form-label">這是第幾期</label>
                            <input type="number" id="installment-no" class="form-control" min="1" placeholder="例如：2">
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button onclick="recordPayment()" class="btn btn-primary">確認記錄付款</button>
                        <button onclick="clearForm()" class="btn btn-outline-secondary">清除</button>
                    </div>
                </div>

                <div id="payment-result" class="mt-3 d-none alert"></div>
            </div>
        </div>

        <!-- 最近付款記錄 -->
        <div class="card mt-4 d-none" id="recent-payments-card">
            <div class="card-body">
                <h6 class="card-title">最近付款記錄</h6>
                <div id="recent-payments-list" class="small"></div>
            </div>
        </div>
    </div>
</div>

<script>
window.CSRF_TOKEN = '<?= csrf_token() ?>';

let currentSale = null;

document.addEventListener('DOMContentLoaded', () => {
    // Phase 3 C1: 支援從 URL 自動載入銷售單 (e.g. ?sale_id=123)
    const urlParams = new URLSearchParams(window.location.search);
    const saleIdFromUrl = urlParams.get('sale_id');
    if (saleIdFromUrl) {
        document.getElementById('sale-id-input').value = saleIdFromUrl;
        loadSale();
    }
});

async function loadSale() {
    const saleId = document.getElementById('sale-id-input').value.trim();
    if (!saleId) {
        alert('請輸入銷售單編號');
        return;
    }

    try {
        const res = await SalonEase.fetch(`/api/sales.php?action=get&id=${saleId}`);
        currentSale = res.data;

        document.getElementById('sale-id').textContent = currentSale.id;
        document.getElementById('sale-total').textContent = 'HK$ ' + parseFloat(currentSale.total).toFixed(2);
        document.getElementById('sale-paid').textContent = 'HK$ ' + parseFloat(currentSale.amount_paid || 0).toFixed(2);

        const statusEl = document.getElementById('sale-status');
        statusEl.textContent = getStatusText(currentSale.payment_status);
        statusEl.className = 'badge ' + getStatusBadgeClass(currentSale.payment_status);

        document.getElementById('sale-info').classList.remove('d-none');
        document.getElementById('payment-form').classList.remove('d-none');

        // 載入付款方法
        await loadPaymentMethods();

        // 載入最近付款記錄
        await loadRecentPayments(saleId);

        // Phase 3: 載入該銷售單的分期計劃
        await loadPlansForSale(saleId);

    } catch (err) {
        alert('載入失敗：' + err.message);
    }
}

async function loadPaymentMethods() {
    try {
        const res = await SalonEase.fetch('/api/payment_methods.php?action=list&active=1');
        const select = document.getElementById('payment-method-id');
        select.innerHTML = '';

        res.data.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
            select.appendChild(opt);
        });
    } catch (err) {
        console.error('載入付款方法失敗', err);
    }
}

async function loadRecentPayments(saleId) {
    try {
        const res = await SalonEase.fetch(`/api/payments.php?action=list_by_sale&sale_id=${saleId}`);
        const container = document.getElementById('recent-payments-list');
        container.innerHTML = '';

        if (!res.data || res.data.length === 0) {
            container.innerHTML = '<div class="text-muted">尚無付款記錄</div>';
            document.getElementById('recent-payments-card').classList.remove('d-none');
            return;
        }

        let html = '<ul class="list-unstyled mb-0">';
        res.data.forEach(p => {
            const sign = p.is_refund ? '-' : '';
            html += `
                <li class="mb-1">
                    ${sign}HK$ ${parseFloat(p.amount).toFixed(2)} 
                    <span class="text-muted">(${p.payment_method_name})</span>
                    ${p.ref_number ? `<small class="text-muted">[${p.ref_number}]</small>` : ''}
                </li>
            `;
        });
        html += '</ul>';
        container.innerHTML = html;
        document.getElementById('recent-payments-card').classList.remove('d-none');
    } catch (err) {
        console.error(err);
    }
}

function clearForm() {
    document.getElementById('payment-amount').value = '';
    document.getElementById('ref-number').value = '';
    document.getElementById('payment-notes').value = '';
}

function getStatusText(status) {
    const map = {
        'unpaid': '未付',
        'partial': '部分已付',
        'paid': '已付清',
        'overpaid': '超付',
        'refunded': '已退款'
    };
    return map[status] || status;
}

function getStatusBadgeClass(status) {
    if (status === 'paid') return 'bg-success';
    if (status === 'partial') return 'bg-warning text-dark';
    if (status === 'unpaid') return 'bg-danger';
    return 'bg-secondary';
}

// ===== Phase 3: 分期計劃相關 =====
let currentPlans = [];

async function loadPlansForSale(saleId) {
    const planSelect = document.getElementById('plan-id');
    const planArea = document.getElementById('plan-selection-area');
    const installmentArea = document.getElementById('installment-area');
    const summaryCard = document.getElementById('sale-plans-summary');
    const summaryList = document.getElementById('sale-plans-list');

    planSelect.innerHTML = '<option value="">不連結計劃</option>';
    currentPlans = [];

    try {
        const res = await SalonEase.fetch(`/api/payment_plans.php?action=list_by_sale&sale_id=${saleId}`);
        currentPlans = res.data || [];

        // 1. 供「記錄付款時連結計劃」使用
        if (currentPlans.length > 0) {
            currentPlans.forEach(plan => {
                const opt = document.createElement('option');
                opt.value = plan.id;
                opt.textContent = `${plan.plan_type === 'installment' ? '分期' : '周期性'} - ${plan.total_installments}期 × ${plan.installment_amount} (狀態: ${plan.status})`;
                planSelect.appendChild(opt);
            });
            planArea.style.display = '';
        } else {
            planArea.style.display = 'none';
            installmentArea.style.display = 'none';
        }

        // 2. Phase 4 A：右側顯眼總結（只顯示活躍計劃，方便日常操作）
        const activePlans = currentPlans.filter(p => p.status === 'active');
        if (activePlans.length > 0 && summaryCard && summaryList) {
            let html = '';
            activePlans.forEach(plan => {
                const made = parseInt(plan.payments_made || 0);
                const total = parseInt(plan.total_installments || 1);
                const progress = total > 0 ? Math.round((made / total) * 100) : 0;
                const planId = plan.id;

                html += `
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div>
                            <strong>#${planId}</strong> ${plan.plan_type === 'installment' ? '分期' : '周期性'}
                            <span class="badge bg-secondary ms-1">${made}/${total} (${progress}%)</span>
                        </div>
                        <div class="btn-group btn-group-sm mt-1">
                            <button type="button" class="btn btn-outline-primary" 
                                    onclick="quickFollowupFromRecord(${planId}, this)">快速跟進</button>
                            <button type="button" class="btn btn-outline-success" 
                                    onclick="window.location.href='/payment_plans.php'">管理計劃</button>
                        </div>
                    </div>
                `;
            });
            summaryList.innerHTML = html;
            summaryCard.classList.remove('d-none');
        } else if (summaryCard) {
            summaryCard.classList.add('d-none');
        }
    } catch (err) {
        console.warn('載入付款計劃失敗', err);
        planArea.style.display = 'none';
        if (summaryCard) summaryCard.classList.add('d-none');
    }
}

function toggleInstallmentField() {
    const planId = document.getElementById('plan-id').value;
    const installmentArea = document.getElementById('installment-area');
    installmentArea.style.display = planId ? '' : 'none';
}

// Phase 4 A：從 record_payment 頁快速跟進計劃（入口整合）
async function quickFollowupFromRecord(planId, btnElement) {
    const originalText = btnElement.textContent;
    btnElement.disabled = true;
    btnElement.textContent = '處理中...';

    try {
        await SalonEase.fetch('/api/payment_plans.php?action=append_followup', {
            method: 'POST',
            body: new URLSearchParams({
                plan_id: planId,
                note: 'record_payment 頁快速跟進',
                csrf_token: window.CSRF_TOKEN
            })
        });

        btnElement.textContent = '已跟進 ✓';
        btnElement.classList.remove('btn-outline-primary');
        btnElement.classList.add('btn-success');

        // 刷新計劃總結（零刷新）
        setTimeout(async () => {
            if (currentSale && currentSale.id) {
                await loadPlansForSale(currentSale.id);
            }
        }, 600);

    } catch (err) {
        alert('快速跟進失敗：' + err.message);
        btnElement.textContent = originalText;
        btnElement.disabled = false;
    }
}

async function recordPayment() {
    if (!currentSale) {
        alert('請先載入銷售單');
        return;
    }

    const amount = parseFloat(document.getElementById('payment-amount').value);
    if (!amount || amount <= 0) {
        alert('請輸入有效的付款金額');
        return;
    }

    const payload = {
        sale_id: currentSale.id,
        payment_method_id: parseInt(document.getElementById('payment-method-id').value),
        amount: amount,
        ref_number: document.getElementById('ref-number').value.trim(),
        notes: document.getElementById('payment-notes').value.trim(),
        plan_id: document.getElementById('plan-id').value ? parseInt(document.getElementById('plan-id').value) : null,
        installment_no: document.getElementById('installment-no').value ? parseInt(document.getElementById('installment-no').value) : null,
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const res = await SalonEase.fetch('/api/payments.php?action=record', {
            method: 'POST',
            body: payload
        });

        alert('付款記錄成功！');
        // 重新載入銷售單資訊
        document.getElementById('sale-id-input').value = currentSale.id;
        await loadSale();

        // 清空表單
        document.getElementById('payment-amount').value = '';
        document.getElementById('ref-number').value = '';
        document.getElementById('payment-notes').value = '';
        document.getElementById('plan-id').value = '';
        document.getElementById('installment-no').value = '';
        document.getElementById('installment-area').style.display = 'none';

    } catch (err) {
        alert('記錄失敗：' + err.message);
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
