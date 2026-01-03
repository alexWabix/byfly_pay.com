-- Migration: Add client information fields to transactions table
-- Date: 2025-12-25
-- Description: Добавляем поля для хранения информации о клиенте (IP, браузер, устройство)

USE `payments`;

-- Добавляем поля для информации о клиенте
ALTER TABLE `transactions` 
ADD COLUMN `client_ip` varchar(50) DEFAULT NULL COMMENT 'IP адрес клиента' AFTER `qr_code`,
ADD COLUMN `client_user_agent` varchar(500) DEFAULT NULL COMMENT 'User Agent клиента' AFTER `client_ip`,
ADD COLUMN `client_device` varchar(100) DEFAULT NULL COMMENT 'Устройство клиента (mobile, tablet, desktop)' AFTER `client_user_agent`,
ADD COLUMN `client_browser` varchar(100) DEFAULT NULL COMMENT 'Браузер клиента' AFTER `client_device`,
ADD COLUMN `client_os` varchar(100) DEFAULT NULL COMMENT 'ОС клиента' AFTER `client_browser`,
ADD COLUMN `client_country` varchar(100) DEFAULT NULL COMMENT 'Страна клиента' AFTER `client_os`,
ADD COLUMN `payment_started_at` datetime DEFAULT NULL COMMENT 'Когда клиент открыл страницу оплаты' AFTER `client_country`;

-- Добавляем индекс для IP адреса (для аналитики)
ALTER TABLE `transactions` 
ADD INDEX `idx_client_ip` (`client_ip`),
ADD INDEX `idx_payment_started` (`payment_started_at`);

