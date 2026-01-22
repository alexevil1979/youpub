-- Миграция: Поддержка мультиаккаунтов для интеграций
-- Версия: 3.0
-- Дата: 2026-01-22

DELIMITER $$

DROP PROCEDURE IF EXISTS add_multi_account_support$$
CREATE PROCEDURE add_multi_account_support()
BEGIN
    -- Добавляем поле account_name для идентификации аккаунтов
    -- YouTube
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `youtube_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `channel_name`,
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Telegram
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'telegram_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `telegram_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `channel_username`,
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- TikTok
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'tiktok_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `tiktok_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `username`,
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Instagram
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'instagram_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `instagram_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `username`,
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Pinterest
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'pinterest_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `pinterest_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `username`,
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Добавляем поле integration_id в schedules для выбора конкретного аккаунта
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'integration_id') THEN
        ALTER TABLE `schedules` 
        ADD COLUMN `integration_id` int(11) DEFAULT NULL COMMENT 'ID конкретной интеграции для публикации' AFTER `platform`,
        ADD COLUMN `integration_type` varchar(50) DEFAULT NULL COMMENT 'Тип интеграции: youtube, telegram, tiktok, instagram, pinterest' AFTER `integration_id`;
    END IF;

    -- Добавляем поле integration_id в publications
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'publications' 
                   AND COLUMN_NAME = 'integration_id') THEN
        ALTER TABLE `publications` 
        ADD COLUMN `integration_id` int(11) DEFAULT NULL COMMENT 'ID конкретной интеграции, через которую опубликовано' AFTER `platform`,
        ADD COLUMN `integration_type` varchar(50) DEFAULT NULL COMMENT 'Тип интеграции' AFTER `integration_id`;
    END IF;
END$$

DELIMITER ;

CALL add_multi_account_support();
DROP PROCEDURE IF EXISTS add_multi_account_support;
