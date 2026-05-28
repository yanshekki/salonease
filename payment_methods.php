<?php
/**
 * SalonEase - 付款方法管理（Phase 1）
 * 自訂付款方式 + 手續費計算規則
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
    header('Location: /dashboard.php?error=permission');
    exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = '付款方法管理';
$pageSubtitle = '自訂 FPS、PayMe、信用卡等付款方式，並設定真實手續費規則（香港市場實務）';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary d-flex align-items-center gap-2">
        <span>+ 新增付款方法</span>
        <span class="small opacity-75">[N]</span>
    </button>
</div>

<!-- 說明卡片 -->
<div class="card mb-4 border-0 bg-light">
    <div class="card-body py-3">
        <div class="small text-muted">
            <strong>手續費模型說明：</strong>
            無手續費（現金/FPS）｜ 固定金額 ｜ 百分比 ｜ 固定金額 + 百分比（信用卡常見）。
            所有計算均在後端強制執行，前端僅供預覽參考。
        </div>
    </div>
</div>

<!-- 列表 -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 22%;">付款方式</th>
                    <th style="width: 12%;">代碼</th>
                    <th style="width: 18%;">手續費模型</th>
                    <th style="width: 18%;">手續費設定</th>
                    <th style="width: 14%;">範例（$1,000）</th>
                    <th style="width: 8%;">狀態</th>
                    <th style="width: 12%;" class="text-center">排序</th>
                    <th style="width: 90px;" class="text-end">操作</th>
                </tr>
            </thead>
            <tbody id="pm-list">
                <tr><td colspan="8" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 新增 / 編輯 Modal -->
<div class="modal fade" id="pmModal" tabindex="-1" aria-labelledby="pmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">新增付款方法</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="pm-id">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">顯示名稱 <span class="text-danger">*</span></label>
                        <input type="text" id="pm-name" class="form-control" placeholder="轉數快 (FPS)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">內部代碼 <span class="text-danger">*</span></label>
                        <input type="text" id="pm-code" class="form-control" placeholder="fps" style="text-transform:lowercase">
                        <div class="form-text">唯一值，建議使用小寫英文</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">手續費計算方式</label>
                        <select id="pm-fee-model" class="form-select" onchange="updateFeeCalculator()">
                            <option value="none">無手續費</option>
                            <option value="fixed">固定金額</option>
                            <option value="percent">百分比</option>
                            <option value="fixed_plus_percent">固定金額 + 百分比</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="fee-fixed-group">
                        <label class="form-label">固定手續費 (HK$)</label>
                        <input type="number" id="pm-fee-fixed" class="form-control" value="0" step="0.01" min="0" onchange="updateFeeCalculator()">
                    </div>
                    <div class="col-md-6" id="fee-percent-group">
                        <label class="form-label">百分比費率 (%)</label>
                        <input type="number" id="pm-fee-percent" class="form-control" value="0" step="0.01" min="0" max="100" onchange="updateFeeCalculator()">
                    </div>

                    <div class="col-12">
                        <label class="form-label">排序值</label>
                        <input type="number" id="pm-sort-order" class="form-control" value="100">
                        <div class="form-text">數字越小顯示越靠前</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">備註說明</label>
                        <textarea id="pm-notes" class="form-control" rows="2" placeholder="香港市場實際收費參考..."></textarea>
                    </div>

                    <!-- 即時試算區 -->
                    <div class="col-12 border rounded p-3 bg-light mt-2">
                        <div class="small fw-semibold mb-2 text-muted">手續費即時試算（以金額為例）</div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="text-muted small">測試金額</span>
                            <input type="number" id="calc-amount" class="form-control form-control-sm" style="width:120px" value="1000" step="0.01" oninput="updateFeeCalculator()">
                            <span class="text-muted">HK$</span>
                        </div>
                        <div id="calc-result" class="small">
                            <!-- JS 動態更新 -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="savePaymentMethod()" class="btn btn-primary" id="save-btn">新增付款方法</button>
            </div>
        </div>
    </div>
</div>

<script>
function e(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

window.CSRF_TOKEN = '<?= csrf_token() ?>';

document.addEventListener('DOMContentLoaded', () => {
    loadPaymentMethods();

    // 熱鍵
    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增付款方法' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }

    // 監聽 model 變化即時顯示/隱藏欄位
    const modelSelect = document.getElementById('pm-fee-model');
    if (modelSelect) modelSelect.addEventListener('change', updateFeeFieldsVisibility);
});

let currentList = [];

async function loadPaymentMethods() {
    const tbody = document.getElementById('pm-list');
    tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-muted">載入中...</td></tr>`;

    try {
        const res = await SalonEase.fetch('/api/payment_methods.php?action=list');
        currentList = res.data || [];
        renderTable(currentList);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-5 text-center text-danger">${err.message}</td></tr>`;
    }
}

function renderTable(list) {
    const tbody = document.getElementById('pm-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-muted">尚未新增任何付款方法</td></tr>`;
        return;
    }

    let html = '';
    list.forEach((m, index) => {
        const modelLabel = {
            'none': '<span class="badge bg-secondary">無手續費</span>',
            'fixed': '<span class="badge bg-info text-dark">固定金額</span>',
            'percent': '<span class="badge bg-warning text-dark">百分比</span>',
            'fixed_plus_percent': '<span class="badge bg-primary">固定 + 百分比</span>'
        }[m.fee_model] || m.fee_model;

        let feeText = '—';
        if (m.fee_model === 'fixed') {
            feeText = `HK$ ${parseFloat(m.fee_fixed).toFixed(2)}`;
        } else if (m.fee_model === 'percent') {
            feeText = `${parseFloat(m.fee_percent)}%`;
        } else if (m.fee_model === 'fixed_plus_percent') {
            feeText = `HK$ ${parseFloat(m.fee_fixed).toFixed(2)} + ${parseFloat(m.fee_percent)}%`;
        }

        const statusBadge = m.is_active == 1
            ? `<span class="badge bg-success">啟用中</span>`
            : `<span class="badge bg-secondary">已停用</span>`;

        const example = m.suggested_fee_example > 0 
            ? `HK$ ${parseFloat(m.suggested_fee_example).toFixed(2)}`
            : '<span class="text-muted">—</span>';

        const canDelete = m.id > 8; // 系統預設前 8 筆不可刪

        html += `
            <tr>
                <td class="font-medium">${e(m.name)}</td>
                <td><code class="small bg-light px-2 py-1 rounded">${e(m.code)}</code></td>
                <td>${modelLabel}</td>
                <td class="small">${feeText}</td>
                <td class="small text-muted">${example}</td>
                <td>${statusBadge}</td>
                <td class="text-center">
                    <div class="d-inline-flex" style="gap: 2px;">
                        <button onclick="moveUp(${index})" class="btn btn-sm btn-outline-secondary px-2 py-0" title="上移">↑</button>
                        <button onclick="moveDown(${index})" class="btn btn-sm btn-outline-secondary px-2 py-0" title="下移">↓</button>
                    </div>
                </td>
                <td class="text-end">
                    <button onclick="editPaymentMethod(${m.id})" class="btn btn-sm btn-link p-0 text-primary">編輯</button>
                    <button onclick="toggleStatus(${m.id}, ${m.is_active})" class="btn btn-sm btn-link p-0 text-muted mx-1">${m.is_active ? '停用' : '啟用'}</button>
                    ${canDelete ? `<button onclick="deleteMethod(${m.id}, '${e(m.name).replace(/'/g, "\\'")}')" class="btn btn-sm btn-link p-0 text-danger">刪除</button>` : ''}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function updateFeeFieldsVisibility() {
    const model = document.getElementById('pm-fee-model').value;
    const fixedGroup = document.getElementById('fee-fixed-group');
    const percentGroup = document.getElementById('fee-percent-group');

    if (model === 'none') {
        fixedGroup.style.display = 'none';
        percentGroup.style.display = 'none';
    } else if (model === 'fixed') {
        fixedGroup.style.display = '';
        percentGroup.style.display = 'none';
    } else if (model === 'percent') {
        fixedGroup.style.display = 'none';
        percentGroup.style.display = '';
    } else {
        fixedGroup.style.display = '';
        percentGroup.style.display = '';
    }
    updateFeeCalculator();
}

function updateFeeCalculator() {
    const model = document.getElementById('pm-fee-model').value;
    const fixed = parseFloat(document.getElementById('pm-fee-fixed').value) || 0;
    const percent = parseFloat(document.getElementById('pm-fee-percent').value) || 0;
    const amount = parseFloat(document.getElementById('calc-amount').value) || 0;

    let fee = 0;
    if (model === 'fixed') fee = fixed;
    else if (model === 'percent') fee = amount * percent / 100;
    else if (model === 'fixed_plus_percent') fee = fixed + (amount * percent / 100);

    fee = Math.round(fee * 100) / 100;
    const total = Math.round((amount + fee) * 100) / 100;

    const resultEl = document.getElementById('calc-result');
    resultEl.innerHTML = `
        <div class="d-flex justify-content-between small">
            <span>建議手續費</span>
            <strong class="text-danger">HK$ ${fee.toFixed(2)}</strong>
        </div>
        <div class="d-flex justify-content-between small mt-1">
            <span>客戶實付總額</span>
            <strong>HK$ ${total.toFixed(2)}</strong>
        </div>
    `;
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增付款方法';
    document.getElementById('save-btn').textContent = '新增付款方法';
    document.getElementById('pm-id').value = '';
    document.getElementById('pm-code').disabled = false;

    // 重置表單
    document.getElementById('pm-name').value = '';
    document.getElementById('pm-code').value = '';
    document.getElementById('pm-fee-model').value = 'none';
    document.getElementById('pm-fee-fixed').value = '0';
    document.getElementById('pm-fee-percent').value = '0';
    document.getElementById('pm-sort-order').value = '100';
    document.getElementById('pm-notes').value = '';
    document.getElementById('calc-amount').value = '1000';

    updateFeeFieldsVisibility();
    updateFeeCalculator();

    new bootstrap.Modal(document.getElementById('pmModal')).show();
}

async function editPaymentMethod(id) {
    const m = currentList.find(x => x.id == id);
    if (!m) return;

    document.getElementById('modal-title').textContent = '編輯付款方法';
    document.getElementById('save-btn').textContent = '儲存變更';
    document.getElementById('pm-id').value = m.id;
    document.getElementById('pm-code').value = m.code;
    document.getElementById('pm-code').disabled = true; // 代碼不可改

    document.getElementById('pm-name').value = m.name || '';
    document.getElementById('pm-fee-model').value = m.fee_model;
    document.getElementById('pm-fee-fixed').value = m.fee_fixed || 0;
    document.getElementById('pm-fee-percent').value = m.fee_percent || 0;
    document.getElementById('pm-sort-order').value = m.sort_order || 100;
    document.getElementById('pm-notes').value = m.notes || '';
    document.getElementById('calc-amount').value = 1000;

    updateFeeFieldsVisibility();
    updateFeeCalculator();

    new bootstrap.Modal(document.getElementById('pmModal')).show();
}

async function savePaymentMethod() {
    const id = document.getElementById('pm-id').value;
    const isEdit = !!id;

    const payload = {
        id: id ? parseInt(id) : undefined,
        name: document.getElementById('pm-name').value.trim(),
        code: document.getElementById('pm-code').value.trim(),
        fee_model: document.getElementById('pm-fee-model').value,
        fee_fixed: parseFloat(document.getElementById('pm-fee-fixed').value) || 0,
        fee_percent: parseFloat(document.getElementById('pm-fee-percent').value) || 0,
        sort_order: parseInt(document.getElementById('pm-sort-order').value) || 100,
        notes: document.getElementById('pm-notes').value.trim(),
        csrf_token: window.CSRF_TOKEN
    };

    const url = isEdit 
        ? '/api/payment_methods.php?action=update' 
        : '/api/payment_methods.php?action=create';

    try {
        await SalonEase.fetch(url, { method: 'POST', body: payload });
        bootstrap.Modal.getInstance(document.getElementById('pmModal')).hide();
        await loadPaymentMethods();
        SalonEase.toast(isEdit ? '已更新' : '新增成功');
    } catch (err) {
        alert('儲存失敗：' + err.message);
    }
}

async function toggleStatus(id, currentStatus) {
    if (!confirm(`確定要${currentStatus ? '停用' : '啟用'}此付款方法嗎？`)) return;

    try {
        await SalonEase.fetch('/api/payment_methods.php?action=toggle', {
            method: 'POST',
            body: { id, status: currentStatus ? 0 : 1, csrf_token: window.CSRF_TOKEN }
        });
        await loadPaymentMethods();
    } catch (err) {
        alert('操作失敗：' + err.message);
    }
}

async function deleteMethod(id, name) {
    if (!confirm(`確定要永久刪除「${name}」嗎？\n\n此操作無法復原。`)) return;

    try {
        await SalonEase.fetch('/api/payment_methods.php?action=delete', {
            method: 'POST',
            body: { id, csrf_token: window.CSRF_TOKEN }
        });
        await loadPaymentMethods();
        SalonEase.toast('已刪除');
    } catch (err) {
        alert('刪除失敗：' + err.message);
    }
}

// 排序功能（上移 / 下移）
async function moveUp(index) {
    if (index <= 0) return;
    const newOrder = [...currentList];
    [newOrder[index - 1], newOrder[index]] = [newOrder[index], newOrder[index - 1]];
    await applyNewOrder(newOrder);
}

async function moveDown(index) {
    if (index >= currentList.length - 1) return;
    const newOrder = [...currentList];
    [newOrder[index], newOrder[index + 1]] = [newOrder[index + 1], newOrder[index]];
    await applyNewOrder(newOrder);
}

async function applyNewOrder(newList) {
    const order = newList.map(m => m.id);
    try {
        await SalonEase.fetch('/api/payment_methods.php?action=reorder', {
            method: 'POST',
            body: { order, csrf_token: window.CSRF_TOKEN }
        });
        await loadPaymentMethods();
    } catch (err) {
        alert('排序更新失敗：' + err.message);
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
