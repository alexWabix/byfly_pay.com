-- SQL запрос для получения всех оплаченных платежей
-- Дата: 2025-12-26

SELECT 
    op.id,
    op.payment_id,
    op.title,
    op.description,
    op.amount,
    op.currency,
    op.recipient,
    op.status,
    op.created_at,
    op.approved_at,
    op.paid_at,
    
    -- Информация о категории
    pc.name as category_name,
    pc.icon_emoji as category_icon,
    pc.color as category_color,
    
    -- Создатель платежа
    creator.name as created_by_name,
    creator.phone as created_by_phone,
    
    -- Кто отметил как оплаченный
    payer.name as paid_by_name,
    payer.phone as paid_by_phone,
    
    -- Документы
    op.document_url,
    op.document_filename,
    op.paid_document_url,
    op.paid_document_filename,
    
    -- Подсчет подписей
    op.required_approvals,
    op.approved_count,
    
    -- Подписанты (в виде JSON)
    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'admin_name', a.name,
                'admin_phone', a.phone,
                'status', pa.status,
                'approved_at', pa.approved_at,
                'comment', pa.comment
            )
        )
        FROM payment_approvals pa
        JOIN admins a ON pa.admin_id = a.id
        WHERE pa.payment_id = op.id
    ) as approvals_json

FROM outgoing_payments op
LEFT JOIN payment_categories pc ON op.category_id = pc.id
LEFT JOIN admins creator ON op.created_by_admin_id = creator.id
LEFT JOIN admins payer ON op.paid_by_admin_id = payer.id

WHERE op.status = 'paid'

ORDER BY op.paid_at DESC;


-- Альтернативный запрос с группировкой по категориям
SELECT 
    pc.name as category_name,
    COUNT(*) as total_payments,
    SUM(op.amount) as total_amount,
    op.currency,
    MIN(op.paid_at) as first_payment,
    MAX(op.paid_at) as last_payment
    
FROM outgoing_payments op
LEFT JOIN payment_categories pc ON op.category_id = pc.id

WHERE op.status = 'paid'

GROUP BY pc.id, op.currency
ORDER BY total_amount DESC;


-- Запрос для статистики по оплаченным платежам за период
SELECT 
    DATE(op.paid_at) as payment_date,
    COUNT(*) as payments_count,
    SUM(op.amount) as total_amount,
    op.currency,
    GROUP_CONCAT(DISTINCT pc.name ORDER BY pc.name SEPARATOR ', ') as categories
    
FROM outgoing_payments op
LEFT JOIN payment_categories pc ON op.category_id = pc.id

WHERE op.status = 'paid'
  AND op.paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)

GROUP BY DATE(op.paid_at), op.currency
ORDER BY payment_date DESC;





