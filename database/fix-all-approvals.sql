-- ПОЛНОЕ исправление всех подписей
-- Создает все недостающие подписи и одобряет их
-- Дата: 2025-12-26

-- 1. ОБНОВЛЯЕМ существующие подписи в статус 'approved'
UPDATE payment_approvals pa
JOIN outgoing_payments op ON pa.payment_id = op.id
SET 
    pa.status = 'approved',
    pa.approved_at = COALESCE(pa.approved_at, op.created_at, NOW())
WHERE op.payment_id LIKE 'OLD_%';

-- 2. Создаем ВСЕ подписи заново из company_pays
-- Подпись 1
INSERT IGNORE INTO payment_approvals (payment_id, admin_id, status, approved_at, created_at)
SELECT 
    op.id,
    COALESCE(cp.user_podpis_1, 2) as admin_id,
    'approved' as status,
    COALESCE(cp.date_podpis1, cp.date_create) as approved_at,
    cp.date_create
FROM company_pays cp
JOIN outgoing_payments op ON op.payment_id = MD5(CONCAT('OLD_', cp.id))
WHERE cp.id IS NOT NULL;

-- Подпись 2
INSERT IGNORE INTO payment_approvals (payment_id, admin_id, status, approved_at, created_at)
SELECT 
    op.id,
    COALESCE(cp.user_podpis_2, 12) as admin_id,
    'approved' as status,
    COALESCE(cp.date_podpis2, cp.date_create) as approved_at,
    cp.date_create
FROM company_pays cp
JOIN outgoing_payments op ON op.payment_id = MD5(CONCAT('OLD_', cp.id))
WHERE cp.id IS NOT NULL;

-- 3. Обновляем счетчик подписей
UPDATE outgoing_payments op
SET approved_count = (
    SELECT COUNT(*) 
    FROM payment_approvals pa 
    WHERE pa.payment_id = op.id AND pa.status = 'approved'
)
WHERE op.payment_id LIKE 'OLD_%';

-- 4. Обновляем статус платежей на 'approved'
UPDATE outgoing_payments
SET 
    status = 'approved',
    approved_at = (
        SELECT MAX(approved_at) 
        FROM payment_approvals pa 
        WHERE pa.payment_id = outgoing_payments.id
    )
WHERE payment_id LIKE 'OLD_%'
AND approved_count >= 2;

-- 5. Отчет
SELECT '=== ОТЧЕТ ПОСЛЕ ИСПРАВЛЕНИЯ ===' as info;

SELECT 
    'Платежи по статусам' as type,
    status,
    COUNT(*) as count
FROM outgoing_payments
WHERE payment_id LIKE 'OLD_%'
GROUP BY status;

SELECT 
    'Подписи по статусам' as type,
    pa.status,
    COUNT(*) as count
FROM payment_approvals pa
JOIN outgoing_payments op ON pa.payment_id = op.id
WHERE op.payment_id LIKE 'OLD_%'
GROUP BY pa.status;

SELECT 
    'Проверка' as type,
    COUNT(DISTINCT op.id) as total_payments,
    SUM(op.approved_count) as total_approvals,
    SUM(op.approved_count) / COUNT(DISTINCT op.id) as avg_approvals_per_payment
FROM outgoing_payments op
WHERE op.payment_id LIKE 'OLD_%';

