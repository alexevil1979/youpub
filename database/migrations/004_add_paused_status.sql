-- Миграция: Добавление статуса "paused" для расписаний
-- Версия: 4.0
-- Дата: 2026-01-22

DELIMITER $$

DROP PROCEDURE IF EXISTS add_paused_status$$
CREATE PROCEDURE add_paused_status()
BEGIN
    -- Проверяем, есть ли уже статус "paused" в enum
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND COLUMN_NAME = 'status'
        AND COLUMN_TYPE LIKE '%paused%'
    ) THEN
        -- Изменяем enum, добавляя "paused"
        ALTER TABLE `schedules` 
        MODIFY COLUMN `status` enum('pending','processing','published','failed','cancelled','paused') DEFAULT 'pending';
    END IF;
END$$

DELIMITER ;

CALL add_paused_status();
DROP PROCEDURE IF EXISTS add_paused_status;
