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
-- Используем JOIN вместо подзапросов для избежания ошибки MySQL 1093

-- YouTube
UPDATE youtube_integrations y1
INNER JOIN (
    SELECT user_id, MIN(id) as first_id
    FROM youtube_integrations
    WHERE status = 'connected'
    AND user_id NOT IN (
        SELECT DISTINCT user_id 
        FROM youtube_integrations 
        WHERE is_default = 1
    )
    GROUP BY user_id
) y2 ON y1.id = y2.first_id AND y1.user_id = y2.user_id
SET y1.is_default = 1
WHERE y1.status = 'connected';

-- Telegram
UPDATE telegram_integrations t1
INNER JOIN (
    SELECT user_id, MIN(id) as first_id
    FROM telegram_integrations
    WHERE status = 'connected'
    AND user_id NOT IN (
        SELECT DISTINCT user_id 
        FROM telegram_integrations 
        WHERE is_default = 1
    )
    GROUP BY user_id
) t2 ON t1.id = t2.first_id AND t1.user_id = t2.user_id
SET t1.is_default = 1
WHERE t1.status = 'connected';

-- TikTok
UPDATE tiktok_integrations tk1
INNER JOIN (
    SELECT user_id, MIN(id) as first_id
    FROM tiktok_integrations
    WHERE status = 'connected'
    AND user_id NOT IN (
        SELECT DISTINCT user_id 
        FROM tiktok_integrations 
        WHERE is_default = 1
    )
    GROUP BY user_id
) tk2 ON tk1.id = tk2.first_id AND tk1.user_id = tk2.user_id
SET tk1.is_default = 1
WHERE tk1.status = 'connected';

-- Instagram
UPDATE instagram_integrations i1
INNER JOIN (
    SELECT user_id, MIN(id) as first_id
    FROM instagram_integrations
    WHERE status = 'connected'
    AND user_id NOT IN (
        SELECT DISTINCT user_id 
        FROM instagram_integrations 
        WHERE is_default = 1
    )
    GROUP BY user_id
) i2 ON i1.id = i2.first_id AND i1.user_id = i2.user_id
SET i1.is_default = 1
WHERE i1.status = 'connected';

-- Pinterest
UPDATE pinterest_integrations p1
INNER JOIN (
    SELECT user_id, MIN(id) as first_id
    FROM pinterest_integrations
    WHERE status = 'connected'
    AND user_id NOT IN (
        SELECT DISTINCT user_id 
        FROM pinterest_integrations 
        WHERE is_default = 1
    )
    GROUP BY user_id
) p2 ON p1.id = p2.first_id AND p1.user_id = p2.user_id
SET p1.is_default = 1
WHERE p1.status = 'connected';
