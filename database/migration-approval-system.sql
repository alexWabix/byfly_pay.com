-- Миграция: Система согласования исходящих платежей
-- Дата: 2025-12-26

-- Таблица категорий платежей
CREATE TABLE IF NOT EXISTS `payment_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название категории',
  `description` TEXT NULL COMMENT 'Описание категории',
  `color` VARCHAR(7) DEFAULT '#667eea' COMMENT 'Цвет для UI (hex)',
  `icon_emoji` VARCHAR(10) DEFAULT '💰' COMMENT 'Иконка категории',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Категории исходящих платежей';

-- Таблица исходящих платежей (на согласование)
CREATE TABLE IF NOT EXISTS `outgoing_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_id` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Уникальный ID платежа',
  `category_id` INT NULL COMMENT 'ID категории',
  `created_by_admin_id` INT NOT NULL COMMENT 'Кто создал (бухгалтер)',
  `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Сумма платежа',
  `currency` VARCHAR(3) DEFAULT 'KZT',
  `title` VARCHAR(500) NOT NULL COMMENT 'Название платежа',
  `description` TEXT NULL COMMENT 'Описание',
  `recipient` VARCHAR(500) NULL COMMENT 'Получатель платежа',
  `document_url` VARCHAR(500) NULL COMMENT 'Путь к загруженному документу',
  `document_filename` VARCHAR(255) NULL COMMENT 'Имя файла документа',
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending' COMMENT 'Статус согласования',
  `required_approvals` INT DEFAULT 1 COMMENT 'Сколько подписей требуется',
  `approved_count` INT DEFAULT 0 COMMENT 'Сколько одобрили',
  `rejected_count` INT DEFAULT 0 COMMENT 'Сколько отклонили',
  `approved_at` DATETIME NULL COMMENT 'Когда полностью одобрен',
  `rejected_at` DATETIME NULL COMMENT 'Когда отклонен',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_payment_id` (`payment_id`),
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_created_by` (`created_by_admin_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`category_id`) REFERENCES `payment_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Исходящие платежи на согласование';

-- Таблица подписей (кто должен подписать и кто подписал)
CREATE TABLE IF NOT EXISTS `payment_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT NOT NULL COMMENT 'ID платежа',
  `admin_id` INT NOT NULL COMMENT 'ID админа-подписанта',
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `comment` TEXT NULL COMMENT 'Комментарий при отклонении',
  `sms_sent` TINYINT(1) DEFAULT 0 COMMENT 'Отправлено ли SMS уведомление',
  `sms_sent_at` DATETIME NULL COMMENT 'Когда отправлено SMS',
  `approved_at` DATETIME NULL COMMENT 'Когда одобрено',
  `rejected_at` DATETIME NULL COMMENT 'Когда отклонено',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_payment_id` (`payment_id`),
  INDEX `idx_admin_id` (`admin_id`),
  INDEX `idx_status` (`status`),
  UNIQUE KEY `unique_payment_admin` (`payment_id`, `admin_id`),
  FOREIGN KEY (`payment_id`) REFERENCES `outgoing_payments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Подписи админов для платежей';

-- Вставляем стандартные категории
INSERT INTO `payment_categories` (`name`, `description`, `color`, `icon_emoji`) VALUES
('Оплата туроператорам', 'Платежи туроператорам за туры', '#10b981', '✈️'),
('Зарплата', 'Выплата зарплаты сотрудникам', '#3b82f6', '💼'),
('Аренда', 'Оплата аренды офиса', '#f59e0b', '🏢'),
('Налоги', 'Налоговые платежи', '#ef4444', '📊'),
('Услуги', 'Оплата услуг (интернет, связь и т.д.)', '#8b5cf6', '🔧'),
('Прочее', 'Прочие платежи', '#6b7280', '📝');




