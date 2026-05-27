/**
 * SalonEase - 全域 JavaScript
 * fetch wrapper、Toast、Modal 工具、全域熱鍵入口
 */

window.SalonEase = window.SalonEase || {};

// 全域 fetch 封裝（統一錯誤處理 + JSON）
window.SalonEase.fetch = async function(url, options = {}) {
    const opts = {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        ...options
    };
    
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
        opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        opts.body = new URLSearchParams(opts.body);
    }

    try {
        const res = await fetch(url, opts);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch { data = { success: false, message: text }; }

        if (!res.ok || data.success === false) {
            const msg = data.message || '發生錯誤';
            if (res.status === 401) {
                window.location.href = '/login.php';
            }
            throw new Error(msg);
        }
        return data;
    } catch (err) {
        console.error('[SalonEase.fetch]', err);
        throw err;
    }
};

// 簡單 Toast 通知
window.SalonEase.toast = function(message, type = 'success', duration = 2400) {
    const colors = {
        success: 'bg-[#8FA68F] text-white',
        error:   'bg-red-600 text-white',
        info:    'bg-[#2C2C2E] text-white'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed bottom-14 left-1/2 -translate-x-1/2 px-5 py-2.5 rounded-2xl shadow-lg text-sm font-medium z-[80] ${colors[type] || colors.info}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'all .2s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 180);
    }, duration);
};

// 顯示全域熱鍵幫助（使用 Bootstrap Modal）
window.showHotkeyHelp = function() {
    const modalEl = document.getElementById('hotkeyModal');
    const content = document.getElementById('hotkey-content');
    if (!modalEl || !content) return;

    // 如果 hotkeys.js 已載入，會覆寫內容
    if (window.SalonEase.Hotkeys && typeof window.SalonEase.Hotkeys.renderHelp === 'function') {
        window.SalonEase.Hotkeys.renderHelp(content);
    } else {
        content.innerHTML = `
            <div class="space-y-4">
                <div class="font-medium">全域快捷鍵</div>
                <div class="row g-2 small">
                    <div class="col-6"><span class="font-mono fw-semibold">?</span> 顯示本頁熱鍵</div>
                    <div class="col-6"><span class="font-mono fw-semibold">Ctrl+K</span> 命令面板</div>
                    <div class="col-6"><span class="font-mono fw-semibold">Esc</span> 關閉彈窗</div>
                    <div class="col-6"><span class="font-mono fw-semibold">Alt+P</span> 前往 POS</div>
                </div>
                <div class="pt-2 small text-muted">提示：每頁都有專屬快捷鍵，按「?」查看完整列表。</div>
            </div>
        `;
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

window.hideHotkeyHelp = function() {
    const modalEl = document.getElementById('hotkeyModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
};

// 全域鍵盤監聽（基本 Esc 與 ?）
document.addEventListener('keydown', function(e) {
    // Esc 關閉 Modal
    if (e.key === 'Escape') {
        const openModal = document.querySelector('#hotkey-modal:not(.hidden)');
        if (openModal) {
            openModal.classList.add('hidden');
            openModal.classList.remove('flex');
        } else {
            // 讓各頁面自己的 modal 處理
            document.dispatchEvent(new CustomEvent('salonease:esc-pressed'));
        }
    }

    // ? 顯示幫助（避免輸入框時觸發）
    if (e.key === '?' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        if (typeof window.showHotkeyHelp === 'function') window.showHotkeyHelp();
    }
});

// 開發提示
console.log('%c[SalonEase] 全域 JS 已載入', 'color:#8A8A8C;font-size:9px');
