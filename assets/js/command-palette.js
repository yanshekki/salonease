/**
 * SalonEase - Ctrl+K 命令面板（獨立模組 v3 - A 選項）
 * 完整功能：情境感知、搜尋評分 + 最近使用加權、鍵盤導航、POS 直接「加入購物車」
 * 完全使用原生 Bootstrap 5 Modal + bootstrap.Modal API
 */
(function() {
  'use strict';

  const RECENT_USAGE_KEY = 'salonease_cmd_recent_usage_v3';
  const RECENT_SEARCHES_KEY = 'salonease_cmd_recent_searches_v3';
  const MAX_RECENT = 8;

  // 基礎導航動作
  const BASE_ACTIONS = [
    { id: 'nav-pos', label: 'POS 銷售', url: '/pos.php', keywords: 'pos 銷售 收銀 結帳', icon: '🛒', contexts: ['*'] },
    { id: 'nav-appointments', label: '預約管理', url: '/appointments.php', keywords: '預約 時間表 今日', icon: '📅', contexts: ['*'] },
    { id: 'nav-customers', label: '客戶管理', url: '/customers.php', keywords: '客戶 會員 電話', icon: '👤', contexts: ['*'] },
    { id: 'nav-dashboard', label: '概覽首頁', url: '/dashboard.php', keywords: '首頁 概覽 dashboard 統計', icon: '🏠', contexts: ['*'] },
    { id: 'nav-reports', label: '報表分析', url: '/reports.php', keywords: '報表 營業額 統計', icon: '📊', contexts: ['*'] },
    { id: 'nav-settings', label: '系統設定', url: '/settings.php', keywords: '設定 員工 房間 服務 產品', icon: '⚙️', contexts: ['*'] },
  ];

  // POS 情境下可直接加入購物車的示範 catalog（真實 POS 實作後改為動態 API）
  const POS_CATALOG = [
    { id: 'svc-1', type: 'service', label: '經典面部護理 60分', price: 380, keywords: '面部 護理 保濕', icon: '💆', category: '面部護理' },
    { id: 'svc-2', type: 'service', label: '深層清潔護理 75分', price: 480, keywords: '清潔 粉刺', icon: '🧼', category: '面部護理' },
    { id: 'svc-3', type: 'service', label: '全身淋巴引流 90分', price: 680, keywords: '淋巴 瘦身', icon: '🌿', category: '身體護理' },
    { id: 'prod-1', type: 'product', label: '保濕精華液 30ml', price: 280, keywords: '精華 保濕', icon: '🧴', sku: 'ES-001', stock: 12 },
    { id: 'prod-2', type: 'product', label: '胺基酸潔面乳 150ml', price: 120, keywords: '潔面 敏感肌', icon: '🧴', sku: 'CL-022', stock: 7 },
    { id: 'prod-3', type: 'product', label: '防曬乳 SPF50 50ml', price: 158, keywords: '防曬 夏日', icon: '☀️', sku: 'SU-050', stock: 19 },
  ];

  let modalEl = null;
  let bsModal = null;
  let inputEl = null;
  let resultsEl = null;
  let activeIndex = 0;
  let currentResults = [];
  let currentContext = 'general';

  function loadRecent() {
    try {
      return {
        usage: JSON.parse(localStorage.getItem(RECENT_USAGE_KEY) || '[]'),
        searches: JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY) || '[]')
      };
    } catch { return { usage: [], searches: [] }; }
  }

  function saveRecent(data) {
    try {
      localStorage.setItem(RECENT_USAGE_KEY, JSON.stringify(data.usage.slice(0, MAX_RECENT * 2)));
      localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(data.searches.slice(0, 12)));
    } catch {}
  }

  function recordUsage(id) {
    const d = loadRecent();
    d.usage = d.usage.filter(x => x !== id);
    d.usage.unshift(id);
    saveRecent(d);
  }

  function recordSearch(q) {
    if (!q || q.trim().length < 2) return;
    const d = loadRecent();
    const v = q.trim().toLowerCase();
    d.searches = d.searches.filter(x => x !== v);
    d.searches.unshift(v);
    saveRecent(d);
  }

  function getContext() {
    const p = (location.pathname || '').toLowerCase();
    if (p.includes('pos')) return 'pos';
    if (p.includes('appointment')) return 'appointments';
    if (p.includes('customer')) return 'customers';
    if (p.includes('report')) return 'reports';
    if (p.includes('setting')) return 'settings';
    if (p.includes('dashboard') || p === '/' || p.endsWith('index.php')) return 'dashboard';
    return 'general';
  }

  function getContextLabel(c) {
    return { pos: 'POS 銷售', appointments: '預約管理', customers: '客戶管理', reports: '報表', settings: '系統設定', dashboard: '概覽', general: '全域' }[c] || '目前頁面';
  }

  function fuzzyScore(query, text) {
    if (!query) return 38;
    const q = query.toLowerCase().trim();
    const t = (text || '').toLowerCase();
    if (t.includes(q)) return 100;
    let s = 0, qi = 0;
    for (let i = 0; i < t.length && qi < q.length; i++) if (t[i] === q[qi]) { s += 7; qi++; }
    return Math.min(92, Math.max(8, s + (qi === q.length ? 28 : 0)));
  }

  function calculateScore(q, item, ctx) {
    let score = fuzzyScore(q, `${item.label} ${item.keywords || ''} ${item.category || ''} ${item.sku || ''}`);
    if (item.contexts && (item.contexts.includes('*') || item.contexts.includes(ctx))) score += 16;
    if (ctx === 'pos' && (item.type === 'service' || item.type === 'product')) score += 28;
    const recent = loadRecent().usage;
    const pos = recent.indexOf(item.id);
    if (pos !== -1) score += Math.max(32 - pos * 3, 6);
    return Math.round(Math.min(148, score));
  }

  function getItems(ctx) {
    let arr = [...BASE_ACTIONS];
    if (ctx === 'pos') {
      POS_CATALOG.forEach(p => arr.push({ ...p, action: 'add-to-cart', contexts: ['pos'] }));
    }
    return arr;
  }

  function updateResults(q = '') {
    if (!resultsEl) return;
    const ctx = getContext();
    currentContext = ctx;
    const items = getItems(ctx);

    let scored = items.map(it => ({ ...it, score: calculateScore(q, it, ctx) }));
    scored.sort((a, b) => {
      if (b.score !== a.score) return b.score - a.score;
      const ra = loadRecent().usage.indexOf(a.id), rb = loadRecent().usage.indexOf(b.id);
      return (ra === -1 ? 999 : ra) - (rb === -1 ? 999 : rb);
    });
    if (q.trim()) scored = scored.filter(s => s.score > 16);
    currentResults = scored.slice(0, 16);
    activeIndex = 0;
    renderResults(q);
  }

  function renderResults(q) {
    if (!resultsEl) return;
    const ctxLabel = getContextLabel(currentContext);
    let html = `<div class="px-3 py-2 small text-muted d-flex justify-content-between align-items-center border-bottom">
      <div>目前在 <span class="fw-medium text-dark">${ctxLabel}</span></div>
      <div class="text-muted" style="font-size:10px">↑↓ 選擇 · Enter 執行 · Esc 關閉</div>
    </div>`;

    if (!currentResults.length) {
      html += `<div class="p-4 text-center text-muted">找不到符合「${q}」的項目</div>`;
      resultsEl.innerHTML = html;
      return;
    }

    const recentIds = new Set(loadRecent().usage.slice(0, 4));
    const recentOnes = currentResults.filter(r => recentIds.has(r.id)).slice(0, 3);

    if (recentOnes.length && !q.trim()) {
      html += `<div class="px-3 pt-2 pb-1 small text-muted fw-medium">最近使用</div>`;
      recentOnes.forEach((item, i) => {
        const gi = currentResults.indexOf(item);
        html += rowHTML(item, gi, true);
      });
    }

    const section = q.trim() ? '搜尋結果' : (currentContext === 'pos' ? '推薦服務／產品（可直接加入購物車）' : '所有功能');
    html += `<div class="px-3 pt-2 pb-1 small text-muted fw-medium">${section}</div>`;

    currentResults.forEach((item, i) => {
      if (recentOnes.some(r => r.id === item.id) && !q.trim()) return;
      html += rowHTML(item, i, false);
    });

    resultsEl.innerHTML = html;

    resultsEl.querySelectorAll('.cmd-row').forEach(row => {
      const idx = parseInt(row.dataset.idx);
      row.addEventListener('mouseenter', () => { activeIndex = idx; highlight(); });
      row.addEventListener('click', e => {
        if (e.target.closest('.cmd-add-btn')) {
          doAddToCart(currentResults[idx]);
        } else {
          doExecute(currentResults[idx]);
        }
      });
    });
    highlight();
  }

  function rowHTML(item, idx, isRecent) {
    const isAdd = item.action === 'add-to-cart' || (currentContext === 'pos' && (item.type === 'service' || item.type === 'product'));
    const price = item.price ? `<span class="text-success fw-medium">$${item.price}</span>` : '';
    const sku = item.sku ? `<span class="text-muted ms-1" style="font-size:10px">(${item.sku})</span>` : '';
    const lowStock = item.stock !== undefined && item.stock < 10 ? `<span class="badge bg-danger-subtle text-danger ms-1" style="font-size:9px">剩${item.stock}</span>` : '';
    const recent = isRecent ? `<span class="badge bg-warning-subtle text-warning ms-2" style="font-size:9px">最近</span>` : '';
    const sec = isAdd
      ? `<button type="button" class="cmd-add-btn btn btn-sm btn-success py-0 px-2 ms-2" style="font-size:12px">加入購物車</button>`
      : `<span class="ms-auto text-muted">${item.icon || ''}</span>`;

    return `<div class="cmd-row d-flex align-items-center gap-2 px-3 py-2 rounded-2 cursor-pointer ${idx === activeIndex ? 'bg-light' : ''}" data-idx="${idx}" style="cursor:pointer">
      <div style="width:28px" class="text-center fs-5">${item.icon || '🔗'}</div>
      <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center">
          <span class="fw-medium text-dark">${item.label}</span>
          ${recent}${sku}${lowStock}
        </div>
        ${item.category ? `<div class="text-muted" style="font-size:11px;line-height:1">${item.category}</div>` : ''}
      </div>
      <div class="d-flex align-items-center small">${price}${sec}</div>
    </div>`;
  }

  function highlight() {
    if (!resultsEl) return;
    resultsEl.querySelectorAll('.cmd-row').forEach(r => {
      const i = parseInt(r.dataset.idx);
      r.classList.toggle('bg-light', i === activeIndex);
      r.classList.toggle('border', i === activeIndex);
      r.classList.toggle('border-success-subtle', i === activeIndex);
    });
  }

  function doExecute(item) {
    if (!item) return;
    recordUsage(item.id);
    hide();

    if (item.action === 'add-to-cart' || (currentContext === 'pos' && (item.type === 'service' || item.type === 'product'))) {
      doAddToCart(item);
      return;
    }
    if (item.url) location.href = item.url;
    else if (typeof item.fn === 'function') item.fn();
  }

  function doAddToCart(item) {
    recordUsage(item.id);
    hide();

    const POS = window.SalonEase && window.SalonEase.POS;
    if (POS && typeof POS.addToCart === 'function') {
      POS.addToCart({ id: item.id.replace(/^(svc|prod)-/, ''), name: item.label, price: item.price, type: item.type });
      (window.SalonEase.toast || alert)(`已加入購物車：${item.label}`);
      return;
    }

    // POS 尚未完整實作時的友好提示
    if (confirm(`「${item.label}」已記錄。\nPOS 完整購物車功能即將完成。\n\n立即前往 POS 頁？`)) {
      location.href = '/pos.php';
    }
  }

  function show() {
    if (bsModal) { bsModal.show(); return; }

    currentContext = getContext();

    // 建立 Bootstrap Modal DOM（完全原生）
    const html = `
      <div class="modal fade" id="cmdPaletteModal" tabindex="-1" aria-hidden="true" style="z-index:1085">
        <div class="modal-dialog modal-dialog-centered" style="max-width:580px">
          <div class="modal-content shadow">
            <div class="modal-header py-2 px-3 border-bottom-0 bg-light">
              <div class="input-group input-group-sm w-100">
                <span class="input-group-text bg-transparent border-0 pe-1">⌘</span>
                <input type="text" id="cmd-input" class="form-control border-0 shadow-none px-0" placeholder="搜尋功能、服務或產品..." style="font-size:15px">
              </div>
            </div>
            <div id="cmd-results" class="modal-body p-0" style="max-height:380px;overflow:auto"></div>
            <div class="modal-footer py-2 px-3 small text-muted border-top-0 d-flex justify-content-between">
              <div>最近使用會自動提升排序</div>
              <div>Ctrl+K 再次開啟</div>
            </div>
          </div>
        </div>
      </div>`;

    document.getElementById('cmdPaletteModal')?.remove();
    document.body.insertAdjacentHTML('beforeend', html);

    modalEl = document.getElementById('cmdPaletteModal');
    inputEl = modalEl.querySelector('#cmd-input');
    resultsEl = modalEl.querySelector('#cmd-results');

    bsModal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: false });

    // 顯示時渲染
    modalEl.addEventListener('shown.bs.modal', () => {
      updateResults('');
      inputEl.focus();
      inputEl.select();
    });

    // 即時搜尋
    inputEl.addEventListener('input', e => updateResults(e.target.value));

    // 鍵盤導航
    inputEl.addEventListener('keydown', e => {
      if (e.key === 'Escape') { e.preventDefault(); hide(); }
      if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, currentResults.length - 1); highlight(); scrollActive(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlight(); scrollActive(); }
      if (e.key === 'Enter') { e.preventDefault(); if (currentResults[activeIndex]) doExecute(currentResults[activeIndex]); }
    });

    // 關閉後清理
    modalEl.addEventListener('hidden.bs.modal', () => {
      const val = inputEl.value.trim();
      if (val) recordSearch(val);
      cleanup();
    });

    bsModal.show();
  }

  function scrollActive() {
    if (!resultsEl) return;
    const el = resultsEl.querySelector(`.cmd-row[data-idx="${activeIndex}"]`);
    if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function hide() {
    if (bsModal) bsModal.hide();
  }

  function cleanup() {
    modalEl?.remove();
    modalEl = null; bsModal = null; inputEl = null; resultsEl = null; currentResults = []; activeIndex = 0;
  }

  // 公開
  window.showCommandPalette = show;
  window.hideCommandPalette = hide;

  window.SalonEase = window.SalonEase || {};
  window.SalonEase.CommandPalette = { show, hide, getContext, recordUsage, _debug: () => ({ recent: loadRecent(), ctx: getContext() }) };
  window.SalonEase.Hotkeys = window.SalonEase.Hotkeys || {};
  window.SalonEase.Hotkeys.showCommandPalette = show;

  console.log('%c[SalonEase] 命令面板 v3（Bootstrap Modal + POS 加購物車）已就緒', 'color:#8FA68F;font-size:9px');
})();