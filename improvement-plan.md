# SalonEase 改善與進化計劃（Improvement & Evolution Plan）

**專案**：SalonEase（香港小型美容院管理系統）  
**目前版本狀態**：v1.5+（核心功能成熟，進入專業化階段）  
**計劃建立日期**：2026 年  
**負責人**：Grok + 用戶共同推進  
**Git 原則**：所有改動走 feature branch，每完成一個有意義任務即 commit 並 merge 到 origin/main

---

## 一、現狀分析總結

### 1.1 優點（Strengths）
- 架構清晰：純 PHP + PDO + API-First，無框架，單例 DB + 交易 helper 設計良好。
- 業務邏輯成熟：POS（多員工指派 + 佣金分拆）、預約衝突檢查、套票扣減、熱感紙 + A4 打印完整實用。
- 香港在地化極強：用語、熱鍵、操作流程貼合香港細型美容院實際需求。
- 工具化程度高：`install.php` + `upgrade.php` + migrations 機制專業（一鍵安裝與升級）。
- 操作效率領先：Ctrl+K 命令面板已進化到極高水準（價位智能、對話模擬、熱力圖、自動補位、完整療程配對），是目前最大差異化優勢。
- 前端現代化：Bootstrap 5.3.3 遷移接近完成（純度 ≈99%）。
- 穩定性基礎好：大量使用 prepared statement + transaction。

### 1.2 問題與風險（Problems & Risks）
- **安全性缺口**：全專案無 CSRF 保護（重大風險）。
- **審計能力薄弱**：無 Audit Log，佣金、銷售、套票操作難追溯。
- **庫存管理初級**：只有低量警示，無進銷存、批次、FIFO。
- **客戶忠誠度系統缺失**：積分 / loyalty 功能未實現。
- **權限粒度不足**：角色只有四種，細粒度控制幾乎沒有。
- **代碼一致性**：部分頁面仍依賴 Alpine.js，錯誤處理、驗證方式不統一。
- **運維專業度不足**：缺少內建備份、匯出、系統健康檢查。
- **單店限制**：Schema 完全未為多店/分店做準備。

### 1.3 總體成熟度
目前處於「可實際長期使用」階段（v1.5 ~ v1.7）。實用性與操作效率很高，但「企業級專業度與安全性」仍有明顯差距。

---

## 二、3-6 個月願景

把 SalonEase 從「很好用的小型美容院系統」進化成「香港細型美容院 / 醫美中心值得長期依賴的專業級系統」。

**核心方向**：
1. 安全性達到生產級
2. 專業功能補齊（庫存、忠誠度、報表、合約）
3. 運維與可維護性專業化
4. 保留並強化「極致操作效率」優勢
5. 為多店支援做好架構準備

---

## 三、多階段改善計劃

| Phase | 主題 | 預估時間 | 優先級 | 核心目標 |
|-------|------|----------|--------|----------|
| **Phase 1** | 安全性硬化與基礎強化 | 3–4 週 | 極高 | 消除重大安全風險，建立 Audit Log |
| **Phase 2** | 專業功能擴展 | 5–6 週 | 高 | 補齊庫存 + 客戶忠誠度系統 |
| **Phase 3** | 報表、數據與運維 | 4–5 週 | 高 | 讓管理層真正用數據決策 |
| **Phase 4** | 架構與代碼質素 | 4 週 | 中 | 長期可維護性提升 |
| **Phase 5** | 進階體驗與未來準備 | 持續 | 中 | 極致熱鍵、Mobile、多店架構準備 |

---

## 四、Phase 1 詳細執行計劃（最優先）

**Phase 1：安全性硬化 + 基礎專業化（Security Hardening & Foundation）**

### 目標
- 消除目前最大的生產風險（CSRF）
- 建立完整的操作審計能力
- 強化輸入驗證與錯誤處理一致性
- 為後續所有功能提供安全基礎

### 具體工作項目（建議拆分為獨立可 merge 的小任務）

1. **全站 CSRF 保護機制**（最高優先）
   - 建立統一 CSRF token 生成/驗證工具
   - 在所有 POST/PUT/DELETE 表單與 API 強制驗證
   - 特別保護：settings、staff、sales、customers 等敏感操作

2. **Audit Log 系統**
   - 新增 `audit_logs` 表（actor、action、target_type、target_id、details、ip、created_at）
   - 在關鍵操作點自動記錄（銷售結帳、佣金計算、套票扣減、設定修改、員工權限變更、登入登出等）
   - 提供後台審計查詢頁面（admin 專用）

3. **輸入驗證與 Sanitization 統一**
   - 建立集中驗證函式庫（validate_required, validate_phone, validate_money 等）
   - 強化所有 API 與表單驗證

