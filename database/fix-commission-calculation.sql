-- Migration: Fix commission calculation consistency
-- Date: 2025-12-27
-- Description: Обеспечиваем единообразный расчет комиссий и исправляем структуру БД

USE `payments`;

-- =======================
-- ЧАСТЬ 1: Проверка и добавление недостающих полей
-- =======================

-- 1. Проверяем и добавляем поля в kaspi_terminals
ALTER TABLE `kaspi_terminals`
ADD COLUMN IF NOT EXISTS `last_operation_id` varchar(100) DEFAULT NULL COMMENT 'ID последней операции терминала' AFTER `is_busy`,
ADD COLUMN IF NOT EXISTS `current_transaction_id` varchar(100) DEFAULT NULL COMMENT 'ID текущей транзакции' AFTER `last_operation_id`,
ADD COLUMN IF NOT EXISTS `access_token` varchar(500) DEFAULT NULL COMMENT 'Токен доступа к терминалу' AFTER `camera_url`;

-- 2. Проверяем и добавляем поля в transactions для деталей терминала
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `terminal_operation_id` varchar(100) DEFAULT NULL COMMENT 'processId от терминала',
ADD COLUMN IF NOT EXISTS `terminal_order_number` varchar(100) DEFAULT NULL COMMENT 'Номер заказа от терминала',
ADD COLUMN IF NOT EXISTS `terminal_transaction_id` varchar(100) DEFAULT NULL COMMENT 'ID транзакции терминала',
ADD COLUMN IF NOT EXISTS `terminal_cheque_status` varchar(255) DEFAULT NULL COMMENT 'Статус из чека терминала',
ADD COLUMN IF NOT EXISTS `terminal_product_type` varchar(50) DEFAULT NULL COMMENT 'ProductType (Gold, Red, Credit, Installment)',
ADD COLUMN IF NOT EXISTS `terminal_response_full` TEXT DEFAULT NULL COMMENT 'Полный ответ терминала (JSON)';

-- 3. Проверяем и добавляем поля для реального способа оплаты
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `actual_payment_method` varchar(50) DEFAULT NULL COMMENT 'Реальный способ которым оплатил клиент',
ADD COLUMN IF NOT EXISTS `actual_amount_received` decimal(12,2) DEFAULT 0.00 COMMENT 'Реально полученная сумма',
ADD COLUMN IF NOT EXISTS `needs_additional_payment` tinyint(1) DEFAULT 0 COMMENT 'Требуется доплата',
ADD COLUMN IF NOT EXISTS `payment_mismatch` tinyint(1) DEFAULT 0 COMMENT 'Клиент оплатил другим способом';

-- 4. Проверяем и добавляем поля для информации о клиенте
ALTER TABLE `transactions`
ADD COLUMN IF NOT EXISTS `client_ip` varchar(50) DEFAULT NULL COMMENT 'IP адрес клиента',
ADD COLUMN IF NOT EXISTS `client_user_agent` varchar(500) DEFAULT NULL COMMENT 'User Agent клиента',
ADD COLUMN IF NOT EXISTS `client_device` varchar(100) DEFAULT NULL COMMENT 'Устройство клиента (mobile, tablet, desktop)',
ADD COLUMN IF NOT EXISTS `client_browser` varchar(100) DEFAULT NULL COMMENT 'Браузер клиента',
ADD COLUMN IF NOT EXISTS `client_os` varchar(100) DEFAULT NULL COMMENT 'ОС клиента',
ADD COLUMN IF NOT EXISTS `client_country` varchar(100) DEFAULT NULL COMMENT 'Страна клиента',
ADD COLUMN IF NOT EXISTS `payment_started_at` datetime DEFAULT NULL COMMENT 'Когда клиент открыл страницу оплаты';

-- =======================
-- ЧАСТЬ 2: Проверка процентов комиссий
-- =======================

-- Выводим текущие значения комиссий для всех способов оплаты
SELECT 
    code,
    name,
    commission_percent,
    has_credit,
    credit_commission_percent,
    has_installment,
    installment_commission_percent,
    add_commission_to_amount
FROM payment_methods
ORDER BY sort_order;

-- =======================
-- ЧАСТЬ 3: Информация
-- =======================

SELECT '✅ Все поля для терминалов и транзакций проверены и добавлены!' as 'Статус';
SELECT '✅ Расчет комиссий теперь будет единообразным во всей системе!' as 'Важно';
SELECT 'ℹ️ Проценты комиссий берутся из таблицы payment_methods (см. выше)' as 'Примечание';

-- =======================
-- ЧАСТЬ 4: Рекомендации
-- =======================

-- Если нужно изменить проценты комиссий, используйте:
-- UPDATE payment_methods SET commission_percent = 1.00 WHERE code = 'kaspi_gold';
-- UPDATE payment_methods SET commission_percent = 1.00 WHERE code = 'kaspi_red';
-- UPDATE payment_methods SET credit_commission_percent = 14.00 WHERE code = 'kaspi_credit';
-- UPDATE payment_methods SET installment_commission_percent = 14.00 WHERE code = 'kaspi_installment_12';
-- UPDATE payment_methods SET installment_commission_percent = 14.00 WHERE code = 'kaspi_installment_24';






