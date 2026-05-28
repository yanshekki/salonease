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

**Phase 1 執行進度**（截至最新 - 已完成）：
- CSRF 保護：已涵蓋所有修改性 POST 端點（含 login / upgrade / cart_templates），核心目標 100% 完成
- Audit Log：關鍵操作已全面覆蓋
- 驗證與錯誤處理：**集中驗證函式庫已全面套用**至 sales, customers, products, packages, services, rooms, appointments 等主要 API + 引入 log_error 輔助函式
- 權限小幅強化：已執行
- **Phase 1 安全性硬化與基礎強化已正式完成**（2026 年）
- 最大剩餘風險（appointments）已於 2026/5 消除
- 進入 Phase 2 準備階段

---

**執行方式**：採用「小步穩健推進」模式，每個有意義 chunk 即 commit + merge origin/main + push，main 永遠保持穩定。

---

*本計劃將隨著實際執行情況持續更新。*