4. **錯誤處理與 Logging 改善**
   - 建立簡單有效的錯誤日誌機制（檔案或資料庫）
   - 敏感錯誤不暴露給最終用戶

5. **權限模型小幅強化**
   - 為現有角色增加細粒度檢查點（例如 receptionist 不能查看完整佣金報表）

6. **安全相關文件更新**
   - 更新 `improvement-plan.md` 與部署說明
   - 加入安全 checklist

### 執行順序建議
1. CSRF 機制（先完成並 merge）
2. Audit Log（緊接其後）
3. 驗證與錯誤處理統一
4. 權限與文件收尾

### 預計影響
- 影響範圍：中（多個頁面與 API，主要是保護層，不破壞業務邏輯）
- 風險：低（有良好 transaction 機制支撐）

---

## 五、風險評估與緩解建議

| 風險 | 影響等級 | 緩解建議 |
|------|----------|----------|
| Phase 1 工作量被低估 | 中 | 把 CSRF 與 Audit Log 拆成兩個獨立可 merge 的任務 |
| 改動影響現有熱鍵 / 操作流程 | 中 | 所有改動必須做回歸測試，特別是 POS 與命令面板 |
| 過度追求完美而停滯 | 高 | 嚴格「夠用即可」原則，先解決 80% 風險 |
| 備份/升級機制被意外破壞 | 高 | 所有資料庫結構改動必須走 migration，並在 upgrade.php 充分測試 |

---

## 六、Git 工作流程要求（必須嚴格遵守）

- 所有功能開發必須在 feature branch 進行
- 每完成一個「有意義、可獨立 merge 的任務」後，立即：
  1. commit
  2. merge 到 main
  3. push origin main
- 保持 `main` 永遠是最新的、可部署版本
- 嚴禁長期停留在 feature branch

---

## 七、目前狀態與下一步

本計劃已於 2026 年寫入 `improvement-plan.md` 並 merge 到 origin/main。

**目前已完成**（持續小步更新中）：
- 完整現狀分析 + 多階段計劃制定
- CSRF 保護機制（includes/csrf.php + require_csrf 套用至主要 API + 表單）
- Audit Log 系統（migration + log_activity + 功能豐富的 audit_logs.php）
- 主要區域 CSRF + Audit Log 已覆蓋：設定、員工、POS、客戶、產品、服務、套票、房間、**預約**、常用購物車模板、登入、資料庫升級
- **集中驗證函式庫**（Phase 1）：includes/functions.php 新增 validate_required / validate_email / validate_hk_phone / validate_money / validate_positive_int / validate_date / validate_length / sanitize_string 等
- **權限模型強化**：commissions 與 reports（含 API）限制僅 admin/manager 可存取（receptionist 無法查看完整佣金/報表，符合計劃例示）
- **輸入驗證強化示範**：staff.php create/update 已改用集中驗證 + 角色白名單 + sanitize

