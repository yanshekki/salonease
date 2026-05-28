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
        tbody.innerHTML = `<tr><td colspan="8" class="py-5 text-center text-muted">沒有符合的客戶</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(c => {
        const lastVisit = c.last_visit_at ? formatDate(c.last_visit_at) : '-';
        const spent = parseFloat(c.total_spent || 0).toFixed(0);

        const points = c.points || 0;
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
                <td class="text-end">
                    <button onclick="editCustomer(${c.id})" class="btn btn-link btn-sm text-success p-0">編輯</button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function formatDate(dt) {
    const d = new Date(dt);
    return d.toLocaleDateString('zh-HK', { month: '2-digit', day: '2-digit' });
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

    const modalEl = document.getElementById('customerModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => document.getElementById('customer-name').focus(), 400);
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
