-- Добавление статуса "Оплачен" в систему согласований
-- Дата: 2025-12-26

-- Изменяем ENUM для добавления статуса 'paid'
ALTER TABLE `outgoing_payments` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'paid') DEFAULT 'pending';

-- Добавляем поля для подтверждения оплаты
ALTER TABLE `outgoing_payments` 
ADD COLUMN `paid_at` DATETIME NULL COMMENT 'Когда отмечен как оплаченный',
ADD COLUMN `paid_by_admin_id` INT NULL COMMENT 'Кто отметил как оплаченный',
ADD COLUMN `paid_document_url` VARCHAR(500) NULL COMMENT 'Документ подтверждения оплаты',
ADD COLUMN `paid_document_filename` VARCHAR(255) NULL COMMENT 'Имя файла документа',
ADD INDEX `idx_paid_at` (`paid_at`),
ADD FOREIGN KEY (`paid_by_admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL;




