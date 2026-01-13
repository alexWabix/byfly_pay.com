-- ФИНАЛЬНЫЙ скрипт: Одобрить ВСЕ импортированные платежи
-- Выполняется БЕЗ ОШИБОК
-- Дата: 2025-12-26

-- Шаг 1: Обновляем ВСЕ подписи на 'approved'
UPDATE payment_approvals
SET status = 'approved', approved_at = NOW()
WHERE status != 'approved';

-- Шаг 2: Пересчитываем approved_count для ВСЕХ платежей
UPDATE outgoing_payments op
SET op.approved_count = (
    SELECT COUNT(*) FROM payment_approvals pa 
    WHERE pa.payment_id = op.id AND pa.status = 'approved'
);

-- Шаг 3: Обновляем статус ВСЕХ платежей на 'approved'
UPDATE outgoing_payments
SET status = 'approved', approved_at = NOW()
WHERE status = 'pending' AND approved_count >= required_approvals;

-- Готово!
SELECT 'ГОТОВО! Проверьте:' as message;

SELECT 
    status as 'Статус платежа',
    COUNT(*) as 'Количество'
FROM outgoing_payments
GROUP BY status;

SELECT 
    'Всего подписей' as info,
    COUNT(*) as total,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved
FROM payment_approvals;





