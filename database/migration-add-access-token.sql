-- Миграция: Добавление поля access_token в kaspi_terminals
-- Выполните этот файл если таблица уже создана

USE `payments`;

-- Добавляем поле access_token
ALTER TABLE `kaspi_terminals` 
ADD COLUMN `access_token` varchar(500) DEFAULT NULL COMMENT 'Access token для авторизации на терминале' 
AFTER `port`;

-- Обновляем существующие терминалы на HTTPS и порт 8080
UPDATE `kaspi_terminals` SET 
  `ip_address` = REPLACE(`ip_address`, 'http://', 'https://'),
  `port` = 8080
WHERE `port` != 8080;

-- Обновляем camera_url на scan-qr
UPDATE `kaspi_terminals` SET 
  `camera_url` = REPLACE(`camera_url`, '/qr/', '/scan-qr/')
WHERE `camera_url` LIKE '%/qr/%';





