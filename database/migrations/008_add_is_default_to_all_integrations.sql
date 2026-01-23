-- Миграция: Добавление полей account_name и is_default во все таблицы интеграций
-- Если поля уже существуют, миграция безопасно пропустит их добавление

DELIMITER $$

DROP PROCEDURE IF EXISTS add_is_default_to_integrations$$
CREATE PROCEDURE add_is_default_to_integrations()
BEGIN
    -- YouTube integrations
    -- Сначала добавляем account_name, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `youtube_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `channel_name`;
    END IF;
    -- Затем добавляем is_default
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
            ALTER TABLE `youtube_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
        ELSE
            ALTER TABLE `youtube_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `channel_name`;
        END IF;
    END IF;

    -- Telegram integrations
    -- Сначала добавляем account_name, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'telegram_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `telegram_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `channel_username`;
    END IF;
    -- Затем добавляем is_default
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'telegram_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'telegram_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
            ALTER TABLE `telegram_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
        ELSE
            ALTER TABLE `telegram_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `channel_username`;
        END IF;
    END IF;

    -- TikTok integrations
    -- Сначала добавляем account_name, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'tiktok_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `tiktok_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `username`;
    END IF;
    -- Затем добавляем is_default
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'tiktok_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'tiktok_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
            ALTER TABLE `tiktok_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
        ELSE
            ALTER TABLE `tiktok_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `username`;
        END IF;
    END IF;

    -- Instagram integrations
    -- Сначала добавляем account_name, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'instagram_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `instagram_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `username`;
    END IF;
    -- Затем добавляем is_default
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'instagram_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'instagram_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
            ALTER TABLE `instagram_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
        ELSE
            ALTER TABLE `instagram_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `username`;
        END IF;
    END IF;

    -- Pinterest integrations
    -- Сначала добавляем account_name, если его нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'pinterest_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
        ALTER TABLE `pinterest_integrations` 
        ADD COLUMN `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации' AFTER `username`;
    END IF;
    -- Затем добавляем is_default
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'pinterest_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'pinterest_integrations' 
                   AND COLUMN_NAME = 'account_name') THEN
            ALTER TABLE `pinterest_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
        ELSE
            ALTER TABLE `pinterest_integrations` 
            ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `username`;
        END IF;
    END IF;
END$$

DELIMITER ;

CALL add_is_default_to_integrations();
DROP PROCEDURE IF EXISTS add_is_default_to_integrations;

-- Устанавливаем is_default = 1 для первого подключенного аккаунта каждого пользователя, если нет аккаунта по умолчанию
-- Используем временные таблицы для избежания ошибки MySQL 1093

-- YouTube
CREATE TEMPORARY TABLE IF NOT EXISTS temp_youtube_defaults AS
SELECT user_id, MIN(id) as first_id
FROM youtube_integrations
WHERE status = 'connected'
AND user_id NOT IN (
    SELECT DISTINCT user_id 
    FROM youtube_integrations 
    WHERE is_default = 1
)
GROUP BY user_id;

UPDATE youtube_integrations y
INNER JOIN temp_youtube_defaults t ON y.id = t.first_id AND y.user_id = t.user_id
SET y.is_default = 1
WHERE y.status = 'connected';

DROP TEMPORARY TABLE IF EXISTS temp_youtube_defaults;

-- Telegram
CREATE TEMPORARY TABLE IF NOT EXISTS temp_telegram_defaults AS
SELECT user_id, MIN(id) as first_id
FROM telegram_integrations
WHERE status = 'connected'
AND user_id NOT IN (
    SELECT DISTINCT user_id 
    FROM telegram_integrations 
    WHERE is_default = 1
)
GROUP BY user_id;

UPDATE telegram_integrations t
INNER JOIN temp_telegram_defaults td ON t.id = td.first_id AND t.user_id = td.user_id
SET t.is_default = 1
WHERE t.status = 'connected';

DROP TEMPORARY TABLE IF EXISTS temp_telegram_defaults;

-- TikTok
CREATE TEMPORARY TABLE IF NOT EXISTS temp_tiktok_defaults AS
SELECT user_id, MIN(id) as first_id
FROM tiktok_integrations
WHERE status = 'connected'
AND user_id NOT IN (
    SELECT DISTINCT user_id 
    FROM tiktok_integrations 
    WHERE is_default = 1
)
GROUP BY user_id;

UPDATE tiktok_integrations tk
INNER JOIN temp_tiktok_defaults tkd ON tk.id = tkd.first_id AND tk.user_id = tkd.user_id
SET tk.is_default = 1
WHERE tk.status = 'connected';

DROP TEMPORARY TABLE IF EXISTS temp_tiktok_defaults;

-- Instagram
CREATE TEMPORARY TABLE IF NOT EXISTS temp_instagram_defaults AS
SELECT user_id, MIN(id) as first_id
FROM instagram_integrations
WHERE status = 'connected'
AND user_id NOT IN (
    SELECT DISTINCT user_id 
    FROM instagram_integrations 
    WHERE is_default = 1
)
GROUP BY user_id;

UPDATE instagram_integrations i
INNER JOIN temp_instagram_defaults id ON i.id = id.first_id AND i.user_id = id.user_id
SET i.is_default = 1
WHERE i.status = 'connected';

DROP TEMPORARY TABLE IF EXISTS temp_instagram_defaults;

-- Pinterest
CREATE TEMPORARY TABLE IF NOT EXISTS temp_pinterest_defaults AS
SELECT user_id, MIN(id) as first_id
FROM pinterest_integrations
WHERE status = 'connected'
AND user_id NOT IN (
    SELECT DISTINCT user_id 
    FROM pinterest_integrations 
    WHERE is_default = 1
)
GROUP BY user_id;

UPDATE pinterest_integrations p
INNER JOIN temp_pinterest_defaults pd ON p.id = pd.first_id AND p.user_id = pd.user_id
SET p.is_default = 1
WHERE p.status = 'connected';

DROP TEMPORARY TABLE IF EXISTS temp_pinterest_defaults;
