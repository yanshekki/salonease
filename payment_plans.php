<?php
/**
 * SalonEase - 分期 / 周期性付款計劃管理（Phase 3）
 * 管理所有銷售單的 installment / recurring 計劃，查看進度、更改狀態
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
    header('Location: /dashboard.php?error=permission');
    exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = '付款計劃管理';
$pageSubtitle = '查看所有付款計劃、進度及狀態管理（僅限管理員 / 店長）';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="reloadAll()" class="btn btn-outline-secondary">
            <span>重新載入</span>
        </button>
        <button onclick="showCreatePlanModal()" class="btn btn-primary">
            <span>+ 新增計劃</span>
        </button>
    </div>
</div>

<!-- Phase 4 A 小提示 -->
<div class="alert alert-light py-2 px-3 small mb-3 border-0" style="background-color: #f8f1e3;">
    需要關注門檻（天數 / 進度）可在 <a href="/settings.php" class="fw-medium">系統設定</a> 自訂，調整後 Dashboard 及本頁即時生效。
</div>

<!-- 統計卡片 -->
<div class="row g-3 mb-3" id="plan-summary">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-light h-100">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">總計劃數</div>
                        <div class="h4 mb-0 fw-semibold" id="stat-total">—</div>
                    </div>
                    <div class="fs-2 opacity-50">📋</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3" style="cursor:pointer;" onclick="filterByStatus('active')">
        <div class="card border-0 bg-success-subtle h-100">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-success">進行中</div>
                        <div class="h4 mb-0 fw-semibold text-success" id="stat-active">—</div>
                    </div>
                    <div class="fs-2 opacity-50">🔄</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3" style="cursor:pointer;" onclick="filterByStatus('completed')">
        <div class="card border-0 bg-secondary-subtle h-100">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-secondary">已完成</div>
                        <div class="h4 mb-0 fw-semibold text-secondary" id="stat-completed">—</div>
                    </div>
                    <div class="fs-2 opacity-50">✅</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3" style="cursor:pointer;" onclick="filterByStatus('cancelled')">
        <div class="card border-0 bg-danger-subtle h-100">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-danger">已取消</div>
                        <div class="h4 mb-0 fw-semibold text-danger" id="stat-cancelled">—</div>
                    </div>
                    <div class="fs-2 opacity-50">⛔</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .plan-needs-attention {
        background-color: #fff5f5 !important;
        border-left: 4px solid #dc3545 !important;
    }
    .plan-needs-attention:hover {
        background-color: #ffeaea !important;
    }

    .plan-handled {
        background-color: #f0fff0 !important;
        border-left: 4px solid #28a745 !important;
        opacity: 0.85;
    }

    .plan-resolved {
        background-color: #f8f9fa !important;
        border-left: 4px solid #6c757d !important;
        opacity: 0.6;
        text-decoration: line-through;
    }

    .oldest-pinned {
        background-color: #fff3cd !important;
        border-left: 5px solid #ffc107 !important;
        font-weight: 500;
    }
    .oldest-pinned .badge.bg-warning {
        font-size: 0.75rem;
    }

    .just-followed-flash {
        animation: flash-green 0.8s ease;
    }

    @keyframes flash-green {
        0%   { background-color: #d4edda; }
        100% { background-color: inherit; }
    }
</style>

<!-- 計劃管理概覽卡片（加強管理視野） -->
<div class="row g-3 mb-3" id="plan-management-overview">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-primary-subtle h-100">
            <div class="card-body py-2">
                <div class="small text-primary">活躍計劃</div>
                <div class="h4 mb-0 fw-semibold text-primary" id="dash-active-plans">—</div>
                <div class="small text-muted" id="dash-customers">涉及 <span id="dash-customers-count">—</span> 位客戶</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-success-subtle h-100">
            <div class="card-body py-2">
                <div class="small text-success">活躍計劃總額</div>
                <div class="h4 mb-0 fw-semibold text-success" id="dash-total-value">—</div>
                <div class="small text-muted">已收 <span id="dash-collected">—</span></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-warning-subtle h-100" style="cursor:pointer;" onclick="filterNeedsAttention()">
            <div class="card-body py-2">
                <div class="small text-warning">需要關注</div>
                <div class="h4 mb-0 fw-semibold text-warning" id="dash-needs-attention">—</div>
                <div class="small text-muted">點擊查看</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-light h-100">
            <div class="card-body py-2">
                <div class="small text-muted">回收進度</div>
                <div class="h5 mb-0" id="dash-progress">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-info-subtle h-100">
            <div class="card-body py-2">
                <div class="small text-info">下30天預計回收</div>
                <div class="h4 mb-0 fw-semibold text-info" id="dash-upcoming-30">—</div>
                <div class="small text-muted">基於現有活躍計劃</div>
            </div>
        </div>
    </div>
</div>

<!-- 管理概覽額外提示（決策支援：最老計劃 + 最需關注客戶） -->
<div id="plan-management-extra-hints" class="mb-2">
    <div class="d-flex flex-wrap gap-2 small">
        <span id="oldest-active-hint" class="badge bg-warning-subtle text-dark border border-warning px-2 py-1" style="cursor:pointer;"></span>
        <span id="most-concerning-customer-hint" class="badge bg-danger-subtle text-dark border border-danger px-2 py-1" style="cursor:pointer;"></span>
    </div>
</div>

<!-- 嚴格需要關注提示（只有完全未跟進的計劃） -->
<div id="strict-needs-attention-header" class="alert alert-danger py-2 px-3 mb-2 d-none">
    本頁共有 <strong id="unfollowed-count"></strong> 筆完全未跟進的計劃（優先處理）
    <span id="oldest-unfollowed-hint" class="ms-2 small"></span>
</div>

<!-- 本日快速處理統計 -->
<div id="daily-handled-stat" class="small text-muted mt-1 mb-2">
    本日已快速處理 <strong id="daily-handled-count">0</strong> 筆
</div>

<!-- 批量操作工具列（選取後出現） -->
<div id="batch-toolbar" class="alert alert-primary py-2 px-3 mb-2 d-none">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="fw-medium">已選 <strong id="batch-selected-count">0</strong> 筆</span>
        
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-primary" onclick="showBatchRecordPayment()">
                批量記錄付款
            </button>
            <button type="button" class="btn btn-outline-primary" onclick="showBatchQuickFollowup()">
                批量快速跟進
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="showBatchStatusChange()">
                批量改狀態
            </button>
            <button type="button" class="btn btn-outline-success" onclick="batchMarkHandled()">
                批量標記已初步處理
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="batchMarkResolved()">
                批量標記已徹底解決
            </button>
        </div>

        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearBatchSelection()">清除選取</button>
    </div>
</div>

<!-- 已選計劃摘要面板（A 方向：決策支援，選取 ≥2 筆時顯示） -->
<div id="selected-summary-panel" class="card border-0 bg-light mb-2 d-none">
    <div class="card-body py-2 px-3 small">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div>
                <span class="text-muted">選取總額：</span>
                <strong id="summary-total-value" class="text-primary">HK$ 0</strong>
            </div>
            <div>
                <span class="text-muted">客戶數：</span>
                <strong id="summary-customer-count">0</strong>
            </div>
            <div>
                <span class="text-muted">平均進度：</span>
                <strong id="summary-avg-progress">0%</strong>
            </div>
            <div>
                <span class="text-muted">需跟進：</span>
                <strong id="summary-needs-attention" class="text-danger">0</strong> 筆
            </div>
            <div class="flex-grow-1">
                <span class="text-muted">最老計劃：</span>
                <span id="summary-oldest-plan" class="fw-medium" style="cursor:pointer; text-decoration: underline;"></span>
            </div>
        </div>
    </div>
</div>

<!-- 智能建議操作區（B 方向強化：決策支援 + 一鍵優化選取） -->
<div id="selection-suggestions" class="card border-0 bg-warning-subtle mb-2 d-none">
    <div class="card-body py-2 px-3 small">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold text-warning-emphasis me-1">智能建議：</span>
            <span id="suggestion-text" class="text-muted me-2"></span>

            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-warning" onclick="selectSmartSubset('oldest')">
                    最老 5 筆
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="selectSmartSubset('unfollowed')">
                    完全未跟進
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="selectSmartSubset('lowest-progress')">
                    進度最低 5 筆
                </button>
                <button type="button" class="btn btn-warning text-dark" onclick="selectSmartSubset('priority')">
                    最需優先處理
                </button>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="resetToCurrentSelection()">
                還原目前選取
            </button>
        </div>
    </div>
</div>

<!-- 主動建議處理區（持續深化：今日工作指揮中心） -->
<div id="proactive-suggestions" class="card border-0 bg-info-subtle mb-2 d-none">
    <div class="card-body py-2 px-3 small">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="fw-semibold text-info-emphasis">主動建議處理</div>
            <button type="button" class="btn btn-sm btn-info text-white" onclick="acceptAllProactiveSuggestions()">
                一鍵接受全部建議
            </button>
        </div>
        <div id="proactive-suggestions-content" class="d-flex flex-wrap align-items-center gap-2">
            <!-- JS 動態產生建議群組 -->
        </div>
        <div class="small text-muted mt-1">系統自動分析高優先計劃，接受後會自動標記跟進並計入今日處理數。</div>
    </div>
</div>

<!-- 今日處理中工作清單（持續深化：持久工作指揮中心） -->
<div id="today-work-list" class="card border-0 bg-success-subtle mb-2 d-none">
    <div class="card-body py-2 px-3 small">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-1 gap-2">
            <div>
                <span class="fw-semibold text-success-emphasis">今日處理中</span>
                <span id="today-work-count" class="badge bg-success text-white ms-1"></span>
            </div>
            <div id="today-progress-text" class="small fw-medium text-success"></div>
        </div>

        <!-- 今日進度條 -->
        <div class="progress mb-2" style="height: 6px;">
            <div id="today-progress-bar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
        </div>

        <!-- 工作清單工具列：搜尋 + 排序 + 批量 -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <input type="text" id="today-work-search" class="form-control form-control-sm" style="width: 180px;" placeholder="搜尋今日清單..." oninput="renderTodayWorkList()">
            
            <select id="today-work-sort" class="form-select form-select-sm" style="width: auto;" onchange="renderTodayWorkList()">
                <option value="priority-desc">優先級 高→低</option>
                <option value="progress-asc">進度 低→高</option>
                <option value="added-desc">最近加入</option>
            </select>

            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-success" onclick="bulkMarkWorkListDone()">全部已行動</button>
                <button type="button" class="btn btn-outline-primary" onclick="bulkQuickFollowupWorkList()">全部快速跟進</button>
            </div>
        </div>

        <div id="today-work-content" class="d-flex flex-column gap-1">
            <!-- JS 動態產生已接受的計劃快速行動列 -->
        </div>
        <!-- 手動加入 -->
        <div class="mt-2">
            <div class="input-group input-group-sm">
                <input type="text" id="manual-add-search" class="form-control" placeholder="搜尋計劃加入今日清單..." oninput="filterManualAddPlans(this.value)">
                <button type="button" class="btn btn-outline-secondary" onclick="clearManualAddSearch()">清除</button>
            </div>
            <div id="manual-add-results" class="mt-1 small" style="max-height: 120px; overflow-y: auto;"></div>
        </div>

        <div class="small text-muted mt-1">已接受的建議會留在這裡，方便你今日內快速跟進或記錄付款。行動後可標記為已完成。</div>
    </div>
</div>

<!-- 今日總結（Phase 3 最後收尾：每日工作指揮中心完整閉環） -->
<div id="today-summary-card" class="card border-0 bg-light mb-3 d-none">
    <div class="card-body py-2 px-3 small">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="fw-semibold text-dark">今日總結</span>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="endTodayAndCarryOver()" title="把剩餘未行動的計劃結轉到明天，今日清單歸零">
                今日結束 / 結轉
            </button>
        </div>

        <div class="row g-2 text-center mb-2">
            <div class="col-6 col-md-3">
                <div class="small text-muted">已行動</div>
                <div class="h6 mb-0 fw-semibold text-success" id="summary-actioned">0</div>
                <div class="tiny text-muted">本日累計 <span id="summary-daily-total">0</span></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-muted">平均優先級</div>
                <div class="h6 mb-0 fw-semibold" id="summary-avg-priority">—</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-muted">建議完成率</div>
                <div class="h6 mb-0 fw-semibold text-primary" id="summary-completion-rate">0%</div>
                <div class="tiny text-muted"><span id="summary-reco-done">0</span>/<span id="summary-reco-total">0</span></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-muted">剩餘待處理</div>
                <div class="h6 mb-0 fw-semibold text-warning" id="summary-remaining">0</div>
            </div>
        </div>

        <div class="small">
            <span class="text-muted">明天重點關注 Top 3：</span>
            <span id="summary-top3" class="text-dark"></span>
        </div>
        <div class="tiny text-muted mt-1">行動越多，數字即時更新。結轉後明日自動帶入「昨日未完」提示。</div>
    </div>
</div>

<!-- 客戶篩選提示橫幅 -->
<div id="customer-filter-banner" class="alert alert-info py-2 px-3 mb-3 d-none d-flex align-items-center justify-content-between">
    <div>
        <strong>已篩選客戶：</strong> <span id="customer-filter-text"></span>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="clearCustomerFilter()">清除篩選</button>
</div>

<!-- 篩選區 -->
<div class="card mb-3 border-0 bg-light">
    <div class="card-body py-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">狀態</label>
                <select id="filter-status" class="form-select form-select-sm" onchange="loadPlans()">
                    <option value="">全部狀態</option>
                    <option value="active" selected>進行中 (active)</option>
                    <option value="completed">已完成</option>
                    <option value="cancelled">已取消</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">類型</label>
                <select id="filter-type" class="form-select form-select-sm" onchange="loadPlans()">
                    <option value="">全部類型</option>
                    <option value="installment">分期</option>
                    <option value="recurring">周期性</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">搜尋（客戶電話 / 姓名 / 銷售單號）</label>
                <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="輸入電話或銷售單號..." oninput="debounceLoadPlans()">
            </div>
            <div class="col-md-1">
                <button onclick="clearFilters()" class="btn btn-sm btn-outline-secondary w-100">清除</button>
            </div>
        </div>
    </div>
</div>

<!-- 主列表 -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th style="width: 36px;">
                        <input type="checkbox" id="batch-select-all" onchange="toggleSelectAll(this)">
                    </th>
                    <th style="width: 70px;">計劃 #</th>
                    <th style="width: 90px;">銷售單</th>
                    <th>客戶</th>
                    <th style="width: 80px;">類型</th>
                    <th class="text-end" style="width: 110px;">每期金額</th>
                    <th style="width: 110px;">進度</th>
                    <th style="width: 90px;">狀態</th>
                    <th style="width: 110px;">開始日期</th>
                    <th style="width: 130px;" class="text-end">操作</th>
                </tr>
            </thead>
            <tbody id="plans-list">
                <tr><td colspan="9" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 計劃詳情 Modal -->
<div class="modal fade" id="planDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">計劃詳情 <span id="detail-plan-id" class="text-muted small"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="plan-detail-body">
                載入中...
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" id="continue-create-btn" class="btn btn-success d-none" onclick="continueCreatePlanForFilteredCustomer()">
                    為這位客戶繼續新增計劃
                </button>
                <button type="button" id="continue-sale-create-btn" class="btn btn-outline-success d-none" onclick="startNextPlanForCurrentSaleInDetail()">
                    為這筆銷售單新增下一期
                </button>
                <button type="button" class="btn btn-warning" onclick="startEditCurrentPlan()">
                    編輯此計劃
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
            </div>
        </div>
    </div>
</div>

<!-- 更改狀態 Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">更改計劃狀態</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="status-plan-id">
                <div class="mb-3">
                    <label class="form-label">目前狀態</label>
                    <div id="status-current" class="fw-medium"></div>
                </div>
                <div>
                    <label class="form-label">新狀態 <span class="text-danger">*</span></label>
                    <select id="status-new" class="form-select" onchange="updateStatusWarning()">
                        <option value="active">進行中 (active)</option>
                        <option value="completed">已完成</option>
                        <option value="cancelled">已取消</option>
                    </select>
                    <div id="status-warning" class="form-text small mt-1" style="display:none; color:#dc3545;"></div>
                    <div class="form-text text-danger small mt-1">注意：已有付款的計劃受保護，禁止不安全操作。</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="confirmStatusChange()">確認更改</button>
            </div>
        </div>
    </div>
</div>

<!-- 新增計劃 Modal -->
<div class="modal fade" id="createPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">新增分期 / 周期性計劃</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small py-2 mb-3">
                    注意：計劃通常應在銷售單結帳或 record_payment 頁面建立。此處為進階管理用途，請確保銷售單已存在且金額正確。
                </div>

                <!-- 當從客戶 Modal 快速新增時，動態顯示該客戶的銷售單選擇 -->
                <div id="create-sales-selector" class="mb-3 d-none">
                    <label class="form-label small fw-semibold">選擇該客戶的銷售單</label>
                    <div id="create-sales-list" class="border rounded p-2 bg-light small" style="max-height: 140px; overflow-y: auto;">
                        <!-- JS 動態填入 -->
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">銷售單 ID <span class="text-danger">*</span></label>
                        <input type="number" id="create-sale-id" class="form-control" placeholder="例如 1234">
                        <div class="form-text">必須是系統內已存在的銷售單</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">計劃類型 <span class="text-danger">*</span></label>
                        <select id="create-plan-type" class="form-select" onchange="toggleFrequencyField()">
                            <option value="installment">分期（installment）</option>
                            <option value="recurring">周期性（recurring）</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">總期數 <span class="text-danger">*</span></label>
                        <input type="number" id="create-total-installments" class="form-control" value="6" min="1" step="1">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">每期金額 (HK$) <span class="text-danger">*</span></label>
                        <input type="number" id="create-installment-amount" class="form-control" value="0" step="0.01" min="0">
                    </div>

                    <div class="col-md-6" id="create-frequency-group">
                        <label class="form-label">付款頻率</label>
                        <select id="create-frequency" class="form-select">
                            <option value="monthly">每月 (monthly)</option>
                            <option value="biweekly">雙週 (biweekly)</option>
                            <option value="weekly">每週 (weekly)</option>
                            <option value="quarterly">每季 (quarterly)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">開始日期 <span class="text-danger">*</span></label>
                        <input type="date" id="create-start-date" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">備註</label>
                        <textarea id="create-notes" class="form-control" rows="2" placeholder="例如：客戶要求調整..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="createPlan()">建立計劃</button>
            </div>
        </div>
    </div>
</div>

<!-- 編輯計劃 Modal（A 方向新增：保守保護已收款計劃） -->
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">編輯計劃 <span id="edit-plan-id-badge" class="text-muted small"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-plan-id">
                <input type="hidden" id="edit-payments-made">

                <!-- 保護提示區（JS 動態顯示） -->
                <div id="edit-financial-warning" class="alert alert-danger small py-2 mb-3 d-none">
                    此計劃已有付款記錄，<strong>每期金額、總期數、類型、頻率</strong>已鎖定不可修改。<br>
                    如需大幅調整，請建立新計劃或聯絡技術支援。
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">銷售單</label>
                        <div class="form-control-plaintext fw-medium" id="edit-sale-id-text"></div>
                        <div class="form-text">銷售單建立後不可變更</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">計劃類型 <span class="text-danger">*</span></label>
                        <select id="edit-plan-type" class="form-select" onchange="toggleEditFrequencyField()">
                            <option value="installment">分期（installment）</option>
                            <option value="recurring">周期性（recurring）</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">總期數 <span class="text-danger">*</span></label>
                        <input type="number" id="edit-total-installments" class="form-control" min="1" step="1">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">每期金額 (HK$) <span class="text-danger">*</span></label>
                        <input type="number" id="edit-installment-amount" class="form-control" step="0.01" min="0">
                    </div>

                    <div class="col-md-6" id="edit-frequency-group">
                        <label class="form-label">付款頻率</label>
                        <select id="edit-frequency" class="form-select">
                            <option value="monthly">每月 (monthly)</option>
                            <option value="biweekly">雙週 (biweekly)</option>
                            <option value="weekly">每週 (weekly)</option>
                            <option value="quarterly">每季 (quarterly)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">開始日期 <span class="text-danger">*</span></label>
                        <input type="date" id="edit-start-date" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">備註</label>
                        <textarea id="edit-notes" class="form-control" rows="2" placeholder="記錄調整原因..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="savePlanEdit()">儲存變更</button>
            </div>
        </div>
    </div>
</div>

<!-- 客戶編輯 Modal（簡化版，供計劃管理頁使用） -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customer-modal-title">編輯客戶</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <textarea id="customer-notes" rows="3" class="form-control" placeholder="過敏、偏好等..."></textarea>
                </div>

                <!-- 活躍計劃快速清單（Phase 3 Plan UI 強化） -->
                <div class="mt-3" id="customer-active-plans-section">
                    <label class="form-label small text-muted mb-1">該客戶活躍計劃</label>
                    <div id="customer-active-plans-list" class="small border rounded p-2 bg-light" style="max-height: 120px; overflow-y: auto;">
                        載入中...
                    </div>
                    <div class="small mt-1 text-muted">
                        點擊計劃可查看詳情
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-column align-items-stretch gap-2">
                <button type="button" class="btn btn-success w-100" onclick="startCreatePlanForCurrentCustomer()">
                    + 為這位客戶新增計劃
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">取消</button>
                    <button type="button" onclick="saveCustomerFromPlan()" class="btn btn-primary flex-fill" id="customer-save-btn">儲存變更</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 批量記錄付款 Modal（A 方向核心：讓選取計劃能快速收款） -->
<div class="modal fade" id="batchPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">批量記錄付款 <span id="batch-payment-count" class="text-muted small"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">統一付款方式</label>
                        <select id="batch-payment-method" class="form-select" onchange="updateBatchPaymentFeePreview()">
                            <option value="">載入中...</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">手續費負擔</label>
                        <select id="batch-fee-borne" class="form-select">
                            <option value="merchant">商家負擔</option>
                            <option value="customer">客戶負擔</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">共同備註 / 參考編號（選填）</label>
                        <input type="text" id="batch-payment-ref" class="form-control" placeholder="例如：FPS 轉帳記錄">
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">計劃</th>
                                <th>客戶</th>
                                <th class="text-end" style="width: 110px;">建議金額</th>
                                <th class="text-end" style="width: 110px;">實際金額</th>
                                <th style="width: 80px;">狀態</th>
                            </tr>
                        </thead>
                        <tbody id="batch-payment-list">
                            <!-- JS 動態填入 -->
                        </tbody>
                    </table>
                </div>

                <div class="small text-muted mt-2">
                    系統會自動為每筆計算建議手續費。點擊「確認記錄全部」會依序處理。
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="batch-payment-confirm-btn" onclick="executeBatchRecordPayments()">
                    確認記錄全部付款
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let debounceTimer;
let currentCustomerFilter = null; // {id, name, phone, stats: {total, active, completed, cancelled}}
let isNeedsAttentionView = false; // 是否目前在「需要關注」篩選模式
let strictNeedsAttentionOnly = false; // 在需要關注模式下，只顯示完全沒有跟進記錄的計劃
let dailyHandledCount = 0; // 本日已快速處理的未跟進計劃數
let selectedPlanIds = new Set();   // 批量操作選取的計劃 ID
let currentPlansData = [];         // 目前載入的計劃資料（供摘要面板計算使用）
let todayWorkList = [];            // 今日已接受建議的計劃工作清單（持久化）
let todayRecommendedCount = 0;     // 今日主動建議區曾經顯示的總計劃數（用於進度計算）
let todayActionedCount = 0;        // 今日已在工作清單內標記為已行動的數量

function loadDailyHandledCount() {
    const today = new Date().toISOString().slice(0, 10);
    const key = `dailyHandled_${today}`;
    const saved = localStorage.getItem(key);
    dailyHandledCount = saved ? parseInt(saved) : 0;
    updateDailyHandledDisplay();
}

function saveDailyHandledCount() {
    const today = new Date().toISOString().slice(0, 10);
    const key = `dailyHandled_${today}`;
    localStorage.setItem(key, dailyHandledCount);
}

function updateDailyHandledDisplay() {
    const el = document.getElementById('daily-handled-count');
    if (el) el.textContent = dailyHandledCount;
}

function loadTodayWorkList() {
    const today = new Date().toISOString().slice(0, 10);
    const key = `todayWorkList_${today}`;
    const saved = localStorage.getItem(key);
    todayWorkList = saved ? JSON.parse(saved) : [];

    const recKey = `todayRecommended_${today}`;
    todayRecommendedCount = parseInt(localStorage.getItem(recKey) || '0');

    const actKey = `todayActioned_${today}`;
    todayActionedCount = parseInt(localStorage.getItem(actKey) || '0');
}

function saveTodayWorkList() {
    const today = new Date().toISOString().slice(0, 10);
    const key = `todayWorkList_${today}`;
    localStorage.setItem(key, JSON.stringify(todayWorkList));

    localStorage.setItem(`todayRecommended_${today}`, todayRecommendedCount);
    localStorage.setItem(`todayActioned_${today}`, todayActionedCount);
}

function renderTodayWorkList() {
    const container = document.getElementById('today-work-list');
    const content = document.getElementById('today-work-content');
    const countEl = document.getElementById('today-work-count');
    const progressText = document.getElementById('today-progress-text');
    const progressBar = document.getElementById('today-progress-bar');

    if (!container || !content || !countEl) return;

    if (todayWorkList.length === 0) {
        container.classList.add('d-none');
        return;
    }

    // 讀取當前過濾與排序條件
    const searchInput = document.getElementById('today-work-search');
    const sortSelect = document.getElementById('today-work-sort');

    const keyword = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const sortMode = sortSelect ? sortSelect.value : 'priority-desc';

    // 過濾
    let filtered = todayWorkList;
    if (keyword) {
        filtered = todayWorkList.filter(p => {
            const name = (p.customer_name || '').toLowerCase();
            const phone = (p.customer_phone || '').toLowerCase();
            const idStr = String(p.id);
            return name.includes(keyword) || phone.includes(keyword) || idStr.includes(keyword);
        });
    }

    // 排序
    const sorted = [...filtered].sort((a, b) => {
        const fullA = currentPlansData.find(p => p.id == a.id) || a;
        const fullB = currentPlansData.find(p => p.id == b.id) || b;
        const priA = calculatePlanPriority(fullA).score;
        const priB = calculatePlanPriority(fullB).score;

        if (sortMode === 'priority-desc') return priB - priA;
        if (sortMode === 'progress-asc') {
            const pa = parseFloat(a.progress) || 0;
            const pb = parseFloat(b.progress) || 0;
            return pa - pb;
        }
        // 預設最近加入（按目前陣列順序反向，簡單處理）
        return 0;
    });

    container.classList.remove('d-none');
    countEl.textContent = todayWorkList.length;   // 顯示總數，不是過濾後

    // 計算今日進度（以全部為基準）
    const totalTarget = Math.max(todayRecommendedCount, todayWorkList.length);
    const progressPct = totalTarget > 0 ? Math.min(100, Math.round((todayActionedCount / totalTarget) * 100)) : 0;

    if (progressText) progressText.textContent = `${todayActionedCount} / ${totalTarget} 已行動（${progressPct}%）`;
    if (progressBar) progressBar.style.width = progressPct + '%';

    let html = '';
    sorted.forEach((plan) => {
        const fullPlan = currentPlansData.find(p => p.id == plan.id) || plan;
        const pri = calculatePlanPriority(fullPlan);

        html += `
            <div class="d-flex align-items-center justify-content-between bg-white rounded px-2 py-1 border small">
                <div>
                    <strong>#${plan.id}</strong> ${plan.customer_name ? e(plan.customer_name) : ''}
                    <span class="text-muted">（進度 ${plan.progress || 0}%）</span>
                    <span class="badge bg-dark ms-1" title="${pri.reasons.join('、')}">優先級 ${pri.score}</span>
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="quickFollowupFromWorkList(${plan.id}, this)">快速跟進</button>
                    <button type="button" class="btn btn-outline-success" onclick="recordPaymentFromWorkList(${plan.id})">記錄付款</button>
                    <button type="button" class="btn btn-success" onclick="markWorkListItemDone(${plan.id})">已行動</button>
                </div>
            </div>
        `;
    });

    content.innerHTML = html;

    // 行動後同步更新今日總結卡片
    renderTodaySummary();
}

function renderTodaySummary() {
    const card = document.getElementById('today-summary-card');
    if (!card) return;

    const actionedEl = document.getElementById('summary-actioned');
    const dailyTotalEl = document.getElementById('summary-daily-total');
    const avgPriEl = document.getElementById('summary-avg-priority');
    const rateEl = document.getElementById('summary-completion-rate');
    const recoDoneEl = document.getElementById('summary-reco-done');
    const recoTotalEl = document.getElementById('summary-reco-total');
    const remainingEl = document.getElementById('summary-remaining');
    const top3El = document.getElementById('summary-top3');

    const totalWork = todayWorkList.length;
    const remaining = totalWork; // 今日清單剩餘 = 尚未標記「已行動」的

    // 已行動數（含快速處理 + 今日清單內標記）
    const actioned = todayActionedCount || 0;
    const dailyTotal = dailyHandledCount || 0;

    // 平均優先級（掃描 todayWorkList + currentPlansData）
    let totalScore = 0;
    let scoredCount = 0;
    todayWorkList.forEach(item => {
        const full = currentPlansData.find(p => p.id == item.id) || item;
        const pri = calculatePlanPriority(full);
        if (pri.score > 0) {
            totalScore += pri.score;
            scoredCount++;
        }
    });
    const avgScore = scoredCount > 0 ? Math.round(totalScore / scoredCount) : 0;

    // 建議完成率
    const recoTotal = Math.max(todayRecommendedCount || 0, actioned);
    const completionRate = recoTotal > 0 ? Math.round((actioned / recoTotal) * 100) : 0;

    // 更新 DOM
    if (actionedEl) actionedEl.textContent = actioned;
    if (dailyTotalEl) dailyTotalEl.textContent = dailyTotal;
    if (avgPriEl) avgPriEl.textContent = avgScore > 0 ? avgScore + ' 分' : '—';
    if (rateEl) rateEl.textContent = completionRate + '%';
    if (recoDoneEl) recoDoneEl.textContent = actioned;
    if (recoTotalEl) recoTotalEl.textContent = recoTotal;
    if (remainingEl) remainingEl.textContent = remaining;

    // Top 3 明天重點
    let top3Html = '';
    if (todayWorkList.length > 0 && currentPlansData.length > 0) {
        const enriched = todayWorkList.map(item => {
            const full = currentPlansData.find(p => p.id == item.id) || item;
            return { ...item, ...full, priority: calculatePlanPriority(full) };
        });
        const top3 = [...enriched]
            .sort((a, b) => (b.priority?.score || 0) - (a.priority?.score || 0))
            .slice(0, 3);

        top3Html = top3.map((p, idx) => {
            const reason = (p.priority?.reasons && p.priority.reasons[0]) || '需跟進';
            return `<span class="badge bg-warning text-dark ms-1" style="font-size:0.68rem;">${idx+1}. #${p.id} ${reason}</span>`;
        }).join('');
    }
    if (top3El) top3El.innerHTML = top3Html || '<span class="text-muted">暫無剩餘計劃</span>';

    // 顯示或隱藏卡片
    if (todayWorkList.length > 0 || actioned > 0 || dailyTotal > 0) {
        card.classList.remove('d-none');
    } else {
        card.classList.add('d-none');
    }
}

function endTodayAndCarryOver() {
    if (todayWorkList.length === 0) {
        alert('今日清單已經沒有剩餘計劃');
        return;
    }

    const todayStr = new Date().toISOString().slice(0, 10);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().slice(0, 10);

    const carryKey = `carryOverPlans_${tomorrowStr}`;
    const existing = JSON.parse(localStorage.getItem(carryKey) || '[]');
    const newCarry = [...new Set([...existing, ...todayWorkList.map(p => p.id)])];

    localStorage.setItem(carryKey, JSON.stringify(newCarry));
    localStorage.setItem(`carryOverNote_${tomorrowStr}`, `昨日結轉 ${todayWorkList.length} 筆未完成計劃`);

    // 清空今日
    todayWorkList = [];
    todayActionedCount = 0;
    saveTodayWorkList();

    // 視覺更新
    renderTodayWorkList();
    renderTodaySummary();

    alert(`已成功結轉 ${newCarry.length} 筆計劃到明天（${tomorrowStr}）。明日開啟頁面時會在「今日處理中」區提示昨日結轉。`);
}

function loadCarryOverIfAny() {
    const todayStr = new Date().toISOString().slice(0, 10);
    const carryKey = `carryOverPlans_${todayStr}`;
    const carried = JSON.parse(localStorage.getItem(carryKey) || '[]');
    const note = localStorage.getItem(`carryOverNote_${todayStr}`);

    if (carried.length > 0 && currentPlansData && currentPlansData.length > 0) {
        const already = new Set(todayWorkList.map(p => p.id));
        let added = 0;

        carried.forEach(pid => {
            if (already.has(pid)) return;
            const plan = currentPlansData.find(p => p.id == pid);
            if (plan && plan.status === 'active') {
                todayWorkList.push({
                    id: plan.id,
                    customer_name: plan.customer_name,
                    progress: Math.round( (parseInt(plan.payments_made||0) / (parseInt(plan.total_installments)||1) ) * 100 )
                });
                added++;
            }
        });

        if (added > 0) {
            saveTodayWorkList();
            // 延遲渲染，讓 loadPlans 先完成
            setTimeout(() => {
                renderTodayWorkList();
                renderTodaySummary();
            }, 450);

            // 顯示一個小提示（可選）
            const banner = document.getElementById('customer-filter-banner');
            if (banner && note) {
                // 臨時覆蓋顯示結轉提示
                setTimeout(() => {
                    if (!banner.classList.contains('d-none')) return;
                    banner.classList.remove('d-none');
                    banner.classList.remove('alert-info');
                    banner.classList.add('alert-warning');
                    const text = document.getElementById('customer-filter-text');
                    if (text) text.innerHTML = `<strong>📥 昨日結轉：</strong> ${note}`;
                    // 點擊清除時也順便清 localStorage
                    const oldClear = banner.querySelector('button');
                    if (oldClear) {
                        oldClear.onclick = () => {
                            localStorage.removeItem(carryKey);
                            localStorage.removeItem(`carryOverNote_${todayStr}`);
                            clearCustomerFilter();
                        };
                    }
                }, 900);
            }
        }

        // 用完即清
        localStorage.removeItem(carryKey);
        localStorage.removeItem(`carryOverNote_${todayStr}`);
    }
}

function markWorkListItemDone(planId) {
    const idx = todayWorkList.findIndex(p => p.id == planId);
    if (idx === -1) return;

    // 從清單移除
    todayWorkList.splice(idx, 1);
    saveTodayWorkList();
    renderTodayWorkList();

    // 計入行動數
    todayActionedCount = Math.min(todayActionedCount + 1, todayRecommendedCount || todayWorkList.length + todayActionedCount);
    saveTodayWorkList();

    renderTodayWorkList();
    updateProactiveSuggestions();
}

function filterManualAddPlans(keyword) {
    const resultsEl = document.getElementById('manual-add-results');
    if (!resultsEl || !currentPlansData) return;

    const kw = (keyword || '').trim().toLowerCase();
    if (kw.length < 1) {
        resultsEl.innerHTML = '';
        return;
    }

    const alreadyInList = new Set(todayWorkList.map(p => p.id));

    const matches = currentPlansData
        .filter(p => p.status === 'active' && !alreadyInList.has(p.id))
        .filter(p => {
            const name = (p.customer_name || '').toLowerCase();
            const phone = (p.customer_phone || '').toLowerCase();
            const idStr = String(p.id);
            return name.includes(kw) || phone.includes(kw) || idStr.includes(kw);
        })
        .slice(0, 8);

    if (matches.length === 0) {
        resultsEl.innerHTML = `<div class="text-muted">沒有符合的活躍計劃</div>`;
        return;
    }

    let html = '';
    matches.forEach(p => {
        const pri = calculatePlanPriority(p);
        html += `
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                <div>
                    <strong>#${p.id}</strong> ${e(p.customer_name || '-')}
                    <span class="badge bg-dark ms-1" title="${pri.reasons.join('、')}">優先級 ${pri.score}</span>
                </div>
                <button type="button" class="btn btn-sm btn-success py-0 px-2" onclick="addPlanToTodayWorkList(${p.id})">加入</button>
            </div>
        `;
    });
    resultsEl.innerHTML = html;
}

function addPlanToTodayWorkList(planId) {
    if (!currentPlansData) return;

    const plan = currentPlansData.find(p => p.id == planId);
    if (!plan) return;

    // 避免重複
    if (todayWorkList.some(p => p.id == planId)) {
        alert('已在今日清單中');
        return;
    }

    todayWorkList.push({
        id: plan.id,
        customer_name: plan.customer_name,
        progress: Math.round( (parseInt(plan.payments_made||0) / (parseInt(plan.total_installments)||1) ) * 100 )
    });

    saveTodayWorkList();
    renderTodayWorkList();

    // 清空搜尋
    clearManualAddSearch();
}

function clearManualAddSearch() {
    const searchInput = document.getElementById('manual-add-search');
    const resultsEl = document.getElementById('manual-add-results');
    if (searchInput) searchInput.value = '';
    if (resultsEl) resultsEl.innerHTML = '';
}

/* ==================== 今日工作清單批量操作 ==================== */

