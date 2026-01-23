-- Исправление: video_id должен быть nullable для расписаний групп контента
-- Если расписание создается для группы контента, video_id может быть NULL

DELIMITER $$

DROP PROCEDURE IF EXISTS fix_schedules_video_id_nullable$$
CREATE PROCEDURE fix_schedules_video_id_nullable()
BEGIN
    -- Удаляем внешний ключ, если он существует (чтобы можно было изменить колонку)
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
    
    -- Проверяем, является ли video_id NOT NULL
    SET @is_not_null = (
        SELECT IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND COLUMN_NAME = 'video_id'
    );
    
    -- Если video_id NOT NULL, делаем его nullable
    IF @is_not_null = 'NO' THEN
        ALTER TABLE `schedules` MODIFY COLUMN `video_id` int(11) DEFAULT NULL;
    END IF;
    
    -- Восстанавливаем внешний ключ (теперь с поддержкой NULL)
    IF @fk_exists > 0 THEN
        ALTER TABLE `schedules` 
        ADD CONSTRAINT `schedules_ibfk_2` 
        FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;
    END IF;
END$$

DELIMITER ;

CALL fix_schedules_video_id_nullable();
DROP PROCEDURE IF EXISTS fix_schedules_video_id_nullable;
