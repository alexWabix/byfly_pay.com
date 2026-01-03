-- Миграция: Система управления банками и балансами
-- Дата: 2025-12-26

-- Таблица банковских счетов
CREATE TABLE IF NOT EXISTS `banks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название банка',
  `account_number` VARCHAR(100) NULL COMMENT 'Номер счета/карты',
  `country_code` VARCHAR(2) NOT NULL DEFAULT 'KZ' COMMENT 'Код страны (KZ, UZ, AZ, RU и т.д.)',
  `country_name` VARCHAR(100) NOT NULL DEFAULT 'Казахстан' COMMENT 'Название страны',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'KZT' COMMENT 'Валюта счета',
  `balance` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Текущий баланс',
  `initial_balance` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Начальный баланс',
  `bank_color` VARCHAR(7) DEFAULT '#667eea' COMMENT 'Цвет для UI',
  `bank_icon` VARCHAR(10) DEFAULT '🏦' COMMENT 'Иконка банка',
  `description` TEXT NULL COMMENT 'Описание/заметки',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Активен ли счет',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_country` (`country_code`),
  INDEX `idx_currency` (`currency`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Банковские счета компании';

-- Таблица движений по счетам
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_id` INT NOT NULL COMMENT 'ID банковского счета',
  `payment_id` INT NULL COMMENT 'Связь с платежом (если есть)',
  `type` ENUM('income', 'expense', 'transfer', 'adjustment') NOT NULL COMMENT 'Тип операции',
  `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Сумма операции',
  `balance_before` DECIMAL(15, 2) NOT NULL COMMENT 'Баланс до операции',
  `balance_after` DECIMAL(15, 2) NOT NULL COMMENT 'Баланс после операции',
  `description` TEXT NULL COMMENT 'Описание операции',
  `reference` VARCHAR(255) NULL COMMENT 'Номер документа/чека',
  `created_by_admin_id` INT NOT NULL COMMENT 'Кто создал операцию',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_bank_id` (`bank_id`),
  INDEX `idx_payment_id` (`payment_id`),
  INDEX `idx_type` (`type`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`bank_id`) REFERENCES `banks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`payment_id`) REFERENCES `outgoing_payments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Движения по банковским счетам';

-- Связь платежей с банками
ALTER TABLE `outgoing_payments` 
ADD COLUMN `bank_id` INT NULL COMMENT 'Из какого банка оплачен',
ADD INDEX `idx_bank_id` (`bank_id`),
ADD FOREIGN KEY (`bank_id`) REFERENCES `banks`(`id`) ON DELETE SET NULL;

-- Вставляем стандартные банки
INSERT INTO `banks` (`name`, `account_number`, `country_code`, `country_name`, `currency`, `initial_balance`, `balance`, `bank_color`, `bank_icon`) VALUES
('Kaspi Bank KZ', '4400430199704070', 'KZ', 'Казахстан', 'KZT', 0, 0, '#f14635', '💳'),
('Halyk Bank KZ', '1234567890123456', 'KZ', 'Казахстан', 'KZT', 0, 0, '#00a651', '🏦'),
('Forte Bank KZ', '1111222233334444', 'KZ', 'Казахстан', 'USD', 0, 0, '#0066cc', '💵'),
('Kapital Bank AZ', '5555666677778888', 'AZ', 'Азербайджан', 'AZN', 0, 0, '#e31e24', '🏦'),
('Ipak Yuli Bank UZ', '9999000011112222', 'UZ', 'Узбекистан', 'UZS', 0, 0, '#00a94f', '🏦'),
('Наличные KZT', 'CASH-KZT', 'KZ', 'Казахстан', 'KZT', 0, 0, '#10b981', '💰'),
('Наличные USD', 'CASH-USD', 'KZ', 'Казахстан', 'USD', 0, 0, '#10b981', '💵'),
('Наличные AZN', 'CASH-AZN', 'AZ', 'Азербайджан', 'AZN', 0, 0, '#10b981', '💰');