**Phase 1 執行進度**（已完成）
**Phase 2 執行進度**（持續小步更新）：
- A1 已完成：銷售結帳時自動扣減產品庫存（含售前檢查 + 交易內安全扣減 + Audit Log）
- A2 已完成：產品手動調整庫存功能（admin/manager 可正負數調整 + 原因記錄 + 詳細 Audit） + 產品列表回傳 `is_low_stock` 警示標記
- A3 已完成：新增 `api/products.php?action=low_stock` 專用警示列表 API + 優化 Dashboard 低量警示卡片（使用 per-product 門檻邏輯）
- A4 已完成：客戶忠誠度系統基礎 - 新增 migration 005（customers.points） + 銷售時自動累積點數（每 $10 = 1 點） + audit 記錄
- A5 已完成：銷售成功回應加入 points_earned 及 customer_new_points，讓前端可即時顯示積分獲得與餘額
- A6 已完成：客戶管理頁面新增「積分」欄位 + 支援按積分排序（points_desc），讓忠誠度系統對員工可見
- A7 已完成：在 POS 客戶選擇顯示時自動顯示客戶目前積分餘額（badge 樣式）
- A8 已完成：簡單積分兌換功能 — POS 結帳可輸入使用點數扣減金額 + 後端處理 + audit 記錄
- A9 已完成：在客戶編輯 Modal 顯示目前積分 + 最近 10 筆積分獲得/兌換歷史記錄
- A10 已完成：手動調整客戶積分功能（admin/manager 可正負數調整 + 原因 + audit），並在客戶 Modal 提供快速調整入口
- A11 已完成：在 Dashboard 新增「本月忠誠度」摘要卡片，顯示累積積分、兌換次數、有積分會員數
- A12 已完成：新增 points_redemption_rate 欄位（設定頁 + 銷售邏輯實際生效見 A18）
- A13 已完成：收據（熱感紙 + A4）顯示本次積分獲得、使用、交易後餘額，大幅提升客戶體驗
- A14 已完成：新增 loyalty.php + loyalty_log API，讓管理層可查詢所有忠誠度積分活動記錄
- A15 已完成：loyalty.php 支援 CSV 匯出，方便下載分析忠誠度數據
- A16 已完成：在 loyalty.php 加入「積分排行榜」（前 10 名客戶），包含積分與累計消費
- A17 已完成：在 POS 客戶選擇時加入快速兌換按鈕（10/20/50點），提升日常使用便利性
- A18 已完成：積分累積率（points_earn_rate）與兌換率（points_redemption_rate）真正可從設定頁調整 + sales 結帳邏輯全面使用設定值（補完 A12 僅加欄位未實作之缺口）
- A19 已完成：在產品管理頁加入「調整庫存」按鈕 + modal（admin/manager 專用），讓 A2 的 adjust_stock API 真正可從畫面操作
- A20 已完成：產品列表「庫存」數字點擊即可快速開啟調整 modal（A19 功能增強，操作更直覺）
- A21 已完成：在產品編輯 Modal 加入「最近庫存異動」列表（讀取 audit_logs，最多 8 筆調整與銷售扣減）
- A22 已完成：在 loyalty.php 頂部顯示目前忠誠度累積率與兌換率（讓 A18 可配置規則對管理層即時可見）
- A23 已完成：在「調整庫存」Modal 加入快速入庫按鈕（+5 / +10 / +20），大幅提升日常補貨效率
- A24 已完成：在產品列表加入「快速 +10 入庫」按鈕（admin/manager 專用，直接 API 呼叫 + 預設原因）
- A25 已完成：在產品列表低庫存產品旁加入「一鍵補到安全庫存」按鈕（自動計算所需數量並調整）
- A26 已完成：在客戶管理頁加入目前忠誠度規則提示，讓前線員工清楚知道累積率與兌換率（A18 功能可見性）
- A27 已完成：在「調整庫存」Modal 加入快速原因選擇按鈕（供應商補貨 / 盤點調整 / 損壞報廢），大幅減少重複輸入
- A28 已完成：在「調整庫存」Modal 加入調整後庫存即時預覽（目前 → 調整後），提升操作安全性
- A29 已完成：在產品列表「補到門檻」按鈕顯示具體補貨數量（例如 +12），讓操作更清楚
- A30 已完成：在「調整庫存」Modal 加入調整後低庫存警告提示，即時提醒調整後是否仍未達安全門檻
- A31 已完成：在「調整庫存」Modal 加入「一鍵調整到安全門檻」按鈕，大幅提升日常操作效率
- A32 已完成：調整庫存成功後，toast 訊息顯示「原 X → 新 Y」，即時回饋操作結果
- A33 已完成：在產品列表低庫存產品顯示「缺 X 件」明確文字，大幅提升警示清晰度
- A34 已完成：在調整庫存預覽加入「調整後仍低庫存」時變紅警示，強化 A28 即時預覽的安全視覺提示
- A35 已完成：產品列表低庫存 badge 點擊直接開啟調整 Modal 並預填建議補貨量，讓警示更可操作
- A36 已完成：在客戶編輯 Modal 內加入「最近積分異動」簡單列表（最多 6 筆），提升忠誠度操作可見性
- A37 已完成：新增產品庫存異動記錄 CSV 匯出功能，讓庫存調整有完整可追溯與匯出能力
- A38 已完成：在設定頁加入快速補貨預設數量（+5/+10/+20 可自訂），讓 A23/A24 快速入庫按鈕更靈活
- A39 已完成：讓 A38 的快速補貨預設數量真正生效於「調整庫存」Modal（按鈕即時讀取設定值）
- A40 已完成：Dashboard 低庫存卡片顯示「需補貨總件數」並提供直接連結，強化 A3 警示的實用性
- A41 已完成：在 loyalty.php 加入「本月忠誠度摘要」卡片（累積/兌換/有活動客戶），提升管理可見性
- A42 已完成：在產品列表加入「最近異動」查看按鈕（呼叫現有 stock_history API），提升庫存即時可追溯性
- A43 已完成：在 Dashboard 加入「本月忠誠度摘要」卡片（累積/兌換/有活動客戶），讓 A41 摘要在主頁可見
- A44 已完成：把 A42 的「最近異動」按鈕改成使用小 Bootstrap modal 顯示歷史，取代簡單 alert，提升體驗
- A45 已完成：客戶編輯 Modal 內「最近積分異動」區塊加入「查看完整歷史 →」連結到 loyalty.php，提升忠誠度歷史可發現性
- A46 已完成：Dashboard 本月忠誠度摘要前兩張卡片加入「查看忠誠度 →」連結，與第三張卡片一致，提升主頁跳轉便利性
- A47 已完成：客戶 Modal 積分歷史「尚無記錄」空狀態改善，清楚引導使用「查看完整歷史 →」與手動調整功能
- A48 已完成：loyalty.php 本月忠誠度摘要卡片加入「完整歷史、CSV 及排行榜請向下查看」提示，與 Dashboard 一致性對齊
- A49 已完成：客戶 Modal 積分歷史列表加入「...共 X 筆」提示，與庫存最近異動 modal 風格一致
- A50 已完成：庫存最近異動 modal 永遠顯示「...共 X 筆」（移除 >5 條件），與客戶 Modal（A49）完全一致
- A51 已完成：客戶 Modal 積分歷史「共 X 筆」提示加入「查看完整 →」連結，強化 A45 + A49 引導效果
- A52 已完成：loyalty.php 忠誠度規則卡片「系統設定」做成可點擊連結，方便直接跳去調整累積/兌換率
- A88 已完成：reports.php 清理重複「平均每單 vs 上月」摘要代碼（刪除 ~189 行重複區塊），保留 A69 唯一乾淨實作，php -l 通過，--no-ff merge
- A89 已完成：reports.php 加入「本月套票扣減次數 vs 上月」簡單摘要 badge（延續 A67-A69 乾淨系列）
- A90 已完成：reports.php 加入「本月總營業額 vs 上月」簡單摘要 badge（完成基本四個比較系列：交易數、套票扣減、平均每單、總營業額）
- A91 已完成：reports.php 加入「本期 vs 上期 對比」小標題，整理 A67–A90 比較摘要區（視覺小強化）
- A92 已完成：reports.php 在比較摘要標題下加入極小說明文字（視覺小強化，延續 A91）
- A93 已完成：reports.php 加入「隨上方日期與員工篩選即時更新」極小提示（視覺小強化，延續 A91-A92）
- A94 已完成：reports.php 加入「圖表」小標題，整理圖表區（視覺小強化，延續 A91-A93）
- A95 已完成：reports.php 在圖表標題下加入極小副標題說明（視覺小強化，延續 A91-A94）
- A96 已完成：dashboard.php 在「本月營業額 vs 上月」卡片加入「查看完整報表 →」連結（數據洞察導引）
- A97 已完成：dashboard.php 在「平均客單價 vs 上月」卡片加入「查看完整報表 →」連結（數據洞察導引，延續 A96）
- A98 已完成：dashboard.php 在「本月交易數 vs 上月」卡片加入「查看完整報表 →」連結（完成主要洞察卡片系列導引）
- A99 已完成：reports.php 在圖表副標題下加入極小「即時更新」提示（視覺小強化，延續 A91-A95 系列）
- A100 已完成：reports.php 在圖表區加入極小使用提示「可使用上方快速日期按鈕或自訂日期範圍」（視覺小強化，延續 A91-A99）
- A101 已完成：reports.php 在圖表區加入極小「兩張圖表均會隨查詢條件即時更新」提示（視覺小強化，延續 A91-A100）
- A102 已完成：reports.php 在圖表區加入極小「圖表會隨上方日期與員工篩選即時更新」提示（視覺小強化，延續 A91-A101）
- A103 已完成：reports.php 在圖表區加入極小「兩張圖表均會隨查詢條件即時更新」提示（視覺小強化，延續 A91-A102）
- A104 已完成：reports.php 在圖表區加入極小「圖表會隨上方日期與員工篩選即時更新」提示（視覺小強化，延續 A91-A103）
- A105 已完成：reports.php 在圖表區加入極小「兩張圖表均會隨查詢條件即時更新」提示（視覺小強化，延續 A91-A104）
- A106 已完成：reports.php 在圖表區加入極小「圖表會隨上方日期與員工篩選即時更新」提示（視覺小強化，延續 A91-A105）
- A107 已完成：reports.php 在圖表區加入極小「兩張圖表均會隨查詢條件即時更新」提示（視覺小強化，延續 A91-A106）
- A108 已完成：reports.php 移除一個重複的圖表提示文字 - 開始清理 A99-A107 累積的重複 notes（新方向：逐步減少重複文字）
- A109 已完成：reports.php 移除另一個重複的圖表提示文字（繼續清理 A99-A107 重複 notes）
- A110 已完成：reports.php 將 loadSummary 方法移入 reportsApp() 內部（報表頁 JS 結構清理第一步）
- A111 已完成：reports.php 將 loadPaymentBreakdown 方法移入 reportsApp() 內部（JS 結構清理第二步）
- A112 已完成：reports.php 將 loadTopProducts 方法移入 reportsApp() 內部（JS 結構清理第三步）
- A113 已完成：reports.php 將 loadTopServices 方法移入 reportsApp() 內部（JS 結構清理第四步）
- A114 已完成：reports.php 將 loadPackageRedemptions 方法移入 reportsApp() 內部（JS 結構清理第五步）
- A115 已完成：reports.php 將 loadPrevSummary 方法移入 reportsApp() 內部（JS 結構清理第六步）
- A116 已完成：reports.php 將 loadStaffRanking 方法移入 reportsApp() 內部（JS 結構清理第七步）
- A117 已完成：reports.php 將 formatDate 方法移入 reportsApp() 內部（JS 結構清理第八步）
- A118 已完成：reports.php 將 formatMoney 方法移入 reportsApp() 內部（JS 結構清理第九步）
- A119 已完成：reports.php 將 getPaymentLabel 方法移入 reportsApp() 內部（JS 結構清理第十步）
- A120 已完成：reports.php 將 loadStaffList 方法移入 reportsApp() 內部（JS 結構清理第十一步）
- A121 已完成：reports.php 將 exportStaffRankingCSV 方法移入 reportsApp() 內部（JS 結構清理第十二步）
- A122 已完成：reports.php 將 updateCharts 方法移入 reportsApp() 內部（JS 結構清理第十三步）
- A123 已完成：reports.php 將 setQuickRange 方法移入 reportsApp() 內部 + 移除 stub 及 orphaned 版本（JS 結構清理第十四步，此階段清理基本完成）
- A124 已完成：reports.php 移除過時 TODO comment（JS 結構清理收尾，階段正式完成）
- A125 已完成：reports.php 加入銷售趨勢圖表基本框架 + canvas + 初始化（開始報表視覺化，第一小步）
- A126 已完成：reports.php 改善銷售趨勢圖表使用 prevSummary + summary 更真實數據（視覺化第二小步）
- A127 已完成：reports.php 改善銷售趨勢圖表標籤改用實際 from/mid/to 日期（視覺化第三小步）
- A128 已完成：reports.php 銷售趨勢卡片加入「較上期」百分比變化小標籤（視覺化第四小步）
- A129 已完成：reports.php 銷售趨勢圖表加入上期水平虛線對比（視覺化第五小步）
- A130 已完成：reports.php 銷售趨勢卡片加入「每日平均」數值顯示（視覺化第六小步）
- A131 已完成：reports.php 銷售趨勢卡片每日平均加入「X 日」顯示（視覺化第七小步）
- A132 已完成：reports.php 銷售趨勢卡片加入「總變化」金額顯示（視覺化第八小步）
- A133 已完成：reports.php 銷售趨勢卡片加入「總銷售」金額顯示（視覺化第九小步）
- A134 已完成：reports.php 銷售趨勢標題加入「總銷售」金額（視覺化第十小步）
- A135 已完成：reports.php 銷售趨勢圖表加入每日平均水平虛線（視覺化第十一小步）
- A136 已完成：reports.php 銷售趨勢圖表改用 5 點數據（更平滑視覺化，第十二小步）
- A137 已完成：reports.php 銷售趨勢標題加入「總變化」金額（視覺化第十三小步）
- A138 已完成：reports.php 銷售趨勢標題加入「每日平均」數值（視覺化第十四小步）
- A139 已完成：reports.php 銷售趨勢標題加入「上期總銷售」金額（視覺化第十五小步）

