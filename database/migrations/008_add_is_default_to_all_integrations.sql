-- Миграция: Добавление поля is_default во все таблицы интеграций
-- Если поле уже существует, миграция безопасно пропустит его добавление

DELIMITER $$

DROP PROCEDURE IF EXISTS add_is_default_to_integrations$$
CREATE PROCEDURE add_is_default_to_integrations()
BEGIN
    -- YouTube integrations
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'youtube_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        ALTER TABLE `youtube_integrations` 
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Telegram integrations
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'telegram_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        ALTER TABLE `telegram_integrations` 
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- TikTok integrations
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'tiktok_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        ALTER TABLE `tiktok_integrations` 
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Instagram integrations
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'instagram_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        ALTER TABLE `instagram_integrations` 
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;

    -- Pinterest integrations
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'pinterest_integrations' 
                   AND COLUMN_NAME = 'is_default') THEN
        ALTER TABLE `pinterest_integrations` 
        ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию' AFTER `account_name`;
    END IF;
END$$

DELIMITER ;

CALL add_is_default_to_integrations();
DROP PROCEDURE IF EXISTS add_is_default_to_integrations;

-- Устанавливаем is_default = 1 для первого подключенного аккаунта каждого пользователя, если нет аккаунта по умолчанию
-- YouTube
UPDATE youtube_integrations y1
SET is_default = 1
WHERE y1.status = 'connected'
AND NOT EXISTS (
    SELECT 1 FROM youtube_integrations y2 
    WHERE y2.user_id = y1.user_id 
    AND y2.is_default = 1
)
AND y1.id = (
    SELECT y3.id FROM youtube_integrations y3 
    WHERE y3.user_id = y1.user_id 
    AND y3.status = 'connected'
    ORDER BY y3.created_at ASC 
    LIMIT 1
);

-- Telegram
UPDATE telegram_integrations t1
SET is_default = 1
WHERE t1.status = 'connected'
AND NOT EXISTS (
    SELECT 1 FROM telegram_integrations t2 
    WHERE t2.user_id = t1.user_id 
    AND t2.is_default = 1
)
AND t1.id = (
    SELECT t3.id FROM telegram_integrations t3 
    WHERE t3.user_id = t1.user_id 
    AND t3.status = 'connected'
    ORDER BY t3.created_at ASC 
    LIMIT 1
);

-- TikTok
UPDATE tiktok_integrations tk1
SET is_default = 1
WHERE tk1.status = 'connected'
AND NOT EXISTS (
    SELECT 1 FROM tiktok_integrations tk2 
    WHERE tk2.user_id = tk1.user_id 
    AND tk2.is_default = 1
)
AND tk1.id = (
    SELECT tk3.id FROM tiktok_integrations tk3 
    WHERE tk3.user_id = tk1.user_id 
    AND tk3.status = 'connected'
    ORDER BY tk3.created_at ASC 
    LIMIT 1
);

-- Instagram
UPDATE instagram_integrations i1
SET is_default = 1
WHERE i1.status = 'connected'
AND NOT EXISTS (
    SELECT 1 FROM instagram_integrations i2 
    WHERE i2.user_id = i1.user_id 
    AND i2.is_default = 1
)
AND i1.id = (
    SELECT i3.id FROM instagram_integrations i3 
    WHERE i3.user_id = i1.user_id 
    AND i3.status = 'connected'
    ORDER BY i3.created_at ASC 
    LIMIT 1
);

-- Pinterest
UPDATE pinterest_integrations p1
SET is_default = 1
WHERE p1.status = 'connected'
AND NOT EXISTS (
    SELECT 1 FROM pinterest_integrations p2 
    WHERE p2.user_id = p1.user_id 
    AND p2.is_default = 1
)
AND p1.id = (
    SELECT p3.id FROM pinterest_integrations p3 
    WHERE p3.user_id = p1.user_id 
    AND p3.status = 'connected'
    ORDER BY p3.created_at ASC 
    LIMIT 1
);
