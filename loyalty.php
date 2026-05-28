<?php
/**
 * SalonEase - 忠誠度積分記錄
 */
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'manager']);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '忠誠度記錄';
$pageSubtitle = '查看客戶積分獲得與兌換歷史';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
</div>

<!-- A22：目前忠誠度規則（從設定讀取） -->
<div class="card mb-3 bg-light border-0">
    <div class="card-body py-2 small">
        <div class="d-flex flex-wrap align-items-center gap-4">
            <div>
                <span class="text-muted">累積率：</span>
                <strong id="loyalty-earn-rate">10</strong> 元 = 1 點
            </div>
            <div>
                <span class="text-muted">兌換率：</span>
                <strong id="loyalty-redemption-rate">10</strong> 點 = $1
            </div>
            <div class="text-muted">（可於「系統設定」頁調整）</div>
        </div>
    </div>
</div>

<!-- Phase 2 A16：積分排行榜 -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">積分排行榜（前 10 名）</div>
            <button onclick="loadLoyaltyRanking()" class="btn btn-sm btn-outline-secondary">重新載入</button>
        </div>
        <div id="ranking-container">
            <div class="text-muted small">載入中...</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small">日期區間</label>
                <div class="d-flex gap-2">
                    <input type="date" id="from" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                    <input type="date" id="to" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small">搜尋客戶</label>
                <input type="text" id="search" class="form-control form-control-sm" placeholder="姓名或電話">
            </div>
            <div class="col-md-2">
                <label class="form-label small">類型</label>
                <select id="action_type" class="form-select form-select-sm">
                    <option value="">全部</option>
                    <option value="earned">獲得</option>
                    <option value="redeemed">兌換</option>
                    <option value="adjusted">調整</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button onclick="loadLoyaltyLog()" class="btn btn-dark btn-sm w-100">查詢</button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button onclick="exportLoyaltyCSV()" class="btn btn-outline-success btn-sm w-100">匯出 CSV</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>時間</th>
                        <th>客戶</th>
                        <th>員工</th>
                        <th>類型</th>
                        <th class="text-end">積分</th>
                        <th>說明</th>
                    </tr>
                </thead>
                <tbody id="loyalty-list">
                    <tr><td colspan="6" class="text-center py-4 text-muted">請設定條件後查詢</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function loadLoyaltyLog() {
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    const search = document.getElementById('search').value;
    const type = document.getElementById('action_type').value;

    const tbody = document.getElementById('loyalty-list');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">載入中...</td></tr>';

    try {
        const params = new URLSearchParams({ from, to, search, action_type: type });
        const res = await SalonEase.fetch(`/api/reports.php?action=loyalty_log&${params}`);
        
        if (!res.data || res.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">查無資料</td></tr>';
            return;
        }

        let html = '';
        res.data.forEach(row => {
            const date = new Date(row.created_at).toLocaleString('zh-HK', { month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            const pointsClass = row.points >= 0 ? 'text-success' : 'text-danger';
            const typeText = { earned: '獲得', redeemed: '兌換', adjusted: '調整' }[row.type] || row.type;
            
            html += `
                <tr>
                    <td class="small">${date}</td>
                    <td>${row.customer_name} <span class="text-muted small">(${row.customer_phone})</span></td>
                    <td class="small">${row.staff_name}</td>
                    <td><span class="badge ${row.type === 'earned' ? 'bg-success' : row.type === 'redeemed' ? 'bg-danger' : 'bg-secondary'}">${typeText}</span></td>
                    <td class="text-end fw-medium ${pointsClass}">${row.points >= 0 ? '+' : ''}${row.points}</td>
                    <td class="small text-muted">${row.note}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">${err.message}</td></tr>`;
    }
}

// 預設載入最近 30 天
document.getElementById('from').value = new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0];
document.getElementById('to').value = new Date().toISOString().split('T')[0];

function exportLoyaltyCSV() {
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    const search = document.getElementById('search').value;
    const type = document.getElementById('action_type').value;

    const params = new URLSearchParams({ from, to, search, action_type: type, format: 'csv' });
    window.location.href = `/api/reports.php?action=loyalty_log&${params}`;
}

async function loadLoyaltyRanking() {
    const container = document.getElementById('ranking-container');
    container.innerHTML = '<div class="text-muted small">載入中...</div>';

    try {
        const res = await SalonEase.fetch('/api/reports.php?action=loyalty_ranking&limit=10');
        if (!res.data || res.data.length === 0) {
            container.innerHTML = '<div class="text-muted small">暫無積分排行資料</div>';
            return;
        }

        let html = '<table class="table table-sm mb-0"><thead><tr><th>#</th><th>客戶</th><th class="text-end">積分</th><th class="text-end">消費</th></tr></thead><tbody>';
        res.data.forEach((row, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${row.customer_name} <span class="text-muted small">(${row.customer_phone})</span></td>
                    <td class="text-end fw-semibold text-amber-700">${row.points}</td>
                    <td class="text-end small">HK$ ${parseFloat(row.total_spent).toFixed(0)}</td>
                </tr>
            `;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="text-danger small">${err.message}</div>`;
    }
}

// 自動載入排行榜
loadLoyaltyRanking();

/* A22：載入目前忠誠度累積率與兌換率 */
async function loadLoyaltyRates() {
    try {
        const res = await SalonEase.fetch('/api/settings.php?action=get');
        if (res.data) {
            const earn = res.data.points_earn_rate || 10;
            const redeem = res.data.points_redemption_rate || 10;
            const earnEl = document.getElementById('loyalty-earn-rate');
            const redeemEl = document.getElementById('loyalty-redemption-rate');
            if (earnEl) earnEl.textContent = earn;
            if (redeemEl) redeemEl.textContent = redeem;
        }
    } catch (e) {
        // 靜默失敗，不影響主要功能
    }
}
loadLoyaltyRates();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