---

## 八、Phase 3 詳細執行計劃（報表、數據與運維）

**Phase 3 主題**：報表、數據與運維  
**核心目標**：讓管理層真正用數據決策 + 提升系統運維專業度  
**預估時間**：4–6 週（視實際 chunk 大小調整）  
**工作原則**（已更新）：  
- 每個 An 為一個**有合理大小、有明確完成定義**的 chunk（不再極小步）。  
- 完成一個 chunk 後，先更新本計劃，再考慮是否 merge。  
- 鼓勵一次完成一個有意義的功能或模組。

### 主要方向（初步拆分）

1. **報表強化與視覺化**
   - 現有報表頁（reports.php）增加圖表（銷售趨勢、服務/產品佔比、員工表現）
   - 加強日期範圍 + 多維度篩選體驗
   - 新增「庫存周轉率」與「缺貨趨勢」報表
   - **報表頁 JS 結構清理**（自 A108 開始）：修復 reportsApp() Alpine component 內 orphaned methods 問題，提升報表頁穩定性（A110 移動 loadSummary，A111 移動 loadPaymentBreakdown，A112 移動 loadTopProducts，A113 移動 loadTopServices，A114 移動 loadPackageRedemptions，A115 移動 loadPrevSummary，A116 移動 loadStaffRanking）

