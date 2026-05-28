<?php
/**
 * SalonEase - 操作審計日誌
 * Phase 1 實作
 */
require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$pageTitle = '操作審計日誌';
$pageSubtitle = '查看系統重要操作記錄';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
</div>

<div class="card mb-4" x-data="auditLogs()">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label small">日期區間</label>
                <div class="d-flex gap-2">
                    <input type="date" x-model="from" class="form-control form-control-sm" @change="loadLogs()">
                    <input type="date" x-model="to" class="form-control form-control-sm" @change="loadLogs()">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small">操作類型</label>
                <div class="d-flex gap-1">
                    <select x-model="selectedAction" @change="loadLogs()" class="form-select form-select-sm flex-grow-1">
                        <option value="">全部</option>
                        <template x-for="act in actions" :key="act">
                            <option :value="act" x-text="act"></option>
                        </template>
                    </select>
                    <button @click="selectedAction=''; loadLogs()" class="btn btn-sm btn-outline-secondary" x-show="selectedAction">清除</button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small">員工</label>
                <select x-model="selectedStaff" @change="loadLogs()" class="form-select form-select-sm">
                    <option value="">全部員工</option>
                    <template x-for="staff in staffList" :key="staff.id">
                        <option :value="staff.id" x-text="staff.name"></option>
                    </template>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">搜尋</label>
                <input type="text" x-model="search" @input="page=1" class="form-control form-control-sm" placeholder="搜尋操作、員工、細節...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" x-model="myActionsOnly" @change="page=1" class="form-check-input" id="myActionsOnly">
                    <label class="form-check-label small" for="myActionsOnly">只看我的操作</label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small">每頁</label>
                <select x-model="perPage" @change="page=1" class="form-select form-select-sm">
                    <template x-for="opt in perPageOptions" :key="opt">
                        <option :value="opt" x-text="opt"></option>
                    </template>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button @click="loadLogs()" class="btn btn-outline-secondary btn-sm w-100">重新載入</button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small text-muted">
                共 <span x-text="logs.length"></span> 筆
                （篩選後 <span x-text="filteredLogs.length"></span> 筆，目前顯示第 <span x-text="page"></span> / <span x-text="totalPages"></span> 頁）
            </div>
            <div class="d-flex gap-2">
                <button @click="resetFilters()" class="btn btn-sm btn-outline-secondary">重置篩選</button>
                <button @click="exportCSV()" class="btn btn-sm btn-outline-success">匯出 CSV</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>時間</th>
                        <th>員工</th>
                        <th>操作</th>
                        <th>實體</th>
                        <th>IP</th>
                        <th>細節</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="paginatedLogs.length === 0">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">沒有符合條件的記錄</td>
                        </tr>
                    </template>
                    <template x-for="log in paginatedLogs" :key="log.id">
                        <tr>
                            <td class="text-nowrap small" x-text="log.created_at"></td>
                            <td x-text="log.staff_name || '系統'"></td>
                            <td><span @click="selectedAction = log.action; page=1" class="badge bg-light text-dark" style="cursor:pointer" x-text="log.action"></span></td>
                            <td class="small" x-text="log.entity_type ? log.entity_type + ' #' + log.entity_id : '-'"></td>
                            <td class="small text-muted" x-text="log.ip_address || '-'"></td>
                            <td class="small">
                                <span x-show="log.details" class="text-muted" style="font-size:12px;" :title="JSON.stringify(log.details)">
                                    <template x-if="log.details.name">
                                        <span x-text="log.details.name"></span>
                                    </template>
                                    <template x-else>
                                        <span x-text="JSON.stringify(log.details).substring(0, 50) + (JSON.stringify(log.details).length > 50 ? '...' : '')"></span>
                                    </template>
                                </span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center gap-2 mt-2">
            <button @click="prevPage()" :disabled="page <= 1" class="btn btn-sm btn-outline-secondary">上一頁</button>
            <span class="align-self-center small">第 <span x-text="page"></span> / <span x-text="totalPages"></span> 頁</span>
            <button @click="nextPage()" :disabled="page >= totalPages" class="btn btn-sm btn-outline-secondary">下一頁</button>
        </div>
    </div>
</div>

<script>
function auditLogs() {
    return {
        logs: [],
        actions: [],
        staffList: [],
        from: '',
        to: '',
        selectedAction: '',
        selectedStaff: '',
        page: 1,
        perPage: 20,
        logs: [],
        search: '',
        myActionsOnly: false,
        perPageOptions: [10, 20, 50, 100],

        init() {
            this.loadActions();
            this.loadStaff();
            this.loadLogs();
        },

        async loadActions() {
            try {
                const res = await SalonEase.fetch('/api/audit.php?action=actions');
                this.actions = res.data || [];
            } catch (e) {}
        },

        async loadStaff() {
            try {
                const res = await SalonEase.fetch('/api/staff.php?action=list&is_active=1');
                this.staffList = res.data || [];
            } catch (e) {}
        },

        async loadLogs() {
            try {
                let url = '/api/audit.php?action=list&limit=200';
                if (this.from) url += `&from=${this.from}`;
                if (this.to) url += `&to=${this.to}`;
                if (this.selectedAction) url += `&action=${encodeURIComponent(this.selectedAction)}`;
                if (this.selectedStaff) url += `&staff_id=${this.selectedStaff}`;

                const res = await SalonEase.fetch(url);
                this.logs = res.data || [];
                this.page = 1;
            } catch (err) {
                this.logs = [];
                SalonEase.toast(err.message || '載入失敗', 'error');
            }
        },

        get filteredLogs() {
            let result = this.logs;

            if (this.myActionsOnly && window.CURRENT_STAFF_ID) {
                result = result.filter(log => log.staff_id == window.CURRENT_STAFF_ID);
            }

            if (this.search) {
                const term = this.search.toLowerCase();
                result = result.filter(log => 
                    (log.action && log.action.toLowerCase().includes(term)) ||
                    (log.staff_name && log.staff_name.toLowerCase().includes(term)) ||
                    (log.entity_type && log.entity_type.toLowerCase().includes(term)) ||
                    (log.details && JSON.stringify(log.details).toLowerCase().includes(term))
                );
            }

            return result;
        },

        get paginatedLogs() {
            const start = (this.page - 1) * this.perPage;
            return this.filteredLogs.slice(start, start + this.perPage);
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredLogs.length / this.perPage));
        },

        prevPage() {
            if (this.page > 1) this.page--;
        },

        nextPage() {
            if (this.page < this.totalPages) this.page++;
        },

        resetFilters() {
            this.from = '';
            this.to = '';
            this.selectedAction = '';
            this.selectedStaff = '';
            this.search = '';
            this.myActionsOnly = false;
            this.page = 1;
            this.loadLogs();
        },

        exportCSV() {
            if (this.logs.length === 0) {
                SalonEase.toast('沒有資料可匯出', 'error');
                return;
            }

            const headers = ['時間', '員工', '操作', '實體類型', '實體ID', 'IP', '細節'];
            const rows = this.logs.map(log => [
                log.created_at,
                log.staff_name || '系統',
                log.action,
                log.entity_type || '',
                log.entity_id || '',
                log.ip_address || '',
                log.details ? JSON.stringify(log.details) : ''
            ]);

            let csv = headers.join(',') + '\n';
            rows.forEach(row => {
                csv += row.map(field => `"${String(field).replace(/"/g, '""')}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.href = url;
            link.download = `audit_logs_${new Date().toISOString().slice(0,10)}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        }
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
window.CURRENT_STAFF_ID = <?= json_encode($_SESSION['staff_id'] ?? null) ?>;
</script>