-- Импорт истории платежей из старой системы (company_pays)
-- Дата: 2025-12-26

-- ВАЖНО: Выполнить ПОСЛЕ создания таблиц и загрузки company_pays.sql

-- Создаем всех админов из старой системы (автоматически)
-- Берем уникальные ID из creator_id, user_podpis_1, user_podpis_2

INSERT IGNORE INTO `admins` (`id`, `phone`, `name`, `is_super_admin`, `is_active`) 
SELECT DISTINCT 
    creator_id,
    CONCAT('+7 700 ', LPAD(creator_id, 6, '0')),
    CONCAT('Импорт: Admin #', creator_id),
    0,
    0
FROM company_pays 
WHERE creator_id IS NOT NULL;

INSERT IGNORE INTO `admins` (`id`, `phone`, `name`, `is_super_admin`, `is_active`)
SELECT DISTINCT 
    user_podpis_1,
    CONCAT('+7 701 ', LPAD(user_podpis_1, 6, '0')),
    CONCAT('Импорт: Подписант 1 #', user_podpis_1),
    0,
    0
FROM company_pays 
WHERE user_podpis_1 IS NOT NULL;

INSERT IGNORE INTO `admins` (`id`, `phone`, `name`, `is_super_admin`, `is_active`)
SELECT DISTINCT 
    user_podpis_2,
    CONCAT('+7 702 ', LPAD(user_podpis_2, 6, '0')),
    CONCAT('Импорт: Подписант 2 #', user_podpis_2),
    0,
    0
FROM company_pays 
WHERE user_podpis_2 IS NOT NULL;

-- Вставляем старые платежи в outgoing_payments
INSERT INTO `outgoing_payments` (
    `payment_id`,
    `category_id`,
    `created_by_admin_id`,
    `amount`,
    `currency`,
    `title`,
    `description`,
    `recipient`,
    `document_url`,
    `document_filename`,
    `status`,
    `required_approvals`,
    `approved_count`,
    `rejected_count`,
    `approved_at`,
    `rejected_at`,
    `paid_at`,
    `paid_by_admin_id`,
    `paid_document_url`,
    `created_at`
)
SELECT 
    MD5(CONCAT('OLD_', cp.id)) as payment_id,
    
    -- Мапим категории (используем CASE для соответствия)
    (CASE cp.category
        WHEN 'Оплата туроператору ' THEN 1  -- Оплата туроператорам
        WHEN 'Оплата за авиа билеты ' THEN 1
        WHEN 'Заработная плата ' THEN 2      -- Зарплата
        WHEN 'Арендная плата ' THEN 3        -- Аренда
        WHEN 'аренда квартиры ' THEN 3
        WHEN 'Аренда зала ' THEN 3
        WHEN 'Налоги' THEN 4                 -- Налоги
        WHEN 'Техническое обслуживание ' THEN 5  -- Услуги
        WHEN 'Техническое сопровождение ' THEN 5
        WHEN 'Бухгалтерское обслуживание ' THEN 5
        WHEN 'Оплата за рекламные услуги ' THEN 5
        WHEN 'услуги по продвижению  ' THEN 5
        ELSE 6  -- Прочее
    END) as category_id,
    
    COALESCE(cp.creator_id, 2) as created_by_admin_id,  -- Если нет создателя - дефолтный админ
    cp.amount,
    'KZT' as currency,
    cp.title,
    cp.description,
    NULL as recipient,
    cp.order_document,
    SUBSTRING_INDEX(cp.order_document, '/', -1) as document_filename,
    
    -- Мапим статус (0=pending, 1=processing, 2=approved, 3=rejected)
    (CASE cp.status
        WHEN 0 THEN 'pending'
        WHEN 1 THEN 'approved'  -- В процессе считаем как одобренный
        WHEN 2 THEN 'paid'      -- Оплаченный
        WHEN 3 THEN 'rejected'
        ELSE 'pending'
    END) as status,
    
    2 as required_approvals,  -- Обычно 2 подписанта
    
    -- Подсчитываем одобрения (если были подписи)
    (CASE 
        WHEN cp.date_podpis1 IS NOT NULL AND cp.date_podpis2 IS NOT NULL THEN 2
        WHEN cp.date_podpis1 IS NOT NULL OR cp.date_podpis2 IS NOT NULL THEN 1
        ELSE 0
    END) as approved_count,
    
    (CASE cp.status WHEN 3 THEN 1 ELSE 0 END) as rejected_count,
    
    -- Дата одобрения (последняя подпись)
    (CASE 
        WHEN cp.date_podpis2 IS NOT NULL THEN cp.date_podpis2
        WHEN cp.date_podpis1 IS NOT NULL THEN cp.date_podpis1
        ELSE NULL
    END) as approved_at,
    
    (CASE cp.status WHEN 3 THEN cp.date_create ELSE NULL END) as rejected_at,
    
    cp.date_pays as paid_at,
    cp.creator_id as paid_by_admin_id,
    cp.schet_na_oplatu as paid_document_url,
    
    cp.date_create as created_at

FROM `company_pays` cp
WHERE cp.id IS NOT NULL
ORDER BY cp.date_create ASC;

-- Вставляем подписи для платежей
INSERT INTO `payment_approvals` (
    `payment_id`,
    `admin_id`,
    `status`,
    `comment`,
    `approved_at`,
    `rejected_at`,
    `created_at`
)
SELECT 
    op.id,
    COALESCE(cp.user_podpis_1, 2) as admin_id,
    (CASE 
        WHEN cp.date_podpis1 IS NOT NULL THEN 'approved'
        WHEN cp.status = 3 THEN 'rejected'
        ELSE 'pending'
    END) as status,
    cp.reject_reason as comment,
    cp.date_podpis1 as approved_at,
    (CASE cp.status WHEN 3 THEN cp.date_create ELSE NULL END) as rejected_at,
    cp.date_create as created_at
FROM company_pays cp
JOIN outgoing_payments op ON op.payment_id = MD5(CONCAT('OLD_', cp.id))
WHERE cp.user_podpis_1 IS NOT NULL;

-- Вставляем вторую подпись
INSERT INTO `payment_approvals` (
    `payment_id`,
    `admin_id`,
    `status`,
    `approved_at`,
    `created_at`
)
SELECT 
    op.id,
    COALESCE(cp.user_podpis_2, 12) as admin_id,
    'approved' as status,
    cp.date_podpis2 as approved_at,
    cp.date_create as created_at
FROM company_pays cp
JOIN outgoing_payments op ON op.payment_id = MD5(CONCAT('OLD_', cp.id))
WHERE cp.user_podpis_2 IS NOT NULL;

-- Отчет
SELECT 
    'Импортировано' as action,
    COUNT(*) as count,
    status,
    MIN(created_at) as first_payment,
    MAX(created_at) as last_payment
FROM outgoing_payments
WHERE payment_id LIKE 'OLD_%'
GROUP BY status;

