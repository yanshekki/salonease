/**
 * SalonEase - Ctrl+K 命令面板（生產力神器）
 * 支援：情境感知、最近使用加權、搜尋評分、鍵盤導航、POS 直接加購物車
 * 版本：v2 (A 選項迭代)
 */
(function() {
  'use strict';

  const RECENT_USAGE_KEY = 'salonease_cmd_recent_usage';
  const RECENT_SEARCHES_KEY = 'salonease_cmd_recent_searches';
  const MAX_RECENT = 8;

  // 基礎靜態動作（所有頁面通用）
  const BASE_ACTIONS = [
    { id: 'nav-pos', label: 'POS 銷售', url: '/pos.php', keywords: 'pos 銷售 收銀 結帳', icon: '🛒', contexts: ['*'] },
    { id: 'nav-appointments', label: '預約管理', url: '/appointments.php', keywords: '預約 時間表 今日', icon: '📅', contexts: ['*'] },
    { id: 'nav-customers', label: '客戶管理', url: '/customers.php', keywords: '客戶 會員 電話', icon: '👤', contexts: ['*'] },
    { id: 'nav-dashboard', label: '概覽首頁', url: '/dashboard.php', keywords: '首頁 概覽 dashboard 統計', icon: '🏠', contexts: ['*'] },
    { id: 'nav-reports', label: '報表分析', url: '/reports.php', keywords: '報表 營業額 統計 分析', icon: '📊', contexts: ['*'] },
    { id: 'nav-settings', label: '系統設定', url: '/settings.php', keywords: '設定 員工 房間 服務 產品', icon: '⚙️', contexts: ['*'] },
  ];

  // POS 專屬可直接加入購物車的示範項目（真實 POS 頁實作後會動態載入）
  const POS_CATALOG = [
    { id: 'svc-1', type: 'service', label: '經典面部護理 60分', price: 380, keywords: '面部 護理 保濕 抗老', icon: '💆', category: '面部護理' },
    { id: 'svc-2', type: 'service', label: '深層清潔護理 75分', price: 480, keywords: '清潔 粉刺 去角質', icon: '🧼', category: '面部護理' },
    { id: 'svc-3', type: 'service', label: '全身淋巴引流 90分', price: 680, keywords: '淋巴 瘦身 排水', icon: '🌿', category: '身體護理' },
    { id: 'prod-1', type: 'product', label: '保濕精華液 30ml', price: 280, keywords: '精華 保濕 hyaluronic', icon: '🧴', sku: 'ES-001', stock: 12 },
    { id: 'prod-2', type: 'product', label: '胺基酸潔面乳 150ml', price: 120, keywords: '潔面 洗面 敏感肌', icon: '🧴', sku: 'CL-022', stock: 7 },
    { id: 'prod-3', type: 'product', label: '防曬乳 SPF50 50ml', price: 158, keywords: '防曬 隔離 夏日', icon: '☀️', sku: 'SU-050', stock: 19 },
  ];

  let currentPalette = null;
  let currentInput = null;
  let currentResultsEl = null;
  let activeIndex = 0;
  let currentResults = [];
  let currentContext = 'global';

  function loadRecent() {
    try {
      return {
        usage: JSON.parse(localStorage.getItem(RECENT_USAGE_KEY) || '[]'),
        searches: JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY) || '[]')
      };
    } catch (e) { return { usage: [], searches: [] }; }
  }

  function saveRecent(data) {
    try {
      localStorage.setItem(RECENT_USAGE_KEY, JSON.stringify(data.usage.slice(0, MAX_RECENT * 2)));
      localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(data.searches.slice(0, 12)));
    } catch (e) {}
  }

  function recordUsage(actionId) {
    const data = loadRecent();
    data.usage = data.usage.filter(x => x !== actionId);
    data.usage.unshift(actionId);
    saveRecent(data);
  }

  function recordSearch(query) {
    if (!query || query.trim().length < 2) return;
    const data = loadRecent();
    const q = query.trim().toLowerCase();
    data.searches = data.searches.filter(x => x !== q);
    data.searches.unshift(q);
    saveRecent(data);
  }

  function getContext() {
    const path = (location.pathname || '').toLowerCase();
    if (path.includes('pos')) return 'pos';
    if (path.includes('appointment')) return 'appointments';
    if (path.includes('customer')) return 'customers';
    if (path.includes('report')) return 'reports';
    if (path.includes('setting')) return 'settings';
    if (path.includes('dashboard') || path === '/' || path.endsWith('index.php')) return 'dashboard';
    return 'global';
  }

  function getContextLabel(ctx) {
    const map = {
      pos: 'POS 銷售頁',
      appointments: '預約管理頁',
      customers: '客戶管理頁',
      reports: '報表頁',
      settings: '設定頁',
      dashboard: '概覽頁',
      global: '全域'
    };
    return map[ctx] || '目前頁面';
  }

  // 簡單模糊比對分數（0-100）
  function fuzzyScore(query, text) {
    if (!query) return 40;
    const q = query.toLowerCase().trim();
    const t = (text || '').toLowerCase();
    if (t.includes(q)) return 100;
    let score = 0;
    let qi = 0;
    for (let i = 0; i < t.length && qi < q.length; i++) {
      if (t[i] === q[qi]) { score += 8; qi++; }
    }
    if (qi === q.length) score += 30;
    return Math.min(95, Math.max(10, score));
  }

  function calculateScore(query, item, context) {
    const ctx = context || currentContext;
    let score = 50;

    // 標籤與關鍵字匹配
    const text = `${item.label} ${item.keywords || ''} ${item.category || ''} ${item.sku || ''}`;
    score = fuzzyScore(query, text);

    // 情境加權
    if (item.contexts && (item.contexts.includes('*') || item.contexts.includes(ctx))) {
      score += 18;
    }
    if (ctx === 'pos' && (item.type === 'service' || item.type === 'product')) {
      score += 25; // POS 頁極力推薦 catalog 項目
    }

    // 最近使用加權（強烈提升排序）
    const recent = loadRecent().usage;
    const idx = recent.indexOf(item.id);
    if (idx !== -1) {
      score += Math.max(35 - idx * 3, 8);
    }

    // 類型加權（POS 頁 catalog 項目再加分）
    if (item.type === 'service' || item.type === 'product') score += 5;

    return Math.round(Math.min(150, score));
  }

  function getAllItems(context) {
    const ctx = context || currentContext;
    let items = [...BASE_ACTIONS];

    // POS 頁加入可直接操作的服務/產品
    if (ctx === 'pos') {
      POS_CATALOG.forEach(p => {
        items.push({
          ...p,
          id: p.id,
          contexts: ['pos'],
          action: 'add-to-cart'   // 特殊標記
        });
      });
    }

    return items;
  }

  function updateResults(query = '') {
    if (!currentResultsEl) return;

    const ctx = getContext();
    currentContext = ctx;

    const allItems = getAllItems(ctx);
    const q = (query || '').trim();

    let scored = allItems.map(item => ({
      ...item,
      score: calculateScore(q, item, ctx)
    }));

    // 排序：分數 > 最近 > 標籤
    scored.sort((a, b) => {
      if (b.score !== a.score) return b.score - a.score;
      const ra = loadRecent().usage.indexOf(a.id);
      const rb = loadRecent().usage.indexOf(b.id);
      if (ra !== rb) return (ra === -1 ? 999 : ra) - (rb === -1 ? 999 : rb);
      return a.label.localeCompare(b.label, 'zh-HK');
    });

    // 過濾極低分（除非空白查詢）
    if (q.length > 0) {
      scored = scored.filter(s => s.score > 18);
    }

    currentResults = scored.slice(0, 18); // 最多顯示
    activeIndex = 0;

    renderResults(query);
  }

  function renderResults(query = '') {
    if (!currentResultsEl) return;

    const ctxLabel = getContextLabel(currentContext);
    let html = '';

    const recentData = loadRecent();
    const recentIds = new Set(recentData.usage.slice(0, 5));

    // 頂部狀態列
    html += `<div class="px-3 py-1.5 text-[11px] text-[#8A8A8C] flex items-center justify-between border-b bg-gray-50">
      <div>目前在 <span class="font-medium text-[#2C2C2E]">${ctxLabel}</span></div>
      <div class="text-[10px]">↑↓ 選擇 · Enter 執行 · Esc 關閉</div>
    </div>`;

    if (currentResults.length === 0) {
      html += `<div class="px-4 py-8 text-center text-[#8A8A8C] text-sm">找不到符合「${query}」的項目</div>`;
      currentResultsEl.innerHTML = html;
      return;
    }

    // 最近使用區塊（只顯示前幾個高分且在最近的）
    const recentResults = currentResults.filter(r => recentIds.has(r.id)).slice(0, 3);
    if (recentResults.length && !query) {
      html += `<div class="px-3 pt-2 pb-1 text-xs font-medium text-[#8A8A8C]">最近使用</div>`;
      recentResults.forEach((item, idx) => {
        const globalIdx = currentResults.indexOf(item);
        html += renderResultRow(item, globalIdx, true);
      });
    }

    // 主要結果
    const mainLabel = query ? '搜尋結果' : (currentContext === 'pos' ? '推薦服務 / 產品（可直接加入購物車）' : '所有功能');
    html += `<div class="px-3 pt-2 pb-1 text-xs font-medium text-[#8A8A8C]">${mainLabel}</div>`;

    currentResults.forEach((item, idx) => {
      // 避免重複顯示最近區已出現的
      if (recentResults.some(r => r.id === item.id) && !query) return;
      html += renderResultRow(item, idx, false);
    });

    currentResultsEl.innerHTML = html;

    // 綁定 hover / click
    currentResultsEl.querySelectorAll('.cmd-result-row').forEach(row => {
      const idx = parseInt(row.dataset.idx, 10);
      row.addEventListener('mouseenter', () => {
        activeIndex = idx;
        highlightActive();
      });
      row.addEventListener('click', (e) => {
        // 判斷是否點擊在次要按鈕上
        if (e.target.closest('.cmd-add-btn')) {
          executeAddToCart(currentResults[idx]);
        } else {
          executeResult(currentResults[idx]);
        }
      });
    });

    highlightActive();
  }

  function renderResultRow(item, idx, isRecent) {
    const isAddable = item.action === 'add-to-cart' || (currentContext === 'pos' && (item.type === 'service' || item.type === 'product'));
    const priceTag = item.price ? `<span class="text-[#8FA68F] font-medium">$${item.price}</span>` : '';
    const skuTag = item.sku ? `<span class="text-[10px] text-[#8A8A8C] ml-1">(${item.sku})</span>` : '';
    const stockTag = (item.stock !== undefined) ? (item.stock < 10 ? `<span class="ml-1 text-[10px] text-red-500">剩${item.stock}</span>` : '') : '';

    const secondary = isAddable 
      ? `<button class="cmd-add-btn ml-auto px-3 py-0.5 text-xs rounded-lg bg-[#8FA68F] text-white font-medium active:scale-95 transition">加入購物車</button>`
      : `<span class="ml-auto text-[11px] text-[#8A8A8C]">${item.icon || ''}</span>`;

    const recentBadge = isRecent ? `<span class="ml-2 text-[10px] px-1.5 py-px rounded bg-amber-100 text-amber-700">最近</span>` : '';

    return `
      <div class="cmd-result-row flex items-center gap-x-3 px-3 py-2 rounded-xl cursor-pointer hover:bg-gray-100 transition ${idx === activeIndex ? 'bg-gray-100' : ''}" data-idx="${idx}">
        <div class="w-8 text-xl flex-shrink-0 text-center">${item.icon || '🔗'}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center">
            <span class="font-medium text-sm text-[#2C2C2E] truncate">${item.label}</span>
            ${recentBadge}
            ${skuTag}
            ${stockTag}
          </div>
          ${item.category ? `<div class="text-[11px] text-[#8A8A8C] truncate">${item.category}</div>` : ''}
        </div>
        <div class="flex items-center text-sm">
          ${priceTag}
          ${secondary}
        </div>
      </div>
    `;
  }

  function highlightActive() {
    if (!currentResultsEl) return;
    currentResultsEl.querySelectorAll('.cmd-result-row').forEach((row, i) => {
      const idx = parseInt(row.dataset.idx, 10);
      if (idx === activeIndex) {
        row.classList.add('bg-gray-100', 'ring-1', 'ring-[#8FA68F]/30');
      } else {
        row.classList.remove('bg-gray-100', 'ring-1', 'ring-[#8FA68F]/30');
      }
    });
  }

  function executeResult(item) {
    if (!item) return;
    recordUsage(item.id);

    if (item.action === 'add-to-cart' || (currentContext === 'pos' && (item.type === 'service' || item.type === 'product'))) {
      executeAddToCart(item);
      return;
    }

    hideCommandPalette();

    if (item.url) {
      location.href = item.url;
    } else if (typeof item.fn === 'function') {
      item.fn();
    }
  }

  function executeAddToCart(item) {
    recordUsage(item.id);
    hideCommandPalette();

    // 嘗試呼叫真實 POS 系統（未來實作）
    const POS = window.SalonEase && window.SalonEase.POS;
    if (POS && typeof POS.addToCart === 'function') {
      POS.addToCart({
        id: item.id.replace(/^(svc|prod)-/, ''),
        name: item.label,
        price: item.price,
        type: item.type,
        staffId: null
      });
      window.SalonEase.toast && window.SalonEase.toast(`已加入：${item.label}`, 'success');
      return;
    }

    // 目前 POS 尚未實作 → 給予明確提示 + 方便跳轉
    const go = confirm(`「${item.label}」已記錄為想加入的項目。\n\nPOS 完整購物車即將實作。\n現在前往 POS 頁嗎？`);
    if (go) {
      location.href = '/pos.php';
    } else {
      window.SalonEase.toast && window.SalonEase.toast(`已記錄「${item.label}」到最近使用`, 'info');
    }
  }

  function showCommandPalette() {
    if (currentPalette) {
      // 如果已開啟就 focus 搜尋框
      if (currentInput) currentInput.focus();
      return;
    }

    currentContext = getContext();

    currentPalette = document.createElement('div');
    currentPalette.className = 'fixed inset-0 bg-black/40 z-[90] flex items-start justify-center pt-[10vh]';
    currentPalette.innerHTML = `
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border border-gray-200">
        <!-- 搜尋框 -->
        <div class="p-3 border-b bg-white">
          <div class="flex items-center gap-x-2 px-3">
            <span class="text-xl">⌘</span>
            <input type="text" id="cmd-input" 
                   class="flex-1 bg-transparent text-base placeholder:text-[#8A8A8C] focus:outline-none"
                   placeholder="搜尋功能、服務、產品或客戶...">
          </div>
        </div>
        
        <!-- 結果區 -->
        <div id="cmd-results" class="max-h-[340px] overflow-auto py-1 text-sm bg-white"></div>
        
        <div class="px-3 py-2 border-t bg-gray-50 text-[11px] text-[#8A8A8C] flex justify-between items-center">
          <div>最近使用會自動提升排序</div>
          <div>Ctrl+K 再次開啟</div>
        </div>
      </div>
    `;

    document.body.appendChild(currentPalette);

    currentInput = currentPalette.querySelector('#cmd-input');
    currentResultsEl = currentPalette.querySelector('#cmd-results');

    // 初始渲染
    setTimeout(() => {
      updateResults('');
      currentInput && currentInput.focus();
      currentInput && currentInput.select();
    }, 10);

    // 輸入即時搜尋
    currentInput.addEventListener('input', (e) => {
      updateResults(e.target.value);
    });

    // 鍵盤導航
    currentInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        hideCommandPalette();
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, currentResults.length - 1);
        highlightActive();
        scrollIntoViewIfNeeded();
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        highlightActive();
        scrollIntoViewIfNeeded();
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        if (currentResults[activeIndex]) {
          executeResult(currentResults[activeIndex]);
        }
      }
      // 快速清空
      if (e.key === '/' && currentInput.value.length === 0) {
        // 允許 / 開始新搜尋
      }
    });

    // 點擊背景關閉
    currentPalette.addEventListener('click', (e) => {
      if (e.target === currentPalette) hideCommandPalette();
    });

    // 記錄本次開啟的搜尋（blur 時）
    currentInput.addEventListener('blur', () => {
      setTimeout(() => {
        if (currentInput && currentInput.value.trim()) {
          recordSearch(currentInput.value);
        }
      }, 120);
    });
  }

  function scrollIntoViewIfNeeded() {
    if (!currentResultsEl) return;
    const activeRow = currentResultsEl.querySelector(`.cmd-result-row[data-idx="${activeIndex}"]`);
    if (activeRow) activeRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function hideCommandPalette() {
    if (currentPalette) {
      currentPalette.remove();
      currentPalette = null;
      currentInput = null;
      currentResultsEl = null;
      currentResults = [];
      activeIndex = 0;
    }
  }

  // 公開 API
  window.showCommandPalette = showCommandPalette;
  window.hideCommandPalette = hideCommandPalette;

  // 開發者工具（方便日後擴展）
  window.SalonEase = window.SalonEase || {};
  window.SalonEase.CommandPalette = {
    show: showCommandPalette,
    hide: hideCommandPalette,
    getContext,
    recordUsage,
    _debug: () => ({ recent: loadRecent(), context: getContext() })
  };

  // 方便熱鍵系統直接呼叫（hotkeys.js 會用到）
  window.SalonEase.Hotkeys = window.SalonEase.Hotkeys || {};
  window.SalonEase.Hotkeys.showCommandPalette = showCommandPalette;

  console.log('%c[SalonEase] 命令面板 v2 已就緒（Ctrl+K 體驗升級）', 'color:#8FA68F;font-size:9px');
})();