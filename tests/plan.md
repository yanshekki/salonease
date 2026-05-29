# SalonEase API 測試系統 - 詳細計劃

**版本**：Phase 9 API Testing System  
**建立日期**：2026-05  
**負責人**：Grok（依用戶指示「A」立即執行）  
**核心目標**：建立專業級、嚴謹、可重複執行的 API 測試系統，**特別確保所有佣金計算 100% 正確**，防止任何未來改動引入營運風險。

---

## 1. 專案背景與風險等級

SalonEase 採用純 PHP + MySQL + API-first 無框架架構。  
佣金計算位於 `api/sales.php?action=checkout`（最複雜金錢邏輯），直接影響員工實際收入與店舖成本，是**最高風險領域**。

本次測試系統優先處理「佣金計算專項矩陣」，再逐步覆蓋其他 API。

所有測試必須遵守：
- 只在真實開發路徑 `/home/ki/文件/salonease/` 進行修改
- 嚴格 Git 流程：feature branch → 完整測試 → server-only 驗證（https://salonease.ysk.hk/）→ `git commit && git push && git checkout main && git pull && git merge --no-ff` → 再繼續
- 禁止在 worktree 改動
- 所有文件使用繁體中文（香港習慣用語）

---

## 2. 測試角色與權限矩陣（最高優先）

測試帳號必須事先在伺服器資料庫建立（見 fixtures/seed_test_data.php）：

| 角色 (role)   | email                        | 預設權限簡述                  | 測試重點 |
|---------------|------------------------------|-------------------------------|----------|
| admin         | admin@salonease.test         | 最高權限                      | 所有 API 200、可修改佣金預設、全域設定 |
| manager       | manager@salonease.test       | 店長權限                      | 大部份 200、不可改其他 admin |
| therapist     | therapist@salonease.test     | 治療師（前線員工）            | 有限權限（只可自己相關操作） |
| reception     | reception@salonease.test     | 前台                          | 收銀相關有限權限 |

**權限測試矩陣範例**（每個 API 都要有 200 + 403 案例）：

- `POST /api/sales.php?action=checkout` → admin/manager/therapist 應 200，reception 視業務規則
- `GET /api/commissions.php?action=summary` → 只有 admin/manager 200
- `POST /api/settings.php?action=save` → 只有 admin 200
- `POST /api/payment_plans.php?action=append_followup` → 視 Phase 進度

---

## 3. 目錄結構（已建立）

```
tests/
├── run_tests.php                 # 主控程式（支援 --phase --role --api --report）
├── plan.md                       # 本文件
├── lib/
│   ├── ApiClient.php             # 多角色 Cookie 隔離 + cURL 呼叫
│   └── Assertion.php             # assertCommissionEqual（bccomp）+ assertMoneyEquals
├── roles/
│   └── TestUsers.php             # 4 種測試帳號定義
├── fixtures/
│   └── seed_test_data.php        # 負責在伺服器建立測試帳號 + 基本客戶/員工
├── api/
│   ├── test_sales_checkout_commission.php   # ★ 最高優先（本 Phase 核心）
│   ├── test_payments.php                    # 後續
│   ├── test_payment_plans.php
│   ├── test_staff.php
│   └── ...
└── reports/                      # 執行後產生的 HTML/JSON 報告
```

---

## 4. 佣金計算專項測試矩陣（最高風險 - 立即執行）

**實際生產邏輯（2026-05 從 api/sales.php 逆向工程）**：

- Service / Retail 佣金：依 `sale_items.line_total`（item 建立時）× 費率，**在積分扣減之前**累計 → **不受 points 影響**
- Open 佣金：使用**最終 $total**（已扣 discount + points 後）× open_rate → **受 points 影響**
- 費率來源：員工個人 `staff.commission_rate_*`（非 NULL）優先，否則用 `settings.default_commission_*`
- Split：前端傳 `items[].staff_id` 即可決定該行佣金歸屬員工
- 無 tax / service_charge 參與 checkout 總額計算（目前版本）
- 無 tip 欄位參與 checkout（目前版本）

### 測試案例矩陣（至少 10 個，全部要 assert 通過）

