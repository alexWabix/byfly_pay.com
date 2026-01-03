-- Migration: Add icons and countries support
-- Date: 2025-12-25
-- Description: Добавляем иконки для способов оплаты и систему стран

USE `payments`;

-- 1. Добавляем поле для иконки в payment_methods
ALTER TABLE `payment_methods` 
ADD COLUMN `icon_url` varchar(500) DEFAULT NULL COMMENT 'URL иконки способа оплаты' AFTER `name`,
ADD COLUMN `icon_emoji` varchar(10) DEFAULT NULL COMMENT 'Emoji иконка (fallback)' AFTER `icon_url`;

-- 2. Создаем таблицу стран
CREATE TABLE IF NOT EXISTS `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL COMMENT 'Код страны (KZ, UZ, KG и т.д.)',
  `name` varchar(100) NOT NULL COMMENT 'Название страны',
  `name_en` varchar(100) DEFAULT NULL COMMENT 'Название на английском',
  `currency_code` varchar(10) DEFAULT NULL COMMENT 'Код валюты (KZT, UZS и т.д.)',
  `currency_symbol` varchar(10) DEFAULT NULL COMMENT 'Символ валюты (₸, сўм и т.д.)',
  `phone_mask` varchar(50) DEFAULT NULL COMMENT 'Маска телефона',
  `phone_code` varchar(10) DEFAULT NULL COMMENT 'Телефонный код (+7, +998 и т.д.)',
  `flag_emoji` varchar(10) DEFAULT NULL COMMENT 'Флаг эмодзи',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Изменяем payment_methods.country на country_id (связь с таблицей countries)
ALTER TABLE `payment_methods`
ADD COLUMN `country_id` int(11) DEFAULT NULL COMMENT 'ID страны' AFTER `country`,
ADD INDEX `idx_country_id` (`country_id`);

-- 4. Вставляем дефолтные страны (СНГ + популярные направления)
INSERT INTO `countries` (`code`, `name`, `name_en`, `currency_code`, `currency_symbol`, `phone_code`, `phone_mask`, `flag_emoji`, `is_active`, `sort_order`) VALUES
('KZ', 'Казахстан', 'Kazakhstan', 'KZT', '₸', '+7', '+7 (###) ###-##-##', '🇰🇿', 1, 1),
('RU', 'Россия', 'Russia', 'RUB', '₽', '+7', '+7 (###) ###-##-##', '🇷🇺', 1, 2),
('UZ', 'Узбекистан', 'Uzbekistan', 'UZS', 'сўм', '+998', '+998 ## ###-##-##', '🇺🇿', 1, 3),
('KG', 'Кыргызстан', 'Kyrgyzstan', 'KGS', 'сом', '+996', '+996 ### ###-###', '🇰🇬', 1, 4),
('TJ', 'Таджикистан', 'Tajikistan', 'TJS', 'смн', '+992', '+992 ## ###-##-##', '🇹🇯', 1, 5),
('TM', 'Туркменистан', 'Turkmenistan', 'TMT', 'ман', '+993', '+993 # ###-##-##', '🇹🇲', 1, 6),
('AM', 'Армения', 'Armenia', 'AMD', '֏', '+374', '+374 ## ###-###', '🇦🇲', 1, 7),
('AZ', 'Азербайджан', 'Azerbaijan', 'AZN', '₼', '+994', '+994 ## ###-##-##', '🇦🇿', 1, 8),
('BY', 'Беларусь', 'Belarus', 'BYN', 'Br', '+375', '+375 ## ###-##-##', '🇧🇾', 1, 9),
('MD', 'Молдова', 'Moldova', 'MDL', 'L', '+373', '+373 #### ####', '🇲🇩', 1, 10),
('GE', 'Грузия', 'Georgia', 'GEL', '₾', '+995', '+995 ### ###-###', '🇬🇪', 1, 11),
('TR', 'Турция', 'Turkey', 'TRY', '₺', '+90', '+90 ### ### ## ##', '🇹🇷', 1, 12),
('AE', 'ОАЭ', 'UAE', 'AED', 'د.إ', '+971', '+971 ## ###-####', '🇦🇪', 1, 13);

-- 5. Обновляем существующие способы оплаты - привязываем к Казахстану
UPDATE `payment_methods` 
SET `country_id` = (SELECT id FROM countries WHERE code = 'KZ' LIMIT 1)
WHERE `country` = 'Казахстан';

-- 6. Устанавливаем дефолтные эмодзи иконки для Kaspi методов
UPDATE `payment_methods` SET `icon_emoji` = '💰' WHERE `code` = 'kaspi_gold';
UPDATE `payment_methods` SET `icon_emoji` = '💳' WHERE `code` = 'kaspi_red';
UPDATE `payment_methods` SET `icon_emoji` = '🏦' WHERE `code` = 'kaspi_credit';
UPDATE `payment_methods` SET `icon_emoji` = '📅' WHERE `code` = 'kaspi_installment_12';
UPDATE `payment_methods` SET `icon_emoji` = '📆' WHERE `code` = 'kaspi_installment_24';

-- Проверяем результат
SELECT 'Страны:' as '';
SELECT id, code, name, currency_code, currency_symbol, flag_emoji, is_active FROM countries ORDER BY sort_order;

SELECT 'Способы оплаты:' as '';
SELECT id, code, name, country_id, icon_emoji, is_active FROM payment_methods ORDER BY id;

