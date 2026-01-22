-- Миграция: Исправление полей account_name и is_default для YouTube
-- Версия: 6.0
-- Дата: 2026-01-22

DELIMITER $$

DROP PROCEDURE IF EXISTS fix_youtube_account_fields$$
CREATE PROCEDURE fix_youtube_account_fields()
BEGIN
    -- Добавляем поле account_name, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `youtube_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `channel_name`;
    END IF;

    -- Добавляем поле is_default, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        ALTER TABLE `youtube_integrations` 
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;
END$$

DELIMITER ;

CALL fix_youtube_account_fields();
DROP PROCEDURE IF EXISTS fix_youtube_account_fields;
