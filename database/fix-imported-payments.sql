-- Исправление импортированных платежей
-- Делаем все подписанными и одобренными

-- 1. Обновляем ВСЕ подписи в статус 'approved'
UPDATE payment_approvals 
SET status = 'approved',
    approved_at = COALESCE(approved_at, NOW())
WHERE payment_id IN (
    SELECT id FROM outgoing_payments WHERE payment_id LIKE 'OLD_%'
);

-- 2. Пересчитываем количество одобрений
UPDATE outgoing_payments op
SET approved_count = (
    SELECT COUNT(*) 
    FROM payment_approvals pa 
    WHERE pa.payment_id = op.id AND pa.status = 'approved'
)
WHERE op.payment_id LIKE 'OLD_%';

-- 3. Обновляем статус на 'approved' для всех где есть подписи
UPDATE outgoing_payments
SET 
    status = 'approved',
    approved_at = COALESCE(approved_at, NOW())
WHERE payment_id LIKE 'OLD_%'
AND approved_count > 0;

-- 4. Отчет
SELECT 
    'ПОСЛЕ ИСПРАВЛЕНИЯ' as moment,
    status,
    COUNT(*) as count,
    AVG(approved_count) as avg_approvals
FROM outgoing_payments
WHERE payment_id LIKE 'OLD_%'
GROUP BY status
ORDER BY count DESC;

-- 5. Проверка подписей
SELECT 
    'Подписи' as type,
    COUNT(DISTINCT payment_id) as payments_count,
    COUNT(*) as approvals_count
FROM payment_approvals
WHERE payment_id IN (
    SELECT id FROM outgoing_payments WHERE payment_id LIKE 'OLD_%'
);

