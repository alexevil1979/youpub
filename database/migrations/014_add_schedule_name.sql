-- Миграция: Добавление поля name в schedules для названия расписания

DELIMITER $$

DROP PROCEDURE IF EXISTS add_schedule_name$$
CREATE PROCEDURE add_schedule_name()
BEGIN
    -- Проверяем и добавляем name в schedules
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'name') THEN
        ALTER TABLE `schedules` 
        ADD COLUMN `name` varchar(255) DEFAULT NULL 
        AFTER `user_id`;
    END IF;
END$$

DELIMITER ;

CALL add_schedule_name();
DROP PROCEDURE IF EXISTS add_schedule_name;