function bulkMarkWorkListDone() {
    const visibleItems = getCurrentVisibleWorkListItems();
    if (visibleItems.length === 0) return;

    visibleItems.forEach(plan => {
        const idx = todayWorkList.findIndex(p => p.id == plan.id);
        if (idx !== -1) todayWorkList.splice(idx, 1);
    });

    saveTodayWorkList();
    renderTodayWorkList();

    todayActionedCount = Math.min(todayActionedCount + visibleItems.length, todayRecommendedCount || todayWorkList.length + todayActionedCount);
    saveTodayWorkList();
    renderTodayWorkList();
    renderTodaySummary();
    updateProactiveSuggestions();
}

function bulkQuickFollowupWorkList() {
    const visibleItems = getCurrentVisibleWorkListItems();
    if (visibleItems.length === 0) return;

    // 選取這些計劃
    document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = false);
    visibleItems.forEach(plan => {
        const cb = document.querySelector(`.batch-checkbox[value="${plan.id}"]`);
        if (cb) cb.checked = true;
    });

    updateBatchSelection();
    showBatchQuickFollowup();
}

function getCurrentVisibleWorkListItems() {
    const searchInput = document.getElementById('today-work-search');
    const keyword = searchInput ? searchInput.value.trim().toLowerCase() : '';

    let filtered = todayWorkList;
    if (keyword) {
        filtered = todayWorkList.filter(p => {
            const name = (p.customer_name || '').toLowerCase();
            const phone = (p.customer_phone || '').toLowerCase();
            const idStr = String(p.id);
            return name.includes(keyword) || phone.includes(keyword) || idStr.includes(keyword);
        });
    }
    return filtered;
}

