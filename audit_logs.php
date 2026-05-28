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
                <select x-model="selectedAction" @change="loadLogs()" class="form-select form-select-sm">
                    <option value="">全部</option>
                    <template x-for="act in actions" :key="act">
                        <option :value="act" x-text="act"></option>
                    </template>
                </select>
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
            <div class="col-md-3 d-flex align-items-end">
                <button @click="loadLogs()" class="btn btn-outline-secondary btn-sm w-100">重新載入</button>
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
                    <template x-if="logs.length === 0">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">沒有符合條件的記錄</td>
                        </tr>
                    </template>
                    <template x-for="log in logs" :key="log.id">
                        <tr>
                            <td class="text-nowrap small" x-text="log.created_at"></td>
                            <td x-text="log.staff_name || '系統'"></td>
                            <td><span class="badge bg-light text-dark" x-text="log.action"></span></td>
                            <td class="small" x-text="log.entity_type ? log.entity_type + ' #' + log.entity_id : '-'"></td>
                            <td class="small text-muted" x-text="log.ip_address || '-'"></td>
                            <td class="small">
                                <span x-show="log.details" class="text-muted" style="font-size:12px;" x-text="JSON.stringify(log.details)"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
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
                let url = '/api/audit.php?action=list&limit=100';
                if (this.from) url += `&from=${this.from}`;
                if (this.to) url += `&to=${this.to}`;
                if (this.selectedAction) url += `&action=${encodeURIComponent(this.selectedAction)}`;
                if (this.selectedStaff) url += `&staff_id=${this.selectedStaff}`;

                const res = await SalonEase.fetch(url);
                this.logs = res.data || [];
            } catch (err) {
                this.logs = [];
                SalonEase.toast(err.message || '載入失敗', 'error');
            }
        }
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>