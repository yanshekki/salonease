# Phase 1 - CSRF 保護 回歸測試檢查清單

**目的**：確認已套用 CSRF 保護的主要功能在正常操作下仍可正常運作，並驗證 CSRF 機制有效。

**測試環境**：本地開發或測試環境（建議使用乾淨的測試資料庫）

---

## 1. 系統設定（Settings）

- [ ] 登入後進入「系統設定」
- [ ] 修改店舖名稱、地址、電話等資訊並儲存
  - 預期：成功儲存，出現「設定已成功儲存」
- [ ] 嘗試直接 POST 到 `/api/settings.php?action=save_shop`（不帶 csrf_token）
  - 預期：回傳 403 + "CSRF 驗證失敗"

## 2. 員工管理（Staff）

- [ ] 新增一位員工
- [ ] 編輯該員工資料（姓名、角色、佣金率）
- [ ] 停用 / 啟用該員工
- [ ] 重設該員工密碼
- [ ] 嘗試在不帶 csrf_token 的情況下呼叫 `/api/staff.php?action=create`
  - 預期：403 CSRF 錯誤

## 3. POS 結帳（最重要）

- [ ] 在 POS 頁加入多個項目（服務 + 產品 + 套票）
- [ ] 選擇客戶、指派員工、輸入備註
- [ ] 正常結帳
  - 預期：結帳成功、購物車清空、產生銷售單
- [ ] 嘗試直接 POST 到 `/api/sales.php?action=checkout`（不帶 csrf_token）
  - 預期：403 CSRF 錯誤

## 4. 客戶管理

- [ ] 新增客戶
- [ ] 編輯客戶資料
- [ ] 嘗試不帶 csrf_token 呼叫 create/update
  - 預期：403 錯誤

## 5. 套票 / 產品 / 服務 / 房間管理

- [ ] 分別測試新增、編輯、啟用/停用
- [ ] 驗證缺少 csrf_token 時會被擋

---

## 注意事項

- 測試時請使用正常流程 + 嘗試「攻擊」流程（省略 csrf_token）。
- 若發現任何功能在正常流程下無法運作，請立即回報。
- 目前 Audit Log 尚未啟用，僅做 CSRF 保護驗證。

**測試人**：________________  
**測試日期**：________________  
**結果**：□ 通過　□ 需修正（請註明）

---

**注意**：Audit Log 基礎已建立，關鍵操作（銷售結帳、員工管理、設定、產品/服務/房間/套票/客戶/預約/購物車模板）均已記錄。

**CSRF 保護狀態**：主要管理區域 + login、upgrade.php、cart_templates.php **已全部完成保護**（所有修改性 POST/表單/API）。

**✅ CSRF 回歸測試階段已正式啟動**

建議依下方檢查清單，逐一驗證「正常流程」與「缺少 csrf_token 的攻擊流程」。

**Audit Log 目前覆蓋範圍**（持續更新中）：
- 銷售結帳、套票扣減
- 員工管理
- 系統設定修改
- 產品 / 服務 / 房間 / 套票 的主要操作

**已建立簡單審計查詢頁面**：`/audit_logs.php`（僅限 admin），支援篩選 + 分頁 + CSV 匯出 + 文字搜尋 +「只看我的操作」 + 細節預覽 + 每頁顯示數量 + 重置篩選

**✅ 可開始執行測試**：請參考下方檢查清單逐步進行。測試時可一併觀察 audit_logs 表是否有正確記錄。

點擊表格中的「操作」標籤可快速篩選該類型記錄。操作類型欄位旁有清除按鈕，並顯示每種操作的記錄數量（快速篩選按鈕亦已加入）。

**房間管理** 的 create / update / toggle 均已記錄 Audit Log。

**最新改善（audit_logs.php）**：
- 「操作類型」下拉選單現在顯示**伺服器端真實總數量**（例如 `sale.created (128)`），不受「limit 200」載入限制影響，數字完全準確。
- API `actions` 端點已升級為 `SELECT action, COUNT(*) as cnt GROUP BY`。
- 保留 client-side actionCounts（顯示目前載入資料內的計數）作為補充參考。

**客戶管理 Audit Log**：
- `customer.created` 及 `customer.updated` 已加入（與產品/員工/服務一致）。

**最新改善（2026/5 後續 + 本次更新）**：
- 補齊剩餘 CSRF 保護：`api/cart_templates.php`（create/delete + Audit Log）、`login.php`（表單 + POST 處理 + api/auth.php login）、`upgrade.php`（管理員升級操作）
- 新增 `cart_template.created`、`cart_template.deleted` Audit Log
- **集中驗證函式庫**（Phase 1 核心）：`includes/functions.php` 新增 validate_required、validate_hk_phone、validate_money、validate_email、validate_positive_int、validate_date、validate_length、sanitize_string
- staff.php create/update 已改用新驗證函式 + 角色白名單 + sanitize
- **權限小幅強化**：`commissions.php`、`reports.php` 及對應 API 改為 require_role(['admin', 'manager'])，receptionist 無法查看完整佣金/銷售報表
- improvement-plan.md 與本 checklist 同步更新

**✅ CSRF 核心保護已全面完成**。建議重新執行關鍵測試（login、upgrade、cart_templates）。

**Phase 1 驗證與權限進度**：
- 驗證函式庫已全面套用至 sales, customers, products, packages, services, rooms, appointments
- 引入 log_error 集中錯誤記錄輔助函式
- commissions / reports 已限制 receptionist 存取
- **Phase 1 已正式完成**，可開始 Phase 2（庫存 + 忠誠度）

**快速 CSRF 驗證指令提示**（建議在測試前先執行）：
1. 先在瀏覽器登入，開啟「系統設定」頁面，正常修改一項並儲存（觀察成功訊息 + audit log）。
2. 使用 curl 攻擊測試（無 csrf_token 應被 403 拒絕）：
   - 準備：先用瀏覽器正常操作一次，複製頁面上的 csrf_token 值（或從 Network 面板看 POST）。
   - 正常流程 + 攻擊流程指令將在下次回覆直接提供（copy-paste 即可）。

點擊表格中的「操作」標籤可快速篩選該類型記錄。操作類型欄位旁有清除按鈕，並顯示每種操作的記錄數量（快速篩選按鈕亦已加入）。