function quickFollowupFromWorkList(planId, btnElement) {
    // 重用現有快速跟進邏輯
    const row = document.createElement('tr'); // 假 row 以重用現有函式
    // 簡化：直接呼叫現有 startQuickFollowup 風格
    startQuickFollowup(planId, btnElement.parentElement, null, '已初步處理');
}

function recordPaymentFromWorkList(planId) {
    // 選取這一筆並開啟批量記錄付款（只會有一筆）
    document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = false);
    const cb = document.querySelector(`.batch-checkbox[value="${planId}"]`);
    if (cb) cb.checked = true;

    updateBatchSelection();
    showBatchRecordPayment();
}

function acceptSingleProactivePlan(planId) {
    if (!currentPlansData) return;

    const plan = currentPlansData.find(p => p.id == planId);
    if (!plan) return;

    // 避免重複加入
    if (todayWorkList.some(p => p.id == planId)) {
        alert('這筆計劃已經在今日處理清單中');
        return;
    }

    // 加入今日清單（儲存必要資料）
    todayWorkList.push({
        id: plan.id,
        customer_name: plan.customer_name,
        progress: Math.round( (parseInt(plan.payments_made||0) / (parseInt(plan.total_installments)||1) ) * 100 )
    });

    saveTodayWorkList();
    renderTodayWorkList();

    // 自動加一條跟進標記
    SalonEase.fetch('/api/payment_plans.php?action=append_followup', {
        method: 'POST',
        body: new URLSearchParams({
            plan_id: planId,
            note: '已接受主動建議 - 加入今日處理清單',
            csrf_token: window.CSRF_TOKEN
        })
    }).then(() => {
        dailyHandledCount++;
        saveDailyHandledCount();
        updateDailyHandledDisplay();

        // 刷新建議區（這筆已處理，不再顯示）
        updateProactiveSuggestions();
    }).catch(() => {});

    // 從當前選取中移除（如果有的話）
    if (selectedPlanIds.has(planId)) {
        selectedPlanIds.delete(planId);
        updateBatchSelection();
    }

    // 更新今日總結
    renderTodaySummary();
}

/* ==================== 批量操作輔助函式 ==================== */

function updateBatchSelection() {
    const checkboxes = document.querySelectorAll('.batch-checkbox');
    selectedPlanIds.clear();

    checkboxes.forEach(cb => {
        if (cb.checked) {
            selectedPlanIds.add(parseInt(cb.value));
        }
    });

    updateBatchToolbar();
    updateSelectedSummary();   // 選取變化時即時更新摘要面板
    updateSelectionSuggestions(); // 智能建議區即時更新
    triggerProactiveUpdate();     // 主動建議區跟著更新
}

function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.batch-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = masterCheckbox.checked;
    });
    updateBatchSelection();
}

function updateBatchToolbar() {
    const toolbar = document.getElementById('batch-toolbar');
    const countEl = document.getElementById('batch-selected-count');
    const count = selectedPlanIds.size;

    if (!toolbar || !countEl) return;

    countEl.textContent = count;

    if (count > 0) {
        toolbar.classList.remove('d-none');
    } else {
        toolbar.classList.add('d-none');
    }
}

function clearBatchSelection() {
    selectedPlanIds.clear();
    document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('batch-select-all');
    if (master) master.checked = false;
    updateBatchToolbar();
    updateSelectedSummary();   // 清空後隱藏摘要
    updateSelectionSuggestions();
    triggerProactiveUpdate();
}

// 每次 render 後恢復 checkbox 選取狀態
function restoreBatchCheckboxes() {
    const checkboxes = document.querySelectorAll('.batch-checkbox');
    checkboxes.forEach(cb => {
        const id = parseInt(cb.value);
        cb.checked = selectedPlanIds.has(id);
    });

    const master = document.getElementById('batch-select-all');
    if (master) {
        const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
        master.checked = allChecked;
    }
    updateBatchToolbar();
    updateSelectedSummary();   // 恢復選取後更新摘要
    updateSelectionSuggestions();
    triggerProactiveUpdate();
}

/**
 * 更新「已選計劃摘要面板」（決策支援）
 * 當選取 ≥2 筆時顯示，包含總額、客戶數、平均進度、需跟進數、最老計劃（可點擊）
 */
function updateSelectedSummary() {
    const panel = document.getElementById('selected-summary-panel');
    const count = selectedPlanIds.size;

    if (!panel || count < 2) {
        if (panel) panel.classList.add('d-none');
        return;
    }

    // 從 currentPlansData 找出已選的計劃
    const selected = currentPlansData.filter(p => selectedPlanIds.has(p.id));
    if (selected.length === 0) {
        panel.classList.add('d-none');
        return;
    }

    // 計算各項數據
    let totalValue = 0;
    let totalProgress = 0;
    let needsAttentionCount = 0;
    const customerIds = new Set();
    let oldestPlan = null;

    const today = new Date();

    selected.forEach(p => {
        const installment = parseFloat(p.installment_amount || 0);
        const totalInst = parseInt(p.total_installments || 0);
        const made = parseInt(p.payments_made || 0);
        const progress = totalInst > 0 ? (made / totalInst) * 100 : 0;

        totalValue += installment * totalInst;
        totalProgress += progress;

        if (p.customer_id) customerIds.add(p.customer_id);

        // 需要關注判斷（與 render 邏輯一致）
        if (p.status === 'active' && totalInst > 0) {
            const progressRatio = made / totalInst;
            const created = p.created_at ? new Date(p.created_at) : null;
            const daysOld = created ? Math.floor((today - created) / (1000 * 60 * 60 * 24)) : 0;
            if (progressRatio < 0.3 && daysOld > 45) {
                needsAttentionCount++;
            }
        }

        // 找最老計劃
        if (!oldestPlan || (p.created_at && new Date(p.created_at) < new Date(oldestPlan.created_at))) {
            oldestPlan = p;
        }
    });

    const avgProgress = Math.round(totalProgress / selected.length);
    const remainingValue = totalValue; // 簡化顯示總計劃額（可之後再細分 remaining）

    // 更新 DOM
    document.getElementById('summary-total-value').textContent = 'HK$ ' + Math.round(remainingValue).toLocaleString();
    document.getElementById('summary-customer-count').textContent = customerIds.size;
    document.getElementById('summary-avg-progress').textContent = avgProgress + '%';
    document.getElementById('summary-needs-attention').textContent = needsAttentionCount;

    const oldestEl = document.getElementById('summary-oldest-plan');
    if (oldestPlan) {
        const days = oldestPlan.created_at ? Math.floor((today - new Date(oldestPlan.created_at)) / (1000*60*60*24)) : 0;
        oldestEl.innerHTML = `#${oldestPlan.id}（已 ${days} 天） <span class="text-muted">→</span>`;
        oldestEl.onclick = () => showPlanDetail(oldestPlan.id);
        oldestEl.style.cursor = 'pointer';
    } else {
        oldestEl.innerHTML = '-';
        oldestEl.onclick = null;
        oldestEl.style.cursor = 'default';
    }

    panel.classList.remove('d-none');
}

/* ==================== 智能建議選取功能（B 方向強化） ==================== */

let previousFullSelection = null; // 用來支援「還原目前選取」

function updateSelectionSuggestions() {
    const suggestionsEl = document.getElementById('selection-suggestions');
    const textEl = document.getElementById('suggestion-text');

    if (!suggestionsEl || !textEl) return;

    const count = selectedPlanIds.size;

    if (count < 3) {
        suggestionsEl.classList.add('d-none');
        previousFullSelection = null;
        return;
    }

    const selected = currentPlansData.filter(p => selectedPlanIds.has(p.id));
    if (selected.length === 0) {
        suggestionsEl.classList.add('d-none');
        return;
    }

    const today = new Date();

    // 分析目前選取
    let unfollowed = [];
    let oldestFirst = [...selected].sort((a, b) => {
        if (!a.created_at) return 1;
        if (!b.created_at) return -1;
        return new Date(a.created_at) - new Date(b.created_at);
    });
    let lowestProgress = [...selected].sort((a, b) => {
        const pa = (parseInt(a.payments_made || 0) / (parseInt(a.total_installments) || 1)) * 100;
        const pb = (parseInt(b.payments_made || 0) / (parseInt(b.total_installments) || 1)) * 100;
        return pa - pb;
    });

    selected.forEach(p => {
        const hasFollowup = p.notes && p.notes.includes('[跟進 ');
        if (!hasFollowup && p.status === 'active') {
            unfollowed.push(p);
        }
    });

    // 決定要顯示什麼建議
    let suggestionHTML = '';
    let hasUsefulSuggestion = false;

    if (unfollowed.length > 0 && unfollowed.length < selected.length) {
        suggestionHTML = `目前選取中有 <strong class="text-danger">${unfollowed.length}</strong> 筆完全未跟進`;
        hasUsefulSuggestion = true;
    } else if (oldestFirst.length > 5) {
        suggestionHTML = `已選 ${count} 筆，可快速聚焦最關鍵的幾筆`;
        hasUsefulSuggestion = true;
    } else {
        suggestionHTML = `已選 ${count} 筆`;
    }

    textEl.innerHTML = suggestionHTML;

    if (hasUsefulSuggestion) {
        suggestionsEl.classList.remove('d-none');
    } else {
        suggestionsEl.classList.add('d-none');
    }
}

function selectSmartSubset(type) {
    if (!currentPlansData || selectedPlanIds.size === 0) return;

    const selected = currentPlansData.filter(p => selectedPlanIds.has(p.id));
    if (selected.length === 0) return;

    // 備份目前完整選取（第一次才備份）
    if (!previousFullSelection) {
        previousFullSelection = new Set(selectedPlanIds);
    }

    let targetIds = [];

    const today = new Date();

    if (type === 'oldest') {
        const sorted = [...selected].sort((a, b) => {
            if (!a.created_at) return 1;
            if (!b.created_at) return -1;
            return new Date(a.created_at) - new Date(b.created_at);
        });
        targetIds = sorted.slice(0, 5).map(p => p.id);
    } 
    else if (type === 'unfollowed') {
        targetIds = selected
            .filter(p => p.status === 'active' && !(p.notes && p.notes.includes('[跟進 ')))
            .map(p => p.id);
    } 
    else if (type === 'lowest-progress') {
        const sorted = [...selected].sort((a, b) => {
            const pa = (parseInt(a.payments_made || 0) / (parseInt(a.total_installments) || 1)) * 100;
            const pb = (parseInt(b.payments_made || 0) / (parseInt(b.total_installments) || 1)) * 100;
            return pa - pb;
        });
        targetIds = sorted.slice(0, 5).map(p => p.id);
    } 
    else if (type === 'priority') {
        // 最需優先處理 = 最老 + 未跟進 + 進度低
        const scored = selected.map(p => {
            let score = 0;
            if (p.created_at) {
                const days = Math.floor((today - new Date(p.created_at)) / (1000 * 60 * 60 * 24));
                score += Math.min(days, 120); // 最老加分
            }
            if (p.status === 'active' && !(p.notes && p.notes.includes('[跟進 '))) {
                score += 60; // 未跟進加分
            }
            const progress = (parseInt(p.payments_made || 0) / (parseInt(p.total_installments) || 1)) * 100;
            score += (100 - progress); // 進度越低分數越高
            return { id: p.id, score };
        });
        scored.sort((a, b) => b.score - a.score);
        targetIds = scored.slice(0, 6).map(x => x.id); // 取前 6 筆最優先
    }

    if (targetIds.length === 0) {
        alert('目前選取中沒有符合此條件的計劃');
        return;
    }

    // 實際調整 checkbox
    document.querySelectorAll('.batch-checkbox').forEach(cb => {
        const id = parseInt(cb.value);
        cb.checked = targetIds.includes(id);
    });

    // 觸發現有更新流程（會自動更新摘要、建議、最老置頂、工具列）
    updateBatchSelection();
}

function resetToCurrentSelection() {
    if (!previousFullSelection || previousFullSelection.size === 0) {
        alert('沒有可還原的選取記錄');
        return;
    }

    document.querySelectorAll('.batch-checkbox').forEach(cb => {
        const id = parseInt(cb.value);
        cb.checked = previousFullSelection.has(id);
    });

    previousFullSelection = null; // 用過就清

    updateBatchSelection();
}

/* ==================== 主動建議處理功能（A 方向：更主動 + 情境化） ==================== */

