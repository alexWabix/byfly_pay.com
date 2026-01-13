-- Проверка токенов для подписания платежей
-- Дата: 2025-12-26

-- 1. Проверить есть ли вообще токены
SELECT 
    COUNT(*) as total,
    COUNT(approval_token) as with_tokens,
    COUNT(*) - COUNT(approval_token) as without_tokens
FROM payment_approvals;

-- 2. Показать последние 10 записей с токенами
SELECT 
    pa.id,
    pa.payment_id,
    pa.approval_token,
    pa.status,
    pa.sms_sent,
    pa.sms_sent_at,
    a.name as admin_name,
    a.phone as admin_phone,
    op.title as payment_title,
    op.amount
FROM payment_approvals pa
JOIN admins a ON pa.admin_id = a.id
JOIN outgoing_payments op ON pa.payment_id = op.id
WHERE pa.approval_token IS NOT NULL
ORDER BY pa.id DESC
LIMIT 10;

-- 3. Найти pending подписи с токенами (готовые к использованию)
SELECT 
    pa.id,
    pa.approval_token,
    pa.status,
    a.phone as admin_phone,
    op.title as payment_title,
    op.amount,
    op.currency,
    CONCAT('https://byfly-pay.com/approve-payment?token=', pa.approval_token) as approval_link
FROM payment_approvals pa
JOIN admins a ON pa.admin_id = a.id
JOIN outgoing_payments op ON pa.payment_id = op.id
WHERE pa.status = 'pending' 
  AND pa.approval_token IS NOT NULL
ORDER BY pa.id DESC
LIMIT 5;

-- 4. Проверить конкретный токен (замените на ваш)
SELECT 
    pa.*,
    a.phone,
    a.name as admin_name,
    op.title as payment_title,
    op.amount,
    op.currency,
    op.status as payment_status
FROM payment_approvals pa
JOIN admins a ON pa.admin_id = a.id
JOIN outgoing_payments op ON pa.payment_id = op.id
WHERE pa.approval_token = 'dd55275ca041e42d93cbade54fb254c8';

-- 5. Если токенов нет - сгенерировать их
-- UPDATE payment_approvals 
-- SET approval_token = MD5(CONCAT(id, payment_id, admin_id, UNIX_TIMESTAMP(), RAND()))
-- WHERE approval_token IS NULL;