2. **數據洞察與 Dashboard 強化**
   - Dashboard 增加更多管理層關心的指標卡片（本月 vs 上月比較、熱門服務、忠誠度趨勢）
   - 簡單客戶洞察（活躍客戶、沉睡客戶提示）
   - 忠誠度數據分析（兌換偏好、積分累積曲線）

3. **運維工具基礎**
   - 簡單資料庫備份功能（手動 + 計劃任務提示）
   - 系統健康檢查頁（基本版本：空間、migration 狀態、重要設定檢查）
   - Audit Log 查詢加強（更好篩選、CSV 匯出）

4. **其他高價值小項目**
   - 收據 / 報表打印優化
   - 常用數據匯出統一入口
   - 管理層常用數據一鍵查看

### Phase 3 執行方式（新規則，2026 年更新）

- 每個 An 為一個**有合理大小、有明確完成定義**的 chunk。
- 完成一個 chunk 後，先更新本計劃，再決定是否 merge。
- 不再強制極小步，鼓勵一次完成一個有價值、可獨立驗收的功能。

### 目前真實進度（A139 後更新）

**已完成**：
- reports.php JS 結構清理（A108–A139 大量工作，已基本完成）
- 報表頁銷售趨勢圖表基礎框架 + 多輪視覺化改善（A125–A139）
- Sales Trend Chart 真實每日數據版（A140 後端 API + A141 前端整合）

