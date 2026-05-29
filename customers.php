<?php
/**
 * SalonEase - 客戶管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = '客戶管理';
$pageSubtitle = '管理客戶資料、查看消費記錄';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary d-flex align-items-center gap-2">
        <span>+ 新增客戶</span>
        <span class="small opacity-75">[N]</span>
    </button>
</div>

<!-- A26：目前忠誠度規則提示 -->
<div class="card mb-3 bg-light border-0">
    <div class="card-body py-2 small">
        <div class="d-flex flex-wrap align-items-center gap-4">
            <div>
                <span class="text-muted">累積率：</span>
                <strong id="customer-earn-rate">10</strong> 元 = 1 點
            </div>
            <div>
                <span class="text-muted">兌換率：</span>
                <strong id="customer-redemption-rate">10</strong> 點 = $1
            </div>
            <div class="text-muted">（可於「系統設定」頁調整）</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label small text-muted mb-1">搜尋客戶（姓名 / 電話 / 電郵）</label>
                <input type="text" id="search" class="form-control" placeholder="輸入電話最快..." oninput="debounceLoadCustomers()">
            </div>
            <div class="col-12 col-md-auto">
                <button onclick="loadCustomers()" class="btn btn-outline-secondary w-100">搜尋</button>
            </div>
            <div class="col-12 col-md-auto">
                <select id="sort" class="form-select" onchange="loadCustomers()" style="width: 140px;">
                    <option value="recent">最近到訪</option>
                    <option value="points_desc">積分由高到低</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>姓名</th>
                    <th>電話</th>
                    <th class="d-none d-md-table-cell">電郵</th>
                    <th class="d-none d-md-table-cell">最近到訪</th>
                    <th class="text-end">累計消費</th>
                    <th class="text-center">積分</th>
                    <th class="text-center">到訪次數</th>
                    <th class="text-center">最近付款</th>
                    <th class="text-center">活躍計劃</th>
                    <th class="text-end" style="width: 90px;">操作</th>
                </tr>
            </thead>
            <tbody id="customers-list">
                <tr><td colspan="8" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 新增 / 編輯 Modal (Bootstrap) -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">新增客戶</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="customer-id">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">姓名 <span class="text-danger">*</span></label>
                        <input type="text" id="customer-name" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">電話 <span class="text-danger">*</span></label>
                        <input type="text" id="customer-phone" class="form-control" placeholder="91234567">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">電郵</label>
                    <input type="email" id="customer-email" class="form-control">
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">性別</label>
                        <select id="customer-gender" class="form-select">
                            <option value="">未填</option>
                            <option value="F">女</option>
                            <option value="M">男</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">生日</label>
                        <input type="date" id="customer-birthday" class="form-control">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">備註</label>
                    <textarea id="customer-notes" rows="2" class="form-control" placeholder="過敏、偏好等..."></textarea>
                </div>

                <!-- A36：最近積分異動 -->
                <div id="points-history-section" class="mt-3 d-none">
                    <label class="form-label small text-muted">最近積分異動（最多 6 筆）</label>
                    <div id="points-history-list" class="small border rounded p-2 bg-light" style="max-height: 110px; overflow-y: auto; font-size: 0.75rem;">
                        <!-- JS 動態填入 -->
                    </div>
                    <div class="small mt-1">
                        <a href="/loyalty.php" class="text-muted text-decoration-none">查看完整歷史 →</a>
                    </div>
                </div>

                <!-- Phase 3: 最近付款歷史 -->
                <div id="payments-history-section" class="mt-3 d-none">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small text-muted mb-0">付款歷史</label>
                        <div id="payments-summary" class="small text-muted"></div>
                    </div>
                    <div id="payments-history-list" class="small border rounded p-2 bg-light" style="max-height: 140px; overflow-y: auto; font-size: 0.75rem;">
                        <!-- JS 動態填入 -->
                    </div>
                    <div class="small mt-1">
                        <a href="/record_payment.php" class="text-muted text-decoration-none">記錄補款 →</a>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="saveCustomer()" class="btn btn-primary" id="save-btn">新增客戶</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadCustomers();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增客戶' },
            { key: '/', desc: '聚焦搜尋框' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }

    // 按 / 聚焦搜尋
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement.tagName === 'BODY') {
            e.preventDefault();
            document.getElementById('search').focus();
        }
    });
});

let debounceTimer;
function debounceLoadCustomers() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadCustomers, 300);
}

async function loadCustomers() {
    const tbody = document.getElementById('customers-list');
    tbody.innerHTML = `<tr><td colspan="8" class="py-5 text-center text-muted">載入中...</td></tr>`;

    const search = document.getElementById('search').value;
    const sortEl = document.getElementById('sort');
    const sort = sortEl ? sortEl.value : 'recent';

    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=list&search=${encodeURIComponent(search)}&sort=${sort}`);
        renderCustomersTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-5 text-center text-danger">${err.message}</td></tr>`;
    }
}

function renderCustomersTable(list) {
    const tbody = document.getElementById('customers-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="py-5 text-center text-muted">沒有符合的客戶</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(c => {
        const lastVisit = c.last_visit_at ? formatDate(c.last_visit_at) : '-';
        const spent = parseFloat(c.total_spent || 0).toFixed(0);

        const points = c.points || 0;

        // Phase 3 C10: 根據 active_plan_summary 嘅「已付 X/Y」計算進度，動態轉色
        let planBadgeClass = 'bg-primary';
        if (c.active_plans_count > 0 && c.active_plan_summary) {
            const m = c.active_plan_summary.match(/已付\s*(\d+)\s*\/\s*(\d+)/);
            if (m) {
                const paid = parseInt(m[1], 10);
                const total = parseInt(m[2], 10);
                if (total > 0) {
                    const pct = (paid / total) * 100;
                    if (pct >= 80) planBadgeClass = 'bg-success';
                    else if (pct >= 40) planBadgeClass = 'bg-warning text-dark';
                    else planBadgeClass = 'bg-danger';
                }
            }
        }

        html += `
            <tr>
                <td class="fw-medium">${e(c.name)}</td>
                <td class="font-mono small">${e(c.phone)}</td>
                <td class="small text-muted">${e(c.email || '-')}</td>
                <td class="small">${lastVisit}</td>
                <td class="text-end fw-medium">HK$ ${spent}</td>
                <td class="text-center">
                    <span class="badge ${points > 0 ? 'bg-success' : 'bg-secondary'}">${points}</span>
                </td>
                <td class="text-center">${c.visit_count || 0}</td>
                <td class="text-center small text-muted">
                    ${c.last_payment_at ? formatDate(c.last_payment_at) : '-'}
                </td>
                <td class="text-center"
                    ${c.active_plans_count > 0 
                        ? `style="cursor:pointer;" 
                           data-bs-toggle="tooltip" 
                           data-bs-placement="top"
                           data-bs-html="true"
                           title="${c.active_plan_summary || '有活躍計劃'}"
                           onclick="showCustomerActivePlans(${c.id}, event)"`
                        : ''}>
                    ${c.active_plans_count > 0 
                        ? `<span class="badge ${planBadgeClass}">${c.active_plans_count}</span>` 
                        : '<span class="text-muted">0</span>'}
                </td>
                <td class="text-end">
                    <button onclick="editCustomer(${c.id})" class="btn btn-link btn-sm text-success p-0">編輯</button>
                    <button onclick="generatePortalLinkForCustomer(${c.id}, this)" class="btn btn-link btn-sm text-primary p-0 ms-1">Portal</button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;

    // Phase 3 C11: 初始化 tooltips（而家係整個 <td> 做 trigger，click/hover 範圍更大；C9 豐富內容處理保留）
    const tooltipTriggerList = [].slice.call(tbody.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        // C9 額外：把「已付進度」拆成第二行，同時突出每期金額，讓 hover 更易讀
        const raw = tooltipTriggerEl.getAttribute('title') || '';
        if (raw.includes(' (已付 ')) {
            tooltipTriggerEl.setAttribute('title', raw.replace(' (已付 ', '<br><small class="text-muted">已付進度</small> '));
        }
        new bootstrap.Tooltip(tooltipTriggerEl, { html: true });
    });
}

function formatDate(dt) {
    const d = new Date(dt);
    return d.toLocaleDateString('zh-HK', { month: '2-digit', day: '2-digit' });
}

// Phase 3 C1: 跳轉到 record_payment.php 並預載銷售單
function goToRecordPayment(saleId) {
    window.location.href = `/record_payment.php?sale_id=${saleId}`;
}

// Phase 3 C3: 在 Modal 內展開更多付款歷史
async function loadMoreCustomerPayments(customerId, linkElement) {
    const paymentsList = document.getElementById('payments-history-list');
    if (!paymentsList) return;

    linkElement.innerHTML = '載入中...';
    linkElement.style.pointerEvents = 'none';

    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=get&id=${customerId}&payments_limit=30`);
        const c = res.data;

        const payments = c.recent_payments || [];
        const totalCount = c.payments_total_count || 0;

        let html = '<table class="table table-sm mb-0" style="font-size:0.7rem;"><tbody>';
        payments.forEach(p => {
            const isRefund = p.is_refund == 1;
            const sign = isRefund ? '-' : '';
            const amountClass = isRefund ? 'text-danger' : 'fw-medium';

            let feeHtml = '';
            if (p.fee_amount && parseFloat(p.fee_amount) > 0) {
                const feeSign = p.fee_borne_by === 'customer' ? '' : '商戶承擔 ';
                feeHtml = `<span class="text-muted" style="font-size:0.6rem;">(${feeSign}手續費 ${sign}HK$ ${parseFloat(p.fee_amount).toFixed(0)})</span>`;
            }

            let planHtml = '';
            if (p.plan_type) {
                const planLabel = p.plan_type === 'installment' ? '分期' : '周期性';
                planHtml = ` <span class="badge bg-info-subtle text-info" style="font-size:0.55rem;">${planLabel}</span>`;
                if (p.installment_no && p.total_installments) {
                    planHtml += ` <span class="text-muted" style="font-size:0.6rem;">#${p.installment_no}/${p.total_installments}</span>`;
                } else if (p.installment_no) {
                    planHtml += ` <span class="text-muted" style="font-size:0.6rem;">#${p.installment_no}</span>`;
                }
            }

            const refundBadge = isRefund 
                ? `<span class="badge bg-danger-subtle text-danger ms-1" style="font-size:0.55rem;">退款</span>` 
                : '';

            html += `
                <tr style="cursor:pointer;" onclick="goToRecordPayment(${p.sale_id})">
                    <td class="text-nowrap">${p.paid_at ? p.paid_at.substring(0,16).replace('T',' ') : p.sale_date}</td>
                    <td class="${amountClass}">${sign}HK$ ${parseFloat(p.amount).toFixed(0)} ${feeHtml}</td>
                    <td class="text-muted small">${p.payment_method_name}${refundBadge}</td>
                    <td>${planHtml}</td>
                </tr>`;
        });
        html += '</tbody></table>';
        html += `<div class="small text-muted mt-1">...共 ${totalCount} 筆 <a href="/record_payment.php" class="text-muted text-decoration-none">記錄補款 →</a></div>`;
        paymentsList.innerHTML = html;

    } catch (err) {
        linkElement.innerHTML = '載入失敗，請重試';
        linkElement.style.pointerEvents = 'auto';
        console.error(err);
    }
}

// Phase 3 C6: 點擊活躍計劃數，彈出該客戶目前所有活躍計劃清單
async function showCustomerActivePlans(customerId, event) {
    event.stopImmediatePropagation();

    const modalId = 'activePlansModal';
    let modalEl = document.getElementById(modalId);

    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = modalId;
        modalEl.className = 'modal fade';
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title">該客戶活躍計劃</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="active-plans-body" style="max-height: 300px; overflow-y: auto;">
                        載入中...
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();

    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=get_active_plans&customer_id=${customerId}`);
        const plans = res.data || [];

        const body = modalEl.querySelector('#active-plans-body');

        if (plans.length === 0) {
            body.innerHTML = '<div class="text-muted small">沒有活躍計劃</div>';
            return;
        }

        let html = '<ul class="list-unstyled mb-0 small">';
        plans.forEach(plan => {
            const progress = plan.sale_total > 0 ? Math.round((plan.paid_amount / plan.sale_total) * 100) : 0;
            const planId = plan.id;
            html += `
                <li class="mb-2 p-2 border rounded">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="cursor:pointer;" onclick="goToRecordPayment(${plan.sale_id})">
                            <strong>銷售單 #${plan.sale_id}</strong> (${plan.plan_type})<br>
                            <span class="text-muted">每期 HK$ ${parseFloat(plan.installment_amount).toFixed(0)} × ${plan.total_installments} 期</span><br>
                            已付 HK$ ${parseFloat(plan.paid_amount).toFixed(0)} <span class="text-muted">(${progress}%)</span>
                        </div>
                        <div class="btn-group btn-group-sm flex-shrink-0">
                            <button type="button" class="btn btn-outline-primary" 
                                    onclick="event.stopImmediatePropagation(); quickFollowupFromCustomer(${planId}, ${customerId}, this)">
                                快速跟進
                            </button>
                            <button type="button" class="btn btn-outline-success" 
                                    onclick="event.stopImmediatePropagation(); recordPaymentFromCustomer(${plan.sale_id})">
                                記錄付款
                            </button>
                        </div>
                    </div>
                    <div class="small mt-1">
                        <a href="/payment_plans.php?customer_id=${customerId}" class="text-decoration-none">在計劃管理頁查看 →</a>
                    </div>
                </li>
            `;
        });
        html += '</ul>';
        body.innerHTML = html;

    } catch (err) {
        const body = modalEl.querySelector('#active-plans-body');
        body.innerHTML = `<div class="text-danger small">載入失敗：${err.message}</div>`;
    }
}

// Phase 4 A：從客戶頁快速跟進計劃（入口整合）
async function quickFollowupFromCustomer(planId, customerId, btnElement) {
    const originalText = btnElement.textContent;
    btnElement.disabled = true;
    btnElement.textContent = '處理中...';

    try {
        const note = '客戶頁快速跟進';
        await SalonEase.fetch('/api/payment_plans.php?action=append_followup', {
            method: 'POST',
            body: new URLSearchParams({
                plan_id: planId,
                note: note,
                csrf_token: window.CSRF_TOKEN
            })
        });

        btnElement.textContent = '已跟進 ✓';
        btnElement.classList.remove('btn-outline-primary');
        btnElement.classList.add('btn-success');

        // 短暫提示後關閉 modal 並刷新列表
        setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('activePlansModal'));
            if (modal) modal.hide();
            loadCustomers();  // 刷新客戶列表的活躍計劃數
        }, 900);

    } catch (err) {
        alert('快速跟進失敗：' + err.message);
        btnElement.textContent = originalText;
        btnElement.disabled = false;
    }
}

function recordPaymentFromCustomer(saleId) {
    window.location.href = `/record_payment.php?sale_id=${saleId}`;
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增客戶';
    document.getElementById('customer-id').value = '';
    document.getElementById('customer-name').value = '';
    document.getElementById('customer-phone').value = '';
    document.getElementById('customer-email').value = '';
    document.getElementById('customer-gender').value = '';
    document.getElementById('customer-birthday').value = '';
    document.getElementById('customer-notes').value = '';

    document.getElementById('save-btn').textContent = '新增客戶';

    // A36：新增模式隱藏積分歷史
    const historySection = document.getElementById('points-history-section');
    if (historySection) historySection.classList.add('d-none');

    const paymentsSection = document.getElementById('payments-history-section');
    if (paymentsSection) paymentsSection.classList.add('d-none');

    const modalEl = document.getElementById('customerModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => document.getElementById('customer-name').focus(), 400);
}

// Phase 8: 快速生成客戶 Portal 連結
async function generatePortalLinkForCustomer(customerId, btnEl) {
    const originalText = btnEl.textContent;
    btnEl.disabled = true;
    btnEl.textContent = '生成中...';

    try {
        const res = await SalonEase.fetch('/api/settings.php?action=generate_portal_link', {
            method: 'POST',
            body: new URLSearchParams({
                customer_id: customerId,
                csrf_token: window.CSRF_TOKEN
            })
        });

        if (res.success && res.data && res.data.link) {
            // 顯示在 toast + 複製到剪貼簿
            if (navigator.clipboard) {
                await navigator.clipboard.writeText(res.data.link);
            }
            SalonEase.toast('Portal 連結已複製到剪貼簿！');
            
            // 可選：彈出確認
            if (confirm('連結已複製！\n\n是否現在打開預覽？')) {
                window.open(res.data.link, '_blank');
            }
        } else {
            alert('生成失敗');
        }
    } catch (err) {
        alert('生成 Portal 連結失敗：' + err.message);
    } finally {
        btnEl.disabled = false;
        btnEl.textContent = originalText;
    }
}

async function editCustomer(id) {
    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=get&id=${id}`);
        const c = res.data;

        document.getElementById('modal-title').textContent = '編輯客戶';
        document.getElementById('customer-id').value = c.id;
        document.getElementById('customer-name').value = c.name || '';
        document.getElementById('customer-phone').value = c.phone || '';
        document.getElementById('customer-email').value = c.email || '';
        document.getElementById('customer-gender').value = c.gender || '';
        document.getElementById('customer-birthday').value = c.birthday || '';
        document.getElementById('customer-notes').value = c.notes || '';

        document.getElementById('save-btn').textContent = '儲存變更';

        // A36：顯示並載入最近積分異動
        const historySection = document.getElementById('points-history-section');
        const historyList = document.getElementById('points-history-list');
        if (historySection && historyList) {
            historySection.classList.remove('d-none');
            historyList.innerHTML = '<div class="text-muted">載入中...</div>';

            const history = c.recent_points_history || [];
            if (history.length === 0) {
                historyList.innerHTML = '<div class="text-muted small">尚無積分異動記錄<br>可點擊下方「查看完整歷史 →」手動調整積分</div>';
            } else {
                let html = '<table class="table table-sm mb-0" style="font-size:0.7rem;"><tbody>';
                history.forEach(item => {
                    const details = JSON.parse(item.details || '{}');
                    let type = '';
                    let pointsText = '';

                    if (item.action === 'customer.points_earned') {
                        type = '<span class="badge bg-success-subtle text-success">獲得</span>';
                        pointsText = `+${details.points || 0}`;
                    } else if (item.action === 'customer.points_redeemed') {
                        type = '<span class="badge bg-warning-subtle text-warning">兌換</span>';
                        pointsText = `-${details.points_used || 0}`;
                    } else if (item.action === 'customer.points_adjusted') {
                        const adj = details.points || 0;
                        type = '<span class="badge bg-info-subtle text-info">調整</span>';
                        pointsText = (adj > 0 ? '+' : '') + adj;
                    }

                    html += `
                        <tr>
                            <td class="text-nowrap">${item.created_at.substring(0,16).replace('T',' ')}</td>
                            <td>${type}</td>
                            <td class="text-end fw-medium">${pointsText}</td>
                            <td class="text-muted small">${details.reason || details.sale_id ? '銷售' : ''}</td>
                        </tr>`;
                });
                html += '</tbody></table>';
                html += `<div class="small text-muted mt-1">...共 ${history.length} 筆 <a href="/loyalty.php" class="text-muted text-decoration-none">查看完整 →</a></div>`;
                historyList.innerHTML = html;
            }
        }

        // Phase 3: 顯示最近付款歷史 + 摘要
        const paymentsSection = document.getElementById('payments-history-section');
        const paymentsList = document.getElementById('payments-history-list');
        const paymentsSummary = document.getElementById('payments-summary');

        if (paymentsSection && paymentsList) {
            paymentsSection.classList.remove('d-none');

            const summary = c.payment_summary || {};
            const totalPaid = parseFloat(summary.total_paid || 0);
            const activePlans = summary.active_plans || 0;
            const totalPlans = summary.total_plans || 0;

            if (paymentsSummary) {
                let summaryText = `總付 HK$ ${totalPaid.toFixed(0)}`;
                if (totalPlans > 0) {
                    summaryText += ` ｜ 計劃 ${activePlans}/${totalPlans}`;
                }
                paymentsSummary.innerHTML = `<span class="text-muted">${summaryText}</span>`;
            }

            paymentsList.innerHTML = '<div class="text-muted">載入中...</div>';

            const payments = c.recent_payments || [];
            const totalCount = c.payments_total_count || 0;

            if (payments.length === 0) {
                paymentsList.innerHTML = '<div class="text-muted small">尚無付款記錄<br>可點擊下方「記錄補款 →」補錄</div>';
            } else {
                let html = '<table class="table table-sm mb-0" style="font-size:0.7rem;"><tbody>';
                payments.forEach(p => {
                    const isRefund = p.is_refund == 1;
                    const sign = isRefund ? '-' : '';
                    const amountClass = isRefund ? 'text-danger' : 'fw-medium';

                    let feeHtml = '';
                    if (p.fee_amount && parseFloat(p.fee_amount) > 0) {
                        const feeSign = p.fee_borne_by === 'customer' ? '' : '商戶承擔 ';
                        feeHtml = `<span class="text-muted" style="font-size:0.6rem;">(${feeSign}手續費 ${sign}HK$ ${parseFloat(p.fee_amount).toFixed(0)})</span>`;
                    }

                    let planHtml = '';
                    if (p.plan_type) {
                        const planLabel = p.plan_type === 'installment' ? '分期' : '周期性';
                        planHtml = ` <span class="badge bg-info-subtle text-info" style="font-size:0.55rem;">${planLabel}</span>`;
                        if (p.installment_no && p.total_installments) {
                            planHtml += ` <span class="text-muted" style="font-size:0.6rem;">#${p.installment_no}/${p.total_installments}</span>`;
                        } else if (p.installment_no) {
                            planHtml += ` <span class="text-muted" style="font-size:0.6rem;">#${p.installment_no}</span>`;
                        }
                    }

                    const refundBadge = isRefund 
                        ? `<span class="badge bg-danger-subtle text-danger ms-1" style="font-size:0.55rem;">退款</span>` 
                        : '';

                    html += `
                        <tr style="cursor:pointer;" onclick="goToRecordPayment(${p.sale_id})">
                            <td class="text-nowrap">${p.paid_at ? p.paid_at.substring(0,16).replace('T',' ') : p.sale_date}</td>
                            <td class="${amountClass}">${sign}HK$ ${parseFloat(p.amount).toFixed(0)} ${feeHtml}</td>
                            <td class="text-muted small">${p.payment_method_name}${refundBadge}</td>
                            <td>${planHtml}</td>
                        </tr>`;
                });
                html += '</tbody></table>';

                // C3: 可展開更多
                if (totalCount > payments.length) {
                    html += `
                        <div class="small text-muted mt-1 text-center">
                            <a href="javascript:void(0)" onclick="loadMoreCustomerPayments(${c.id}, this)" class="text-muted text-decoration-none">
                                查看更多（共 ${totalCount} 筆）→
                            </a>
                        </div>`;
                } else {
                    html += `<div class="small text-muted mt-1">...共 ${payments.length} 筆 <a href="/record_payment.php" class="text-muted text-decoration-none">記錄補款 →</a></div>`;
                }

                paymentsList.innerHTML = html;
            }
        }

        const modalEl = document.getElementById('customerModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideCustomerModal() {
    const modalEl = document.getElementById('customerModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

async function saveCustomer() {
    const id = document.getElementById('customer-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('customer-name').value.trim(),
        phone: document.getElementById('customer-phone').value.trim(),
        email: document.getElementById('customer-email').value.trim(),
        gender: document.getElementById('customer-gender').value,
        birthday: document.getElementById('customer-birthday').value,
        notes: document.getElementById('customer-notes').value.trim(),
        csrf_token: '<?= csrf_token() ?>'
    };

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/customers.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hideCustomerModal();
        SalonEase.toast(isEdit ? '客戶資料已更新' : '客戶新增成功');
        loadCustomers();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key.toLowerCase() === 'n' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        showAddModal();
    }
});

/* A26：載入目前忠誠度規則 */
async function loadCustomerLoyaltyRates() {
    try {
        const res = await SalonEase.fetch('/api/settings.php?action=get');
        if (res.data) {
            const earn = res.data.points_earn_rate || 10;
            const redeem = res.data.points_redemption_rate || 10;
            const earnEl = document.getElementById('customer-earn-rate');
            const redeemEl = document.getElementById('customer-redemption-rate');
            if (earnEl) earnEl.textContent = earn;
            if (redeemEl) redeemEl.textContent = redeem;
        }
    } catch (e) {
        // 靜默失敗
    }
}
loadCustomerLoyaltyRates();

function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
