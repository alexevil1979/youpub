<?php
/**
 * Тестовый скрипт для проверки работы страниц
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Настройка логирования
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$errorLogFile = $logDir . '/error.log';
ini_set('error_log', $errorLogFile);

error_log("=== TEST PAGES SCRIPT START ===");

// Проверяем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// Проверяем автозагрузку
require_once __DIR__ . '/vendor/autoload.php';
use Core\Database;
error_log("Autoload OK");

// Проверяем конфигурацию
try {
    $config = require __DIR__ . '/config/env.php';
    error_log("Config loaded OK");
} catch (\Exception $e) {
    error_log("Config error: " . $e->getMessage());
}

// Проверяем базу данных
try {
    Database::init($config);
    error_log("Database initialized OK");
} catch (\Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Проверяем контроллеры
try {
    $templateControllerPath = __DIR__ . '/app/Modules/ContentGroups/Controllers/TemplateController.php';
    if (file_exists($templateControllerPath)) {
        error_log("TemplateController file exists");
        require_once $templateControllerPath;
        error_log("TemplateController class loaded");
    } else {
        error_log("TemplateController file NOT FOUND: " . $templateControllerPath);
    }
} catch (\Exception $e) {
    error_log("TemplateController error: " . $e->getMessage());
}

try {
    $scheduleControllerPath = __DIR__ . '/app/Modules/ContentGroups/Controllers/SmartScheduleController.php';
    if (file_exists($scheduleControllerPath)) {
        error_log("SmartScheduleController file exists");
        require_once $scheduleControllerPath;
        error_log("SmartScheduleController class loaded");
    } else {
        error_log("SmartScheduleController file NOT FOUND: " . $scheduleControllerPath);
    }
} catch (\Exception $e) {
    error_log("SmartScheduleController error: " . $e->getMessage());
}

// Проверяем представления
$templateViewPath = __DIR__ . '/views/content_groups/templates/index.php';
$scheduleViewPath = __DIR__ . '/views/content_groups/schedules/index.php';

error_log("Template view exists: " . (file_exists($templateViewPath) ? 'YES' : 'NO'));
error_log("Schedule view exists: " . (file_exists($scheduleViewPath) ? 'YES' : 'NO'));

// Проверяем репозитории
try {
    $templateRepoPath = __DIR__ . '/app/Modules/ContentGroups/Repositories/PublicationTemplateRepository.php';
    if (file_exists($templateRepoPath)) {
        require_once $templateRepoPath;
        error_log("PublicationTemplateRepository class loaded");
        
        // Проверяем таблицу
        $repo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
        $db = $repo->getDb();
        $tableExists = $db->query("SHOW TABLES LIKE 'publication_templates'")->rowCount() > 0;
        error_log("Table publication_templates exists: " . ($tableExists ? 'YES' : 'NO'));
    }
} catch (\Exception $e) {
    error_log("Repository check error: " . $e->getMessage());
}

error_log("=== TEST PAGES SCRIPT END ===");

echo "Check complete. See logs in: " . $errorLogFile . "\n";
