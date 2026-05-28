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

  // 動態 POS 項目快取（避免重複 fetch）
  let posDynamicCache = { query: '', items: [], ts: 0 };

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
    return arr;
  }

  // 真正從 API 動態取得 POS 可銷售項目（服務 + 產品）
  async function fetchPosItems(query) {
    if (!query || query.trim().length < 1) return [];

    const q = query.trim();
    const cacheKey = q.toLowerCase();

    // 簡單快取（5 秒）
    if (posDynamicCache.query === cacheKey && (Date.now() - posDynamicCache.ts) < 5000) {
      return posDynamicCache.items;
    }

    try {
      const [svcRes, prodRes] = await Promise.all([
        window.SalonEase.fetch(`/api/services.php?action=list&search=${encodeURIComponent(q)}&status=1`),
        window.SalonEase.fetch(`/api/products.php?action=list&search=${encodeURIComponent(q)}&status=1`)
      ]);

      const services = (svcRes.data || []).map(s => ({
        id: `svc-${s.id}`,
        ref_id: s.id,
        type: 'service',
        label: s.name,
        price: parseFloat(s.price) || 0,
        keywords: s.category || '',
        icon: '💆',
        category: s.category,
        duration_min: s.duration_min,
        action: 'add-to-cart'
      }));

      const products = (prodRes.data || []).map(p => ({
        id: `prod-${p.id}`,
        ref_id: p.id,
        type: 'product',
        label: p.name,
        price: parseFloat(p.price) || 0,
        keywords: `${p.sku || ''} ${p.category || ''}`,
        icon: '🧴',
        sku: p.sku,
        stock: p.stock_qty,
        effective_low_stock_threshold: p.effective_low_stock_threshold,
        action: 'add-to-cart'
      }));

      const combined = [...services, ...products];

      // 更新快取
      posDynamicCache = { query: cacheKey, items: combined, ts: Date.now() };
      return combined;

    } catch (err) {
      console.warn('[CommandPalette] POS 動態搜尋失敗', err);
      return [];
    }
  }

  // 取得目前 POS 已選客戶的可用套票（優先使用 POS 已載入的資料，避免多餘 API）
  function getCurrentPosCustomerPackages() {
    const POS = window.SalonEase && window.SalonEase.POS;
    if (!POS) return [];

    const customer = POS.getCurrentCustomer ? POS.getCurrentCustomer() : null;
    if (!customer) return [];

    const pkgs = POS.getCustomerPackages ? POS.getCustomerPackages() : [];
    return pkgs.map(p => ({
      id: `pkg-${p.id}`,
      ref_id: p.id,
      type: 'package_redemption',
      label: p.name,
      price: 0,                    // 套票扣減不計費
      keywords: '套票 扣減 ' + p.name,
      icon: '🎫',
      remaining: p.remaining_sessions,
      expiry: p.expiry_date,
      action: 'add-package'
    }));
  }

  // 取得本次 POS 開單期間最近加入的項目（快速重複銷售）
  function getPosRecentSessionItems() {
    const POS = window.SalonEase && window.SalonEase.POS;
    if (!POS || !POS.getRecentSessionItems) return [];
    return POS.getRecentSessionItems();
  }

  // 動態搜尋客戶（POS 頁專用，可直接切換客戶）
  async function fetchPosCustomers(query) {
    if (!query || query.trim().length < 1) return [];
    try {
      const res = await window.SalonEase.fetch(
        `/api/customers.php?action=list&search=${encodeURIComponent(query)}&limit=6`
      );
      return (res.data || []).map(c => ({
        id: `customer-${c.id}`,
        type: 'customer',
        label: c.name,
        sublabel: c.phone || '',
        keywords: `${c.name} ${c.phone || ''}`,
        icon: '👤',
        action: 'switch-customer',
        customerData: c
      }));
    } catch (err) {
      console.warn('[CommandPalette] 客戶搜尋失敗', err);
      return [];
    }
  }

  async function updateResults(q = '') {
    if (!resultsEl) return;
    const ctx = getContext();
    currentContext = ctx;

    let baseItems = getItems(ctx);
    let dynamicItems = [];

    // POS 情境：有查詢字串時 → 真正呼叫 API 動態搜尋（服務/產品 + 客戶）
    if (ctx === 'pos' && q.trim().length >= 1) {
      // 先顯示 loading
      renderLoadingState(q);

      const [servicesProducts, customers] = await Promise.all([
        fetchPosItems(q),
        fetchPosCustomers(q)
      ]);

      dynamicItems = [...servicesProducts, ...customers];

      // 額外加入：已選客戶的套票扣減（若有符合搜尋字串）
      const customerPkgs = getCurrentPosCustomerPackages();
      if (customerPkgs.length > 0) {
        const qLower = q.toLowerCase();
        const matchedPkgs = customerPkgs.filter(pkg =>
          pkg.label.toLowerCase().includes(qLower) ||
          (pkg.keywords || '').toLowerCase().includes(qLower)
        );
        dynamicItems = [...dynamicItems, ...matchedPkgs];
      }
    } else if (ctx === 'pos' && q.trim().length === 0) {
      // POS 頁空白查詢時，優先顯示：
      // 1. 本單最近加入的項目（最強大快速重複功能）
      // 2. 目前客戶可用的套票
      const recent = getPosRecentSessionItems();
      const customerPkgs = getCurrentPosCustomerPackages();

      dynamicItems = [...recent];
      // 套票放在最近之後（如果有重疊就讓最近優先）
      customerPkgs.forEach(pkg => {
        if (!dynamicItems.some(x => x.ref_id == pkg.ref_id && x.type === 'package_redemption')) {
          dynamicItems.push(pkg);
        }
      });
    }

    let allItems = [...baseItems, ...dynamicItems];

    // POS 頁空白查詢時，把「本單最近加入」排在最前面（高分加權）
    if (ctx === 'pos' && !q.trim()) {
      const posRecent = getPosRecentSessionItems();
      if (posRecent.length) {
        const recentBoosted = posRecent.map(r => ({
          ...r,
          id: `recent-${r.type}-${r.ref_id}`,
          label: r.name,
          price: r.unit_price,
          action: 'add-to-cart',
          keywords: r.name || '',
          _isRecentSession: true
        }));
        // 放在最前面
        allItems = [...recentBoosted, ...allItems];
      }
    }

    let scored = allItems.map(it => ({ ...it, score: calculateScore(q, it, ctx) }));
    scored.sort((a, b) => {
      if (b.score !== a.score) return b.score - a.score;
      const ra = loadRecent().usage.indexOf(a.id), rb = loadRecent().usage.indexOf(b.id);
      return (ra === -1 ? 999 : ra) - (rb === -1 ? 999 : rb);
    });
    if (q.trim()) scored = scored.filter(s => s.score > 16);
    currentResults = scored.slice(0, 18);
    activeIndex = 0;
    renderResults(q);
  }

  function renderLoadingState(q) {
    if (!resultsEl) return;
    const ctxLabel = getContextLabel(currentContext);
    resultsEl.innerHTML = `
      <div class="px-3 py-2 small text-muted d-flex justify-content-between align-items-center border-bottom">
        <div>目前在 <span class="fw-medium text-dark">${ctxLabel}</span></div>
      </div>
      <div class="p-4 text-center text-muted">
        <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
        正在搜尋「${q}」的服務、產品與客戶...
      </div>
    `;
  }

  function renderResults(q) {
    if (!resultsEl) return;
    const ctxLabel = getContextLabel(currentContext);
    let html = `<div class="px-3 py-2 small text-muted d-flex justify-content-between align-items-center border-bottom">
      <div>目前在 <span class="fw-medium text-dark">${ctxLabel}</span></div>
      <div class="text-muted" style="font-size:10px">↑↓ 選擇 · Enter 執行 · Esc 關閉</div>
    </div>`;

    if (!currentResults.length) {
      let emptyMsg = `找不到符合「${q}」的項目`;
      if (currentContext === 'pos') {
        emptyMsg = `找不到符合「${q}」的服務/產品<br><span class="small">可嘗試「面部」「精華」等關鍵字</span>`;
      }
      html += `<div class="p-4 text-center text-muted">${emptyMsg}</div>`;
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

    let section = q.trim() ? '搜尋結果' : '所有功能';
    if (currentContext === 'pos') {
      if (q.trim()) {
        section = '服務 / 產品 / 客戶 / 套票';
      } else {
        const hasRecent = getPosRecentSessionItems().length > 0;
        section = hasRecent 
          ? '本單最近加入（快速重複） + 客戶可用套票'
          : '快速功能 + 客戶可用套票（可直接扣減）';
      }
    }
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
        const item = currentResults[idx];
        if (e.target.closest('.cmd-add-btn')) {
          if (item.action === 'switch-customer' && item.customerData) {
            doSwitchCustomer(item.customerData);
          } else if (item.type === 'package_redemption' || item.action === 'add-package') {
            doAddPackageRedemption(item);
          } else {
            doAddToCart(item);
          }
        } else {
          doExecute(item);
        }
      });
    });
    highlight();
  }

  function rowHTML(item, idx, isRecent) {
    const isNormalAdd = item.action === 'add-to-cart' || (currentContext === 'pos' && (item.type === 'service' || item.type === 'product'));
    const isPackage = item.type === 'package_redemption' || item.action === 'add-package';

    const price = item.price ? `<span class="text-success fw-medium">$${item.price}</span>` : '';
    const sku = item.sku ? `<span class="text-muted ms-1" style="font-size:10px">(${item.sku})</span>` : '';
    const lowStock = item.stock !== undefined && item.stock < 10 ? `<span class="badge bg-danger-subtle text-danger ms-1" style="font-size:9px">剩${item.stock}</span>` : '';
    const recent = isRecent ? `<span class="badge bg-warning-subtle text-warning ms-2" style="font-size:9px">最近</span>` : '';

    let sec;
    if (isPackage) {
      const remain = item.remaining ? `剩 ${item.remaining} 次` : '';
      sec = `<button type="button" class="cmd-add-btn btn btn-sm py-0 px-2 ms-2" style="font-size:12px; background:#c084fc; color:white; border:none;">扣減套票</button>`;
    } else if (item.action === 'switch-customer') {
      sec = `<button type="button" class="cmd-add-btn btn btn-sm btn-outline-primary py-0 px-2 ms-2" style="font-size:12px">切換客戶</button>`;
    } else if (isNormalAdd) {
      sec = `<button type="button" class="cmd-add-btn btn btn-sm btn-success py-0 px-2 ms-2" style="font-size:12px">加入購物車</button>`;
    } else {
      sec = `<span class="ms-auto text-muted">${item.icon || ''}</span>`;
    }

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

    if (item.action === 'add-package' || item.type === 'package_redemption') {
      doAddPackageRedemption(item);
      return;
    }

    if (item.action === 'switch-customer' && item.customerData) {
      doSwitchCustomer(item.customerData);
      return;
    }

    if (item.url) location.href = item.url;
    else if (typeof item.fn === 'function') item.fn();
  }

  function doAddToCart(item) {
    recordUsage(item.id);
    hide();

    // 最高優先：直接呼叫 POS 頁已暴露的 window.addToCart（最可靠，即時生效）
    if (window.addToCart) {
      const type = item.type || 'service';
      const refId = (item.id || '').replace(/^(svc|prod)-/, '');
      const name = item.label || item.name;
      const price = item.price || 0;

      window.addToCart(type, refId, name, price);

      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast(`已加入：${name}`, 'success', 1600);
      }
      return;
    }

    // 備援：使用命名空間（pos.js 已暴露）
    const POS = window.SalonEase && window.SalonEase.POS;
    if (POS && typeof POS.addToCart === 'function') {
      POS.addToCart({ id: item.id.replace(/^(svc|prod)-/, ''), name: item.label, price: item.price, type: item.type });
      return;
    }

    // 最後 fallback
    if (confirm(`「${item.label}」已記錄。\n請前往 POS 頁使用完整購物車。`)) {
      location.href = '/pos.php';
    }
  }

  // 專門處理套票扣減（從命令面板呼叫）
  function doAddPackageRedemption(item) {
    recordUsage(item.id);
    // 已經 hide() 了

    const POS = window.SalonEase && window.SalonEase.POS;
    if (POS && typeof POS.addPackageRedemption === 'function') {
      const success = POS.addPackageRedemption(item);
      if (success) {
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast(`已加入套票扣減：${item.label}`, 'success', 1800);
        }
        return;
      }
    }

    // 如果 POS 沒有暴露或客戶未選，給提示
    if (confirm(`「${item.label}」套票扣減需要先在 POS 選擇客戶。\n\n立即前往 POS 頁？`)) {
      location.href = '/pos.php';
    }
  }

  // 從命令面板切換客戶
  async function doSwitchCustomer(customerData) {
    const POS = window.SalonEase && window.SalonEase.POS;
    if (POS && typeof POS.setCurrentCustomer === 'function') {
      await POS.setCurrentCustomer(customerData);
      hide();

      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast(`已切換客戶：${customerData.name}`, 'success', 1800);
      }
      return;
    }

    // fallback
    hide();
    if (confirm(`已找到客戶「${customerData.name}」。\n請前往 POS 頁完成切換。`)) {
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
      updateResults('');   // async ok
      inputEl.focus();
      inputEl.select();
    });

    // 即時搜尋（POS 頁動態搜尋時自動 debounce）
    let searchTimer = null;
    inputEl.addEventListener('input', e => {
      const val = e.target.value;
      clearTimeout(searchTimer);

      // POS 頁輸入較長時給予輕微 debounce，避免狂打 API
      const delay = (getContext() === 'pos' && val.trim().length >= 2) ? 180 : 0;

      searchTimer = setTimeout(() => {
        updateResults(val);   // async 自動處理
      }, delay);
    });

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