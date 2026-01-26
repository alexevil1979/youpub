-- Миграция: Перемещение расписания из schedules в content_groups
-- Расписание становится атрибутом группы, шаблон берется из группы

-- 1. Добавляем schedule_id в content_groups для связи с расписанием
DELIMITER $$

DROP PROCEDURE IF EXISTS move_schedule_to_group$$
CREATE PROCEDURE move_schedule_to_group()
BEGIN
    -- Проверяем и добавляем schedule_id в content_groups
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'content_groups' 
                   AND COLUMN_NAME = 'schedule_id') THEN
        ALTER TABLE `content_groups` 
        ADD COLUMN `schedule_id` int(11) DEFAULT NULL 
        AFTER `template_id`,
        ADD KEY `schedule_id` (`schedule_id`),
        ADD CONSTRAINT `content_groups_ibfk_3` 
        FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL;
    END IF;
    
    -- 2. Удаляем template_id из schedules (только для расписаний групп)
    -- Сначала обновляем расписания групп - переносим template_id из расписания в группу
    UPDATE `schedules` s
    INNER JOIN `content_groups` cg ON s.content_group_id = cg.id
    SET cg.template_id = COALESCE(cg.template_id, s.template_id)
    WHERE s.content_group_id IS NOT NULL 
    AND s.template_id IS NOT NULL
    AND cg.template_id IS NULL;
    
    -- 3. Удаляем внешний ключ для template_id в schedules
    SET @fk_exists = (
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND CONSTRAINT_NAME = 'schedules_ibfk_4'
    );
    
    IF @fk_exists > 0 THEN
        ALTER TABLE `schedules` DROP FOREIGN KEY `schedules_ibfk_4`;
    END IF;
    
    -- 4. Удаляем индекс для template_id в schedules
    SET @idx_exists = (
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND INDEX_NAME = 'template_id'
    );
    
    IF @idx_exists > 0 THEN
        ALTER TABLE `schedules` DROP KEY `template_id`;
    END IF;
    
    -- 5. Удаляем колонку template_id из schedules
    IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'schedules' 
               AND COLUMN_NAME = 'template_id') THEN
        ALTER TABLE `schedules` DROP COLUMN `template_id`;
    END IF;
END$$

DELIMITER ;

CALL move_schedule_to_group();
DROP PROCEDURE IF EXISTS move_schedule_to_group;
