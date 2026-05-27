/**
 * SalonEase - 熱鍵管理系統（核心功能）
 * 支援全域 + 頁面專屬熱鍵
 * 自動產生幫助 Modal 內容
 */

(function() {
    const registry = {
        global: [
            { key: '?', desc: '顯示目前頁面快捷鍵說明', action: () => window.showHotkeyHelp?.() },
            { key: 'Ctrl+K', desc: '開啟命令面板（快速跳轉）', action: showCommandPalette },
            { key: 'Esc', desc: '關閉彈窗 / 取消操作' },
            { key: 'Alt+H', desc: '返回概覽頁', action: () => location.href = '/dashboard.php' },
            { key: 'Alt+P', desc: '前往 POS 銷售', action: () => location.href = '/pos.php' },
            { key: 'Alt+A', desc: '前往預約管理', action: () => location.href = '/appointments.php' },
            { key: 'Alt+C', desc: '前往客戶管理', action: () => location.href = '/customers.php' },
            { key: 'Alt+R', desc: '前往報表', action: () => location.href = '/reports.php' },
            { key: 'Alt+S', desc: '前往設定', action: () => location.href = '/settings.php' },
        ],
        // 各頁面可透過 window.SalonEase.Hotkeys.registerPage 加入
        page: []
    };

    function showCommandPalette() {
        const palette = document.createElement('div');
        palette.className = 'fixed inset-0 bg-black/30 z-[80] flex items-start justify-center pt-[12vh]';
        palette.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
                <div class="p-3 border-b">
                    <input type="text" id="cmd-input" placeholder="輸入頁面名稱或功能..." 
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-[#8FA68F]">
                </div>
                <div class="p-2 text-sm max-h-[260px] overflow-auto" id="cmd-results">
                    <div class="px-3 py-2 text-[#8A8A8C]">快速跳轉</div>
                    <div onclick="location.href='/pos.php'" class="px-3 py-2 hover:bg-gray-50 rounded-xl cursor-pointer flex justify-between"><span>POS 銷售</span><span class="text-xs text-gray-400">Alt+P</span></div>
                    <div onclick="location.href='/appointments.php'" class="px-3 py-2 hover:bg-gray-50 rounded-xl cursor-pointer flex justify-between"><span>預約管理</span><span class="text-xs text-gray-400">Alt+A</span></div>
                    <div onclick="location.href='/customers.php'" class="px-3 py-2 hover:bg-gray-50 rounded-xl cursor-pointer flex justify-between"><span>客戶管理</span><span class="text-xs text-gray-400">Alt+C</span></div>
                    <div onclick="location.href='/dashboard.php'" class="px-3 py-2 hover:bg-gray-50 rounded-xl cursor-pointer flex justify-between"><span>概覽首頁</span><span class="text-xs text-gray-400">Alt+H</span></div>
                </div>
            </div>
        `;
        document.body.appendChild(palette);

        const input = palette.querySelector('#cmd-input');
        setTimeout(() => input.focus(), 30);

        function close() { palette.remove(); }
        palette.addEventListener('click', (e) => { if (e.target === palette) close(); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    }

    // 公開 API
    window.SalonEase = window.SalonEase || {};
    window.SalonEase.Hotkeys = {
        registerPage: function(keys) {
            registry.page = keys;
        },
        renderHelp: function(container) {
            let html = '<div class="space-y-6 text-sm">';
            
            // 全局
            html += '<div><div class="font-semibold mb-2 text-[#2C2C2E]">全域快捷鍵（任何頁面）</div>';
            html += '<div class="grid grid-cols-1 gap-x-6 gap-y-1">';
            registry.global.forEach(item => {
                html += `<div class="flex justify-between py-px"><span class="font-mono text-[#8FA68F]">${item.key}</span><span>${item.desc}</span></div>`;
            });
            html += '</div></div>';

            // 頁面專屬
            if (registry.page.length) {
                html += '<div><div class="font-semibold mb-2 text-[#2C2C2E]">本頁專屬</div>';
                html += '<div class="grid grid-cols-1 gap-x-6 gap-y-1">';
                registry.page.forEach(item => {
                    html += `<div class="flex justify-between py-px"><span class="font-mono text-[#8FA68F]">${item.key}</span><span>${item.desc}</span></div>`;
                });
                html += '</div></div>';
            }

            html += '<div class="pt-2 text-[11px] text-[#8A8A8C]">提示：F 系列鍵（F2-F10）在 POS 頁最常用</div>';
            html += '</div>';

            container.innerHTML = html;
        },
        getAll: () => ({ global: registry.global, page: registry.page })
    };

    // 自動綁定全域熱鍵
    document.addEventListener('keydown', function(e) {
        const tag = document.activeElement.tagName;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) && e.key !== 'Escape') return;

        // 簡單全域 Alt 組合
        if (e.altKey) {
            const map = { 'h': '/dashboard.php', 'p': '/pos.php', 'a': '/appointments.php', 'c': '/customers.php', 'r': '/reports.php', 's': '/settings.php' };
            if (map[e.key.toLowerCase()]) {
                e.preventDefault();
                location.href = map[e.key.toLowerCase()];
            }
        }
    });

    console.log('%c[SalonEase] 熱鍵系統已就緒（按 ? 測試）', 'color:#8FA68F;font-size:9px');
})();
