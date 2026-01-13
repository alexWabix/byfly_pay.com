-- Добавление токенов для подписания платежей
-- Дата: 2025-12-26

-- Добавляем поле approval_token для уникальных ссылок подписания
ALTER TABLE `payment_approvals` 
ADD COLUMN `approval_token` VARCHAR(128) NULL COMMENT 'Уникальный токен для подписания по ссылке',
ADD UNIQUE KEY `unique_approval_token` (`approval_token`);

-- Генерируем токены для существующих записей
UPDATE payment_approvals 
SET approval_token = MD5(CONCAT(id, payment_id, admin_id, UNIX_TIMESTAMP()))
WHERE approval_token IS NULL;





