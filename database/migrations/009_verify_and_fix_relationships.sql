-- Миграция: Проверка и исправление взаимосвязей
-- Версия: 9.0
-- Дата: 2026-01-24
-- Проверяет и исправляет связи между шаблонами, группами, расписаниями, видео и интеграциями

DELIMITER $$

-- Процедура для проверки и исправления связей
DROP PROCEDURE IF EXISTS verify_and_fix_relationships$$
CREATE PROCEDURE verify_and_fix_relationships()
BEGIN
    -- 1. Проверяем и добавляем внешний ключ для template_id в content_groups
    SET @fk_exists = (
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'content_groups' 
        AND CONSTRAINT_NAME = 'content_groups_ibfk_2'
    );
    
    IF @fk_exists = 0 THEN
        ALTER TABLE `content_groups` 
        ADD CONSTRAINT `content_groups_ibfk_2` 
        FOREIGN KEY (`template_id`) REFERENCES `publication_templates` (`id`) ON DELETE SET NULL;
    END IF;
    
    -- 2. Проверяем и добавляем внешний ключ для content_group_id в schedules
    SET @fk_exists = (
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND CONSTRAINT_NAME = 'schedules_ibfk_3'
    );
    
    IF @fk_exists = 0 THEN
        ALTER TABLE `schedules` 
        ADD CONSTRAINT `schedules_ibfk_3` 
        FOREIGN KEY (`content_group_id`) REFERENCES `content_groups` (`id`) ON DELETE CASCADE;
    END IF;
    
    -- 3. Проверяем и добавляем внешний ключ для template_id в schedules
    SET @fk_exists = (
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND CONSTRAINT_NAME = 'schedules_ibfk_4'
    );
    
    IF @fk_exists = 0 THEN
        ALTER TABLE `schedules` 
        ADD CONSTRAINT `schedules_ibfk_4` 
        FOREIGN KEY (`template_id`) REFERENCES `publication_templates` (`id`) ON DELETE SET NULL;
    END IF;
    
    -- 4. Проверяем, что video_id в schedules может быть NULL
    SET @is_nullable = (
        SELECT IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND COLUMN_NAME = 'video_id'
    );
    
    IF @is_nullable = 'NO' THEN
        -- Временно удаляем внешний ключ
        SET @fk_exists = (
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'schedules' 
            AND CONSTRAINT_NAME = 'schedules_ibfk_2'
        );
        
        IF @fk_exists > 0 THEN
            ALTER TABLE `schedules` DROP FOREIGN KEY `schedules_ibfk_2`;
        END IF;
        
        ALTER TABLE `schedules` MODIFY COLUMN `video_id` int(11) DEFAULT NULL;
        
        -- Восстанавливаем внешний ключ
        IF @fk_exists > 0 THEN
            ALTER TABLE `schedules` 
            ADD CONSTRAINT `schedules_ibfk_2` 
            FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;
        END IF;
    END IF;
    
    -- 5. Проверяем, что status в schedules включает 'paused'
    SET @enum_values = (
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND COLUMN_NAME = 'status'
    );
    
    IF @enum_values NOT LIKE '%paused%' THEN
        ALTER TABLE `schedules` 
        MODIFY COLUMN `status` enum('pending','processing','published','failed','cancelled','paused') DEFAULT 'pending';
    END IF;
    
    -- 6. Проверяем, что status в content_group_files включает 'paused'
    SET @enum_values = (
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'content_group_files' 
        AND COLUMN_NAME = 'status'
    );
    
    IF @enum_values NOT LIKE '%paused%' THEN
        ALTER TABLE `content_group_files` 
        MODIFY COLUMN `status` enum('new','queued','published','error','skipped','paused') DEFAULT 'new';
    END IF;
    
    -- 7. Очищаем невалидные связи (группы, которые не существуют)
    DELETE FROM `schedules` 
    WHERE `content_group_id` IS NOT NULL 
    AND `content_group_id` NOT IN (SELECT `id` FROM `content_groups`);
    
    -- 8. Очищаем невалидные связи (шаблоны, которые не существуют)
    UPDATE `schedules` 
    SET `template_id` = NULL 
    WHERE `template_id` IS NOT NULL 
    AND `template_id` NOT IN (SELECT `id` FROM `publication_templates`);
    
    UPDATE `content_groups` 
    SET `template_id` = NULL 
    WHERE `template_id` IS NOT NULL 
    AND `template_id` NOT IN (SELECT `id` FROM `publication_templates`);
    
    -- 9. Очищаем невалидные связи (видео, которые не существуют)
    DELETE FROM `content_group_files` 
    WHERE `video_id` NOT IN (SELECT `id` FROM `videos`);
    
    DELETE FROM `schedules` 
    WHERE `video_id` IS NOT NULL 
    AND `video_id` NOT IN (SELECT `id` FROM `videos`);
    
    -- 10. Очищаем невалидные связи (группы, которые не существуют)
    DELETE FROM `content_group_files` 
    WHERE `group_id` NOT IN (SELECT `id` FROM `content_groups`);
    
END$$

DELIMITER ;

CALL verify_and_fix_relationships();
DROP PROCEDURE IF EXISTS verify_and_fix_relationships;
