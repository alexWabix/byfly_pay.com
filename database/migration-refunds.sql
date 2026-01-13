-- Миграция: Добавление поддержки возвратов (refunds)
-- Дата: 2025-12-26

-- Добавляем поля для возврата в partial_payments
ALTER TABLE `partial_payments` 
ADD COLUMN `is_refunded` TINYINT(1) DEFAULT 0 COMMENT 'Был ли возврат',
ADD COLUMN `refund_amount` DECIMAL(10, 2) DEFAULT 0 COMMENT 'Сумма возврата',
ADD COLUMN `refund_terminal_id` INT NULL COMMENT 'ID терминала для возврата',
ADD COLUMN `refund_process_id` VARCHAR(255) NULL COMMENT 'Process ID возврата на терминале',
ADD COLUMN `refund_qr_code` TEXT NULL COMMENT 'QR код подтверждения возврата',
ADD COLUMN `refund_transaction_id` VARCHAR(255) NULL COMMENT 'ID транзакции возврата от терминала',
ADD COLUMN `refund_details` TEXT NULL COMMENT 'Детали возврата от терминала (JSON)',
ADD COLUMN `refunded_at` DATETIME NULL COMMENT 'Дата и время возврата',
ADD COLUMN `refunded_by_admin_id` INT NULL COMMENT 'ID админа, который сделал возврат',
ADD INDEX `idx_is_refunded` (`is_refunded`),
ADD INDEX `idx_refunded_at` (`refunded_at`);

-- Комментарий к таблице
ALTER TABLE `partial_payments` 
COMMENT = 'Частичные оплаты транзакций с поддержкой возвратов';