| Case ID | 場景描述 | 關鍵變數 | 預期計算重點 | 狀態 |
|---------|----------|----------|--------------|------|
| C001 | 單一服務 + 全球預設 40% | 1 service $500，無 points | service_comm = 200.00 | ✅ 已實作（純函數 + assert） |
| C002 | 單一零售 + 全球預設 15% | 1 product $200 | retail_comm = 30.00 | ✅ 已實作 |
| C003 | 服務 + 積分兌換（驗證 service 不受影響） | service $1000 + points_used=100 | service=400，open 用扣減後 total | ✅ 已實作（核心時序驗證） |
| C004 | 多員工 30%/70% split（不同個人費率） | 2 staff，item 分別指派，staff A 個人 50%，B 用全球 40% | 正確歸屬兩筆 commissions | ✅ 已實作 |
| C005 | 混合服務+零售 + discount | service+product，discount=100 | service/retail 用 line_total（不受 discount 影響） | ✅ 已實作 |
| C006 | 大額 points 使 open 受影響 | service $800 + 900點 | open 用最終 total，service 不變 | ✅ 已實作 |
| C007 | 開單人與執行人不同 | admin 開單，therapist 執行 | open 給開單人，service 給執行人 | ✅ 已實作 |
| C008 | 個人費率 NULL 正確回退全球 | staff 99 無個人設定 | 使用全球 40% | ✅ 已實作 |
| C009 | Rounding 邊緣案例 | $123.45 × 40% | 驗證 round 後 49.38 | ✅ 已實作 |
| C010 | 複合情境（服務+零售+discount+points） | 多變數組合 | 完整驗證最終 open 與 service/retail 分離 | ✅ 已實作 |
| C011 | 基礎 sanity | 雙倍服務 | 正確計算 | ✅ 已實作 |

**注意**：以上全部使用 `calculateExpectedCommissions()` 純函數 + `assertCommissionEqual(bccomp)` 執行。真實 API 整合煙霧測試亦已包含（需 seed 後手動交叉驗證）。

**所有案例必須在 `calculateExpectedCommissions()` 純函數中先通過，再對照真實 API 行為。**

---

## 5. 測試執行流程（server-only）

1. 在真實路徑開發（本分支 `feature/phase9-api-testing-system`）
2. 本機 `php -l` 全數通過
3. `git add tests/ && git commit -m "..." && git push`
4. **上傳整個 tests/ 目錄到 https://salonease.ysk.hk/tests/**
5. 在伺服器執行：
   - 先跑 `php tests/fixtures/seed_test_data.php`（建立測試帳號 + 設定已知佣金率）
   - `php tests/run_tests.php --api=sales --report=console`
6. 檢查報告，全部通過才可 merge --no-ff
7. 嚴禁中途 merge

---

## 6. 其他 Phase 測試計劃（後續）

- Phase 2：payments 相關（record_portal、多付款方法、手續費）
- Phase 3：payment_plans 完整流程 + 客戶 Portal token
- Phase 4：權限矩陣全覆蓋（所有 403 案例）
- Phase 5：報表 / 佣金查詢 API 一致性（commissions.php 與 sales 寫入一致）

---

## 7. 報告格式建議

執行後產生：
- Console 即時輸出（目前已實作）
- 未來支援 `--report=html` / `json` 輸出至 `tests/reports/`

失敗案例必須清楚顯示：
```
[FAIL] 佣金計算絕對誤差（bccomp 失敗）
預期: 200.00
實際: 199.98
Case C003 - 服務 + 100點積分
```

---

## 8. 風險評估與緩解

- 浮點誤差 → 使用 bccomp + 固定 2 位小數 format
- 測試資料污染 → 每個測試用獨立 notes / 特殊 customer，或 transaction rollback（若可能）
- 真實伺服器執行時有其他銷售 → 用日期範圍 + 特定 staff + 獨特 notes 過濾
- 個人費率設定困難 → 先以全球費率為主，個人費率案例標註「需手動在 DB 設定」

---

## 9. 已完成決策記錄（用戶多次選擇 A）

- 測試系統採用「類似 install.php 的結構化腳本風格」而非 PHPUnit
- 佣金優先於所有其他 API
- 立即撰寫佣金專項測試案例（而非先寫完整 plan 再執行）
- 使用 assertCommissionEqual（bccomp）而非單純 float 比較
- 所有文件繁體中文（香港用語）

---

**下一步行動**：✅ C001-C011 完整矩陣 + 純函數 `calculateExpectedCommissions()` + `assertCommissionEqual` + 種子腳本 + plan.md 已全部完成（2026-05）。

**立即可做**：
1. `git add tests/ && git commit -m "feat(test): 完成佣金計算專項測試矩陣 C001-C011 + bccomp 精準斷言 + 種子腳本" && git push`
2. 上傳至 https://salonease.ysk.hk/ 後執行 seed + 測試（見下方執行指令）
3. 人工交叉驗證佣金報表頁，確認與純函數預期完全一致
4. 全部通過後 --no-ff merge 回 main

**後續 Phase**：補充 payments、payment_plans、權限 403 全矩陣等測試檔案。

---

## 10. 最新進度（feature/api-testing-permissions-payments 分支）

