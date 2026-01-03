-- Migration: Add terminal payment details
-- Date: 2025-12-25
-- Description: Добавляем детальные данные от терминала

USE `payments`;

-- Добавляем поля для деталей от терминала
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `terminal_order_number` varchar(100) DEFAULT NULL COMMENT 'Номер заказа от терминала',
ADD COLUMN IF NOT EXISTS `terminal_transaction_id` varchar(100) DEFAULT NULL COMMENT 'ID транзакции терминала',
ADD COLUMN IF NOT EXISTS `terminal_cheque_status` varchar(255) DEFAULT NULL COMMENT 'Статус из чека терминала',
ADD COLUMN IF NOT EXISTS `terminal_product_type` varchar(50) DEFAULT NULL COMMENT 'ProductType (Gold, Red, Credit, Installment)',
ADD COLUMN IF NOT EXISTS `terminal_response_full` TEXT DEFAULT NULL COMMENT 'Полный ответ терминала (JSON)';

SELECT 'Поля для деталей терминала добавлены!' as '';