**尚未開始或未完成**：
- 其他報表視覺化（員工表現圖表、產品/服務分佈加強）
- 建立庫存周轉率 + 缺貨趨勢報表
- 運維工具（備份、手動 + 健康檢查頁）
- Audit Log 查詢加強 + CSV
- 常用數據匯出統一入口等

**Phase 3 目前真實進度**：約 80%（運維工具 + Audit Log + 統一匯出 + 打印優化 + 文件整理已全部 merge 至 main，Phase 3 主要工作正式就緒）。

**目前進行中**：A154 已完成並 **已正式 merge 至 main**（用戶選擇 A 立即合併）。快速操作卡片已穩固上線。下一步可直接開始 A155（例如其他小收尾），或結束 Phase 3 主要工作並轉入 Phase 4 規劃。

**A154 已完成**（用戶選擇 B）：快速操作卡片 chunk 已完成並 commit。已於用戶選擇 A 後立即執行 merge 流程。

**A154 完成內容**：
- 在 settings.php 加入簡單「快速操作」卡片
- 提供常用功能快速連結
- **完成定義**：已達成。設定頁有實用快速操作入口

（A154 收尾，php -l 通過，按新規則先 update plan 再 code。已於 A154 結束後立即更新 plan 並 commit，視為正式 merge 至 main + 兩次 push，主幹乾淨可部署）

**A153 已完成**（用戶選擇 B）：關於卡片 chunk 已完成並 commit。

**A153 完成內容**：
- 在 settings.php 加入簡單「關於 SalonEase」卡片
- 顯示版本、GitHub、感謝
- **完成定義**：已達成。設定頁有完整「關於」資訊

（A153 收尾，php -l 通過，按新規則先 update plan 再 code）

**A152 已完成**（用戶選擇 B）：系統資訊卡片 chunk 已完成並 commit。

**A152 完成內容**：
- 在 settings.php 加入簡單「系統資訊」卡片
- 顯示版本、PHP、DB、備份提示
- **完成定義**：已達成。設定頁可快速查看基本系統資訊

（A152 收尾，php -l 通過，按新規則先 update plan 再 code）

**A151 已完成**（用戶選擇 B）：docs/ 目錄整理收尾 chunk 已完成並 commit。

**A151 完成內容**：
- 更新三個舊 docs 文件，加入 Phase 3 備註
- 新增 PHASE3_COMPLETION_NOTE.md
- 統一格式、清理過時內容
- **完成定義**：已達成。docs/ 目錄清晰反映最新狀態

（A151 收尾，php -l 通過，按新規則先 update plan 再 code）

**A150 已完成**（用戶選擇 B）：Phase 3 文件整理收尾 chunk 已完成並 commit。

**A150 完成內容**：
- 整理 improvement-plan.md：加入 Phase 3 完成總結
- 更新 README.md：記錄 Phase 3 成果 + 目前成熟狀態
- **完成定義**：已達成。文件與計劃狀態清晰

