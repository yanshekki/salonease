# Phase 1 完成報告：付款方法管理 + 手續費基礎

**Phase**：1 / 「每張帳單支援多次付款 + 分期/周期性付款 + 付款方法 + 手續費」  
**Branch**：`feature/multi-payment-phase1`  
**完成日期**：2026-05-28  
**負責人**：Grok（依用戶批准計劃執行）

---

## 快速驗證指南（請優先執行）

**Option A 當前策略**：先完整驗證，再 commit。

### 推薦執行順序

1. **自動檢查**（強烈建議先跑這個）
   ```bash
   cd /home/ki/文件/salonease
   chmod +x docs/verify-phase1.sh
   ./docs/verify-phase1.sh
   ```

2. **手動關鍵驗證**
   - 備份資料庫
   - 透過瀏覽器進入 `/upgrade.php` 執行 Migration 009
   - 登入後台 → 系統設定 → 點擊「💳 付款方法管理」卡片
   - 完整測試：新增、編輯、即時手續費試算器、↑↓排序、啟用/停用、刪除保護
   - 最後確認現有 POS 結帳、報表、收據完全正常

3. 驗證全部通過後，直接告訴我：
   > 「Phase 1 驗證全部通過」

我會立刻幫你做乾淨 commit（標註 Phase 1 完成，尚未 merge）。

---

## 一、Phase 1 目標回顧（已全部達成）

- [x] 建立獨立的 `payment_methods` 表（含 4 種手續費模型）
- [x] 提供完整的管理頁面（新增/編輯/啟用/停用/排序/刪除 + 即時手續費試算器）
- [x] 提供高品質 API（list / create / update / toggle / reorder / delete / calculate_fee）
- [x] 完全符合現有專案規範（CSRF、驗證、Audit Log、權限、API 風格）
- [x] **零破壞**：不改動任何現有 `sales`、`reports`、`POS`、`收據` 流程
- [x] 香港市場 8 種實用付款方法已預設（依用戶提供費率參考）

---

## 二、交付物清單

| 檔案 | 說明 | 位置 |
|------|------|------|
| `migrations/009_add_payment_methods_table.php` | 核心 migration（表 + 8 筆種子資料） | 已建立 |
| `sql/schema.sql` | 新鮮安裝用 schema（已同步） | `/home/ki/文件/salonease/sql/schema.sql` |
| `api/payment_methods.php` | 完整管理 API（含後端權威計算函式） | 已建立 |
| `payment_methods.php` | 管理頁面（豐富即時試算器 + 排序 UI） | 已建立 |
| `settings.php` | 入口卡片整合 | 已更新 |
| `install.php` | 加入 Phase 2 遷移提示註解 | 已更新 |
| `docs/PHASE_MULTI_PAYMENT_1.md` | 本文件 | 當前 |

---

## 三、手動驗證清單（請在合併前執行）

### 3.1 資料庫升級測試
1. 備份目前資料庫（強烈建議）
2. 登入管理員 → 前往 `/upgrade.php`
3. 確認出現「Migration 009」且描述正確
4. 點擊「一鍵升級」
5. 觀察日誌出現：
   - `✓ payment_methods 表建立完成`
   - `✓ 已插入/更新 8 種付款方法`
6. 使用 phpMyAdmin / 終端機驗證：
   ```sql
   SELECT * FROM payment_methods ORDER BY sort_order;
   SELECT COUNT(*) FROM payment_methods;  -- 應為 8
   ```

### 3.2 管理頁面功能測試
1. 從「系統設定」頁點擊「付款方法管理」卡片進入
2. 確認 8 筆資料正確顯示 + 範例手續費（$1000）
3. 測試「新增」：
   - 加入一筆測試方法（例如「現金 + 禮券」）
   - 切換不同 fee_model，觀察即時試算器是否正確計算
4. 測試「編輯」：修改名稱、費率、備註
5. 測試排序：使用 ↑ ↓ 按鈕多次，刷新後排序應持久化
6. 測試啟用/停用：停用一筆，確認前端無法選（未來 POS 會用到）
7. 測試刪除：
   - 系統預設前 8 筆應無法刪除（有提示）
   - 自己新增的測試資料可正常刪除

### 3.3 API 直接測試（可選，開發者用）
```bash
# 需先登入取得 session
curl -b cookie.txt "http://localhost/api/payment_methods.php?action=list&active=1"
curl -b cookie.txt -X POST -d "action=calculate_fee&method_id=4&amount=680" \
     "http://localhost/api/payment_methods.php"
```

### 3.4 無破壞性驗證
- [ ] POS 結帳流程完全正常（快速結帳、打印收據）
- [ ] 報表頁「付款方式分佈」數字與之前一致
- [ ] 客戶頁、Dashboard、佣金頁無任何錯誤
- [ ] `/upgrade.php` 再次進入應顯示「無待執行 migration」

---

## 四、已知限制（Phase 1 刻意保留）

- 尚未建立 `payments` 表（Phase 2 負責）
- 舊 `sales.payment_method` 欄位仍然存在且繼續運作（向後相容）
- 目前刪除保護只擋 id <= 8，Phase 2 會加強「已有付款記錄不可刪」檢查
- POS 與收據仍使用舊單一付款方式（Phase 2 開始逐步替換）

這些都是**設計決策**，確保 Phase 1 可以安全獨立 merge。

---

## 五、後續 Phase 2 準備建議

Phase 2 應包含：
- `payments` 表 + `sales` 欄位擴充 migration 010
- `api/payments.php`（record / list / refund）
- `api/sales.php` checkout 改動（相容模式）
- 收據多筆付款顯示
- 開始在 POS 提供「記錄補款」入口

建議在 Phase 1 merge 後，盡快啟動 Phase 2 branch，避免長期停留在 feature branch。

---

## 六、Git 提醒（重要）

**本 Phase 所有 commit 必須明確標註**：
```
feat(payment-methods): Phase 1 完成 - 付款方法管理 + 手續費基礎

⚠️ 此為 Phase 1 獨立 branch，嚴禁中途 merge 至 main
```

只有當你（用戶）確認「Phase 1 通過驗證」後，才可執行：
```bash
git checkout main
git merge --no-ff feature/multi-payment-phase1
git push origin main
```

---

**Phase 1 狀態**：開發完成，等待用戶執行上方驗證清單後給予 merge 指示。

如有任何問題或需要調整，請直接告知。
