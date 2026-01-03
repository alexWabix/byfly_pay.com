-- Исправление транзакций с небольшим остатком (проблема округления)
-- Дата: 2025-12-27

-- Показать транзакции которые будут исправлены
SELECT 
    id,
    transaction_id,
    amount,
    paid_amount,
    remaining_amount,
    status,
    GREATEST(500, amount * 0.005) as tolerance,
    CASE 
        WHEN remaining_amount <= GREATEST(500, amount * 0.005) THEN 'WILL FIX'
        ELSE 'OK'
    END as action
FROM transactions
WHERE status = 'partially_paid'
  AND remaining_amount > 0
  AND remaining_amount <= GREATEST(500, amount * 0.005);

-- Исправить все транзакции где остаток меньше 500₸ или 0.5% от суммы
UPDATE transactions
SET 
    status = 'paid',
    remaining_amount = 0
WHERE status = 'partially_paid'
  AND remaining_amount > 0
  AND remaining_amount <= GREATEST(500, amount * 0.005);

-- Показать результат
SELECT 
    COUNT(*) as total_fixed,
    SUM(remaining_amount) as total_rounded_off
FROM transactions
WHERE status = 'paid'
  AND remaining_amount = 0
  AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);

-- Примеры до/после:
-- Сумма 657 756₸, остаток 66.67₸ → допуск 1 315₸ → ИСПРАВЛЯЕТСЯ
-- Сумма 641 753₸, остаток 66.33₸ → допуск 1 283₸ → ИСПРАВЛЯЕТСЯ
-- Сумма 100 000₸, остаток 500₸ → допуск 200₸ → НЕ исправляется (реально частичная)