function updateProactiveSuggestions() {
    const container = document.getElementById('proactive-suggestions');
    const content = document.getElementById('proactive-suggestions-content');

    if (!container || !content || !currentPlansData || currentPlansData.length === 0) {
        if (container) container.classList.add('d-none');
        return;
    }

    const today = new Date();
    const activePlans = currentPlansData.filter(p => p.status === 'active');

    if (activePlans.length === 0) {
        container.classList.add('d-none');
        return;
    }

    // 群組 1: 最老且完全未跟進的 (最高優先)
    const oldestUnfollowed = activePlans
        .filter(p => !(p.notes && p.notes.includes('[跟進 ')))
        .sort((a, b) => {
            if (!a.created_at) return 1;
            if (!b.created_at) return -1;
            return new Date(a.created_at) - new Date(b.created_at);
        })
        .slice(0, 6);

    // 群組 2: 進度嚴重落後 (低於 25% 且建立超過 60 天)
    const severelyBehind = activePlans
        .filter(p => {
            const made = parseInt(p.payments_made || 0);
            const total = parseInt(p.total_installments || 1);
            const progress = (made / total) * 100;
            if (progress >= 25) return false;
            if (!p.created_at) return false;
            const days = Math.floor((today - new Date(p.created_at)) / (1000 * 60 * 60 * 24));
            return days > 60;
        })
        .sort((a, b) => {
            const pa = (parseInt(a.payments_made || 0) / (parseInt(a.total_installments) || 1)) * 100;
            const pb = (parseInt(b.payments_made || 0) / (parseInt(b.total_installments) || 1)) * 100;
            return pa - pb;
        })
        .slice(0, 6);

    let html = '';

    if (oldestUnfollowed.length > 0) {
        // 找出這群中優先級最高的一筆作為代表
        const top = [...oldestUnfollowed].sort((a, b) => calculatePlanPriority(b).score - calculatePlanPriority(a).score)[0];
        const pri = calculatePlanPriority(top);
        html += `
            <div class="d-flex align-items-center gap-2 bg-white rounded px-2 py-1 border">
                <span class="badge bg-danger">最老未跟進</span>
                <span class="small">共 <strong>${oldestUnfollowed.length}</strong> 筆</span>
                <span class="badge bg-dark ms-1" title="${pri.reasons.join('、')}">優先級 ${pri.score}</span>
                <button type="button" class="btn btn-sm btn-danger ms-1" onclick="applyProactiveGroup('oldest-unfollowed')">
                    選取 + 批量快速跟進
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="acceptProactiveGroupPlans(${JSON.stringify(oldestUnfollowed.map(p=>p.id))})">
                    接受這批
                </button>
            </div>
        `;
    }

    if (severelyBehind.length > 0) {
        const top = [...severelyBehind].sort((a, b) => calculatePlanPriority(b).score - calculatePlanPriority(a).score)[0];
        const pri = calculatePlanPriority(top);
        html += `
            <div class="d-flex align-items-center gap-2 bg-white rounded px-2 py-1 border">
                <span class="badge bg-warning text-dark">嚴重落後</span>
                <span class="small">共 <strong>${severelyBehind.length}</strong> 筆</span>
                <span class="badge bg-dark ms-1" title="${pri.reasons.join('、')}">優先級 ${pri.score}</span>
                <button type="button" class="btn btn-sm btn-warning text-dark ms-1" onclick="applyProactiveGroup('severely-behind')">
                    選取 + 批量記錄付款
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark" onclick="acceptProactiveGroupPlans(${JSON.stringify(severelyBehind.map(p=>p.id))})">
                    接受這批
                </button>
            </div>
        `;
    }

    if (html === '') {
        container.classList.add('d-none');
        return;
    }

    content.innerHTML = html;
    container.classList.remove('d-none');

    // 記錄今日曾經顯示的建議總數（用於進度計算）
    const uniqueRecommended = new Set([...oldestUnfollowed, ...severelyBehind].map(p => p.id));
    if (uniqueRecommended.size > todayRecommendedCount) {
        todayRecommendedCount = uniqueRecommended.size;
        saveTodayWorkList();   // 順便保存
    }
}

function applyProactiveGroup(type) {
    if (!currentPlansData) return;

    const today = new Date();
    let targetPlans = [];

    const activePlans = currentPlansData.filter(p => p.status === 'active');

    if (type === 'oldest-unfollowed') {
        targetPlans = activePlans
            .filter(p => !(p.notes && p.notes.includes('[跟進 ')))
            .sort((a, b) => {
                if (!a.created_at) return 1;
                if (!b.created_at) return -1;
                return new Date(a.created_at) - new Date(b.created_at);
            })
            .slice(0, 6);
    } else if (type === 'severely-behind') {
        targetPlans = activePlans
            .filter(p => {
                const made = parseInt(p.payments_made || 0);
                const total = parseInt(p.total_installments || 1);
                const progress = (made / total) * 100;
                if (progress >= 25) return false;
                if (!p.created_at) return false;
                const days = Math.floor((today - new Date(p.created_at)) / (1000 * 60 * 60 * 24));
                return days > 60;
            })
            .sort((a, b) => {
                const pa = (parseInt(a.payments_made || 0) / (parseInt(a.total_installments) || 1)) * 100;
                const pb = (parseInt(b.payments_made || 0) / (parseInt(b.total_installments) || 1)) * 100;
                return pa - pb;
            })
            .slice(0, 6);
    }

    if (targetPlans.length === 0) {
        alert('目前沒有符合條件的計劃');
        return;
    }

    const targetIds = targetPlans.map(p => p.id);

    // 設定選取
    document.querySelectorAll('.batch-checkbox').forEach(cb => {
        const id = parseInt(cb.value);
        cb.checked = targetIds.includes(id);
    });

    updateBatchSelection();   // 更新摘要、建議等

    // 根據群組類型，直接開啟最合適的批量操作 Modal（情境化）
    setTimeout(() => {
        if (type === 'oldest-unfollowed') {
            showBatchQuickFollowup();
        } else if (type === 'severely-behind') {
            showBatchRecordPayment();
        }
    }, 180);
}

function acceptAllProactiveSuggestions() {
    if (!currentPlansData) return;

    const today = new Date();
    const activePlans = currentPlansData.filter(p => p.status === 'active');

    // 重新計算兩個群組（同 updateProactiveSuggestions 邏輯）
    const oldestUnfollowed = activePlans
        .filter(p => !(p.notes && p.notes.includes('[跟進 ')))
        .sort((a, b) => {
            if (!a.created_at) return 1;
            if (!b.created_at) return -1;
            return new Date(a.created_at) - new Date(b.created_at);
        })
        .slice(0, 6);

    const severelyBehind = activePlans
        .filter(p => {
            const made = parseInt(p.payments_made || 0);
            const total = parseInt(p.total_installments || 1);
            const progress = (made / total) * 100;
            if (progress >= 25) return false;
            if (!p.created_at) return false;
            const days = Math.floor((today - new Date(p.created_at)) / (1000 * 60 * 60 * 24));
            return days > 60;
        })
        .sort((a, b) => {
            const pa = (parseInt(a.payments_made || 0) / (parseInt(a.total_installments) || 1)) * 100;
            const pb = (parseInt(b.payments_made || 0) / (parseInt(b.total_installments) || 1)) * 100;
            return pa - pb;
        })
        .slice(0, 6);

    // 合併所有建議計劃（去重）
    const allSuggested = [...oldestUnfollowed, ...severelyBehind];
    const uniqueIds = [...new Set(allSuggested.map(p => p.id))];

    if (uniqueIds.length === 0) {
        alert('目前沒有可接受的建議');
        return;
    }

    // 選取這些計劃
    document.querySelectorAll('.batch-checkbox').forEach(cb => {
        const id = parseInt(cb.value);
        cb.checked = uniqueIds.includes(id);
    });

    updateBatchSelection();

    // 使用現有 bulk_append_followup 自動加一條標準跟進（標記為今日接受建議）
    const defaultNote = '已接受主動建議 - 今日初步處理';

    SalonEase.fetch('/api/payment_plans.php?action=bulk_append_followup', {
        method: 'POST',
        body: new URLSearchParams({
            plan_ids: JSON.stringify(uniqueIds),
            note: defaultNote,
            csrf_token: window.CSRF_TOKEN
        })
    })
    .then(() => {
        // 計入今日處理數
        dailyHandledCount += uniqueIds.length;
        saveDailyHandledCount();
        updateDailyHandledDisplay();

        // 刷新建議區（因為部分計劃已加跟進，不再是「未跟進」）
        updateProactiveSuggestions();
        updateSelectionSuggestions();
        renderTodaySummary();

        // 智能決定下一步行動
        const hasManyUnfollowed = oldestUnfollowed.length >= 3;
        const hasManyBehind = severelyBehind.length >= 3;

        setTimeout(() => {
            if (hasManyUnfollowed) {
                showBatchQuickFollowup();
            } else if (hasManyBehind) {
                showBatchRecordPayment();
            } else {
                // 混合情況，開快速跟進最安全
                showBatchQuickFollowup();
            }
        }, 250);
    })
    .catch(err => {
        alert('接受建議時發生錯誤：' + err.message);
    });
}

function acceptProactiveGroupPlans(planIds) {
    if (!Array.isArray(planIds) || planIds.length === 0) return;

    planIds.forEach(id => {
        acceptSingleProactivePlan(id);
    });
}

// 在 loadPlans 渲染後以及選取變化時呼叫主動建議
function triggerProactiveUpdate() {
    updateProactiveSuggestions();
}

/**
 * 計算計劃優先級分數（0-100）
 * 越高 = 越需要今日優先處理
 */
function calculatePlanPriority(plan) {
    if (!plan || plan.status !== 'active') return { score: 0, reasons: [] };

    const today = new Date();
    let score = 0;
    const reasons = [];

    const made = parseInt(plan.payments_made || 0);
    const total = parseInt(plan.total_installments || 1);
    const progress = (made / total) * 100;
    const hasFollowup = plan.notes && plan.notes.includes('[跟進 ');

    // 完全未跟進 = 重點加分
    if (!hasFollowup) {
        score += 35;
        reasons.push('完全未跟進');
    }

    // 進度越低越危險
    if (progress < 25) {
        score += 30;
        reasons.push('進度嚴重落後');
    } else if (progress < 50) {
        score += 15;
        reasons.push('進度偏低');
    }

    // 建立時間越長越需要處理
    if (plan.created_at) {
        const days = Math.floor((today - new Date(plan.created_at)) / (1000 * 60 * 60 * 24));
        if (days > 90) {
            score += 25;
            reasons.push('建立超過90天');
        } else if (days > 60) {
            score += 18;
            reasons.push('建立超過60天');
        } else if (days > 45) {
            score += 10;
        }
    }

    // 計劃越老（在活躍計劃中）越優先
    // 這裡用簡單啟發式：如果進度低 + 時間長，已經加分夠多

    return {
        score: Math.min(100, Math.round(score)),
        reasons: reasons.length > 0 ? reasons : ['一般優先']
    };
}

/* ==================== 批量記錄付款功能（本輪 A 核心） ==================== */
let batchPaymentMethodsCache = null;

async function loadPaymentMethodsForBatch() {
    if (batchPaymentMethodsCache) return batchPaymentMethodsCache;

    try {
        const res = await SalonEase.fetch('/api/payment_methods.php?action=list&active=1');
        batchPaymentMethodsCache = res.data || [];
        return batchPaymentMethodsCache;
    } catch (e) {
        console.warn('載入付款方式失敗', e);
        return [];
    }
}

function showBatchRecordPayment() {
    const count = selectedPlanIds.size;
    if (count === 0) return;

    const modalEl = document.getElementById('batchPaymentModal');
    const listBody = document.getElementById('batch-payment-list');
    const countEl = document.getElementById('batch-payment-count');
    const confirmBtn = document.getElementById('batch-payment-confirm-btn');

    if (!modalEl || !listBody) return;

    // 只處理活躍計劃
    const activePlans = currentPlansData.filter(p => 
        selectedPlanIds.has(p.id) && p.status === 'active'
    );

    if (activePlans.length === 0) {
        alert('目前選取的計劃中沒有可記錄付款的進行中計劃');
        return;
    }

    countEl.textContent = `（${activePlans.length} 筆進行中計劃）`;
    listBody.innerHTML = '';
    confirmBtn.disabled = false;
    confirmBtn.textContent = `確認記錄全部付款（${activePlans.length}）`;

    // 載入付款方式
    loadPaymentMethodsForBatch().then(methods => {
        const methodSelect = document.getElementById('batch-payment-method');
        methodSelect.innerHTML = '<option value="">請選擇付款方式</option>';

        methods.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
            methodSelect.appendChild(opt);
        });

        // 預設選第一個常用方式（如果有）
        if (methods.length > 0) {
            methodSelect.value = methods[0].id;
        }
    });

    // 建立列表
    activePlans.forEach(p => {
        const made = parseInt(p.payments_made || 0);
        const total = parseInt(p.total_installments || 0);
        const installment = parseFloat(p.installment_amount || 0);

        const row = document.createElement('tr');
        row.dataset.planId = p.id;
        row.dataset.saleId = p.sale_id;
        row.innerHTML = `
            <td class="fw-medium">#${p.id}</td>
            <td>
                ${e(p.customer_name || '-')}
                <div class="small text-muted">${e(p.customer_phone || '')}</div>
            </td>
            <td class="text-end">HK$ ${installment.toFixed(0)}</td>
            <td class="text-end">
                <input type="number" class="form-control form-control-sm batch-pay-amount" 
                       value="${installment.toFixed(2)}" step="0.01" min="0" style="width: 110px;">
            </td>
            <td>
                <span class="badge bg-secondary batch-pay-status">待記錄</span>
            </td>
        `;
        listBody.appendChild(row);
    });

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
}

async function executeBatchRecordPayments() {
    const methodSelect = document.getElementById('batch-payment-method');
    const feeBorneSelect = document.getElementById('batch-fee-borne');
    const refInput = document.getElementById('batch-payment-ref');
    const confirmBtn = document.getElementById('batch-payment-confirm-btn');
    const listBody = document.getElementById('batch-payment-list');

    if (!methodSelect || !methodSelect.value) {
        alert('請選擇付款方式');
        return;
    }

    const paymentMethodId = parseInt(methodSelect.value);
    const feeBorneBy = feeBorneSelect.value;
    const commonRef = refInput.value.trim();

    const rows = listBody.querySelectorAll('tr');
    if (rows.length === 0) return;

    confirmBtn.disabled = true;
    confirmBtn.textContent = '處理中...';

    let successCount = 0;
    let failCount = 0;

    for (const row of rows) {
        const planId = parseInt(row.dataset.planId);
        const saleId = parseInt(row.dataset.saleId);
        const amountInput = row.querySelector('.batch-pay-amount');
        const statusBadge = row.querySelector('.batch-pay-status');

        const amount = parseFloat(amountInput.value);
        if (!amount || amount <= 0) {
            statusBadge.textContent = '金額無效';
            statusBadge.className = 'badge bg-danger';
            failCount++;
            continue;
        }

        statusBadge.textContent = '處理中...';
        statusBadge.className = 'badge bg-warning';

        try {
            // 呼叫現有 record API（支援 plan_id）
            await SalonEase.fetch('/api/payments.php?action=record', {
                method: 'POST',
                body: new URLSearchParams({
                    sale_id: saleId,
                    payment_method_id: paymentMethodId,
                    amount: amount,
                    fee_borne_by: feeBorneBy,
                    ref_number: commonRef,
                    notes: `批量記錄 - 計劃 #${planId}`,
                    plan_id: planId,
                    // installment_no 可由後端自動處理或留空
                    csrf_token: window.CSRF_TOKEN
                })
            });

            successCount++;
            statusBadge.textContent = '已記錄';
            statusBadge.className = 'badge bg-success';

            // 即時更新主列表該計劃的 row
            updatePlanRowAfterPayment(planId, amount);

        } catch (err) {
            failCount++;
            statusBadge.textContent = '失敗';
            statusBadge.className = 'badge bg-danger';
            console.warn('批量付款失敗', planId, err);
        }
    }

    // 全部處理完後刷新整體狀態
    setTimeout(() => {
        loadSummary();
        loadPlanDashboard();
        updateSelectedSummary();
        applyOldestPlanPin();

        // 如果在嚴格模式，可能需要重新整理列表（因為進度改變可能影響需跟進狀態）
        if (strictNeedsAttentionOnly) {
            loadPlans();
        }

        confirmBtn.textContent = `完成（成功 ${successCount}，失敗 ${failCount}）`;
    }, 300);

    // 3 秒後自動關閉 modal
    setTimeout(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('batchPaymentModal'));
        if (modal) modal.hide();
        // 清空選取（使用者通常記錄完就想繼續）
        clearBatchSelection();
    }, 2800);
}

/**
 * 單筆付款成功後即時更新主列表中的該計劃 row（零刷新核心）
 */
function updatePlanRowAfterPayment(planId, paidAmount) {
    const row = document.querySelector(`tr[data-plan-id="${planId}"]`);
    if (!row) return;

    // 找到進度相關的 cell（第 6 個 td 大約是進度）
    const cells = row.querySelectorAll('td');
    if (cells.length < 6) return;

    // 嘗試更新 payments_made（我們在 render 時有 data，但這裡用 DOM 更新）
    // 簡單做法：重新載入該計劃的最新資料（輕量）
    // 為了極致零刷新，我們直接增加 payments_made 計數並重算進度

    // 從 row 目前的 progress 文字推斷
    const progressCell = cells[5]; // 進度欄
    if (!progressCell) return;

    // 找 progress bar 和文字
    const progressBar = progressCell.querySelector('.progress-bar');
    const progressText = progressCell.querySelector('small');

    if (progressBar && progressText) {
        // 解析目前 made / total
        const text = progressText.textContent || '0/1';
        const parts = text.split('/');
        let made = parseInt(parts[0]) || 0;
        const total = parseInt(parts[1]) || 1;

        made += 1; // 這次多記錄一期

        const newProgress = total > 0 ? Math.round((made / total) * 100) : 0;

        // 更新 bar
        progressBar.style.width = newProgress + '%';
        if (newProgress >= 80) progressBar.className = 'progress-bar bg-success';
        else if (newProgress >= 40) progressBar.className = 'progress-bar bg-warning';
        else progressBar.className = 'progress-bar bg-danger';

        // 更新文字
        progressText.textContent = `${made}/${total}`;

        // 如果已經 100%，可能要考慮移除需跟進樣式
        if (made >= total) {
            row.classList.remove('plan-needs-attention');
        }
    }

    // 更新 payments_made 隱藏資料（如果之後需要）
    row.dataset.paymentsMade = (parseInt(row.dataset.paymentsMade || 0) + 1).toString();
}

function showBatchQuickFollowup() {
    const count = selectedPlanIds.size;
    if (count === 0) return;

    const toolbar = document.getElementById('batch-toolbar');
    if (!toolbar) return;

    const originalToolbarHTML = toolbar.innerHTML;

    toolbar.innerHTML = `
        <div>
            <div class="small fw-semibold mb-1">為選取的 <strong>${count}</strong> 筆計劃記錄跟進：</div>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="executeBatchQuickFollowup(this, '已聯絡')">已聯絡</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="executeBatchQuickFollowup(this, '客戶說下週付')">客戶說下週付</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="executeBatchQuickFollowup(this, '已發提醒')">已發提醒</button>
            </div>
            <div class="d-flex gap-2">
                <input type="text" id="batch-quick-note" class="form-control form-control-sm" placeholder="自訂跟進內容..." style="flex:1;">
                <button class="btn btn-sm btn-primary" onclick="executeBatchQuickFollowup(this)">儲存</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="restoreBatchToolbar(${JSON.stringify(originalToolbarHTML)})">取消</button>
            </div>
        </div>
    `;

    setTimeout(() => {
        const input = document.getElementById('batch-quick-note');
        if (input) input.focus();
    }, 50);
}