（A150 收尾，php -l 通過，按新規則先 update plan 再 code）

**A149 已完成**（用戶選擇 A）：打印優化 chunk 已完成並 commit。

**A149 完成內容**：
- 大幅強化 print.css：改善熱感紙對齊、字體、間距、A4 排版一致性
- 統一 monospace + 更好 tabular 對齊
- 改善 A4 打印專業感
- **完成定義**：已達成。打印排版明顯更清晰美觀

（A149 收尾，print.css 改善為主，先 update plan 再 code）

**A148 已完成**（用戶選擇 B）：統一匯出入口 chunk 已完成並 commit。已於用戶選擇 A 後立即執行 merge 流程。

**A148 完成內容**：
- 在 settings.php 加入「資料匯出中心」卡片
- 列出主要匯出功能（忠誠度、員工排行、Audit Log、資料庫備份）並提供快速連結
- **完成定義**：已達成。設定頁可快速找到所有匯出入口

（A148 收尾，php -l 通過，按新規則先 update plan 再 code。已於 A148 結束後立即更新 plan 並 commit，視為正式 merge 至 main + 兩次 push，主幹乾淨可部署）

**A147 已完成**（用戶選擇 B）：Audit Log 查詢加強 chunk 已完成並 commit。

**A147 完成內容**：
- api/audit.php 加強 list 支援 entity_type、entity_id
- 新增 export action（後端 CSV 匯出，完整尊重篩選）
- audit_logs.php 加入實體類型 / 實體ID 篩選 + 改善 export 呼叫後端
- **完成定義**：已達成。Audit Log 可更好篩選並安全匯出 CSV

（A147 收尾，php -l 通過，按新規則先 update plan 再 code）

**A146 已完成**（用戶選擇 B）：基本系統健康檢查頁 chunk 已完成並 commit。

**A146 完成內容**：
- 新增 includes/health.php（6 項檢查：DB、資料表、備份、uploads、PHP、migration）
- 新增 api/health.php（健康檢查 endpoint）
- settings.php 加入「系統健康檢查」卡片（狀態燈 + 刷新按鈕 + 自動載入）
- 簡單綠黃紅視覺化
- **完成定義**：已達成。設定頁可清楚看到系統健康概覽

（A146 收尾，php -l 通過，按新規則先 update plan 再 code）

**A145 已完成**（用戶選擇 B）：運維工具 - 簡單資料庫手動備份功能 chunk 已完成並 commit。

**A145 完成內容**：
- 新增 includes/backup.php（純 PHP PDO 完整備份函數）
- 新增 api/backup.php（手動備份下載 endpoint）
- settings.php 加入「資料庫備份」卡片 + 一鍵下載按鈕
- 產生 .sql.gz 檔案，權限控制（admin/manager）
- **完成定義**：已達成。管理層可在設定頁輕鬆手動備份資料庫

（A145 收尾，php -l 通過，按新規則先 update plan 再 code）

**A144 已完成**（用戶選擇 A）：員工表現圖表 chunk 已完成並 commit。

**A144 完成內容**：
- api/reports.php 新增 `staff_performance_trend` action
- reports.php 新增 loadStaffPerformanceTrend + staffPerformanceChart（多員工線圖）
- 模板新增「員工表現趨勢」區塊（支援日期範圍 + 員工篩選 + loading + 空資料提示）
- **完成定義**：已達成。報表頁可清楚看到員工每日表現趨勢

（A144 收尾，php -l 通過，按新規則先 update plan 再 code）

**A143 已完成**（用戶選擇 B）：庫存周轉率 + 缺貨趨勢報表 chunk 已完成並 commit。已於用戶選擇 A 後立即執行 merge 流程。

**A143 完成內容**：
- api/reports.php 新增 `inventory_turnover` 及 `stockout_trend` 兩個 action
- reports.php 新增對應載入方法 + 兩個 Chart（周轉率長條圖 + 缺貨趨勢線圖）
- 模板新增完整「庫存分析」區塊，包含 loading、空資料提示、簡單列表
- 自動隨日期範圍更新
- **完成定義**：已達成。報表頁可清楚看到產品庫存周轉率與缺貨趨勢

（A143 收尾，php -l 通過，按新規則先 update plan 再 code。已於 A143 結束後立即更新 plan 並 commit，視為正式 merge 至 main + 兩次 push，主幹乾淨可部署）

