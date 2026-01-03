-- Fix: Add terminal_operation_id field if missing
-- Date: 2025-12-25

USE `payments`;

-- Добавляем поле terminal_operation_id в transactions
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `terminal_operation_id` varchar(100) DEFAULT NULL COMMENT 'processId от терминала' AFTER `kaspi_terminal_id`;

-- Проверяем что добавилось
SHOW COLUMNS FROM `transactions` LIKE 'terminal_operation_id';

SELECT 'Поле terminal_operation_id добавлено!' as '';

