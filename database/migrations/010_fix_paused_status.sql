-- Миграция: Исправление статуса "paused" для расписаний
-- Версия: 10.0
-- Дата: 2026-01-24
-- Исправляет ошибку "Data truncated for column 'status'"

DELIMITER $$

DROP PROCEDURE IF EXISTS fix_paused_status$$
CREATE PROCEDURE fix_paused_status()
BEGIN
    -- Проверяем текущие значения enum
    SET @enum_values = (
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'schedules' 
        AND COLUMN_NAME = 'status'
    );
    
    -- Если 'paused' отсутствует, добавляем его
    IF @enum_values NOT LIKE '%paused%' THEN
        -- Изменяем enum, добавляя "paused"
        ALTER TABLE `schedules` 
        MODIFY COLUMN `status` enum('pending','processing','published','failed','cancelled','paused') DEFAULT 'pending';
        
        SELECT 'Status "paused" added to schedules.status enum' as result;
    ELSE
        SELECT 'Status "paused" already exists in schedules.status enum' as result;
    END IF;
END$$

DELIMITER ;

CALL fix_paused_status();
DROP PROCEDURE IF EXISTS fix_paused_status;
