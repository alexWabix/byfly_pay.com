-- Migration: Payment methods with multiple countries and payment currency
-- Date: 2025-12-25

USE `payments`;

-- 1. Добавляем поле для хранения списка стран (вместо одного country_id)
ALTER TABLE `payment_methods`
ADD COLUMN `allowed_countries` JSON DEFAULT NULL COMMENT 'Список ID стран где доступен способ' AFTER `country_id`,
ADD COLUMN `payment_currency` varchar(10) DEFAULT 'KZT' COMMENT 'Валюта в которой принимает платежи' AFTER `allowed_countries`;

-- 2. Конвертируем существующие country_id в JSON массив
UPDATE `payment_methods` 
SET `allowed_countries` = JSON_ARRAY(country_id)
WHERE `country_id` IS NOT NULL;

-- 3. Для Kaspi - делаем доступным в КЗ, РУ, УЗ (принимает KZT)
UPDATE `payment_methods` 
SET `allowed_countries` = JSON_ARRAY(
    (SELECT id FROM countries WHERE code = 'KZ'),
    (SELECT id FROM countries WHERE code = 'RU'),
    (SELECT id FROM countries WHERE code = 'UZ')
),
`payment_currency` = 'KZT'
WHERE `provider` = 'Kaspi';

-- 4. Индексы для оптимизации
ALTER TABLE `payment_methods`
ADD INDEX `idx_payment_currency` (`payment_currency`);

-- Проверяем результат
SELECT id, name, country_id, allowed_countries, payment_currency 
FROM payment_methods 
WHERE provider = 'Kaspi';

