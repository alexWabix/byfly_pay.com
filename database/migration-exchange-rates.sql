-- Migration: Add exchange rates support
-- Date: 2025-12-25
-- Description: Добавляем систему курсов валют от Нацбанка РК

USE `payments`;

-- Таблица курсов валют
CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(10) NOT NULL COMMENT 'Базовая валюта (всегда KZT)',
  `to_currency` varchar(10) NOT NULL COMMENT 'Целевая валюта',
  `rate` decimal(12,6) NOT NULL COMMENT 'Курс обмена',
  `source` varchar(50) DEFAULT 'nationalbank.kz' COMMENT 'Источник данных',
  `fetched_at` datetime NOT NULL COMMENT 'Когда получен курс',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currency_pair` (`from_currency`, `to_currency`),
  KEY `to_currency` (`to_currency`),
  KEY `fetched_at` (`fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставляем дефолтные курсы (базовые значения, будут обновляться от Нацбанка РК)
-- KZT = 1 (базовая валюта)
-- Примерные курсы на декабрь 2025
-- Реальные курсы от Нацбанка РК на 25.12.2025
INSERT INTO `exchange_rates` (`from_currency`, `to_currency`, `rate`, `fetched_at`) VALUES
('KZT', 'KZT', 1.000000, NOW()),          -- Казахстан (базовая)
('KZT', 'USD', 0.001957, NOW()),          -- США: 511 KZT/USD → 1 KZT = 1/511 = 0.001957 USD
('KZT', 'EUR', 0.001659, NOW()),          -- Евро: 602.72 KZT/EUR → 1 KZT = 1/602.72 = 0.001659 EUR
('KZT', 'RUB', 0.154083, NOW()),          -- Россия: 6.49 KZT/RUB → 1 KZT = 1/6.49 = 0.154 RUB
('KZT', 'UZS', 23.585, NOW()),            -- Узбекистан: 100 UZS = 4.24 KZT → 1 KZT = 100/4.24 = 23.585 UZS
('KZT', 'KGS', 0.171233, NOW()),          -- Кыргызстан: 5.84 KZT/KGS → 1 KZT = 1/5.84 = 0.171 KGS
('KZT', 'TJS', 0.017807, NOW()),          -- Таджикистан: 56.15 KZT/TJS → 1 KZT = 1/56.15 = 0.0178 TJS
('KZT', 'AMD', 0.741700, NOW()),          -- Армения: 13.48 KZT/10AMD → 1 KZT = 10/13.48 = 0.742 AMD
('KZT', 'AZN', 0.003317, NOW()),          -- Азербайджан: 301.47 KZT/AZN → 1 KZT = 1/301.47 = 0.0033 AZN
('KZT', 'BYN', 0.005676, NOW()),          -- Беларусь: 176.21 KZT/BYN → 1 KZT = 1/176.21 = 0.0057 BYN
('KZT', 'MDL', 0.032493, NOW()),          -- Молдова: 30.78 KZT/MDL → 1 KZT = 1/30.78 = 0.0325 MDL
('KZT', 'GEL', 0.005217, NOW()),          -- Грузия: 191.67 KZT/GEL → 1 KZT = 1/191.67 = 0.0052 GEL
('KZT', 'TRY', 0.083682, NOW()),          -- Турция: 11.95 KZT/TRY → 1 KZT = 1/11.95 = 0.0837 TRY
('KZT', 'AED', 0.007185, NOW()),          -- ОАЭ: 139.16 KZT/AED → 1 KZT = 1/139.16 = 0.0072 AED
('KZT', 'UAH', 0.082576, NOW())           -- Украина: 12.11 KZT/UAH → 1 KZT = 1/12.11 = 0.0826 UAH
ON DUPLICATE KEY UPDATE
  rate = VALUES(rate),
  fetched_at = VALUES(fetched_at);

-- Проверяем
SELECT * FROM exchange_rates ORDER BY to_currency;

