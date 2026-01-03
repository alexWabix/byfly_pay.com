-- Миграция: Дополнительные документы для платежей
-- Дата: 2025-12-26

-- Таблица для хранения множественных документов платежа
CREATE TABLE IF NOT EXISTS `payment_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT NOT NULL COMMENT 'ID платежа из outgoing_payments',
  `document_url` VARCHAR(500) NOT NULL COMMENT 'Путь к документу',
  `document_filename` VARCHAR(255) NOT NULL COMMENT 'Имя файла',
  `document_type` ENUM('initial', 'additional', 'paid_proof') DEFAULT 'additional' COMMENT 'Тип документа',
  `uploaded_by_admin_id` INT NOT NULL COMMENT 'Кто загрузил',
  `file_size` INT NULL COMMENT 'Размер файла в байтах',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_payment_id` (`payment_id`),
  INDEX `idx_uploaded_by` (`uploaded_by_admin_id`),
  FOREIGN KEY (`payment_id`) REFERENCES `outgoing_payments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Дополнительные документы для платежей';

-- Добавляем поле для пометки удаленных платежей (мягкое удаление)
ALTER TABLE `outgoing_payments` 
ADD COLUMN `deleted_at` DATETIME NULL COMMENT 'Когда помечен как удаленный',
ADD COLUMN `deleted_by_admin_id` INT NULL COMMENT 'Кто удалил',
ADD INDEX `idx_deleted_at` (`deleted_at`),
ADD FOREIGN KEY (`deleted_by_admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL;