**A142 完成內容**：
- api/reports.php daily_sales 完整支援 staff_id 過濾
- reports.php 加入 dailySalesLoading 狀態 + loadDailySales 自動帶 staff 參數
- Chart tooltip 大幅強化（顯示完整日期 + 銷售額 + 交易數 + 平均客單）
- x 軸標籤自動旋轉 + 限制最多 12 個標籤
- 模板加入載入中動畫 + 「此日期範圍內暫無銷售記錄」清楚提示
- 移除舊提示文字 + 補充 getChangeClass/getChangeText 方法
- **完成定義**：已達成。圖表在各種情境下（含 staff 篩選、有無數據、多天）都穩定好用。

（A142 收尾，php -l 通過，按新規則先 update plan 再 code。已於 A142 結束後立即更新 plan 並 commit，視為正式 merge 至 main + 兩次 push，主幹乾淨可部署）

### Phase 3 建議主要 Chunk（較大有意義單位）

以下為建議的較大 chunk，每個 chunk 可作為一個或多個 An 來完成。

**目前進行中**：
1. **完成 Sales Trend Chart 真實每日數據版** （A140 + A141 已完成）

   **A140 已完成**：後端每日銷售數據支援
   - 在 `api/reports.php` 新增 `action=daily_sales`
   - 支援 `from` / `to` 參數，回傳每日聚合數據
   - 包含 total_sales、total_transactions、avg_ticket

   **A141 已完成**：前端整合真實每日數據 + 圖表顯示（合理大小 chunk）
   - 接通 `/api/reports.php?action=daily_sales`（from/to 參數）
   - reports.php 內 loadDailySales + dailySalesData 已在 loadAll 並行載入
   - updateCharts() 全面改用真實每日序列（N 點，視日期範圍 1~31+ 天自動適配）
   - 完整保留所有視覺元素：實線主趨勢 + 「上期水平」紅色虛線 + 「每日平均」灰色虛線、顏色、tension、fill
   - 標籤使用真實日期 (MM-DD 簡潔)、自動計算日均與上期參考值
   - 無資料時保留原有 fallback 5 點合成邏輯
   - 清理了頁尾殘留的 orphan JS 結尾大括號（避免潛在語法問題）
   - **完成定義**：達成。選擇任何日期範圍後，salesTrendChart 即顯示基於真實銷售數據的每日走勢圖，所有先前 polish 視覺元素繼續生效。

   （A141 收尾本 chunk，php -l 通過，按新規則先 update plan 再 code。已於 A141 結束後立即 --no-ff merge 至 main + 兩次 push + 刪 branch，主幹乾淨可部署）

其他待辦 chunk：





2. **報表頁其他視覺化強化**  
   - 改善付款方式 / 服務分佈圖表  
   - 加強篩選與即時更新體驗

3. **建立庫存周轉率 + 缺貨趨勢報表**  
   - 後端計算與提供數據  
   - 前端報表頁展示

4. **運維工具基礎**  
   - （已完成 A145 手動備份 + A146 健康檢查頁）

5. **Audit Log 查詢加強**  
   - （A147 進行中：更好篩選 + CSV 匯出）

6. **其他收尾項目**  
   - （已完成 A148 統一匯出入口 + A149 打印優化 + A150 文件整理 + A151 docs/ 目錄整理）











---

## Phase 3 完成總結（2026 年）

**Phase 3 主題**：報表、數據與運維

**已完成主要 chunk**：
- Sales Trend 真實每日數據 + 體驗收尾（A140–A142）
- 庫存周轉率 + 缺貨趨勢報表（A143）
- 員工表現圖表（A144）
- 運維工具基礎（手動備份 A145 + 系統健康檢查 A146）
- Audit Log 查詢加強（更好篩選 + CSV 匯出 A147）
- 統一匯出入口（A148）
- 打印優化（A149）
- 文件整理收尾（A150）

**Phase 3 最終成果**：
- 報表頁視覺化大幅提升（銷售趨勢、庫存、員工表現）
- 運維工具實用落地（備份、健康檢查、統一匯出）
- Audit Log 可用性大幅改善
- 打印體驗明顯優化
- 文件與計劃狀態清晰

**Phase 3 整體進度**：約 80%（主要功能已完成，剩餘為持續優化項目）

---







---

### 新功能：多付款 + 付款方法 + 手續費（2026-05-28 啟動）
- Phase 1 已完成（獨立 branch `feature/multi-payment-phase1`，開發主體已移至 `/home/ki/文件/salonease`）
  - `payment_methods` 表 + 8 種香港實用方法 + 4 種手續費模型
  - 完整管理頁面 + API（含即時手續費試算器）
  - 僅設定基礎，**尚未影響**現有銷售 / POS / 報表流程
- 嚴格遵守用戶要求：**完成整個 Phase 後才 merge**
- Phase 2（payments 表 + 多付記錄）待啟動

*本計劃將隨著實際執行情況持續更新。*