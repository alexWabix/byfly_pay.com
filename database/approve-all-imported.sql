-- Простой скрипт: Одобрить ВСЕ импортированные платежи
-- Дата: 2025-12-26

-- 1. Обновляем ВСЕ подписи в 'approved'
UPDATE payment_approvals
SET 
    status = 'approved',
    approved_at = NOW()
WHERE payment_id IN (
    SELECT id FROM outgoing_payments WHERE payment_id LIKE 'OLD_%'
);

-- 2. Обновляем счетчик
UPDATE outgoing_payments
SET approved_count = (
    SELECT COUNT(*) FROM payment_approvals 
    WHERE payment_id = outgoing_payments.id
)
WHERE payment_id LIKE 'OLD_%';

-- 3. Меняем статус на 'approved'
UPDATE outgoing_payments
SET 
    status = 'approved',
    approved_at = NOW()
WHERE payment_id LIKE 'OLD_%';

-- 4. Проверка
SELECT 
    status,
    COUNT(*) as count
FROM outgoing_payments
WHERE payment_id LIKE 'OLD_%'
GROUP BY status;




