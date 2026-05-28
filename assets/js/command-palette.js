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

  // POS 專屬靜態快速動作
  const POS_QUICK_ACTIONS = [
    { id: 'pos-quick-create-customer', label: '快速新增客戶', action: 'quick-create-customer', icon: '➕', keywords: '新增 建立 客戶 新客戶', contexts: ['pos'] },
    { id: 'pos-quick-checkout', label: '快速結帳', action: 'quick-checkout', icon: '💳', keywords: '結帳 付款 收銀 完成交易', contexts: ['pos'] },
    { id: 'pos-save-cart-template', label: '儲存目前購物車為常用組合', action: 'save-cart-template', icon: '💾', keywords: '儲存 常用組合 模板 套餐 療程組合', contexts: ['pos'] },
    { id: 'pos-search-history', label: '搜尋歷史銷售 / 重複組合', action: 'search-sales-history', icon: '📜', keywords: '歷史 銷售 記錄 重複 再買 舊單', contexts: ['pos'] },
  ];

  // POS 快速折扣方案（動態產生，支援百分比與固定金額）
  function getPosDiscountActions() {
    const POS = window.SalonEase && window.SalonEase.POS;
    const subtotal = POS && POS.getCurrentSubtotal ? POS.getCurrentSubtotal() : 0;

    const actions = [
      { id: 'disc-9fold', label: '9 折優惠', discountType: 'percent', value: 0.1, keywords: '9折 九折 會員 優惠', icon: '🏷️' },
      { id: 'disc-95fold', label: '95 折優惠', discountType: 'percent', value: 0.05, keywords: '95折 九五折 會員', icon: '🏷️' },
      { id: 'disc-8fold', label: '員工 8 折', discountType: 'percent', value: 0.2, keywords: '員工 8折 八折 內部', icon: '👔' },
      { id: 'disc-100', label: '減 $100', discountType: 'fixed', value: 100, keywords: '減100 扣100', icon: '💰' },
      { id: 'disc-200', label: '減 $200', discountType: 'fixed', value: 200, keywords: '減200 扣200', icon: '💰' },
      { id: 'disc-50', label: '減 $50', discountType: 'fixed', value: 50, keywords: '減50 扣50', icon: '💰' },
    ];

    return actions.map(a => {
      let displayLabel = a.label;
      if (a.discountType === 'percent' && subtotal > 0) {
        const discountAmount = Math.round(subtotal * a.value);
        displayLabel = `${a.label}（減 HK$ ${discountAmount}）`;
      }
      return {
        ...a,
        label: displayLabel,
        action: 'apply-discount',
        keywords: a.keywords,
        icon: a.icon,
        discountType: a.discountType,
        value: a.value
      };
    });
  }

  // 動態 POS 項目快取（避免重複 fetch）
  let posDynamicCache = { query: '', items: [], ts: 0 };

  let modalEl = null;
  let bsModal = null;
  let inputEl = null;
  let resultsEl = null;
  let activeIndex = 0;
  let currentResults = [];
  let currentContext = 'general';
  let createCustomerMode = false;   // POS 快速新增客戶模式

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

    // POS 快速新增客戶永遠給較高分
    if (ctx === 'pos' && item.id === 'pos-quick-create-customer') {
      score += 35;
    }

    // 折扣相關動作在 POS 頁給額外加分，尤其是輸入「折」「折扣」時
    if (ctx === 'pos' && item.action === 'apply-discount') {
      score += 22;
      const qLower = (q || '').toLowerCase();
      if (qLower.includes('折') || qLower.includes('折扣') || qLower.includes('優惠') || qLower.includes('減')) {
        score += 25;
      }
    }

    const recent = loadRecent().usage;
    const pos = recent.indexOf(item.id);
    if (pos !== -1) score += Math.max(32 - pos * 3, 6);
    return Math.round(Math.min(148, score));
  }

  function getItems(ctx) {
    let arr = [...BASE_ACTIONS];

    if (ctx === 'pos') {
      arr = [...arr, ...POS_QUICK_ACTIONS, ...getPosDiscountActions()];
    }

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

  // 取得目前員工的常用購物車組合（POS 專用）
  async function fetchCartTemplates(query = '') {
    try {
      const res = await window.SalonEase.fetch('/api/cart_templates.php?action=list');
      let templates = res.data || [];

      if (query) {
        const q = query.toLowerCase();
        templates = templates.filter(t =>
          t.name.toLowerCase().includes(q)
        );
      }

      return templates.map(t => ({
        id: `template-${t.id}`,
        type: 'cart_template',
        label: t.name,
        keywords: t.name + ' 組合 套餐 療程',
        icon: '📦',
        action: 'load-cart-template',
        templateId: t.id,
        itemCount: t.items ? t.items.length : 0
      }));
    } catch (err) {
      console.warn('[CommandPalette] 載入常用組合失敗', err);
      return [];
    }
  }

  // 智能推薦：根據目前選中的客戶，推薦最相關的組合（進階私人銷售顧問版）
  async function fetchSmartRecommendationsForCurrentCustomer() {
    const POS = window.SalonEase && window.SalonEase.POS;
    const customer = POS && POS.getCurrentCustomer ? POS.getCurrentCustomer() : null;

    if (!customer || !customer.id) return [];

    const recommendations = [];
    const itemFrequency = {};
    const lastPurchaseDate = {};
    const purchaseIntervals = {}; // 記錄每次購買的間隔天數，用來算平均
    const allItemPrices = [];      // 用來計算「客戶個人最常接受的價格區間」
    const freqItemPrice = {};      // 記錄每個高頻項目的平均價格

    try {
      const salesRes = await window.SalonEase.fetch(`/api/sales.php?action=list&customer_id=${customer.id}&limit=15`);
      const recentSales = salesRes.data || [];

      // 計算客戶最近一次購買距今天數 + 平均消費金額（用來做價格區間建議）
      let daysSinceLastVisit = 999;
      let avgSpend = 0;
      if (recentSales.length > 0) {
        const lastVisit = new Date(recentSales[0].sale_date);
        daysSinceLastVisit = Math.floor((new Date() - lastVisit) / (1000 * 60 * 60 * 24));

        const totalSpend = recentSales.reduce((sum, s) => sum + parseFloat(s.total || 0), 0);
        avgSpend = totalSpend / recentSales.length;
      }

      const recentSalesWithItems = await Promise.all(
        recentSales.slice(0, 8).map(async (sale) => {
          try {
            const itemsRes = await window.SalonEase.fetch(`/api/sales.php?action=get_items&sale_id=${sale.id}`);
            return { ...sale, items: itemsRes.data || [] };
          } catch {
            return { ...sale, items: [] };
          }
        })
      );

      // 排序銷售由舊到新，方便計算購買間隔
      const sortedSales = [...recentSalesWithItems].sort((a, b) => a.sale_date.localeCompare(b.sale_date));

      // 分析頻率 + 購買間隔 + 關聯 + 個人價格偏好
      const pairFrequency = {};

      sortedSales.forEach((sale, index) => {
        const saleDate = sale.sale_date;
        const itemsInSale = sale.items || [];

        itemsInSale.forEach(item => {
          const key = `${item.item_type}:${item.ref_id}`;
          itemFrequency[key] = (itemFrequency[key] || 0) + 1;

          const itemPrice = parseFloat(item.unit_price || item.price || 0);
          if (itemPrice > 0) {
            allItemPrices.push(itemPrice);
            if (!freqItemPrice[key]) freqItemPrice[key] = [];
            freqItemPrice[key].push(itemPrice);
          }

          // 記錄購買日期歷史
          if (!lastPurchaseDate[key]) lastPurchaseDate[key] = [];
          lastPurchaseDate[key].push(saleDate);

          // 計算購買間隔
          if (index > 0) {
            const prevDate = new Date(sortedSales[index - 1].sale_date);
            const currDate = new Date(saleDate);
            const diffDays = Math.floor((currDate - prevDate) / (1000 * 60 * 60 * 24));
            if (!purchaseIntervals[key]) purchaseIntervals[key] = [];
            purchaseIntervals[key].push(diffDays);
          }
        });

        // 關聯分析
        for (let i = 0; i < itemsInSale.length; i++) {
          for (let j = i + 1; j < itemsInSale.length; j++) {
            const a = itemsInSale[i];
            const b = itemsInSale[j];
            const keyA = `${a.item_type}:${a.ref_id}`;
            const keyB = `${b.item_type}:${b.ref_id}`;
            const pairKey = keyA < keyB ? `${keyA}|${keyB}` : `${keyB}|${keyA}`;
            pairFrequency[pairKey] = (pairFrequency[pairKey] || 0) + 1;
          }
        }
      });

      // === 根據客戶歷史計算「個人最可能接受的價格區間」+ 最適合的組合核心 ===
      let personalPriceBand = null;
      let optimalComboCore = [];
      if (allItemPrices.length > 0) {
        const sortedPrices = [...allItemPrices].sort((a, b) => a - b);
        const p25 = sortedPrices[Math.floor(sortedPrices.length * 0.25)] || sortedPrices[0];
        const p75 = sortedPrices[Math.floor(sortedPrices.length * 0.75)] || sortedPrices[sortedPrices.length - 1];
        const median = sortedPrices[Math.floor(sortedPrices.length * 0.5)] || sortedPrices[0];

        personalPriceBand = {
          min: Math.round(p25 * 0.9),
          max: Math.round(p75 * 1.15),
          median: Math.round(median),
          avgItem: Math.round(allItemPrices.reduce((a, b) => a + b, 0) / allItemPrices.length)
        };

        // 從高頻項目中挑選落在「個人舒適價格區間」內的 1~2 個，作為「最適合此客戶的組合核心」
        const freqKeys = Object.entries(itemFrequency)
          .sort((a, b) => b[1] - a[1])
          .slice(0, 5)
          .map(([k]) => k);

        for (const key of freqKeys) {
          const prices = freqItemPrice[key] || [];
          if (prices.length === 0) continue;
          const avgP = prices.reduce((a, b) => a + b, 0) / prices.length;
          if (avgP >= personalPriceBand.min * 0.85 && avgP <= personalPriceBand.max * 1.05) {
            let name = '項目';
            for (const sale of recentSalesWithItems) {
              const found = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === key);
              if (found) { name = found.name; break; }
            }
            optimalComboCore.push({ key, name, avgPrice: Math.round(avgP) });
            if (optimalComboCore.length >= 2) break;
          }
        }
      }

      const today = new Date();

      // === 進階：購買間隔 + 缺口建議（私人銷售顧問核心）===
      Object.entries(purchaseIntervals).forEach(([key, intervals]) => {
        if (intervals.length < 2) return; // 至少要有兩次購買才有意義

        const avgInterval = intervals.reduce((a, b) => a + b, 0) / intervals.length;
        const lastDateStr = lastPurchaseDate[key][lastPurchaseDate[key].length - 1];
        const lastDate = new Date(lastDateStr);
        const daysSinceLast = Math.floor((today - lastDate) / (1000 * 60 * 60 * 24));

        const [type, refId] = key.split(':');

        // 找出項目名稱
        let name = '項目';
        for (const sale of recentSalesWithItems) {
          const found = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === key);
          if (found) { name = found.name; break; }
        }

        // 如果已經超過平均間隔的 80%，就強力建議補貨
        if (daysSinceLast >= avgInterval * 0.8) {
          const urgency = daysSinceLast > avgInterval ? '強烈建議' : '建議';
          recommendations.push({
            id: `gap-${key}`,
            type: 'gap_suggestion',
            label: `${urgency}補充：${name}`,
            sublabel: `平均 ${Math.round(avgInterval)} 天買一次，上次已 ${daysSinceLast} 天`,
            suggestedReason: `根據此客戶平均購買間隔約 ${Math.round(avgInterval)} 天，上次購買已 ${daysSinceLast} 天，建議及時補充`,
            keywords: '補貨 缺貨 該買 間隔',
            icon: '⏰',
            action: 'quick-add-recommendation',
            priority: 20,
            isGap: true,
            suggestedItems: [{ type, ref_id: parseInt(refId), name }]
          });
        }
      });

      // 其餘推薦（頻率、關聯、歷史、模板）維持之前邏輯...
      const sortedFrequent = Object.entries(itemFrequency)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3);

      sortedFrequent.forEach(([key, count], idx) => {
        const [type, refId] = key.split(':');
        let name = '常用項目';
        for (const sale of recentSalesWithItems) {
          const found = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === key);
          if (found) { name = found.name; break; }
        }

        recommendations.push({
          id: `freq-${key}`,
          type: 'frequent_item',
          label: `最常買：${name}`,
          sublabel: `已買 ${count} 次`,
          keywords: '最常 常用 頻繁',
          icon: '🔥',
          action: 'load-history-sale',
          priority: 14 + (3 - idx),
          isFrequent: true
        });
      });

      // 關聯推薦
      const sortedPairs = Object.entries(pairFrequency)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 2);

      sortedPairs.forEach(([pairKey, count], idx) => {
        const [keyA, keyB] = pairKey.split('|');
        let nameA = '項目A', nameB = '項目B';
        for (const sale of recentSalesWithItems) {
          const foundA = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === keyA);
          const foundB = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === keyB);
          if (foundA) nameA = foundA.name;
          if (foundB) nameB = foundB.name;
        }

        recommendations.push({
          id: `pair-${pairKey}`,
          type: 'frequent_pair',
          label: `最常一起買：${nameA} + ${nameB}`,
          sublabel: `一起買 ${count} 次`,
          keywords: '一起 搭配 組合',
          icon: '🔗',
          action: 'load-history-sale',
          priority: 17 + (2 - idx),
          isPair: true
        });
      });

      // 簡單交叉銷售
      // 更進階：自動產生「這個客戶最可能接受的 Top 3 搭配組合」
      // 做法：找出此客戶最常買的前幾個項目，再為每個找出其最強搭配，形成 bundle
      const top3Frequent = Object.entries(itemFrequency)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3);

      top3Frequent.forEach(([mainKey, freq], idx) => {
        let bestCompanion = null;
        let bestCompanionCount = 0;
        let companionName = '';

        Object.entries(pairFrequency).forEach(([pairKey, count]) => {
          if (pairKey.includes(mainKey)) {
            const [k1, k2] = pairKey.split('|');
            const companionKey = k1 === mainKey ? k2 : k1;
            if (count > bestCompanionCount) {
              bestCompanionCount = count;
              bestCompanion = companionKey;
            }
          }
        });

        if (bestCompanion) {
          let mainName = '項目';
          for (const sale of recentSalesWithItems) {
            const found = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === mainKey);
            if (found) { mainName = found.name; break; }
          }
          for (const sale of recentSalesWithItems) {
            const found = (sale.items || []).find(i => `${i.item_type}:${i.ref_id}` === bestCompanion);
            if (found) { companionName = found.name; break; }
          }

          const matchRate = recentSales.length > 0 
            ? Math.round((bestCompanionCount / recentSales.length) * 100) 
            : 0;

          recommendations.push({
            id: `bundle-${mainKey}-${bestCompanion}`,
            type: 'smart_bundle',
            label: `推薦組合：${mainName} + ${companionName}`,
            sublabel: `此客戶歷史匹配度 ${matchRate}%`,
            suggestedReason: `根據此客戶購買歷史，買「${mainName}」後有很高機率會一起加購「${companionName}」`,
            keywords: '推薦組合 搭配 智能',
            icon: '🎁',
            action: 'quick-add-recommendation',
            priority: 16 + (3 - idx),
            suggestedItems: [
              { type: mainKey.split(':')[0], ref_id: parseInt(mainKey.split(':')[1]), name: mainName },
              { type: bestCompanion.split(':')[0], ref_id: parseInt(bestCompanion.split(':')[1]), name: companionName }
            ]
          });
        }
      });

      // 歷史 + 模板作為備選
      recentSales.slice(0, 2).forEach(sale => {
        recommendations.push({
          id: `history-${sale.id}`,
          type: 'history_sale',
          label: `歷史購買 #${sale.id}`,
          sublabel: `${sale.sale_date}`,
          keywords: '歷史',
          icon: '🕒',
          action: 'load-history-sale',
          saleId: sale.id,
          priority: 7
        });
      });

      const templatesRes = await window.SalonEase.fetch('/api/cart_templates.php?action=list');
      const templates = templatesRes.data || [];
      templates.slice(0, 2).forEach(t => {
        recommendations.push({
          id: `template-${t.id}`,
          type: 'cart_template',
          label: `常用：${t.name}`,
          sublabel: `${t.items?.length || 0} 項`,
          keywords: '常用 套餐',
          icon: '⭐',
          action: 'load-cart-template',
          templateId: t.id,
          priority: 6
        });
      });

      // === 根據客戶真實歷史產生「個人最可能接受的價格區間 + 最適合的服務/產品組合」===
      if (personalPriceBand && personalPriceBand.median > 0) {
        const band = personalPriceBand;
        const bandLabel = `客製價位（HK$ ${band.min}–${band.max}）`;
        let comboText = '您最常接受的價位組合';
        let reasonText = `根據您過去 ${recentSales.length} 次消費，個人舒適價位大約落在 HK$ ${band.median} 左右，我們為您精選最匹配的項目。`;

        const suggestedItemsForTier = [];
        if (optimalComboCore.length > 0) {
          comboText = optimalComboCore.map(c => c.name).join(' + ');
          // 盡量把真實歷史高頻項目帶到 suggestedItems，讓一鍵加入真正有效
          optimalComboCore.forEach(c => {
            const [t, rid] = c.key.split(':');
            suggestedItemsForTier.push({
              type: t,
              ref_id: parseInt(rid),
              name: c.name,
              unit_price: c.avgPrice
            });
          });
          reasonText = `根據您的歷史，這個價位區間（中位 ${band.median}）的 ${comboText} 是您最常購買且最滿意的搭配，CP 值最高。`;
        }

        recommendations.push({
          id: `price-tier-${customer.id}`,
          type: 'price_suggestion',
          label: bandLabel,
          sublabel: comboText,
          suggestedReason: reasonText,
          keywords: '價格 升級 消費 客製 最適合',
          icon: '💎',
          action: 'quick-add-recommendation',
          priority: 18,
          suggestedItems: suggestedItemsForTier,   // 現在有真實項目了！
          priceBand: band
        });
      } else if (avgSpend > 0) {
        // 後備：如果歷史項目太少，用總消費簡單估計（保留相容）
        const low = Math.round(avgSpend * 0.75);
        recommendations.push({
          id: `price-tier-${customer.id}`,
          type: 'price_suggestion',
          label: `參考價位（約 HK$ ${low}–${Math.round(avgSpend * 1.4)}）`,
          sublabel: '根據您平均消費',
          suggestedReason: '根據您過往消費金額，我們建議這個區間的搭配最合您心水',
          keywords: '價格 升級',
          icon: '💎',
          action: 'quick-add-recommendation',
          priority: 12
        });
      }

      // 季節性建議（獨立加入）
      const month = new Date().getMonth() + 1;
      let seasonalLabel = '';
      let seasonalReason = '';
      if (month >= 6 && month <= 8) {
        seasonalLabel = '防曬 / 美白 / 淨化';
        seasonalReason = '目前為夏季，紫外線強烈，建議主動推薦防曬、美白及淨化護理';
      } else if (month >= 12 || month <= 2) {
        seasonalLabel = '保濕 / 修護 / 抗老';
        seasonalReason = '目前為冬季，肌膚容易乾燥，適合推薦深度保濕與修護系列';
      }

      if (seasonalLabel) {
        recommendations.push({
          id: `seasonal-${month}`,
          type: 'seasonal_suggestion',
          label: `季節推薦：${seasonalLabel}`,
          sublabel: `現在很適合推廣`,
          suggestedReason: seasonalReason,
          keywords: '季節 夏天 冬天 推薦',
          icon: '🌸',
          action: 'quick-add-recommendation',
          priority: 13
        });
      }

      // === 新：產生 Compound 推薦（缺口 + 搭配）===
      // 找出一個 gap 項目，再找一個常與它搭配的項目，形成複合建議
      const gapRecs = recommendations.filter(r => r.isGap);
      const frequentPairs = recommendations.filter(r => r.isPair || r.type === 'smart_bundle');

      if (gapRecs.length > 0 && frequentPairs.length > 0) {
        const gap = gapRecs[0];
        const pair = frequentPairs[0];

        recommendations.push({
          id: `compound-${gap.id}-${pair.id}`,
          type: 'compound_recommendation',
          label: `複合建議：${gap.label.split('：')[1] || gap.label} + ${pair.label.split('：')[1] || pair.label}`,
          sublabel: `缺口補充 + 高頻搭配`,
          suggestedReason: `根據此客戶購買間隔，建議先補充「${gap.label.split('：')[1] || ''}」，同時搭配常一起購買的「${pair.label.split('：')[1] || ''}」，效果與客單價都會更好`,
          keywords: '複合 缺口 搭配 強力推薦',
          icon: '💎',
          action: 'quick-add-recommendation',
          priority: 22,  // 最高權重
          suggestedItems: [...(gap.suggestedItems || []), ...(pair.suggestedItems || [])]
        });
      }

    } catch (err) {
      console.warn('[CommandPalette] 進階智能推薦分析失敗', err);
    }

    // 根據客戶最近回來時間調整推薦積極程度（久未回來的客人給更多「價值型 / 搭配型」推薦）
    if (typeof daysSinceLastVisit !== 'undefined' && daysSinceLastVisit > 90) {
      recommendations.forEach(rec => {
        if (rec.type === 'frequent_pair' || rec.type === 'smart_bundle' || rec.type === 'cross_sell') {
          rec.priority = (rec.priority || 0) + 5;
        }
        if (rec.isGap) {
          rec.label = rec.label.replace('建議', '超值建議');
        }
      });
    }

    // 最終排序
    recommendations.sort((a, b) => {
      const scoreA = (b.priority || 0) + (b.isGap ? 6 : 0) + (b.isFrequent ? 3 : 0) + (b.isPair ? 4 : 0);
      const scoreB = (a.priority || 0) + (a.isGap ? 6 : 0) + (a.isFrequent ? 3 : 0) + (a.isPair ? 4 : 0);
      return scoreA - scoreB;
    });

    return recommendations.slice(0, 9);
  }

  async function updateResults(q = '') {
    if (!resultsEl) return;
    const ctx = getContext();
    currentContext = ctx;

    let baseItems = getItems(ctx);
    let dynamicItems = [];

    // POS 情境：有查詢字串時 → 真正呼叫 API 動態搜尋（服務/產品 + 客戶 + 常用組合）
    if (ctx === 'pos' && q.trim().length >= 1) {
      // 清掉英雄卡片狀態
      if (window.SalonEase) window.SalonEase._cmdHeroRecs = [];
      // 先顯示 loading
      renderLoadingState(q);

      const [servicesProducts, customers, templates] = await Promise.all([
        fetchPosItems(q),
        fetchPosCustomers(q),
        fetchCartTemplates(q)
      ]);

      dynamicItems = [...servicesProducts, ...customers, ...templates];

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
      // POS 頁空白查詢時，智能推薦：
      // - 如果有選客戶 → 顯示「該客戶的智能推薦」（最近購買 + 常用組合）
      // - 否則顯示一般最近 + 常用組合
      const recent = getPosRecentSessionItems();
      const customerPkgs = getCurrentPosCustomerPackages();

      const POS = window.SalonEase && window.SalonEase.POS;
      const hasCustomer = POS && POS.getCurrentCustomer && POS.getCurrentCustomer();

      if (hasCustomer) {
        // 有客戶時，優先顯示智能推薦
        const smartRecs = await fetchSmartRecommendationsForCurrentCustomer();

        // === A 選項新功能：抽取 Top 英雄組合（獨立視覺卡片區）===
        const heroTypes = ['compound_recommendation', 'price_suggestion', 'smart_bundle'];
        const heroRecs = smartRecs
          .filter(r => heroTypes.includes(r.type) || r.isPair || r.priority >= 18)
          .slice(0, 3);

        // 剩餘的普通智能推薦
        const normalSmart = smartRecs.filter(r => !heroRecs.some(h => h.id === r.id));

        // 把 hero 資訊存到全域，方便 renderResults 特別處理
        window.SalonEase = window.SalonEase || {};
        window.SalonEase._cmdHeroRecs = heroRecs;

        dynamicItems = [...normalSmart, ...recent];
      } else {
        window.SalonEase = window.SalonEase || {};
        window.SalonEase._cmdHeroRecs = [];
        const templates = await fetchCartTemplates();
        dynamicItems = [...recent, ...templates.slice(0, 5)];
      }

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

    // === A 選項：POS + 空白查詢 + 有客戶時，優先顯示「Top 3 最可能接受的智能組合」英雄卡片 ===
    const POS = window.SalonEase && window.SalonEase.POS;
    const hasCustomer = POS && POS.getCurrentCustomer && POS.getCurrentCustomer();
    const heroes = (window.SalonEase && window.SalonEase._cmdHeroRecs) || [];

    if (currentContext === 'pos' && !q.trim() && hasCustomer && heroes.length > 0) {
      html += `<div class="px-3 pt-2 pb-1 small text-success fw-semibold d-flex align-items-center">
        <span>⭐ Top ${heroes.length} 最可能接受的智能組合（根據您歷史）</span>
      </div>`;

      heroes.forEach((hero, hIdx) => {
        const itemsCount = (hero.suggestedItems || []).length;
        const reasonShort = hero.suggestedReason ? hero.suggestedReason.substring(0, 72) + (hero.suggestedReason.length > 72 ? '...' : '') : '';
        const bandBadge = hero.priceBand ? `<span class="badge bg-success-subtle text-success ms-1" style="font-size:9px">HK$${hero.priceBand.min}-${hero.priceBand.max}</span>` : '';

        html += `
          <div class="cmd-hero mx-2 my-1 p-2 rounded-3 border" style="background:#f8f5f0;border-left:4px solid #8FA68F;" data-hero-idx="${hIdx}">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1 min-w-0 pe-2">
                <div class="fw-semibold text-dark small">${hero.icon || '🎁'} ${hero.label}</div>
                <div class="text-muted" style="font-size:10.5px;line-height:1.25;margin-top:2px;">${hero.sublabel || ''} ${bandBadge}</div>
                ${reasonShort ? `<div class="text-success" style="font-size:10px;margin-top:3px;">${reasonShort}</div>` : ''}
              </div>
              <div class="text-end flex-shrink-0">
                <button type="button" class="btn btn-sm btn-success py-0 px-2 mb-1" style="font-size:11px;" data-hero-action="add" data-hero-idx="${hIdx}">一鍵加入組合${itemsCount ? `(${itemsCount})` : ''}</button><br>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:10px;" data-hero-action="copy-script" data-hero-idx="${hIdx}">💬 複製話術</button>
              </div>
            </div>
          </div>`;
      });

      html += `<div class="px-3 pt-2 pb-0 small text-muted fw-medium">其餘推薦項目</div>`;
    }

    if (!currentResults.length) {
      let emptyMsg = `找不到符合「${q}」的項目`;
      if (currentContext === 'pos') {
        emptyMsg = `找不到符合「${q}」的服務/產品<br><span class="small">可嘗試「面部」「精華」等關鍵字</span>`;
      }
      html += `<div class="p-4 text-center text-muted">${emptyMsg}</div>`;
      resultsEl.innerHTML = html;
      // 即使有英雄卡片，都要綁定事件
      bindHeroCardEvents();
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
        // 當有英雄卡片時，下面顯示「其餘智能推薦 + 最近項目」
        const hasHero = (window.SalonEase && window.SalonEase._cmdHeroRecs && window.SalonEase._cmdHeroRecs.length > 0);
        const hasRecent = getPosRecentSessionItems().length > 0;
        if (hasHero) {
          section = hasRecent ? '其餘推薦 + 本單最近加入' : '其餘智能推薦項目';
        } else {
          section = hasRecent 
            ? '本單最近加入（快速重複） + 客戶可用套票'
            : '快速功能 + 客戶可用套票（可直接扣減）';
        }
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

    // 綁定英雄卡片按鈕（一鍵加入組合 + 複製話術）
    bindHeroCardEvents();

    highlight();
  }

  function bindHeroCardEvents() {
    if (!resultsEl) return;
    const heroCards = resultsEl.querySelectorAll('.cmd-hero');
    heroCards.forEach(card => {
      const addBtn = card.querySelector('[data-hero-action="add"]');
      const copyBtn = card.querySelector('[data-hero-action="copy-script"]');
      const hIdx = parseInt(card.dataset.heroIdx || card.getAttribute('data-hero-idx'));

      if (addBtn) {
        addBtn.onclick = (e) => {
          e.stopImmediatePropagation();
          const heroes = (window.SalonEase && window.SalonEase._cmdHeroRecs) || [];
          const hero = heroes[hIdx];
          if (hero) quickAddHeroBundle(hero);
        };
      }
      if (copyBtn) {
        copyBtn.onclick = (e) => {
          e.stopImmediatePropagation();
          const heroes = (window.SalonEase && window.SalonEase._cmdHeroRecs) || [];
          const hero = heroes[hIdx];
          if (hero) {
            const script = generateFullBundleSalesScript(hero, {
              daysSinceLastVisit: (typeof daysSinceLastVisit !== 'undefined' ? daysSinceLastVisit : 45)
            });
            copySalesScriptToClipboard(script);
          }
        };
      }
    });
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

    // 智能推薦標籤（加強價格區間 + 複合建議視覺）
    let smartBadge = '';
    if (item.type === 'price_suggestion' || item.priceBand) {
      const b = item.priceBand;
      const bandTxt = b ? `HK$${b.min}–${b.max}` : '客製價位';
      smartBadge = `<span class="badge bg-success-subtle text-success ms-2" style="font-size:9px">💎 ${bandTxt}</span>`;
    } else if (item.type === 'compound_recommendation') {
      smartBadge = `<span class="badge bg-primary-subtle text-primary ms-2" style="font-size:9px">複合強推</span>`;
    } else if (item.isFrequent) {
      smartBadge = `<span class="badge bg-warning-subtle text-warning ms-2" style="font-size:10px">最常用</span>`;
    } else if (item.isGap || item.type === 'gap_suggestion') {
      smartBadge = `<span class="badge bg-info-subtle text-info ms-2" style="font-size:10px">該補貨</span>`;
    } else if (item.isPair || item.type === 'smart_bundle') {
      smartBadge = `<span class="badge bg-secondary-subtle text-secondary ms-2" style="font-size:9px">高匹配</span>`;
    }

    // 有建議原因的推薦，顯示小提示圖示
    const hasReason = item.suggestedReason ? `<span class="ms-1 text-muted" style="font-size:10px" title="有專業銷售話術">💬</span>` : '';

    return `<div class="cmd-row d-flex align-items-center gap-2 px-3 py-2 rounded-2 cursor-pointer ${idx === activeIndex ? 'bg-light' : ''}" data-idx="${idx}" style="cursor:pointer">
      <div style="width:28px" class="text-center fs-5">${item.icon || '🔗'}</div>
      <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center">
          <span class="fw-medium text-dark">${item.label}</span>
          ${smartBadge}${hasReason}${recent}${sku}${lowStock}
        </div>
        ${item.category ? `<div class="text-muted" style="font-size:11px;line-height:1">${item.category}</div>` : ''}
        ${item.sublabel && (item.type && item.type.includes('suggestion') || item.isGap || item.isPair) ? `<div class="text-success" style="font-size:10px;line-height:1.1">${item.sublabel}</div>` : ''}
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

    if (item.action === 'quick-create-customer') {
      enterQuickCreateCustomerMode();
      return;
    }

    if (item.action === 'quick-checkout') {
      enterQuickCheckoutMode();
      return;
    }

    if (item.action === 'apply-discount') {
      doApplyDiscount(item);
      return;
    }

    if (item.action === 'save-cart-template') {
      enterSaveCartTemplateMode();
      return;
    }

    if (item.action === 'load-cart-template' && item.templateId) {
      doLoadCartTemplate(item);
      return;
    }

    if (item.action === 'search-sales-history') {
      enterSalesHistorySearchMode();
      return;
    }

    if (item.action === 'load-history-sale' && item.saleId) {
      // 智能推薦點擊歷史銷售 → 進入確認模式（支援部分選擇）
      await enterLoadConfirmationMode('history', item.saleId, item.label);
      return;
    }

    if (item.action === 'load-cart-template' && item.templateId) {
      // 智能推薦或一般模板點擊 → 進入確認模式
      await enterLoadConfirmationMode('template', item.templateId, item.label);
      return;
    }

    if (item.action === 'quick-add-recommendation' && item.suggestedItems) {
      await quickAddRecommendedItems(item);
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

  // 進入快速新增客戶模式（在命令面板內完成）
  function enterQuickCreateCustomerMode() {
    if (!resultsEl || !inputEl) return;

    createCustomerMode = true;

    const prefillName = inputEl.value.trim();

    resultsEl.innerHTML = `
      <div class="px-3 py-3">
        <div class="small fw-medium mb-2 text-success">快速新增客戶</div>
        
        <div class="mb-2">
          <label class="form-label small mb-1">姓名 <span class="text-danger">*</span></label>
          <input type="text" id="cmd-create-name" class="form-control form-control-sm" value="${prefillName}" placeholder="例如：陳小美">
        </div>
        
        <div class="mb-3">
          <label class="form-label small mb-1">電話 <span class="text-danger">*</span></label>
          <input type="tel" id="cmd-create-phone" class="form-control form-control-sm" placeholder="例如：91234567">
        </div>

        <div class="d-flex gap-2">
          <button id="cmd-create-btn" class="btn btn-success btn-sm flex-fill">建立並切換到此客戶</button>
          <button id="cmd-create-cancel" class="btn btn-outline-secondary btn-sm">取消</button>
        </div>
      </div>
    `;

    const nameInput = resultsEl.querySelector('#cmd-create-name');
    const phoneInput = resultsEl.querySelector('#cmd-create-phone');
    const createBtn = resultsEl.querySelector('#cmd-create-btn');
    const cancelBtn = resultsEl.querySelector('#cmd-create-cancel');

    // 焦點處理
    setTimeout(() => {
      if (prefillName && phoneInput) {
        phoneInput.focus();
      } else if (nameInput) {
        nameInput.focus();
        nameInput.select();
      }
    }, 50);

    createBtn.onclick = async () => {
      const name = nameInput.value.trim();
      const phone = phoneInput.value.trim();

      if (!name || !phone) {
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast('請填寫姓名與電話', 'error');
        }
        return;
      }

      createBtn.disabled = true;
      createBtn.innerHTML = '建立中...';

      try {
        const res = await window.SalonEase.fetch('/api/customers.php?action=create', {
          method: 'POST',
          body: { name, phone }
        });

        const newCustomer = { id: res.data.id, name, phone };

        // 建立成功後立即切換
        const POS = window.SalonEase && window.SalonEase.POS;
        if (POS && typeof POS.setCurrentCustomer === 'function') {
          await POS.setCurrentCustomer(newCustomer);
        }

        hide();

        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast(`已建立並切換至 ${name}`, 'success', 2000);
        }

      } catch (err) {
        createBtn.disabled = false;
        createBtn.innerHTML = '建立並切換到此客戶';
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast(err.message || '建立客戶失敗', 'error');
        }
      }
    };

    cancelBtn.onclick = () => {
      createCustomerMode = false;
      updateResults(inputEl.value); // 恢復正常搜尋
    };

    // 允許 Enter 送出
    [nameInput, phoneInput].forEach(el => {
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          createBtn.click();
        }
        if (e.key === 'Escape') {
          cancelBtn.click();
        }
      });
    });
  }

  // 進入快速結帳模式（在命令面板內完成）
  function enterQuickCheckoutMode() {
    if (!resultsEl) return;

    const totalEl = document.getElementById('cart-total');
    const discountEl = document.getElementById('cart-discount');
    const currentTotal = totalEl ? parseFloat(totalEl.textContent.replace('HK$', '').trim()) || 0 : 0;
    const currentDiscount = discountEl ? parseFloat(discountEl.value) || 0 : 0;

    if (currentTotal <= 0) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast('購物車是空的，無法結帳', 'error');
      }
      hide();
      return;
    }

    resultsEl.innerHTML = `
      <div class="px-3 py-3">
        <div class="small fw-medium mb-2 text-primary">快速結帳</div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted">小計</span>
          <span id="qc-subtotal">HK$ ${(currentTotal + currentDiscount).toFixed(2)}</span>
        </div>
        ${currentDiscount > 0 ? `
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted">折扣</span>
          <span class="text-danger">- HK$ ${currentDiscount.toFixed(2)}</span>
        </div>` : ''}
        <div class="d-flex justify-content-between align-items-center mb-3 border-top pt-2">
          <span class="fw-semibold">總計</span>
          <span id="qc-total" class="fw-semibold fs-5 text-success">HK$ ${currentTotal.toFixed(2)}</span>
        </div>

        <div class="mb-2">
          <label class="form-label small mb-1">付款方式</label>
          <div class="d-flex flex-wrap gap-1" id="qc-payment-buttons">
            <button type="button" class="btn btn-sm btn-outline-secondary qc-pay-btn active" data-method="cash">現金</button>
            <button type="button" class="btn btn-sm btn-outline-secondary qc-pay-btn" data-method="fps">轉數快</button>
            <button type="button" class="btn btn-sm btn-outline-secondary qc-pay-btn" data-method="card">信用卡</button>
            <button type="button" class="btn btn-sm btn-outline-secondary qc-pay-btn" data-method="wechat">WeChat</button>
            <button type="button" class="btn btn-sm btn-outline-secondary qc-pay-btn" data-method="alipay">Alipay</button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small mb-1">實收金額</label>
          <input type="number" id="qc-received" class="form-control form-control-sm" value="${currentTotal.toFixed(2)}" step="0.01">
          <div id="qc-change" class="small mt-1 text-success"></div>
        </div>

        <div class="d-flex gap-2">
          <button id="qc-checkout-btn" class="btn btn-primary btn-sm flex-fill">立即結帳</button>
          <button id="qc-checkout-cancel" class="btn btn-outline-secondary btn-sm">取消</button>
        </div>
      </div>
    `;

    const receivedInput = resultsEl.querySelector('#qc-received');
    const changeDiv = resultsEl.querySelector('#qc-change');
    const checkoutBtn = resultsEl.querySelector('#qc-checkout-btn');
    const cancelBtn = resultsEl.querySelector('#qc-checkout-cancel');
    const payButtons = resultsEl.querySelectorAll('.qc-pay-btn');

    let selectedMethod = 'cash';

    // 付款方式切換
    payButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        payButtons.forEach(b => b.classList.remove('active', 'btn-secondary'));
        btn.classList.add('active', 'btn-secondary');
        selectedMethod = btn.dataset.method;
      });
    });

    // 找零即時計算
    const updateChange = () => {
      const received = parseFloat(receivedInput.value) || 0;
      const change = received - currentTotal;
      if (received > 0) {
        if (change >= 0) {
          changeDiv.innerHTML = `<span class="text-success">應找：HK$ ${change.toFixed(2)}</span>`;
        } else {
          changeDiv.innerHTML = `<span class="text-danger">尚差：HK$ ${Math.abs(change).toFixed(2)}</span>`;
        }
      } else {
        changeDiv.innerHTML = '';
      }
    };

    receivedInput.addEventListener('input', updateChange);
    updateChange();

    // 立即結帳
    checkoutBtn.onclick = async () => {
      const received = parseFloat(receivedInput.value) || 0;

      if (received < currentTotal) {
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast('實收金額不足', 'error');
        }
        return;
      }

      // 把值寫回原本的 DOM，讓原本的 checkout() 函式可以直接使用
      const paymentSelect = document.getElementById('payment-method');
      const receivedInputMain = document.getElementById('amount-received');

      if (paymentSelect) paymentSelect.value = selectedMethod;
      if (receivedInputMain) receivedInputMain.value = received.toFixed(2);

      checkoutBtn.disabled = true;
      checkoutBtn.innerHTML = '結帳中...';

      try {
        // 直接呼叫原本的結帳流程（會處理所有驗證、API、低庫存、打印等）
        if (typeof window.checkout === 'function') {
          await window.checkout();
          // checkout 成功後會自己清畫面 + 開打印選擇
          hide();
        } else {
          throw new Error('找不到結帳函式');
        }
      } catch (err) {
        checkoutBtn.disabled = false;
        checkoutBtn.innerHTML = '立即結帳';
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast(err.message || '結帳失敗', 'error');
        }
      }
    };

    cancelBtn.onclick = () => {
      updateResults(''); // 回到正常模式
    };

    // Enter 鍵快速結帳
    receivedInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        checkoutBtn.click();
      }
      if (e.key === 'Escape') {
        cancelBtn.click();
      }
    });

    // 預設 focus 在金額欄
    setTimeout(() => receivedInput.focus(), 50);
  }

  // 進入歷史銷售搜尋模式
  async function enterSalesHistorySearchMode() {
    if (!resultsEl) return;

    const POS = window.SalonEase && window.SalonEase.POS;
    const currentCustomer = POS && POS.getCurrentCustomer ? POS.getCurrentCustomer() : null;

    resultsEl.innerHTML = `
      <div class="px-3 py-3">
        <div class="small fw-medium mb-2 text-primary">搜尋歷史銷售</div>
        
        ${currentCustomer ? `
          <div class="small mb-2 text-muted">
            目前客戶：<strong>${currentCustomer.name}</strong>（只顯示此客戶的歷史）
          </div>
        ` : `
          <div class="small mb-2 text-muted">顯示最近銷售（可切換客戶後再搜）</div>
        `}

        <div id="history-results" class="border rounded" style="max-height: 320px; overflow: auto;">
          <div class="p-3 text-center text-muted small">載入中...</div>
        </div>

        <div class="mt-2 d-flex gap-2">
          <button id="history-refresh" class="btn btn-sm btn-outline-secondary">重新載入</button>
          <button id="history-cancel" class="btn btn-sm btn-outline-secondary ms-auto">取消</button>
        </div>
      </div>
    `;

    const resultsContainer = resultsEl.querySelector('#history-results');
    const refreshBtn = resultsEl.querySelector('#history-refresh');
    const cancelBtn = resultsEl.querySelector('#history-cancel');

    const loadHistory = async () => {
      resultsContainer.innerHTML = `<div class="p-3 text-center text-muted small">載入中...</div>`;

      try {
        let url = '/api/sales.php?action=list&limit=15';
        if (currentCustomer && currentCustomer.id) {
          url += `&customer_id=${currentCustomer.id}`;
        }

        const res = await window.SalonEase.fetch(url);
        const sales = res.data || [];

        if (sales.length === 0) {
          resultsContainer.innerHTML = `<div class="p-3 text-center text-muted small">沒有找到歷史銷售</div>`;
          return;
        }

        let html = '';
        sales.forEach(sale => {
          const customerInfo = sale.customer_name ? `${sale.customer_name} (${sale.customer_phone || ''})` : '散客';
          html += `
            <div class="history-sale-item p-2 border-bottom cursor-pointer hover-bg" data-sale-id="${sale.id}" style="cursor:pointer;">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="small fw-medium">#${sale.id} · ${sale.sale_date}</div>
                  <div class="small text-muted">${customerInfo} · ${sale.staff_name || ''}</div>
                </div>
                <div class="text-end">
                  <div class="fw-semibold text-success">HK$ ${parseFloat(sale.total).toFixed(0)}</div>
                  <div class="small text-muted">${sale.item_count} 項</div>
                </div>
              </div>
            </div>
          `;
        });

        resultsContainer.innerHTML = html;

        // 綁定點擊載入
        resultsContainer.querySelectorAll('.history-sale-item').forEach(el => {
          el.addEventListener('click', async () => {
            const saleId = el.dataset.saleId;
            await loadItemsFromSale(saleId, resultsContainer);
          });
        });

      } catch (err) {
        resultsContainer.innerHTML = `<div class="p-3 text-center text-danger small">載入失敗</div>`;
      }
    };

    await loadHistory();

    refreshBtn.onclick = loadHistory;
    cancelBtn.onclick = () => updateResults('');
  }

  // 新通用確認模式：支援從歷史銷售或常用組合中選擇性加入項目
  async function enterLoadConfirmationMode(sourceType, sourceId, sourceLabel) {
    if (!resultsEl) return;

    resultsEl.innerHTML = `<div class="p-3 text-center text-muted small">正在載入項目明細...</div>`;

    let items = [];

    try {
      if (sourceType === 'history') {
        const res = await window.SalonEase.fetch(`/api/sales.php?action=get_items&sale_id=${sourceId}`);
        items = res.data || [];
      } else if (sourceType === 'template') {
        const res = await window.SalonEase.fetch(`/api/cart_templates.php?action=apply&id=${sourceId}`);
        items = res.data.items || [];
      }

      if (items.length === 0) {
        resultsEl.innerHTML = `<div class="p-3 text-center text-muted small">沒有可加入的項目</div>`;
        return;
      }

      // 建立確認畫面
      let html = `
        <div class="px-3 py-3">
          <div class="small fw-medium mb-2">選擇要加入的項目</div>
          <div class="small text-muted mb-2">來源：${sourceLabel}</div>
          <div style="max-height: 260px; overflow:auto; border: 1px solid #eee; border-radius: 6px; padding: 4px;">
      `;

      items.forEach((item, index) => {
        const displayName = item.name || item.label || '項目';
        const price = item.unit_price || item.price || 0;
        html += `
          <div class="d-flex align-items-center p-2 border-bottom">
            <input type="checkbox" class="form-check-input me-2 load-item-check" data-index="${index}" checked>
            <div class="flex-grow-1 small">
              ${displayName}
              ${item.qty > 1 ? `<span class="text-muted">×${item.qty}</span>` : ''}
            </div>
            <div class="small text-end" style="min-width: 70px;">HK$ ${parseFloat(price).toFixed(0)}</div>
          </div>
        `;
      });

      html += `
          </div>

          <div class="d-flex gap-2 mt-3">
            <button id="btn-load-selected" class="btn btn-success btn-sm flex-fill">加入選取項目</button>
            <button id="btn-load-all" class="btn btn-outline-success btn-sm">加入全部</button>
            <button id="btn-load-cancel" class="btn btn-outline-secondary btn-sm">取消</button>
          </div>
        </div>
      `;

      resultsEl.innerHTML = html;

      const checkboxes = resultsEl.querySelectorAll('.load-item-check');
      const btnSelected = resultsEl.querySelector('#btn-load-selected');
      const btnAll = resultsEl.querySelector('#btn-load-all');
      const btnCancel = resultsEl.querySelector('#btn-load-cancel');

      btnSelected.onclick = async () => {
        const selectedItems = [];
        checkboxes.forEach(cb => {
          if (cb.checked) {
            selectedItems.push(items[parseInt(cb.dataset.index)]);
          }
        });
        await applySelectedItems(selectedItems, sourceLabel);
      };

      btnAll.onclick = async () => {
        await applySelectedItems(items, sourceLabel);
      };

      btnCancel.onclick = () => {
        updateResults(''); // 返回推薦列表
      };

    } catch (err) {
      resultsEl.innerHTML = `<div class="p-3 text-center text-danger small">載入失敗</div>`;
    }
  }

  async function applySelectedItems(selectedItems, sourceLabel) {
    if (!selectedItems || selectedItems.length === 0) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast('請至少選擇一個項目', 'error');
      }
      return;
    }

    const cart = window.SalonEase.POS.getCart ? window.SalonEase.POS.getCart() : [];
    let shouldClear = false;

    if (cart.length > 0) {
      shouldClear = confirm(`目前購物車有 ${cart.length} 項。\n\n[確定] 清空後加入選取項目\n[取消] 保留現有項目 + 加入`);
    }

    if (shouldClear && window.SalonEase.POS.clearCart) {
      window.SalonEase.POS.clearCart();
    }

    for (const item of selectedItems) {
      if (window.addToCart) {
        const type = item.item_type || item.type;
        const refId = item.ref_id || item.id;
        const name = item.name;
        const price = item.unit_price || item.price;

        window.addToCart(type, refId, name, price);

        // 處理 qty > 1
        const qty = item.qty || 1;
        for (let i = 1; i < qty; i++) {
          window.addToCart(type, refId, name, price);
        }
      }
    }

    hide();
    if (window.SalonEase && window.SalonEase.toast) {
      window.SalonEase.toast(`已從「${sourceLabel}」加入 ${selectedItems.length} 項`, 'success');
    }
  }

  /**
   * 產生極致專業、自然的銷售話術（香港粵語風格）
   * 包含：開場白（個人化）+ 歷史原因 + 價格定位 + 效益/社群證明 + 軟性行動呼籲
   */
  function generateProfessionalSalesScript(recommendation, extra = {}) {
    const rec = recommendation || {};
    const reason = rec.suggestedReason || '';
    const days = extra.daysSinceLastVisit || 999;
    const band = rec.priceBand;
    const isLongAbsent = days > 90;
    const isPrice = rec.type === 'price_suggestion';
    const isGap = rec.isGap || rec.type === 'gap_suggestion';
    const isBundle = rec.isPair || rec.type === 'smart_bundle' || rec.type === 'frequent_pair';
    const isCompound = rec.type === 'compound_recommendation';

    let script = '';

    if (isCompound) {
      script = `「喂，${isLongAbsent ? '好耐冇見，您最近都冇嚟做嘢呀～' : '又見到您啦！'} 根據您之前的記錄，呢個項目已經到補充的時候啦。同時好多客人買完之後都會一齊加呢個搭配，兩樣一齊用效果真係正好多，而且客單價都高咗少少。您覺得呢個組合點呀？只係多咗唔多錢，感覺就完全唔同。」`;
    } else if (isGap) {
      script = `「上次您買呢個已經有 ${extra.daysSince || '一段'} 日啦！好多客人平均 ${Math.round((extra.avgInterval || 35))} 日就會補返一次。您而家補返，效果會即刻返到最佳狀態，而且而家做呢個價位都幾抵。您要唔要一齊補呢個？CP值真係高。」`;
    } else if (isBundle) {
      script = `「呢個係您之前好鍾意嘅項目，好多客人買完 ${rec.label ? rec.label.split('：')[1] || '呢樣' : '呢樣'} 之後，都會順便加呢個搭配。兩樣一齊用，效果同感覺都會明顯升級，只係多咗少少錢。您覺得點呀？試下啦！」`;
    } else if (isPrice) {
      const bandText = band ? `（您最舒服嘅價位大約 HK$ ${band.median} 左右）` : '';
      script = `「根據您過去幾次消費習慣，我們為您度身訂造咗呢個價位區間 ${bandText}。呢個組合既唔會太貴，又可以感受到明顯嘅效果同體驗，好多同您消費水平差唔多嘅客人，都覺得呢個最啱自己。您睇下喜唔喜歡？」`;
    } else if (rec.isFrequent) {
      script = `「呢個係您之前返購好多次嘅王牌項目！效果穩定，價錢都幾親民，好多客人係固定每隔幾個星期就買一次。您而家要唔要再嚟一組？絕對抵。」`;
    } else {
      script = `「根據您嘅購買習慣，我哋推介呢個搭配。效果同性價比都幾高，好多客人用完之後都話值得，而且整體感覺會更好。您有興趣試下嗎？」`;
    }

    // 統一加上溫柔 CTA
    if (!script.includes('您覺得') && !script.includes('試下') && !script.includes('點呀')) {
      script += ` 您覺得呢個建議如何呀？`;
    }
    return script;
  }

  /**
   * 專為「Top 3 最可能接受的智能組合」產生極致完整銷售腳本
   * 包含：個人化開場 + 3個具體好處 + 價格錨定 + 社群證明 + 軟收結 + WhatsApp-ready
   */
  function generateFullBundleSalesScript(rec, extra = {}) {
    const label = rec.label || '呢個組合';
    const reason = rec.suggestedReason || '';
    const days = extra.daysSinceLastVisit || 45;
    const isLongAbsent = days > 85;
    const band = rec.priceBand;

    let opening = isLongAbsent 
      ? `「喂，${extra.customerName ? extra.customerName + '，' : ''}好耐冇見到您啦！呢段時間有冇特別保養呀？」`
      : `「又見到您，好開心！上次您做完之後反應都幾好呀。」`;

    let benefits = '';
    if (rec.type === 'compound_recommendation' || rec.type === 'smart_bundle') {
      benefits = `第一，呢兩個項目一齊用，效果會加乘；第二，您之前已經試過其中一樣，接受度高；第三，呢個價位區間係您自己最舒服嘅，性價比最高。`;
    } else if (rec.type === 'price_suggestion') {
      const bandText = band ? `大約 HK$ ${band.median} 左右` : '您習慣嘅價位';
      benefits = `最啱您嘅價位 ${bandText}，效果同體驗都有明顯提升，好多同您消費習慣相近嘅客人，都覺得呢個組合最啱自己。`;
    } else {
      benefits = `根據您嘅歷史記錄，呢個組合係您最常一齊買、反應最好嘅搭配，CP值好高。`;
    }

    const priceAnchor = band 
      ? `整體只係比您平時多咗少少錢，感覺卻完全唔同層次。`
      : `價錢都幾親民，效果卻好正。`;

    const close = `您而家要唔要我幫您即刻加落去？或者我可以再調一調，睇下邊個組合最啱您今次嘅需要。`;

    return `${opening} ${benefits} ${priceAnchor} ${close}`;
  }

  function copySalesScriptToClipboard(text) {
    const cleanText = text.replace(/^「|」$/g, ''); // 移除引號方便 WhatsApp 貼上
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(cleanText).then(() => {
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast('✅ 銷售話術已複製，可直接貼去 WhatsApp', 'success', 2200);
        }
      }).catch(() => {
        fallbackCopy(cleanText);
      });
    } else {
      fallbackCopy(cleanText);
    }
  }

  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    if (window.SalonEase && window.SalonEase.toast) {
      window.SalonEase.toast('✅ 已複製銷售話術', 'success', 2000);
    }
  }

  // 專門處理 Top 英雄組合的一鍵加入（多項目 + 更豐富話術 + 自動記錄來源）
  async function quickAddHeroBundle(rec) {
    const items = rec.suggestedItems || [];
    if (items.length === 0) {
      // 退回普通流程
      return quickAddRecommendedItems(rec);
    }

    const POS = window.SalonEase && window.SalonEase.POS;
    const customer = POS && POS.getCurrentCustomer ? POS.getCurrentCustomer() : null;

    // 加入所有項目
    for (const item of items) {
      if (window.addToCart) {
        window.addToCart(item.type, item.ref_id, item.name, item.unit_price || 0);
      }
    }

    // 結構化記錄推薦來源
    const notesEl = document.getElementById('sale-notes');
    if (notesEl) {
      const tag = `[智能組合推薦] ${rec.label} | 來源：${rec.type} | 匹配原因：${rec.suggestedReason ? rec.suggestedReason.substring(0, 80) : '歷史分析'} | ${new Date().toLocaleDateString('zh-HK')}`;
      const cur = notesEl.value.trim();
      notesEl.value = cur ? `${cur}\n${tag}` : tag;
    }

    const richScript = generateFullBundleSalesScript(rec, {
      daysSinceLastVisit: (typeof daysSinceLastVisit !== 'undefined' ? daysSinceLastVisit : 40),
      customerName: customer ? customer.name : ''
    });

    hide();

    if (window.SalonEase && window.SalonEase.toast) {
      window.SalonEase.toast(`✅ 已加入整個組合（${items.length} 項）`, 'success', 1600);
      
      setTimeout(() => {
        // 顯示完整話術 + 提供「複製」操作提示
        const scriptShort = richScript.length > 110 ? richScript.substring(0, 107) + '...' : richScript;
        window.SalonEase.toast(scriptShort, 'info', 6000);
        
        // 額外提供一個可點擊的 toast 提示複製
        setTimeout(() => {
          if (window.SalonEase && window.SalonEase.toast) {
            window.SalonEase.toast('💬 想直接複製完整話術去 WhatsApp？按 Ctrl+K 後輸入「複製話術」或稍後再開命令面板選擇', 'info', 4200);
          }
        }, 6200);
      }, 1900);
    }

    // 把完整 script 存到 window，方便之後快速複製（用戶可再開 Ctrl+K 打 "複製"）
    window.SalonEase = window.SalonEase || {};
    window.SalonEase.lastSalesScript = richScript;
  }

  // 從智能推薦直接一鍵加入（帶建議原因 + 自動產生銷售話術 + 記錄到備註）
  async function quickAddRecommendedItems(recommendation) {
    const items = recommendation.suggestedItems || [];
    if (items.length === 0) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast('此推薦暫無可加入項目', 'info');
      }
      return;
    }

    const reason = recommendation.suggestedReason || '系統推薦';

    // 直接加入項目
    for (const item of items) {
      if (window.addToCart) {
        window.addToCart(item.type, item.ref_id, item.name, item.unit_price || 0);
      }
    }

    // 自動將建議原因結構化記錄到銷售備註
    const notesEl = document.getElementById('sale-notes');
    if (notesEl) {
      const currentNotes = notesEl.value.trim();
      const structuredNote = `[推薦] 來源：智能推薦 | 原因：${reason} | 時間：${new Date().toISOString().slice(0,16)}`;
      if (!currentNotes.includes(reason)) {
        notesEl.value = currentNotes ? `${currentNotes}\n${structuredNote}` : structuredNote;
      }
    }

    // 使用全新專業銷售話術生成器（更自然、更長、更香港粵語風）
    const POS = window.SalonEase && window.SalonEase.POS;
    const ctxForScript = {
      daysSinceLastVisit: (typeof daysSinceLastVisit !== 'undefined' ? daysSinceLastVisit : 30)
    };
    const richScript = generateProfessionalSalesScript(recommendation, ctxForScript);

    // 特別處理 price_suggestion：如果沒有 suggestedItems，嘗試從 POS itemsCache 揀最接近客戶中位價的 1-2 項自動加入
    if (items.length === 0 && recommendation.type === 'price_suggestion' && POS && POS.getItemsCache) {
      const cache = POS.getItemsCache();
      const band = recommendation.priceBand;
      if (cache && cache.length && band) {
        const candidates = cache
          .filter(i => i.price >= band.min * 0.7 && i.price <= band.max * 1.1)
          .sort((a, b) => Math.abs(a.price - band.median) - Math.abs(b.price - band.median))
          .slice(0, 2);
        candidates.forEach(it => {
          if (window.addToCart) {
            window.addToCart(it.type || 'service', it.id, it.name, it.price);
          }
        });
        if (candidates.length > 0) {
          // 補充 notes
          if (notesEl) {
            const extraNote = `[智能推薦] 價格區間自動配對：${candidates.map(c => c.name).join(' + ')}`;
            notesEl.value = notesEl.value ? `${notesEl.value}\n${extraNote}` : extraNote;
          }
        }
      }
    }

    hide();

    if (window.SalonEase && window.SalonEase.toast) {
      window.SalonEase.toast(`已加入推薦項目`, 'success', 1800);
      setTimeout(() => {
        if (window.SalonEase && window.SalonEase.toast) {
          // 顯示完整專業銷售話術（更長時間）
          window.SalonEase.toast(richScript, 'info', 5200);
        }
      }, 2100);
    }
  }

  async function loadItemsFromSale(saleId, container) {
    // 保留舊函式以相容歷史模式，但引導到新確認模式
    await enterLoadConfirmationMode('history', saleId, `歷史銷售 #${saleId}`);
  }

  // 載入常用組合到購物車
  async function doLoadCartTemplate(item) {
    hide();

    try {
      const res = await window.SalonEase.fetch(
        `/api/cart_templates.php?action=apply&id=${item.templateId}`
      );

      const items = res.data.items || [];

      if (items.length === 0) {
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast('此組合沒有項目', 'error');
        }
        return;
      }

      // 通知使用者即將清空購物車（簡單起見先直接替換）
      // 未來可改成詢問「清空現有購物車還是合併？」
      const cart = window.SalonEase.POS.getCart ? window.SalonEase.POS.getCart() : [];
      if (cart.length > 0) {
        if (!confirm(`目前購物車有 ${cart.length} 項。\n載入「${item.label}」會先清空購物車，確定繼續？`)) {
          return;
        }
      }

      // 清空現有購物車
      if (window.SalonEase.POS.clearCart) {
        window.SalonEase.POS.clearCart();
      }

      // 逐一加入項目（利用現有 addToCart）
      for (const it of items) {
        if (window.addToCart) {
          window.addToCart(it.type, it.ref_id, it.name, it.unit_price);
        }
      }

      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast(`已載入常用組合：「${item.label}」`, 'success');
      }

    } catch (err) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast(err.message || '載入常用組合失敗', 'error');
      }
    }
  }

  // 進入儲存購物車為常用組合模式
  function enterSaveCartTemplateMode() {
    if (!resultsEl) return;

    // 從 POS 頁讀取目前購物車
    const cart = (window.SalonEase && window.SalonEase.POS && window.SalonEase.POS.getCart)
      ? window.SalonEase.POS.getCart()
      : [];

    if (!cart || cart.length === 0) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast('購物車是空的，無法儲存', 'error');
      }
      updateResults('');
      return;
    }

    resultsEl.innerHTML = `
      <div class="px-3 py-3">
        <div class="small fw-medium mb-2 text-success">儲存常用組合</div>
        
        <div class="mb-2 small text-muted">
          目前有 <strong>${cart.length}</strong> 項商品，將會儲存為快速可用的組合。
        </div>

        <div class="mb-3">
          <label class="form-label small mb-1">組合名稱 <span class="text-danger">*</span></label>
          <input type="text" id="tpl-name" class="form-control form-control-sm" placeholder="例如：經典面部護理套餐">
        </div>

        <div class="d-flex gap-2">
          <button id="tpl-save-btn" class="btn btn-success btn-sm flex-fill">儲存此組合</button>
          <button id="tpl-save-cancel" class="btn btn-outline-secondary btn-sm">取消</button>
        </div>
      </div>
    `;

    const nameInput = resultsEl.querySelector('#tpl-name');
    const saveBtn = resultsEl.querySelector('#tpl-save-btn');
    const cancelBtn = resultsEl.querySelector('#tpl-save-cancel');

    setTimeout(() => nameInput.focus(), 50);

    saveBtn.onclick = async () => {
      const name = nameInput.value.trim();
      if (!name) {
        if (window.SalonEase && window.SalonEase.toast) window.SalonEase.toast('請輸入組合名稱', 'error');
        return;
      }

      saveBtn.disabled = true;
      saveBtn.innerHTML = '儲存中...';

      try {
        const res = await window.SalonEase.fetch('/api/cart_templates.php?action=create', {
          method: 'POST',
          body: {
            name: name,
            items: JSON.stringify(cart)
          }
        });

        hide();
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast(`已儲存常用組合：「${name}」`, 'success');
        }

      } catch (err) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '儲存此組合';
        if (window.SalonEase && window.SalonEase.toast) {
          window.SalonEase.toast(err.message || '儲存失敗', 'error');
        }
      }
    };

    cancelBtn.onclick = () => {
      updateResults('');
    };

    nameInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') saveBtn.click();
      if (e.key === 'Escape') cancelBtn.click();
    });
  }

  // 套用折扣（從命令面板觸發）
  function doApplyDiscount(item) {
    const POS = window.SalonEase && window.SalonEase.POS;
    if (!POS || typeof POS.applyDiscount !== 'function') {
      hide();
      if (confirm('無法套用折扣，請前往 POS 頁操作。')) {
        location.href = '/pos.php';
      }
      return;
    }

    const subtotal = POS.getCurrentSubtotal ? POS.getCurrentSubtotal() : 0;
    let discountAmount = 0;
    let label = item.label;

    if (item.discountType === 'percent') {
      discountAmount = Math.round(subtotal * (item.value || 0));
      label = item.label.replace(/（.*）/, ''); // 清理顯示
    } else {
      discountAmount = item.value || 0;
    }

    if (discountAmount <= 0) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast('折扣金額為 0，無需套用', 'info');
      }
      hide();
      return;
    }

    const success = POS.applyDiscount(discountAmount, label);
    hide();

    if (!success) {
      if (window.SalonEase && window.SalonEase.toast) {
        window.SalonEase.toast('套用折扣失敗', 'error');
      }
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
    createCustomerMode = false;
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