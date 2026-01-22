-- Миграция: Добавление поддержки нескольких точек времени в день
-- Версия: 5.0
-- Дата: 2026-01-22

DELIMITER $$

DROP PROCEDURE IF EXISTS add_daily_time_points$$
CREATE PROCEDURE add_daily_time_points()
BEGIN
    -- Добавляем поле для хранения точек времени (JSON массив)
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'daily_time_points') THEN
        ALTER TABLE `schedules` ADD COLUMN `daily_time_points` text DEFAULT NULL COMMENT 'JSON массив временных точек в формате ["HH:MM", "HH:MM", ...]';
    END IF;
END$$

DELIMITER ;

CALL add_daily_time_points();
DROP PROCEDURE IF EXISTS add_daily_time_points;
