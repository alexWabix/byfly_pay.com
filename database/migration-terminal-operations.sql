-- Migration: Terminal operations tracking
-- Date: 2025-12-25
-- Description: Добавляем отслеживание операций на терминалах

USE `payments`;

-- 1. Добавляем поля если их нет (проверка существования)
ALTER TABLE `kaspi_terminals`
ADD COLUMN IF NOT EXISTS `last_operation_id` varchar(100) DEFAULT NULL COMMENT 'ID последней операции терминала' AFTER `is_busy`,
ADD COLUMN IF NOT EXISTS `current_transaction_id` varchar(100) DEFAULT NULL COMMENT 'ID текущей транзакции' AFTER `last_operation_id`;

-- 2. Добавляем поля для реального способа оплаты в транзакции (с проверкой существования)
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `terminal_operation_id` varchar(100) DEFAULT NULL COMMENT 'processId от терминала',
ADD COLUMN IF NOT EXISTS `actual_payment_method` varchar(50) DEFAULT NULL COMMENT 'Реальный способ которым оплатил клиент',
ADD COLUMN IF NOT EXISTS `actual_amount_received` decimal(12,2) DEFAULT 0.00 COMMENT 'Реально полученная сумма',
ADD COLUMN IF NOT EXISTS `needs_additional_payment` tinyint(1) DEFAULT 0 COMMENT 'Требуется доплата',
ADD COLUMN IF NOT EXISTS `payment_mismatch` tinyint(1) DEFAULT 0 COMMENT 'Клиент оплатил другим способом';

-- 3. Индексы (пропускаем если уже существуют)
-- Индексы создадутся автоматически или уже существуют

-- Проверяем
SELECT 'Терминалы:' as '';
SELECT id, name, is_busy, last_operation_id, current_transaction_id FROM kaspi_terminals;

SELECT 'Транзакции:' as '';
SELECT id, transaction_id, status, actual_payment_method, actual_amount_received, needs_additional_payment 
FROM transactions 
ORDER BY id DESC 
LIMIT 5;

