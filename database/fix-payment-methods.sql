-- Скрипт для проверки и исправления способов оплаты
-- Дата: 25.12.2025

USE `payments`;

-- 1. Проверяем существующие способы оплаты
SELECT 'Текущие способы оплаты:' as '';
SELECT id, code, name, provider, is_active, commission_percent 
FROM payment_methods 
ORDER BY id;

-- 2. Удаляем все существующие (если есть проблемы)
-- DELETE FROM payment_methods;

-- 3. Вставляем правильные способы оплаты для Kaspi
INSERT INTO `payment_methods` (`code`, `name`, `country`, `type`, `provider`, `commission_percent`, `has_credit`, `credit_commission_percent`, `has_installment`, `installment_months`, `installment_commission_percent`, `add_commission_to_amount`, `is_active`, `sort_order`) 
VALUES
('kaspi_gold', 'Kaspi Gold', 'Казахстан', 'wallet', 'Kaspi', 1.00, 0, 0.00, 0, NULL, 0.00, 1, 1, 1),
('kaspi_red', 'Kaspi Red', 'Казахстан', 'card', 'Kaspi', 1.00, 0, 0.00, 0, NULL, 0.00, 1, 1, 2),
('kaspi_credit', 'Kaspi Кредит', 'Казахстан', 'credit', 'Kaspi', 0.00, 1, 14.00, 0, NULL, 0.00, 1, 1, 3),
('kaspi_installment_12', 'Kaspi рассрочка 0-0-12', 'Казахстан', 'installment', 'Kaspi', 0.00, 0, 0.00, 1, 12, 14.00, 1, 1, 4),
('kaspi_installment_24', 'Kaspi рассрочка 0-0-24', 'Казахстан', 'installment', 'Kaspi', 0.00, 0, 0.00, 1, 24, 14.00, 1, 1, 5)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  commission_percent = VALUES(commission_percent),
  has_credit = VALUES(has_credit),
  credit_commission_percent = VALUES(credit_commission_percent),
  has_installment = VALUES(has_installment),
  installment_months = VALUES(installment_months),
  installment_commission_percent = VALUES(installment_commission_percent),
  is_active = 1,  -- Важно! Активируем все методы
  sort_order = VALUES(sort_order);

-- 4. Проверяем что все способы активны
SELECT 'После обновления:' as '';
SELECT id, code, name, is_active, commission_percent 
FROM payment_methods 
WHERE is_active = 1
ORDER BY sort_order;

-- 5. Показываем количество активных методов
SELECT COUNT(*) as 'Количество активных методов' 
FROM payment_methods 
WHERE is_active = 1;

-- Если количество = 5, то все ОК!