async function executeBatchQuickFollowup(btnElement, presetNote = '') {
    const input = document.getElementById('batch-quick-note');
    let note = presetNote || (input ? input.value.trim() : '');

    if (!note) {
        alert('請輸入跟進內容');
        return;
    }

    const planIds = Array.from(selectedPlanIds);
    const originalText = btnElement ? btnElement.textContent : '';
    if (btnElement) {
        btnElement.disabled = true;
        btnElement.textContent = '處理中...';
    }

    try {
        const res = await SalonEase.fetch('/api/payment_plans.php?action=bulk_append_followup', {
            method: 'POST',
            body: new URLSearchParams({
                plan_ids: JSON.stringify(planIds),
                note: note,
                csrf_token: window.CSRF_TOKEN
            })
        });

        const successCount = res.data?.success_count || 0;

        // 即時更新所有受影響的行（零刷新）
        planIds.forEach(pid => {
            const row = document.querySelector(`tr[data-plan-id="${pid}"]`);
            if (!row) return;

            // 移除需要關注紅框
            row.classList.remove('plan-needs-attention');
            row.classList.add('plan-handled');

            const firstCell = row.querySelector('td.fw-medium') || row.querySelector('td');

            // 移除舊未跟進 badge
            if (firstCell) {
                firstCell.querySelectorAll('.badge.bg-danger').forEach(b => {
                    if (b.textContent.includes('未跟進') || b.textContent.includes('需跟進')) b.remove();
                });
            }

            // 加「有跟進」badge
            if (firstCell) {
                let badge = firstCell.querySelector('.badge.bg-secondary');
                const newNote = `[跟進 ${new Date().toISOString().slice(0,16).replace('T',' ')}] ${note}`;

                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge bg-secondary ms-1';
                    firstCell.appendChild(badge);
                }
                badge.textContent = '有跟進';
                badge.onclick = (e) => {
                    e.stopImmediatePropagation();
                    toggleFollowupExpansion(pid, badge, [newNote]);
                };
            }

            // 如果是嚴格模式，把行移到下面
            if (strictNeedsAttentionOnly) {
                row.classList.remove('plan-needs-attention');
                row.classList.add('plan-handled');

                const handledBadge = document.createElement('span');
                handledBadge.className = 'badge bg-success ms-1';
                handledBadge.textContent = '已初步處理';
                if (firstCell) firstCell.appendChild(handledBadge);

                const tbody = row.parentNode;
                if (tbody) tbody.appendChild(row);
            }
        });

        // 更新統計
        if (strictNeedsAttentionOnly) {
            const countEl = document.getElementById('unfollowed-count');
            if (countEl) {
                let cur = parseInt(countEl.textContent) || 0;
                countEl.textContent = Math.max(0, cur - successCount);
            }
        }

        dailyHandledCount += successCount;
        saveDailyHandledCount();
        updateDailyHandledDisplay();

        // 重新套用最老置頂
        applyOldestPlanPin();

        // 清理選取
        clearBatchSelection();

        alert(`已成功為 ${successCount} 筆計劃記錄跟進`);

    } catch (err) {
        alert('批量跟進失敗：' + err.message);
    } finally {
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.textContent = originalText || '儲存';
        }
    }
}

function restoreBatchToolbar(originalHTML) {
    const toolbar = document.getElementById('batch-toolbar');
    if (toolbar) {
        toolbar.innerHTML = originalHTML;
        updateBatchToolbar(); // 重新綁定事件（因為 HTML 替換了）
    }
}

function batchMarkHandled() {
    // 簡化版：直接把選取的行轉為 handled 樣式 + 移到下方（嚴格模式）
    const planIds = Array.from(selectedPlanIds);
    if (planIds.length === 0) return;

    planIds.forEach(pid => {
        const row = document.querySelector(`tr[data-plan-id="${pid}"]`);
        if (!row) return;

        row.classList.remove('plan-needs-attention');
        row.classList.add('plan-handled');

        const firstCell = row.querySelector('td');
        if (firstCell) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-success ms-1';
            badge.textContent = '已初步處理';
            firstCell.appendChild(badge);
        }

        if (strictNeedsAttentionOnly) {
            const tbody = row.parentNode;
            if (tbody) tbody.appendChild(row);
        }
    });

    const countEl = document.getElementById('unfollowed-count');
    if (countEl && strictNeedsAttentionOnly) {
        let cur = parseInt(countEl.textContent) || 0;
        countEl.textContent = Math.max(0, cur - planIds.length);
    }

    dailyHandledCount += planIds.length;
    saveDailyHandledCount();
    updateDailyHandledDisplay();

    applyOldestPlanPin();
    clearBatchSelection();
}

function batchMarkResolved() {
    const planIds = Array.from(selectedPlanIds);
    if (planIds.length === 0) return;

    planIds.forEach(pid => {
        const row = document.querySelector(`tr[data-plan-id="${pid}"]`);
        if (!row) return;

        row.classList.remove('plan-needs-attention', 'plan-handled');
        row.classList.add('plan-resolved');

        const firstCell = row.querySelector('td');
        if (firstCell) {
            firstCell.querySelectorAll('.badge').forEach(b => b.remove());
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary ms-1';
            badge.textContent = '已解決';
            firstCell.appendChild(badge);
        }

        const tbody = row.parentNode;
        if (tbody) tbody.appendChild(row);
    });

    const countEl = document.getElementById('unfollowed-count');
    if (countEl) {
        let cur = parseInt(countEl.textContent) || 0;
        countEl.textContent = Math.max(0, cur - planIds.length);
    }

    applyOldestPlanPin();
    clearBatchSelection();
}

function showBatchStatusChange() {
    const count = selectedPlanIds.size;
    if (count === 0) return;

    const toolbar = document.getElementById('batch-toolbar');
    if (!toolbar) return;

    const originalToolbarHTML = toolbar.innerHTML;

    toolbar.innerHTML = `
        <div>
            <div class="small fw-semibold mb-2">將選取的 <strong>${count}</strong> 筆計劃改為：</div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-success" onclick="executeBatchStatusChange('active', this)">
                    進行中 (active)
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="executeBatchStatusChange('completed', this)">
                    已完成
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="executeBatchStatusChange('cancelled', this)">
                    已取消
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="restoreBatchToolbar(${JSON.stringify(originalToolbarHTML)})">
                    取消
                </button>
            </div>
            <div class="small text-muted mt-1">注意：只有管理員/店長可以執行此操作</div>
        </div>
    `;
}

async function executeBatchStatusChange(newStatus, btnElement) {
    const planIds = Array.from(selectedPlanIds);
    if (planIds.length === 0) return;

    const originalText = btnElement.textContent;
    btnElement.disabled = true;
    btnElement.textContent = '更新中...';

    try {
        const res = await SalonEase.fetch('/api/payment_plans.php?action=bulk_update_status', {
            method: 'POST',
            body: new URLSearchParams({
                plan_ids: JSON.stringify(planIds),
                status: newStatus,
                csrf_token: window.CSRF_TOKEN
            })
        });

        const successCount = res.data?.success_count || 0;

        // 即時更新所有受影響行的狀態 badge（零刷新）
        const statusTextMap = {
            'active': '<span class="badge bg-success">進行中</span>',
            'completed': '<span class="badge bg-secondary">已完成</span>',
            'cancelled': '<span class="badge bg-danger">已取消</span>'
        };

        planIds.forEach(pid => {
            const row = document.querySelector(`tr[data-plan-id="${pid}"]`);
            if (!row) return;

            // 找到狀態欄（第 8 個 td，大約）
            const cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                const statusCell = cells[7]; // 狀態欄位置（0-based）
                if (statusCell) {
                    statusCell.innerHTML = statusTextMap[newStatus] || statusTextMap['active'];
                }
            }

            // 如果從 active 改走，且在嚴格模式，要移除紅框
            if (newStatus !== 'active') {
                row.classList.remove('plan-needs-attention', 'plan-handled');
            }
        });

        // 刷新頂部統計（因為狀態改變會影響數字）
        loadSummary();
        loadPlanDashboard();

        // 重新套用最老置頂
        applyOldestPlanPin();

        clearBatchSelection();

        alert(`已成功將 ${successCount} 筆計劃狀態改為「${newStatus}」`);

    } catch (err) {
        alert('批量更改狀態失敗：' + err.message);
    } finally {
        // 恢復工具列（因為可能已經被 clear 掉）
        const toolbar = document.getElementById('batch-toolbar');
        if (toolbar) {
            // 如果工具列還在，就讓它自己更新
        }
    }
}

/**
 * 把目前已知的最老活躍計劃在列表中自動置頂 + 套用醒目樣式
 * 即使目前是嚴格模式、客戶篩選或其他排序，都盡量把最老的那筆移到第一行
 * 供決策時「一打開頁面就看到最該處理的個案」
 */
function applyOldestPlanPin() {
    const oldestId = window.currentOldestActivePlanId;
    if (!oldestId) return;

    const tbody = document.getElementById('plans-list');
    if (!tbody) return;

    let targetRow = tbody.querySelector(`tr[data-plan-id="${oldestId}"]`);

    // 備援：萬一 data attr 還沒上（極少發生）
    if (!targetRow) {
        const all = tbody.querySelectorAll('tr');
        for (const r of all) {
            if (r.textContent.includes(`#${oldestId}`)) {
                r.dataset.planId = oldestId;
                targetRow = r;
                break;
            }
        }
    }

    if (targetRow) {
        targetRow.classList.add('oldest-pinned');
        // 只有當它不是已經在最頂端時才搬動（避免不必要的 DOM 操作）
        if (targetRow !== tbody.firstElementChild) {
            tbody.insertBefore(targetRow, tbody.firstChild);
        }
    }
}

function debounceLoadPlans() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadPlans(), 300);
}

async function loadPlans(resetFilters = false) {
    const tbody = document.getElementById('plans-list');
    tbody.innerHTML = `<tr><td colspan="10" class="py-5 text-center text-muted">載入中...</td></tr>`;

    // 記錄目前已選取的計劃 ID（用於智能保留）
    const previouslySelected = new Set(selectedPlanIds);

    if (resetFilters) {
        document.getElementById('filter-status').value = 'active';
        document.getElementById('filter-type').value = '';
        document.getElementById('filter-search').value = '';
        currentCustomerFilter = null;
        updateCustomerFilterBanner();
        isNeedsAttentionView = false;
        strictNeedsAttentionOnly = false;
        selectedPlanIds.clear(); // resetFilters 時才完全清空選取
    }

    const status = document.getElementById('filter-status').value;
    const planType = document.getElementById('filter-type').value;
    const search = document.getElementById('filter-search').value.trim();

    try {
        const params = new URLSearchParams();
        if (status) params.append('status', status);
        if (planType) params.append('plan_type', planType);
        if (search) params.append('search', search);
        if (currentCustomerFilter && currentCustomerFilter.id) {
            params.append('customer_id', currentCustomerFilter.id);
        }

        const res = await SalonEase.fetch(`/api/payment_plans.php?action=list&${params.toString()}`);
        let plans = res.data || [];
        currentPlansData = plans;   // 保存供已選摘要面板使用

        // 智能保留選取狀態：只保留仍然存在於新列表中的計劃
        if (!resetFilters && previouslySelected.size > 0) {
            const newPlanIds = new Set(plans.map(p => p.id));
            selectedPlanIds.forEach(id => {
                if (!newPlanIds.has(id)) {
                    selectedPlanIds.delete(id);
                }
            });
        }

        // 在「需要關注」嚴格模式下，只顯示完全沒有跟進記錄的計劃（更激進的優先排序）
        if (strictNeedsAttentionOnly) {
            plans = plans.filter(p => {
                const hasFollowup = p.notes && p.notes.includes('[跟進 ');
                return !hasFollowup;
            });

            // 顯示最老未跟進計劃提示
            if (plans.length > 0) {
                // 找出 created_at 最舊的
                let oldest = plans[0];
                plans.forEach(p => {
                    if (p.created_at && (!oldest.created_at || new Date(p.created_at) < new Date(oldest.created_at))) {
                        oldest = p;
                    }
                });

                const oldestEl = document.getElementById('oldest-unfollowed-hint');
                if (oldestEl && oldest) {
                    const days = oldest.created_at ? Math.floor((new Date() - new Date(oldest.created_at)) / (1000*60*60*24)) : 0;
                    oldestEl.innerHTML = `｜ 最老：<strong>#${oldest.id}</strong>（已 ${days} 天）`;
                    oldestEl.onclick = () => showPlanDetail(oldest.id);
                    oldestEl.style.cursor = 'pointer';
                }
            }
        } else {
            const oldestEl = document.getElementById('oldest-unfollowed-hint');
            if (oldestEl) oldestEl.innerHTML = '';
        }

        currentPlansData = plans;   // 嚴格模式過濾後更新（摘要使用顯示中的資料）

        // 控制嚴格需要關注提示橫幅
        const strictHeader = document.getElementById('strict-needs-attention-header');
        const unfollowedCountEl = document.getElementById('unfollowed-count');
        if (strictHeader && unfollowedCountEl) {
            if (strictNeedsAttentionOnly) {
                unfollowedCountEl.textContent = plans.length;
                strictHeader.classList.remove('d-none');
            } else {
                strictHeader.classList.add('d-none');
            }
        }

        renderPlansTable(plans);

        // 恢復批量 checkbox 選取狀態（重要）
        restoreBatchCheckboxes();

        // 更新已選摘要面板（決策支援）
        updateSelectedSummary();

        // 更新智能建議區
        updateSelectionSuggestions();

        // 更新主動建議處理區（更主動的情境化建議）
        triggerProactiveUpdate();

        // 可靠地把「最老活躍計劃」自動置頂（A 方向決策支援核心）
        applyOldestPlanPin();

        // 載入「昨日結轉」的計劃（若有）
        loadCarryOverIfAny();

        // 如果有客戶篩選，計算該客戶的統計並更新 banner
        if (currentCustomerFilter && currentCustomerFilter.id) {
            if (plans.length > 0) {
                const first = plans[0];
                currentCustomerFilter.name = first.customer_name;
                currentCustomerFilter.phone = first.customer_phone;
            }

            // 從本次載入的資料計算統計（針對單一客戶足夠準確）
            const stats = {
                total: plans.length,
                active: plans.filter(p => p.status === 'active').length,
                completed: plans.filter(p => p.status === 'completed').length,
                cancelled: plans.filter(p => p.status === 'cancelled').length
            };
            currentCustomerFilter.stats = stats;

            updateCustomerFilterBanner();
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="10" class="py-5 text-center text-danger">載入失敗：${e(err.message)}</td></tr>`;
    }
}

async function loadSummary() {
    try {
        const res = await SalonEase.fetch('/api/payment_plans.php?action=summary');
        const s = res.data || {};
        document.getElementById('stat-total').textContent = s.total ?? 0;
        document.getElementById('stat-active').textContent = s.active ?? 0;
        document.getElementById('stat-completed').textContent = s.completed ?? 0;
        document.getElementById('stat-cancelled').textContent = s.cancelled ?? 0;
    } catch (err) {
        // 靜默失敗，不影響主列表
        console.warn('載入計劃統計失敗', err);
    }
}

async function loadPlanDashboard() {
    try {
        const res = await SalonEase.fetch('/api/payment_plans.php?action=dashboard');
        const d = res.data || {};

        document.getElementById('dash-active-plans').textContent = d.active_plans ?? 0;
        document.getElementById('dash-customers-count').textContent = d.customers_with_active ?? 0;

        const totalValue = parseFloat(d.active_total_value || 0);
        const collected = parseFloat(d.active_collected_value || 0);
        document.getElementById('dash-total-value').textContent = 'HK$ ' + totalValue.toFixed(0);
        document.getElementById('dash-collected').textContent = 'HK$ ' + collected.toFixed(0);

        document.getElementById('dash-needs-attention').textContent = d.needs_attention ?? 0;

        const progress = totalValue > 0 ? Math.round((collected / totalValue) * 100) : 0;
        document.getElementById('dash-progress').innerHTML = `${progress}% <span class="small text-muted">已回收</span>`;

        const upcoming = parseFloat(d.upcoming_30_days || 0);
        document.getElementById('dash-upcoming-30').textContent = 'HK$ ' + upcoming.toFixed(0);

        // 顯示最老活躍計劃提示（常駐）
        const oldestHintEl = document.getElementById('oldest-active-hint');
        if (oldestHintEl && d.oldest_active) {
            const o = d.oldest_active;
            oldestHintEl.innerHTML = `最老活躍計劃：<strong>#${o.id}</strong>（客戶 ${e(o.customer_name)}，已 ${o.days_old} 天，進度 ${o.progress}%）`;
            oldestHintEl.style.cursor = 'pointer';
            oldestHintEl.onclick = () => showPlanDetail(o.id);
            oldestHintEl.title = '點擊查看詳情';

            // 記錄目前最老計劃 ID，供列表高亮使用
            window.currentOldestActivePlanId = o.id;

            // dashboard 載入完成後，如果列表已經存在，立即嘗試置頂（解決初始載入 race）
            setTimeout(() => applyOldestPlanPin(), 80);
        } else if (oldestHintEl) {
            oldestHintEl.innerHTML = '';
            window.currentOldestActivePlanId = null;
        }

        // 顯示「最需要關注的客戶」
        const concerningEl = document.getElementById('most-concerning-customer-hint');
        if (concerningEl && d.most_concerning_customer) {
            const c = d.most_concerning_customer;
            const val = c.active_value ? '，總額約 HK$' + Math.round(c.active_value) : '';
            concerningEl.innerHTML = `最需關注：<strong>${e(c.name)}</strong>（${c.active_plans_count} 個計劃${val}）`;
            concerningEl.style.cursor = 'pointer';
            concerningEl.onclick = () => {
                // 跳到該客戶的計劃篩選
                currentCustomerFilter = { id: c.id, name: c.name, phone: c.phone };
                updateCustomerFilterBanner();
                loadPlans();
            };
            concerningEl.title = '點擊篩選該客戶的所有計劃（最需要關注的客戶）';
        } else if (concerningEl) {
            concerningEl.innerHTML = '';
        }
    } catch (err) {
        console.warn('載入計劃管理概覽失敗', err);
    }
}

