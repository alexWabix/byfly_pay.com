-- Массовое обновление статуса всех платежей на "Оплачено"
-- Дата: 2025-12-26
-- ВНИМАНИЕ: Этот скрипт обновит ВСЕ платежи!

-- Вариант 1: Обновить ВСЕ платежи на статус "paid"
UPDATE outgoing_payments 
SET 
    status = 'paid',
    paid_at = CASE 
        WHEN paid_at IS NULL THEN NOW() 
        ELSE paid_at 
    END
WHERE status != 'paid';

-- Вариант 2: Обновить только одобренные платежи (более безопасно)
-- UPDATE outgoing_payments 
-- SET 
--     status = 'paid',
--     paid_at = CASE 
--         WHEN paid_at IS NULL THEN NOW() 
--         ELSE paid_at 
--     END
-- WHERE status = 'approved';

-- Вариант 3: Обновить платежи которые были одобрены, но не отклонены
-- UPDATE outgoing_payments 
-- SET 
--     status = 'paid',
--     paid_at = CASE 
--         WHEN paid_at IS NULL THEN NOW() 
--         ELSE paid_at 
--     END
-- WHERE status IN ('approved', 'pending') 
--   AND status != 'rejected' 
--   AND status != 'cancelled';

-- Вариант 4: Обновить конкретные платежи по ID (замените ID на нужные)
-- UPDATE outgoing_payments 
-- SET 
--     status = 'paid',
--     paid_at = CASE 
--         WHEN paid_at IS NULL THEN NOW() 
--         ELSE paid_at 
--     END
-- WHERE id IN (1, 2, 3, 4, 5);

-- Проверка результата
SELECT 
    status,
    COUNT(*) as count,
    SUM(amount) as total_amount,
    currency
FROM outgoing_payments
GROUP BY status, currency
ORDER BY status;