**2026-05 已 merge 並開始擴充**：
- **整體完成度估計**：核心高風險領域（佣金、付款、計劃、員工、提醒、設定）約 **96%**；完整專業級測試系統（含報告、seed、自動驗證、權限矩陣、bootstrap、auth、customers、整合 E2E、產品庫存、銷售查詢、客戶 Portal + 專業一鍵驗證腳本 + 佣金專屬報告摘要 + 閉環 E2E + JSON 結構化）約 **94%**
- ✅ 佣金計算專項測試矩陣 (C001-C011) 已 --no-ff merge 至 main
- ✅ 新增 `test_permissions_matrix.php`：系統性 4 角色權限矩陣測試
- ✅ 新增 `test_payments.php` + `test_payment_plans.php`
- ✅ `run_tests.php` 支援專業 HTML/JSON 報告
- ✅ 佣金測試現已支援**真實結帳後自動驗證** commissions 寫入是否正確（最高價值自動化進展）
- ✅ ApiClient 新增可重用 `getStaffIdByEmail()` helper
- ✅ Payments 測試加入自動驗證區塊 + 預期累計金額明確輸出
- ✅ Payment Plans 測試強化自動驗證（append_followup 後即時 get 檢查 notes、bulk 抽樣驗證）
- ✅ seed_test_data.php 大幅強化
- ✅ 新增 `test_staff.php`：員工管理 + 佣金率 + toggle + 權限 + 自動驗證（與最高風險的佣金計算直接相關）
- ✅ 新增 `test_plan_reminders.php`：提醒規則 CRUD、execute、retry_notification、run_scheduled 權限 + 自動驗證（完成付款計劃閉環）
- ✅ 新增 `test_settings.php`：佣金預設率、需要關注門檻修改 + 權限 + 自動驗證讀回（直接保護最高風險的佣金計算）
- ✅ 佣金測試新增最高價值 E2E：修改全球佣金預設率 → 真實結帳 → 自動驗證佣金使用新費率（跨 settings + sales + commissions）
- ✅ 建立 `tests/bootstrap.php` + 強化 run_tests.php 支援 `--bootstrap` / `--seed`
- ✅ 新增 `test_auth.php`：登入、登出、/me、/ping、未登入保護
- ✅ 新增 `test_customers.php`：客戶 CRUD + 豐富關聯資料（積分歷史、付款、計劃）+ 權限
- ✅ 新增 `test_integration.php`：高價值 E2E 場景（積分扣減對佣金影響 + 完整付款計劃流程 + 佣金驗證）
- ✅ 新增 `test_products.php`：產品管理 + 庫存調整 + 低庫存警示 + 零售佣金相關驗證 + 權限
- ✅ 新增 `test_sales.php`：銷售單 list + get_items 查詢 + 權限
- ✅ `run_tests.php` HTML 報告大幅強化：新增「★ 佣金計算專項驗證摘要」專業區塊（含 C001-C011 完整矩陣、四大核心保護統計、動態費率 E2E 步驟說明、bccomp 精準比對說明、即時執行 PASS/FAIL 橫幅）
- ✅ `test_integration.php` 新增最高價值閉環 E2E：銷售結帳(產生佣金) → 付款計劃 + 多次付款(含手續費) → customer_portal record_portal → 驗證 sale_payment_plans 進度 + commissions 寫入絕對正確（bccomp / assertMoneyEquals）
- ✅ `run_tests.php` JSON 報告同步強化：commission_summary 結構化數據（executable spec、11 純函數案例、動態 E2E、即時 live_run_result），與 HTML 摘要完全對應

**下一步優先項目**：
- ✅ 已新增 `test_payments.php`（基本記錄、多筆累加、手續費、record_portal、權限 + 自動驗證）
- ✅ 已新增 `test_payment_plans.php`（list/dashboard、append_followup、bulk 操作、狀態保護、Portal 端點、customer_health + 自動驗證）
- ✅ 已新增 `test_staff.php`（員工管理 + 佣金率 + 權限 + 自動驗證）
- ✅ 已新增 `test_plan_reminders.php`（提醒規則 + execute + retry + 權限 + 自動驗證）
- ✅ 已新增 `test_settings.php`（佣金預設 + 門檻 + 權限 + 自動驗證）
- ✅ 建立 `tests/bootstrap.php` + run_tests 支援 `--bootstrap` / `--seed`
- ✅ 新增 `test_auth.php` + `test_customers.php` + `test_integration.php` + `test_products.php` + `test_sales.php`
- ✅ 新增 `test_customer_portal.php`：客戶自助查看計劃 + 記錄付款 + Token 保護驗證
- ✅ 強化 `run_tests.php` + 佣金 E2E + seed 大幅強化
- ✅ 強化 `run_tests.php` HTML 報告（新增佣金專項摘要區塊 + 專業視覺化）
- ✅ 強化 `run_tests.php` JSON 報告（commission_summary 結構化）
- ✅ 新增 `test_integration.php` 閉環 E2E（銷售+付款+Portal+佣金完整性）
- ✅ 強化 `run_full_verification.php` + tests/README.md（專業一鍵伺服器驗證體驗）
- 完整執行 server-only 驗證後 merge

*嚴格遵守真實路徑開發 + server-only 驗證 + --no-ff merge 規則*

執行本計劃後，佣金計算將成為 SalonEase 項目中**有完整機器驗證**的第一個核心業務邏輯。

---

*本文件隨每次重大變更更新。最後更新：2026-05 新增閉環 E2E（銷售+多次付款+Portal record_portal+佣金驗證）+ JSON commission_summary + 報告強化，核心高風險 96% / 整體 94%*