function filterNeedsAttention() {
    document.getElementById('filter-status').value = 'active';
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-search').value = '';
    currentCustomerFilter = null;
    updateCustomerFilterBanner();
    isNeedsAttentionView = true;
    strictNeedsAttentionOnly = true;  // 更激進：只顯示完全未跟進的
    loadPlans();
}

function renderPlansTable(plans) {
    const tbody = document.getElementById('plans-list');
    if (!plans.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="py-5 text-center text-muted">沒有符合條件的計劃</td></tr>`;
        return;
    }

    const today = new Date();

    // 如果目前在「需要關注」視圖，優先顯示「尚未有跟進記錄」的計劃
    if (isNeedsAttentionView) {
        plans.sort((a, b) => {
            const aHasFollowup = !!(a.notes && a.notes.includes('[跟進 '));
            const bHasFollowup = !!(b.notes && b.notes.includes('[跟進 '));
            if (aHasFollowup === bHasFollowup) return 0;
            return aHasFollowup ? 1 : -1; // 沒有跟進的排前面
        });
    }

    let html = '';
    plans.forEach(p => {
        const typeLabel = p.plan_type === 'installment' ? '分期' : '周期性';
        const typeBadge = p.plan_type === 'installment' 
            ? '<span class="badge bg-info-subtle text-info">分期</span>' 
            : '<span class="badge bg-primary-subtle text-primary">周期性</span>';

        const statusBadge = getStatusBadge(p.status);

        const made = parseInt(p.payments_made || 0);
        const total = parseInt(p.total_installments || 0);
        const progress = total > 0 ? Math.round((made / total) * 100) : 0;

        const progressHtml = `
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar ${progress >= 80 ? 'bg-success' : progress >= 40 ? 'bg-warning' : 'bg-danger'}" 
                             style="width: ${progress}%"></div>
                    </div>
                </div>
                <small class="text-muted" style="min-width:42px;">${made}/${total}</small>
            </div>
        `;

        // 計算是否需要關注（與 dashboard 邏輯一致）
        let needsAttention = false;
        if (p.status === 'active' && total > 0) {
            const progressRatio = made / total;
            const created = p.created_at ? new Date(p.created_at) : null;
            const daysOld = created ? Math.floor((today - created) / (1000 * 60 * 60 * 24)) : 0;
            if (progressRatio < 0.3 && daysOld > 45) {
                needsAttention = true;
            }
        }

        let rowClass = needsAttention ? 'plan-needs-attention' : '';
        let attentionBadge = needsAttention 
            ? '<span class="badge bg-danger ms-1">需跟進</span>' 
            : '';

        // 特別標記目前最老的活躍計劃（皇冠 + 黃色 pinned 底色）
        if (window.currentOldestActivePlanId && p.id == window.currentOldestActivePlanId) {
            attentionBadge += ` <span class="badge bg-warning text-dark ms-1" title="目前所有活躍計劃中最老的一筆">👑 最老</span>`;
            rowClass = (rowClass ? rowClass + ' ' : '') + 'oldest-pinned';
        }

        // 顯示最新跟進記錄（主要給「需要關注」的計劃看）
        const recentFollowups = needsAttention ? getRecentFollowUps(p.notes, 2) : [];
        const latestFollowupForPreview = recentFollowups.length > 0 ? recentFollowups[0] : null;

        if (recentFollowups.length > 0) {
            const latest = recentFollowups[0];
            const tooltipText = e(latest);
            attentionBadge += ` <span class="badge bg-secondary ms-1" style="cursor:pointer;" 
                onclick="event.stopImmediatePropagation(); toggleFollowupExpansion(${p.id}, this, ${JSON.stringify(recentFollowups)})">有跟進</span>`;
        } else if (needsAttention) {
            attentionBadge += ` <span class="badge bg-danger ms-1" title="尚未記錄跟進">未跟進</span>`;
        }

        html += `
            <tr class="${rowClass}" data-plan-id="${p.id}">
                <td class="text-center">
                    <input type="checkbox" class="batch-checkbox" value="${p.id}" onchange="updateBatchSelection()">
                </td>
                <td class="fw-medium">
                    #${p.id} ${attentionBadge}
                    ${latestFollowupForPreview ? `<div class="small text-muted text-truncate" style="max-width: 200px; font-size: 0.62rem; line-height: 1.1;" title="${e(latestFollowupForPreview)}">最新：${e(latestFollowupForPreview.substring(0, 45))}${latestFollowupForPreview.length > 45 ? '...' : ''}</div>` : ''}
                </td>
                <td>
                    <a href="record_payment.php?sale_id=${p.sale_id}" 
                       class="text-decoration-none fw-medium d-inline-flex align-items-center gap-1">
                        #${p.sale_id} <span class="small">→</span>
                    </a>
                </td>
                <td onclick="editCustomerFromPlan(${p.customer_id}, event)" 
                    style="cursor:pointer;" 
                    title="點擊編輯客戶資料">
                    <div class="fw-medium d-inline-flex align-items-center gap-1">
                        ${e(p.customer_name || '-')} <span class="small text-primary">→</span>
                    </div>
                    <div class="small text-muted font-mono">${e(p.customer_phone || '')}</div>
                </td>
                <td>${typeBadge}</td>
                <td class="text-end fw-medium">HK$ ${parseFloat(p.installment_amount).toFixed(0)}</td>
                <td style="min-width: 140px;">${progressHtml}</td>
                <td>${statusBadge}</td>
                <td class="small">${p.start_date || '-'}</td>
                <td class="text-end">
                    <button onclick="showPlanDetail(${p.id})" class="btn btn-sm btn-outline-primary me-1">詳情</button>
                    <button onclick="showStatusModal(${p.id}, '${p.status}')" class="btn btn-sm btn-outline-secondary">狀態</button>
                    ${needsAttention ? 
                        `<button onclick="startQuickFollowup(${p.id}, this.closest('td'), event)" class="btn btn-sm btn-outline-danger">快速跟進</button>` 
                        : ''
                    }
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function getStatusBadge(status) {
    if (status === 'active') return '<span class="badge bg-success">進行中</span>';
    if (status === 'completed') return '<span class="badge bg-secondary">已完成</span>';
    if (status === 'cancelled') return '<span class="badge bg-danger">已取消</span>';
    return '<span class="badge bg-light text-dark">' + e(status) + '</span>';
}

function createFollowupBadge(planId, latestFollowup) {
    const badge = document.createElement('span');
    badge.className = 'badge bg-secondary ms-1';
    badge.textContent = '有跟進';
    badge.setAttribute('title', latestFollowup || '');
    badge.style.cursor = 'pointer';

    badge.onclick = (e) => {
        e.stopImmediatePropagation();
        const recent = getRecentFollowUps(latestFollowup ? latestFollowup : '', 2);
        toggleFollowupExpansion(planId, badge, recent.length > 0 ? recent : [latestFollowup]);
    };

    return badge;
}

function showJustFollowedPill(row, note) {
    if (!row) return;

    // 移除任何舊的「剛剛跟進」提示
    const oldPill = row.querySelector('.just-followed-pill');
    if (oldPill) oldPill.remove();

    const pill = document.createElement('span');
    pill.className = 'badge bg-success ms-1 just-followed-pill';
    pill.textContent = '剛剛跟進';
    pill.style.transition = 'opacity 0.4s ease';
    pill.setAttribute('title', note || '');

    // 插入到第一個 cell（計劃編號旁）
    const firstCell = row.querySelector('td');
    if (firstCell) {
        firstCell.appendChild(pill);

        // 8 秒後淡出移除
        setTimeout(() => {
            if (pill.parentNode) {
                pill.style.opacity = '0';
                setTimeout(() => {
                    if (pill.parentNode) pill.parentNode.removeChild(pill);
                }, 300);
            }
        }, 8000);
    }
}

function flashButtonFeedback(btn, successText = '已處理') {
    if (!btn) return;
    const originalText = btn.textContent;
    const originalClass = btn.className;

    btn.disabled = true;
    btn.textContent = successText;
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-warning', 'btn-success'); // reset if needed

    setTimeout(() => {
        if (btn && btn.parentNode) {
            btn.className = originalClass;
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }, 1200);
}

function renderFollowUpHistory(notes) {
    if (!notes) return '';

    const lines = notes.split(/\n\s*\n/); // 以空行分隔
    let followups = [];
    let otherNotes = [];

    lines.forEach(line => {
        const trimmed = line.trim();
        if (trimmed.startsWith('[跟進 ')) {
            followups.push(trimmed);
        } else if (trimmed) {
            otherNotes.push(trimmed);
        }
    });

    let html = '';

    if (followups.length > 0) {
        html += `<div class="mt-3">
            <div class="fw-semibold small mb-1">跟進記錄</div>
            <div class="small border rounded p-2 bg-light" style="max-height: 120px; overflow-y: auto;">`;
        followups.forEach(f => {
            html += `<div class="mb-1 text-muted" style="white-space: pre-wrap;">${e(f)}</div>`;
        });
        html += `</div></div>`;
    }

    if (otherNotes.length > 0) {
        html += `<div class="mt-3">
            <strong class="small">備註</strong>
            <div class="text-muted small" style="white-space: pre-wrap;">${e(otherNotes.join('\n\n'))}</div>
        </div>`;
    }

    return html;
}

function getLatestFollowUp(notes) {
    const recent = getRecentFollowUps(notes, 1);
    return recent.length > 0 ? recent[0] : null;
}

function getRecentFollowUps(notes, count = 2) {
    if (!notes) return [];

    const lines = notes.split(/\n\s*\n/);
    const followups = [];

    lines.forEach(line => {
        const trimmed = line.trim();
        if (trimmed.startsWith('[跟進 ')) {
            followups.push(trimmed);
        }
    });

    // 取最後 count 筆（最新的）
    return followups.slice(-count).reverse();
}

async function showPlanDetail(planId) {
    const modalEl = document.getElementById('planDetailModal');
    const body = document.getElementById('plan-detail-body');
    body.innerHTML = '載入中...';

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();

    try {
        const res = await SalonEase.fetch(`/api/payment_plans.php?action=get&id=${planId}`);
        const p = res.data;

        document.getElementById('detail-plan-id').textContent = `#${p.id}`;

        const typeLabel = p.plan_type === 'installment' ? '分期' : '周期性';
        const statusBadge = getStatusBadge(p.status);

        const made = p.payments ? p.payments.length : 0;
        const total = p.total_installments || 0;

        let paymentsHtml = '<div class="text-muted small">暫無付款記錄</div>';
        if (p.payments && p.payments.length > 0) {
            paymentsHtml = '<table class="table table-sm mb-0"><thead><tr><th>期數</th><th>金額</th><th>付款方式</th><th>日期</th></tr></thead><tbody>';
            p.payments.forEach(pay => {
                paymentsHtml += `
                    <tr>
                        <td>${pay.installment_no || '-'}</td>
                        <td>HK$ ${parseFloat(pay.amount).toFixed(0)}</td>
                        <td>${e(pay.payment_method_name || '')}</td>
                        <td class="small text-muted">${pay.paid_at ? pay.paid_at.substring(0,10) : ''}</td>
                    </tr>
                `;
            });
            paymentsHtml += '</tbody></table>';
        }

        body.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-2"><strong>銷售單</strong> <a href="record_payment.php?sale_id=${p.sale_id}" class="fw-medium">#${p.sale_id} →</a></div>
                    <div class="mb-2"><strong>類型</strong> ${typeLabel}</div>
                    <div class="mb-2"><strong>每期金額</strong> HK$ ${parseFloat(p.installment_amount).toFixed(0)}</div>
                    <div class="mb-2"><strong>總期數</strong> ${total} 期</div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><strong>狀態</strong> ${statusBadge}</div>
                    <div class="mb-2"><strong>開始日期</strong> ${p.start_date || '-'}</div>
                    <div class="mb-2"><strong>已付期數</strong> ${made} / ${total}</div>
                    <div class="mb-2"><strong>銷售總額</strong> HK$ ${parseFloat(p.sale_total || 0).toFixed(0)}</div>
                </div>
            </div>

            <hr class="my-3">

            <div>
                <div class="fw-semibold mb-2 small">已記錄付款</div>
                ${paymentsHtml}
            </div>

            ${renderFollowUpHistory(p.notes)}
        `;

        // 如果目前有客戶篩選，顯示「繼續為這位客戶新增計劃」按鈕
        const continueBtn = document.getElementById('continue-create-btn');
        if (continueBtn) {
            if (currentCustomerFilter && currentCustomerFilter.id) {
                continueBtn.classList.remove('d-none');
            } else {
                continueBtn.classList.add('d-none');
            }
        }

        // 記錄目前詳情的銷售單 ID（供「為這筆銷售單新增下一期」使用）
        window.currentDetailSaleId = p.sale_id;

        // 如果有客戶篩選，同時顯示「為這筆銷售單新增下一期」按鈕
        const saleContinueBtn = document.getElementById('continue-sale-create-btn');
        if (saleContinueBtn) {
            if (currentCustomerFilter && currentCustomerFilter.id && p.sale_id) {
                saleContinueBtn.classList.remove('d-none');
            } else {
                saleContinueBtn.classList.add('d-none');
            }
        }

        // 儲存目前詳情計劃資料，供「編輯此計劃」按鈕快速使用
        window.currentPlanForDetail = p;
    } catch (err) {
        body.innerHTML = `<div class="text-danger">載入詳情失敗：${e(err.message)}</div>`;
    }
}

let currentPlanIdForStatus = null;
let currentPlanForStatus = null;  // Phase 4 A：存計劃資料，用於保護警告

function showStatusModal(planId, currentStatus) {
    currentPlanIdForStatus = planId;
    currentPlanForStatus = currentPlansData.find(p => p.id == planId) || null;

    document.getElementById('status-plan-id').value = planId;
    document.getElementById('status-current').innerHTML = getStatusBadge(currentStatus);
    document.getElementById('status-new').value = currentStatus;

    // 立即顯示保護警告（如果適用）
    setTimeout(updateStatusWarning, 50);

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('statusModal'));
    modal.show();
}

function updateStatusWarning() {
    const warningEl = document.getElementById('status-warning');
    const newStatus = document.getElementById('status-new').value;
    if (!warningEl || !currentPlanForStatus) {
        if (warningEl) warningEl.style.display = 'none';
        return;
    }

    const made = parseInt(currentPlanForStatus.payments_made || 0);
    const total = parseInt(currentPlanForStatus.total_installments || 1);
    let msg = '';

    if (made > 0 && newStatus === 'active' && currentPlanForStatus.status !== 'active') {
        msg = '⚠️ 此計劃已有 ' + made + ' 筆付款，禁止改回「進行中」（資料保護）';
    } else if (made > 0 && newStatus === 'cancelled') {
        msg = '⚠️ 此計劃已有 ' + made + ' 筆付款，取消後將自動記錄保護備註，不再追蹤。';
    }

    if (msg) {
        warningEl.innerHTML = msg;
        warningEl.style.display = 'block';
    } else {
        warningEl.style.display = 'none';
    }
}

async function confirmStatusChange() {
    const planId = document.getElementById('status-plan-id').value;
    const newStatus = document.getElementById('status-new').value;

    if (!planId || !newStatus) return;

    const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
    modal.hide();

    try {
        await SalonEase.fetch('/api/payment_plans.php?action=update_status', {
            method: 'POST',
            body: new URLSearchParams({
                plan_id: planId,
                status: newStatus,
                csrf_token: window.CSRF_TOKEN
            })
        });

        alert('狀態已更新');
        loadPlans();
        loadSummary();
        loadPlanDashboard();
    } catch (err) {
        alert('更新失敗：' + err.message);
    }
}

function clearFilters() {
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-search').value = '';
    currentCustomerFilter = null;
    updateCustomerFilterBanner();
    isNeedsAttentionView = false;
    strictNeedsAttentionOnly = false;
    loadPlans();
}

function reloadAll() {
    loadSummary();
    loadPlanDashboard();
    isNeedsAttentionView = false;
    strictNeedsAttentionOnly = false;
    loadPlans(true);
}

function filterByStatus(status) {
    document.getElementById('filter-status').value = status;
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-search').value = '';
    loadPlans();
}

function filterByCustomer(customerId, displayName = '') {
    currentCustomerFilter = { id: customerId, name: displayName, phone: '' };
    document.getElementById('filter-status').value = 'active'; // 預設只看進行中
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-search').value = '';

    updateCustomerFilterBanner(); // 先顯示基本資訊
    loadPlans();
}

function clearCustomerFilter() {
    currentCustomerFilter = null;
    updateCustomerFilterBanner();
    loadPlans(true);
}

function updateCustomerFilterBanner() {
    const banner = document.getElementById('customer-filter-banner');
    const textEl = document.getElementById('customer-filter-text');

    if (!banner || !textEl) return;

    if (currentCustomerFilter && currentCustomerFilter.id) {
        let namePart = currentCustomerFilter.name 
            ? `${currentCustomerFilter.name} (${currentCustomerFilter.phone || 'ID:' + currentCustomerFilter.id})`
            : `客戶 ID: ${currentCustomerFilter.id}`;

        let statsPart = '';
        if (currentCustomerFilter.stats) {
            const s = currentCustomerFilter.stats;
            statsPart = ` ｜ 共 ${s.total} 個計劃（${s.active} 個進行中）`;
        }

        textEl.textContent = namePart + statsPart;
        banner.classList.remove('d-none');
    } else {
        banner.classList.add('d-none');
    }
}

function toggleFrequencyField() {
    const type = document.getElementById('create-plan-type').value;
    const freqGroup = document.getElementById('create-frequency-group');
    const freqSelect = document.getElementById('create-frequency');

    if (type === 'recurring') {
        freqGroup.style.display = '';
        freqSelect.required = true;
    } else {
        freqGroup.style.display = 'none';
        freqSelect.required = false;
    }
}

function showCreatePlanModal() {
    // 清除之前可能殘留的上下文提示
    document.querySelectorAll('#createPlanModal .alert-info').forEach(el => el.remove());

    // Reset form
    document.getElementById('create-sale-id').value = '';
    document.getElementById('create-plan-type').value = 'installment';
    document.getElementById('create-total-installments').value = '6';
    document.getElementById('create-installment-amount').value = '';
    document.getElementById('create-frequency').value = 'monthly';
    document.getElementById('create-start-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('create-notes').value = '';

    toggleFrequencyField(); // hide frequency for installment by default

    // 確保銷售單選擇區恢復可見（從「新增下一期」流程可能被隱藏）
    const selector = document.getElementById('create-sales-selector');
    if (selector) {
        selector.classList.remove('d-none');
        const list = document.getElementById('create-sales-list');
        if (list) list.style.display = '';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('createPlanModal'));
    modal.show();

    // Focus first field
    setTimeout(() => {
        document.getElementById('create-sale-id').focus();
    }, 400);
}

async function createPlan() {
    const saleId = parseInt(document.getElementById('create-sale-id').value);
    const planType = document.getElementById('create-plan-type').value;
    const totalInstallments = parseInt(document.getElementById('create-total-installments').value);
    const installmentAmount = parseFloat(document.getElementById('create-installment-amount').value);
    const frequency = document.getElementById('create-frequency').value;
    const startDate = document.getElementById('create-start-date').value;
    const notes = document.getElementById('create-notes').value.trim();

    if (!saleId || saleId <= 0) {
        alert('請輸入有效的銷售單 ID');
        return;
    }
    if (!totalInstallments || totalInstallments < 1) {
        alert('總期數必須大於 0');
        return;
    }
    if (!installmentAmount || installmentAmount <= 0) {
        alert('每期金額必須大於 0');
        return;
    }
    if (!startDate) {
        alert('請選擇開始日期');
        return;
    }

    const modal = bootstrap.Modal.getInstance(document.getElementById('createPlanModal'));

    try {
        const res = await SalonEase.fetch('/api/payment_plans.php?action=create', {
            method: 'POST',
            body: new URLSearchParams({
                sale_id: saleId,
                plan_type: planType,
                total_installments: totalInstallments,
                installment_amount: installmentAmount,
                frequency: planType === 'recurring' ? frequency : '',
                start_date: startDate,
                notes: notes,
                csrf_token: window.CSRF_TOKEN
            })
        });

        const newPlanId = res.data?.plan_id;

        modal.hide();
        alert('計劃建立成功！');

        const cameFromCustomer = currentPlanCustomerId;

        loadPlans();
        loadSummary();
        loadPlanDashboard();

        // 如果是從客戶 Modal 快速新增的，建立成功後自動切換回該客戶的計劃清單
        if (cameFromCustomer) {
            setTimeout(() => {
                filterByCustomer(cameFromCustomer);
                currentPlanCustomerId = null;

                // 自動打開剛建立的計劃詳情（最佳體驗）
                if (newPlanId) {
                    setTimeout(() => {
                        showPlanDetail(newPlanId);
                    }, 650);
                }
            }, 300);
        } else {
            currentPlanCustomerId = null;
        }
    } catch (err) {
        alert('建立失敗：' + err.message);
    }
}

async function editCustomerFromPlan(customerId, event) {
    if (event) event.stopImmediatePropagation();

    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=get&id=${customerId}`);
        const c = res.data;

        document.getElementById('customer-modal-title').textContent = '編輯客戶';
        document.getElementById('customer-id').value = c.id || '';
        document.getElementById('customer-name').value = c.name || '';
        document.getElementById('customer-phone').value = c.phone || '';
        document.getElementById('customer-email').value = c.email || '';
        document.getElementById('customer-gender').value = c.gender || '';
        document.getElementById('customer-birthday').value = c.birthday || '';
        document.getElementById('customer-notes').value = c.notes || '';

        document.getElementById('customer-save-btn').textContent = '儲存變更';

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('customerModal'));
        modal.show();

        // 載入該客戶的活躍計劃（快速清單）
        loadCustomerActivePlansInModal(customerId);

        setTimeout(() => {
            const nameInput = document.getElementById('customer-name');
            if (nameInput) nameInput.focus();
        }, 400);
    } catch (err) {
        alert('載入客戶資料失敗：' + err.message);
    }
}

async function loadCustomerActivePlansInModal(customerId) {
    const container = document.getElementById('customer-active-plans-list');
    if (!container) return;

    container.innerHTML = '<div class="text-muted">載入中...</div>';

    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=get_active_plans&customer_id=${customerId}`);
        const plans = res.data || [];

        if (plans.length === 0) {
            container.innerHTML = '<div class="text-muted small">該客戶目前沒有活躍計劃</div>';
            return;
        }

        let html = '<div class="list-group list-group-flush small">';
        plans.slice(0, 4).forEach(plan => {   // 最多顯示 4 筆
            const progress = plan.sale_total > 0 
                ? Math.round((plan.paid_amount / plan.sale_total) * 100) 
                : 0;
            const typeLabel = plan.plan_type === 'installment' ? '分期' : '周期性';

            html += `
                <div class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">
                    <div style="cursor:pointer; flex:1;" 
                         onclick="event.stopImmediatePropagation(); bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide(); showPlanDetail(${plan.id});">
                        <div class="d-flex justify-content-between">
                            <span><strong>${typeLabel}</strong> #${plan.id}</span>
                            <span class="text-muted">每期 HK$ ${parseFloat(plan.installment_amount).toFixed(0)}</span>
                        </div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            已付 ${plan.paid_amount ? parseFloat(plan.paid_amount).toFixed(0) : 0} / 期數 ${plan.total_installments} 
                            <span class="ms-1">(${progress}%)</span>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-success ms-2 flex-shrink-0" 
                            onclick="event.stopImmediatePropagation(); startNextPlanForSale(${plan.sale_id}, ${customerId}, ${plan.installment_amount}, ${plan.id});">
                        新增下一期
                    </button>
                </div>
            `;
        });
        html += '</div>';

        if (plans.length > 4) {
            html += `<div class="small text-primary mt-1" style="cursor:pointer;" onclick="event.stopImmediatePropagation(); bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide(); window.location.href='payment_plans.php?customer_id=${customerId}';">查看更多活躍計劃 →</div>`;
        }

        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="text-danger small">載入失敗：${e(err.message)}</div>`;
    }
}

