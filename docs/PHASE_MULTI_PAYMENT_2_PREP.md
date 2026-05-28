# Phase 2 準備筆記（付款記錄核心）

**前置條件**：Phase 1 已 merge 且通過完整驗證。

## 核心任務（建議順序）
1. Migration 010：
   - 新增 `payments` 表（完整設計見原計劃）
   - 在 `sales` 表安全加入 `amount_paid`、`payment_status`、`primary_payment_method_id`
   - 為所有既有 sales 建立對應單筆 payment 記錄（legacy 遷移）

2. `api/payments.php`：
   - record（核心，含 transaction 更新 sales 狀態）
   - list_by_sale
   - refund

3. 修改 `api/sales.php` checkout：
   - 增加 `payment_mode` 參數（full / partial / unpaid）
   - 相容模式：舊流程自動建立 1 筆 payment

4. 收據強化：
   - 支援顯示多筆付款 + 目前餘額

5. 提供簡單「補款」入口（可先做成 Modal）

## 重要設計提醒
- 繼續保留 `sales.payment_method` 欄位至少 6 個月（相容）
- 手續費計算邏輯已在 Phase 1 後端存在，可直接複用
- 所有 payment 操作必須呼叫 `log_activity`

## 建議 Git 流程
```bash
git checkout -b feature/multi-payment-phase2
# ... 開發 ...
# 完成後才 merge
```

此文件僅供參考，實際 Phase 2 啟動時請重新參考主計劃文件。