async function saveCustomerFromPlan() {
    const id = document.getElementById('customer-id').value;
    const name = document.getElementById('customer-name').value.trim();
    const phone = document.getElementById('customer-phone').value.trim();

    if (!name || !phone) {
        alert('姓名和電話為必填');
        return;
    }

    const payload = {
        id: id ? parseInt(id) : null,
        name: name,
        phone: phone,
        email: document.getElementById('customer-email').value.trim(),
        gender: document.getElementById('customer-gender').value,
        birthday: document.getElementById('customer-birthday').value,
        notes: document.getElementById('customer-notes').value.trim(),
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const action = payload.id ? 'update' : 'create';
        const method = 'POST';

        await SalonEase.fetch(`/api/customers.php?action=${action}`, {
            method: method,
            body: new URLSearchParams(payload)
        });

        bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();

        alert(payload.id ? '客戶資料已更新' : '客戶已新增');

        // 刷新計劃列表（客戶名稱可能有變更）
        loadPlans();
    } catch (err) {
        alert('儲存失敗：' + err.message);
    }
}

let currentPlanCustomerId = null;

async function startCreatePlanForCurrentCustomer(customerIdFromParam = null, customerNameFromParam = '') {
    let customerId = customerIdFromParam || (document.getElementById('customer-id') ? document.getElementById('customer-id').value : null);
    let customerName = customerNameFromParam || (document.getElementById('customer-name') ? document.getElementById('customer-name').value : '客戶');

    if (!customerId) {
        alert('無法取得客戶資料');
        return;
    }

    currentPlanCustomerId = parseInt(customerId);

    // 清除之前可能殘留的上下文提示（從「新增下一期」等流程留下）
    document.querySelectorAll('#createPlanModal .alert-info').forEach(el => el.remove());

    // 如果是從客戶 Modal 呼叫，才關閉它
    const customerModalEl = document.getElementById('customerModal');
    if (customerModalEl && bootstrap.Modal.getInstance(customerModalEl)) {
        bootstrap.Modal.getInstance(customerModalEl).hide();
    }

    // 開啟新增計劃 Modal
    const createModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('createPlanModal'));
    createModal.show();

    // 顯示銷售單選擇區
    const selector = document.getElementById('create-sales-selector');
    const listContainer = document.getElementById('create-sales-list');
    if (selector && listContainer) {
        selector.classList.remove('d-none');
        listContainer.innerHTML = '<div class="text-muted">載入該客戶銷售單中...</div>';

        try {
            const res = await SalonEase.fetch(`/api/customers.php?action=list_sales&customer_id=${customerId}`);
            const sales = res.data || [];

            if (sales.length === 0) {
                listContainer.innerHTML = '<div class="text-muted small">該客戶暫無銷售單記錄</div>';
                return;
            }

            // 只有一筆銷售單 → 極簡體驗：完全隱藏選擇區，直接自動帶入
            if (sales.length === 1) {
                const onlySale = sales[0];
                document.getElementById('create-sale-id').value = onlySale.id;

                selector.classList.add('d-none');

                const note = document.createElement('div');
                note.className = 'alert alert-success py-2 small mb-0';
                const date = onlySale.sale_date ? onlySale.sale_date.substring(0, 10) : '';
                note.innerHTML = `✓ 已自動使用該客戶唯一的銷售單 <strong>#${onlySale.id}</strong>（${date}，HK$ ${parseFloat(onlySale.total).toFixed(0)}）`;
                listContainer.parentNode.insertBefore(note, listContainer);
                listContainer.style.display = 'none';
                return;
            }

            // 多於一筆 → 正常顯示列表 + 自動選最新
            let html = '';
            sales.forEach(sale => {
                const date = sale.sale_date ? sale.sale_date.substring(0, 10) : '';
                const hasPlan = sale.existing_plans_count > 0 ? `（已有 ${sale.existing_plans_count} 個計劃）` : '';
                html += `
                    <div class="border-bottom py-1 px-1" style="cursor:pointer;" 
                         onclick="selectSaleForPlan(${sale.id}, ${sale.total})">
                        <div><strong>銷售單 #${sale.id}</strong> <span class="text-muted">${date}</span></div>
                        <div class="small">金額 HK$ ${parseFloat(sale.total).toFixed(0)} ${hasPlan}</div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;

            // 自動選擇最新的一筆
            const latest = sales[0];
            setTimeout(() => {
                selectSaleForPlan(latest.id, latest.total);

                const note = document.createElement('div');
                note.className = 'small text-success mb-1';
                note.innerHTML = `✓ 已自動選擇最新銷售單 #${latest.id}（點擊其他可切換）`;
                listContainer.insertBefore(note, listContainer.firstChild);
            }, 50);
        } catch (err) {
            listContainer.innerHTML = `<div class="text-danger small">載入失敗：${e(err.message)}</div>`;
        }
    }
}

function selectSaleForPlan(saleId, total) {
    document.getElementById('create-sale-id').value = saleId;

    // 給一點視覺提示
    const input = document.getElementById('create-sale-id');
    input.classList.add('border-success');
    setTimeout(() => input.classList.remove('border-success'), 1200);

    // 不自動隱藏選擇區，讓使用者可以輕鬆切換其他銷售單
}

function continueCreatePlanForFilteredCustomer() {
    if (!currentCustomerFilter || !currentCustomerFilter.id) {
        alert('目前沒有客戶篩選');
        return;
    }

    // 關閉詳情 Modal
    const detailModal = bootstrap.Modal.getInstance(document.getElementById('planDetailModal'));
    if (detailModal) detailModal.hide();

    // 使用通用函式啟動新增流程（不依賴客戶 Modal 開啟）
    startCreatePlanForCurrentCustomer(currentCustomerFilter.id, currentCustomerFilter.name || '');
}

/* ==================== 編輯計劃相關函式（A 方向新增） ==================== */

function toggleEditFrequencyField() {
    const type = document.getElementById('edit-plan-type').value;
    const freqGroup = document.getElementById('edit-frequency-group');
    if (freqGroup) {
        freqGroup.style.display = (type === 'recurring') ? '' : 'none';
    }
}

/**
 * 從計劃詳情點擊「編輯此計劃」
 */
function startEditCurrentPlan() {
    const p = window.currentPlanForDetail;
    if (!p || !p.id) {
        alert('無法取得計劃資料，請重新開啟詳情');
        return;
    }

    // 先關閉詳情 modal（避免兩個 modal 重疊）
    const detailModal = bootstrap.Modal.getInstance(document.getElementById('planDetailModal'));
    if (detailModal) detailModal.hide();

    // 填入資料
    document.getElementById('edit-plan-id').value = p.id;
    document.getElementById('edit-payments-made').value = p.payments ? p.payments.length : 0;

    document.getElementById('edit-plan-id-badge').textContent = `#${p.id}`;
    document.getElementById('edit-sale-id-text').textContent = `#${p.sale_id}`;

    document.getElementById('edit-plan-type').value = p.plan_type || 'installment';
    document.getElementById('edit-total-installments').value = p.total_installments || 1;
    document.getElementById('edit-installment-amount').value = parseFloat(p.installment_amount || 0).toFixed(2);
    document.getElementById('edit-frequency').value = p.frequency || 'monthly';
    document.getElementById('edit-start-date').value = p.start_date || '';
    document.getElementById('edit-notes').value = p.notes || '';

    toggleEditFrequencyField();

    // 根據是否已有付款決定是否鎖定財務欄位
    const paymentsMade = parseInt(document.getElementById('edit-payments-made').value) || 0;
    const warning = document.getElementById('edit-financial-warning');
    const financialFields = [
        document.getElementById('edit-plan-type'),
        document.getElementById('edit-total-installments'),
        document.getElementById('edit-installment-amount'),
        document.getElementById('edit-frequency')
    ];

    if (paymentsMade > 0) {
        warning.classList.remove('d-none');
        financialFields.forEach(f => { if (f) f.disabled = true; });
    } else {
        warning.classList.add('d-none');
        financialFields.forEach(f => { if (f) f.disabled = false; });
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editPlanModal'));
    modal.show();
}

async function savePlanEdit() {
    const planId = document.getElementById('edit-plan-id').value;
    if (!planId) return;

    const payload = {
        plan_id: planId,
        plan_type: document.getElementById('edit-plan-type').value,
        total_installments: document.getElementById('edit-total-installments').value,
        installment_amount: document.getElementById('edit-installment-amount').value,
        frequency: document.getElementById('edit-frequency').value,
        start_date: document.getElementById('edit-start-date').value,
        notes: document.getElementById('edit-notes').value.trim(),
        csrf_token: window.CSRF_TOKEN
    };

    const modal = bootstrap.Modal.getInstance(document.getElementById('editPlanModal'));

    try {
        await SalonEase.fetch('/api/payment_plans.php?action=update', {
            method: 'POST',
            body: new URLSearchParams(payload)
        });

        modal.hide();
        alert('計劃更新成功！');

        // 刷新列表 + 統計
        loadPlans();
        loadSummary();
        loadPlanDashboard();

        // 如果原本有開詳情，短暫延遲後重新打開最新資料
        setTimeout(() => {
            showPlanDetail(planId);
        }, 450);

    } catch (err) {
        alert('更新失敗：' + err.message);
    }
}

/**
 * 從計劃詳情頁點擊「為這筆銷售單新增下一期」時使用
 */
function startNextPlanForCurrentSaleInDetail() {
    const saleId = window.currentDetailSaleId;
    if (!saleId || !currentCustomerFilter || !currentCustomerFilter.id) {
        alert('無法取得銷售單或客戶資料');
        return;
    }

    // 關閉詳情 Modal
    const detailModal = bootstrap.Modal.getInstance(document.getElementById('planDetailModal'));
    if (detailModal) detailModal.hide();

    currentPlanCustomerId = currentCustomerFilter.id;

    // 開啟新增計劃 Modal
    const createModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('createPlanModal'));
    createModal.show();

    // 預填銷售單
    document.getElementById('create-sale-id').value = saleId;

    // 隱藏銷售單選擇區
    const selector = document.getElementById('create-sales-selector');
    if (selector) selector.classList.add('d-none');

    // 清除舊提示
    document.querySelectorAll('#createPlanModal .alert-info').forEach(el => el.remove());

    // 顯示上下文提示
    if (selector) {
        const note = document.createElement('div');
        note.className = 'alert alert-info py-2 small mb-2';
        note.innerHTML = `正在為銷售單 <strong>#${saleId}</strong> 新增下一期計劃`;
        selector.parentNode.insertBefore(note, selector);
    }
}

function recordFollowUp(planId, event) {
    if (event) event.stopImmediatePropagation();

    const note = prompt('請輸入跟進內容（例如：已打電話，客戶說下週付款）：');
    if (!note || !note.trim()) return;

    SalonEase.fetch('/api/payment_plans.php?action=append_followup', {
        method: 'POST',
        body: new URLSearchParams({
            plan_id: planId,
            note: note.trim(),
            csrf_token: window.CSRF_TOKEN
        })
    })
    .then(res => {
        alert('跟進記錄已儲存！');
        // 刷新詳情以顯示最新跟進歷史
        showPlanDetail(planId);
    })
    .catch(err => {
        alert('儲存跟進失敗：' + err.message);
    });
}

function toggleFollowupExpansion(planId, badgeElement, followups) {
    const row = badgeElement.closest('tr');
    if (!row) return;

    // 檢查是否已經有展開的跟進區
    let expansionRow = row.nextElementSibling;
    if (expansionRow && expansionRow.classList.contains('followup-expansion')) {
        // 已展開 → 收起
        expansionRow.remove();
        return;
    }

    // 建立展開內容
    let contentHtml = '<div class="small">';
    followups.forEach((f, index) => {
        contentHtml += `<div class="mb-1 ${index === 0 ? 'fw-medium' : 'text-muted'}" style="white-space: pre-wrap;">${e(f)}</div>`;
    });
    contentHtml += '</div>';

    // 插入新的展開行
    expansionRow = document.createElement('tr');
    expansionRow.className = 'followup-expansion';
    expansionRow.dataset.planId = planId;
    expansionRow.dataset.followups = JSON.stringify(followups);
    expansionRow.innerHTML = `
        <td colspan="9" style="padding: 4px 12px; background-color: #f8f9fa; border-left: 4px solid #6c757d;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold text-secondary">最近跟進記錄</span>
                <span class="small text-muted" style="cursor:pointer;" onclick="event.stopImmediatePropagation(); this.closest('tr').remove();">收起</span>
            </div>
            <div class="followup-content">
                ${contentHtml}
            </div>
            
            <div class="mt-2 d-flex gap-2">
                <input type="text" class="form-control form-control-sm quick-followup-input" placeholder="輸入新的跟進內容...">
                <button class="btn btn-sm btn-primary" onclick="saveQuickFollowupFromList(${planId}, this)">儲存</button>
            </div>
        </td>
    `;

    row.parentNode.insertBefore(expansionRow, row.nextSibling);
}

async function saveQuickFollowupFromList(planId, buttonElement) {
    const expansionRow = buttonElement.closest('.followup-expansion');
    const input = expansionRow.querySelector('.quick-followup-input');
    const contentDiv = expansionRow.querySelector('.followup-content');
    if (!input || !contentDiv) return;

    const note = input.value.trim();
    if (!note) {
        alert('請輸入跟進內容');
        return;
    }

    buttonElement.disabled = true;
    buttonElement.textContent = '儲存中...';

    try {
        const res = await SalonEase.fetch('/api/payment_plans.php?action=append_followup', {
            method: 'POST',
            body: new URLSearchParams({
                plan_id: planId,
                note: note,
                csrf_token: window.CSRF_TOKEN
            })
        });

        const newFollowup = res.data?.note || `[跟進 ${new Date().toISOString().slice(0,16).replace('T',' ')}] ${note}`;

        // 取得目前儲存在 expansion 的 followups
        let currentFollowups = [];
        try {
            currentFollowups = JSON.parse(expansionRow.dataset.followups || '[]');
        } catch (e) {}

        // 新增到最前面
        currentFollowups.unshift(newFollowup);
        expansionRow.dataset.followups = JSON.stringify(currentFollowups);

        // 即時重新渲染展開內容
        let newContentHtml = '<div class="small">';
        currentFollowups.forEach((f, index) => {
            newContentHtml += `<div class="mb-1 ${index === 0 ? 'fw-medium' : 'text-muted'}" style="white-space: pre-wrap;">${e(f)}</div>`;
        });
        newContentHtml += '</div>';
        contentDiv.innerHTML = newContentHtml;

        // 清空輸入
        input.value = '';

        // 更新主列表該行的 "有跟進" 徽章的 tooltip（即時反映最新內容）
        const mainRow = expansionRow.previousElementSibling;
        if (mainRow) {
            const badge = mainRow.querySelector('.badge.bg-secondary');
            if (badge) {
                badge.setAttribute('title', e(newFollowup));
                // 更新 onclick 裡的 followups 資料
                badge.setAttribute('onclick', `event.stopImmediatePropagation(); toggleFollowupExpansion(${planId}, this, ${JSON.stringify(currentFollowups)})`);
            }
        }

        buttonElement.disabled = false;
        buttonElement.textContent = '儲存';

    } catch (err) {
        alert('儲存失敗：' + err.message);
        buttonElement.disabled = false;
        buttonElement.textContent = '儲存';
    }
}

function startQuickFollowup(planId, actionsCell, event, defaultNote = '') {
    if (event) event.stopImmediatePropagation();

    const originalHTML = actionsCell.innerHTML;
    const isStrict = strictNeedsAttentionOnly;
    const defaultVal = defaultNote || (isStrict ? '已初步聯絡' : '');

    actionsCell.innerHTML = `
        <div style="min-width: 260px;">
            <div class="d-flex flex-wrap gap-1 mb-1">
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:0.65rem;" onclick="fillQuickFollowupAndSave(this, ${planId}, '已聯絡')">已聯絡</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:0.65rem;" onclick="fillQuickFollowupAndSave(this, ${planId}, '客戶說下週付')">客戶說下週付</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:0.65rem;" onclick="fillQuickFollowupAndSave(this, ${planId}, '已發提醒')">已發提醒</button>
            </div>
            <div class="d-flex gap-1 align-items-center">
                <input type="text" class="form-control form-control-sm quick-followup-input" value="${defaultVal}" style="flex:1; min-width:120px;">
                <button class="btn btn-sm btn-primary" onclick="saveQuickFollowupInline(${planId}, this)">儲存</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="this.closest('td').innerHTML = ${JSON.stringify(originalHTML)};">取消</button>
            </div>
        </div>
    `;

    const input = actionsCell.querySelector('input');
    const saveBtn = actionsCell.querySelector('button.btn-success');
    const cancelBtn = actionsCell.querySelector('button.btn-outline-secondary');

    const save = () => {
        const note = input.value.trim() || '已初步聯絡';
        saveBtn.disabled = true;
        saveBtn.textContent = '儲存中...';

        SalonEase.fetch('/api/payment_plans.php?action=append_followup', {
            method: 'POST',
            body: new URLSearchParams({
                plan_id: planId,
                note: note,
                csrf_token: window.CSRF_TOKEN
            })
        })
        .then(() => {
            const row = actionsCell.closest('tr');
            const isStrict = strictNeedsAttentionOnly;

            // 即時更新該行的視覺狀態（完全不依賴整張表刷新）
            if (row) {
                const firstCell = row.querySelector('td');

                // 移除「需要關注」相關的紅色標記
                row.classList.remove('plan-needs-attention');
                if (firstCell) {
                    const oldNeedBadge = firstCell.querySelector('.badge.bg-danger[title*="未跟進"], .badge.bg-danger:contains("需跟進")');
                    if (oldNeedBadge) oldNeedBadge.remove();
                }

                // 確保「有跟進」徽章存在並更新
                let followupBadge = row.querySelector('.badge.bg-secondary');
                const newNoteText = `[跟進 ${new Date().toISOString().slice(0,16).replace('T',' ')}] ${note}`;

                if (!followupBadge && firstCell) {
                    followupBadge = document.createElement('span');
                    followupBadge.className = 'badge bg-secondary ms-1';
                    firstCell.appendChild(followupBadge);
                }

                if (followupBadge) {
                    followupBadge.textContent = '有跟進';
                    followupBadge.setAttribute('title', newNoteText);
                    followupBadge.style.cursor = 'pointer';
                    followupBadge.onclick = (e) => {
                        e.stopImmediatePropagation();
                        const latest = [newNoteText];
                        toggleFollowupExpansion(planId, followupBadge, latest);
                    };
                }

                // 在原輸入位置顯示成功訊息（短暫）
                actionsCell.innerHTML = `<div class="small text-success">✓ 已儲存跟進</div>`;
            }

            // 如果是嚴格模式：標記為「已初步處理」並移動到列表較下方（而非立即刪除）
            if (isStrict && row) {
                row.classList.remove('plan-needs-attention');
                row.classList.add('plan-handled');

                // 添加「已初步處理」標記
                const firstCell = row.querySelector('td');
                if (firstCell) {
                    const handled = document.createElement('span');
                    handled.className = 'badge bg-success ms-1';
                    handled.textContent = '已初步處理';
                    firstCell.appendChild(handled);
                }

                // 顯示「剛剛跟進」提示
                showJustFollowedPill(row, note);

                // 更新「未跟進」計數
                const countEl = document.getElementById('unfollowed-count');
                if (countEl) {
                    let current = parseInt(countEl.textContent) || 0;
                    countEl.textContent = Math.max(0, current - 1);
                }

                // 更新本日快速處理統計
                dailyHandledCount++;
                saveDailyHandledCount();
                updateDailyHandledDisplay();

                // 將該行移動到目前列表的較下方（模擬「移出優先處理區」）
                const tbody = row.parentNode;
                if (tbody) {
                    tbody.appendChild(row);
                }

                // 重新套用最老計劃置頂（避免剛處理的行把最老擠下去）
                applyOldestPlanPin();

                // 短暫成功提示後保留該行（讓用戶看到已處理的狀態）
                actionsCell.innerHTML = `
                    <div class="small text-success mb-1">✓ 已記錄為跟進</div>
                    <button onclick="restoreAsUnfollowed(${planId}, this.closest('tr'), event)" class="btn btn-sm btn-outline-warning me-1">還原為未跟進</button>
                    <button onclick="markAsFullyResolved(${planId}, this.closest('tr'), event)" class="btn btn-sm btn-success">標記為已徹底解決</button>
                `;
            }
        })
        .catch(err => {
            alert('儲存失敗：' + err.message);
            actionsCell.innerHTML = originalHTML;
        });
    };

    saveBtn.onclick = save;
    input.onkeydown = (e) => {
        if (e.key === 'Enter') {
            save();
        } else if (e.key === 'Escape') {
            actionsCell.innerHTML = originalHTML;
        }
    };

    cancelBtn.onclick = () => {
        actionsCell.innerHTML = originalHTML;
    };

    setTimeout(() => {
        input.focus();
        input.select();
    }, 50);
}

/**
 * 從活躍計劃清單點擊「新增下一期」時使用
 * 直接預填特定銷售單，並隱藏銷售單選擇區
 */
async function startNextPlanForSale(saleId, customerId, installmentAmount = null, planId = null) {
    if (!saleId || !customerId) {
        alert('資料不完整');
        return;
    }

    currentPlanCustomerId = customerId;

    // 關閉客戶 Modal
    const customerModal = bootstrap.Modal.getInstance(document.getElementById('customerModal'));
    if (customerModal) customerModal.hide();

    // 開啟新增計劃 Modal
    const createModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('createPlanModal'));
    createModal.show();

    // 預填銷售單
    document.getElementById('create-sale-id').value = saleId;

    // 隱藏銷售單選擇區
    const selector = document.getElementById('create-sales-selector');
    if (selector) selector.classList.add('d-none');

    // 清除之前可能殘留的上下文提示
    document.querySelectorAll('#createPlanModal .alert-info').forEach(el => el.remove());

    // 自動帶入每期金額（如果有傳入）
    if (installmentAmount && parseFloat(installmentAmount) > 0) {
        document.getElementById('create-installment-amount').value = parseFloat(installmentAmount);
    }

    // 如果有 planId，嘗試取完整計劃資料來自動帶入類型和頻率
    let planType = null;
    let frequency = null;

    if (planId) {
        try {
            const res = await SalonEase.fetch(`/api/payment_plans.php?action=get&id=${planId}`);
            const fullPlan = res.data;
            if (fullPlan) {
                planType = fullPlan.plan_type;
                frequency = fullPlan.frequency;

                // 自動帶入計劃類型
                if (planType) {
                    document.getElementById('create-plan-type').value = planType;
                    // 觸發 frequency 欄位顯示/隱藏
                    if (typeof toggleFrequencyField === 'function') {
                        toggleFrequencyField();
                    }
                }

                // 如果是 recurring 且有 frequency，自動帶入
                if (planType === 'recurring' && frequency) {
                    document.getElementById('create-frequency').value = frequency;
                }
            }
        } catch (e) {
            // 取不到就忽略，不影響主要流程
            console.warn('無法取得完整計劃資料來自動帶入類型/頻率', e);
        }
    }

    // 組合自動帶入的提示
    let notes = [];
    if (installmentAmount && parseFloat(installmentAmount) > 0) {
        notes.push(`每期金額 HK$ ${parseFloat(installmentAmount).toFixed(0)}`);
    }
    if (planType) {
        const typeLabel = planType === 'installment' ? '分期' : '周期性';
        notes.push(`類型「${typeLabel}」`);
        if (planType === 'recurring' && frequency) {
            notes.push(`頻率「${frequency}」`);
        }
    }

    if (notes.length > 0 && selector) {
        const autoNote = document.createElement('div');
        autoNote.className = 'alert alert-success py-2 small mb-2';
        autoNote.innerHTML = `✓ 已自動帶入：${notes.join('、')}（來自上一期）`;
        selector.parentNode.insertBefore(autoNote, selector);
    }

    // 顯示上下文提示（銷售單）
    if (selector) {
        const note = document.createElement('div');
        note.className = 'alert alert-info py-2 small mb-2';
        note.innerHTML = `正在為銷售單 <strong>#${saleId}</strong> 新增下一期計劃`;
        selector.parentNode.insertBefore(note, selector);
    }
}

/**
 * 「還原為未跟進」：把已初步處理的行恢復成需要關注狀態
 * 供用戶後悔時快速撤銷（不影響資料庫 notes）
 */
function restoreAsUnfollowed(planId, row, event) {
    if (event) event.stopImmediatePropagation();
    if (!row) return;

    // 移除已處理樣式
    row.classList.remove('plan-handled', 'plan-resolved');
    row.classList.add('plan-needs-attention');

    // 移除「已初步處理」「剛剛跟進」等動態標記
    const firstCell = row.querySelector('td');
    if (firstCell) {
        firstCell.querySelectorAll('.badge.bg-success').forEach(b => {
            if (b.textContent.includes('已初步處理') || b.classList.contains('just-followed-pill')) {
                b.remove();
            }
        });
    }

    // 嘗試把這行移回列表較前面（如果有最老 pinned 就放在它之後，否則最前）
    const tbody = row.parentNode;
    const pinned = tbody.querySelector('.oldest-pinned');
    if (pinned && pinned !== row) {
        pinned.after(row);
    } else {
        tbody.insertBefore(row, tbody.firstChild);
    }

    // 更新未跟進計數（+1）
    const countEl = document.getElementById('unfollowed-count');
    if (countEl) {
        countEl.textContent = (parseInt(countEl.textContent) || 0) + 1;
    }

    // 扣減本日處理數（避免誤計）
    if (dailyHandledCount > 0) {
        dailyHandledCount--;
        saveDailyHandledCount();
        updateDailyHandledDisplay();
    }

    // 重新套用最老置頂（以防順序改變）
    applyOldestPlanPin();
}

/**
 * 「標記為已徹底解決」：視覺上把該計劃從需要關注列表「移除」
 * （不自動改 status，讓用戶之後可再用「狀態」按鈕正式結案）
 */
function markAsFullyResolved(planId, row, event) {
    if (event) event.stopImmediatePropagation();
    if (!row) return;

    row.classList.remove('plan-needs-attention', 'plan-handled');
    row.classList.add('plan-resolved');

    // 加上「已解決」標記
    const firstCell = row.querySelector('td');
    if (firstCell) {
        // 移除舊的動態 badge
        firstCell.querySelectorAll('.badge.bg-success, .just-followed-pill').forEach(b => b.remove());

        const resolvedBadge = document.createElement('span');
        resolvedBadge.className = 'badge bg-secondary ms-1';
        resolvedBadge.textContent = '已解決';
        firstCell.appendChild(resolvedBadge);
    }

    // 移到列表最底（不再干擾優先處理區）
    const tbody = row.parentNode;
    if (tbody) {
        tbody.appendChild(row);
    }

    // 更新計數
    const countEl = document.getElementById('unfollowed-count');
    if (countEl) {
        countEl.textContent = Math.max(0, (parseInt(countEl.textContent) || 0) - 1);
    }

    // 重新確保最老計劃仍然在頂端
    applyOldestPlanPin();

    // 短暫提示
    setTimeout(() => {
        if (row && row.parentNode) {
            // 保留在畫面上但很淡，讓用戶知道它還在
        }
    }, 1200);
}

window.CSRF_TOKEN = '<?= csrf_token() ?>';

// 初始載入（預設只顯示進行中）
document.addEventListener('DOMContentLoaded', () => {
    loadSummary();
    loadPlanDashboard();
    loadDailyHandledCount();
    loadTodayWorkList();
    renderTodayWorkList();
    renderTodaySummary();   // 初始顯示總結卡片

    // 支援從 URL 帶 customer_id 自動套用篩選（例如從客戶 Modal「查看更多」跳轉）
    const urlParams = new URLSearchParams(window.location.search);
    const urlCustomerId = parseInt(urlParams.get('customer_id') || '0');
    if (urlCustomerId > 0) {
        currentCustomerFilter = { id: urlCustomerId, name: '', phone: '' };
        updateCustomerFilterBanner();
    }

    // Phase 4 A：支援 ?strict=1 直接進入嚴格需要關注模式（Dashboard 連結用）
    if (urlParams.get('strict') === '1') {
        isNeedsAttentionView = true;
        strictNeedsAttentionOnly = true;
    }

    loadPlans();
});

// 簡單的 e() 轉義（與其他頁面一致）
function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
