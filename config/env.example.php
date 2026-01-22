<?php
/**
 * Конфигурация окружения
 * Скопируйте в env.php и заполните значения
 */

return [
    // Приложение
    'APP_NAME' => 'YouPub',
    'APP_URL' => 'https://you.1tlt.ru',
    'APP_ENV' => 'production', // development, production
    'APP_DEBUG' => false,
    
    // База данных
    'DB_HOST' => '127.0.0.1', // Используйте 127.0.0.1 вместо localhost для TCP/IP
    'DB_NAME' => 'youpub',
    'DB_USER' => 'youpub_user',
    'DB_PASS' => 'qweasd333123',
    'DB_CHARSET' => 'utf8mb4',
    
    // Безопасность
    'SECRET_KEY' => 'CHANGE_THIS_TO_RANDOM_STRING_32_CHARS_MIN',
    'JWT_SECRET' => 'CHANGE_THIS_TO_RANDOM_STRING_32_CHARS_MIN',
    'SESSION_LIFETIME' => 86400, // 24 часа
    
    // Загрузка файлов
    'UPLOAD_DIR' => __DIR__ . '/../storage/uploads',
    'UPLOAD_MAX_SIZE' => 5368709120, // 5GB
    'ALLOWED_VIDEO_TYPES' => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
    
    // YouTube API
    'YOUTUBE_CLIENT_ID' => '',
    'YOUTUBE_CLIENT_SECRET' => '',
    'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/auth/youtube/callback',
    
    // Telegram
    'TELEGRAM_API_URL' => 'https://api.telegram.org/bot',
    
    // Очереди
    'WORKER_INTERVAL' => 60, // секунды
    'WORKER_LOG_DIR' => __DIR__ . '/../storage/logs/workers',
    
    // Лимиты
    'RATE_LIMIT_REQUESTS' => 100,
    'RATE_LIMIT_WINDOW' => 3600, // 1 час
    
    // Email (опционально)
    'SMTP_HOST' => '',
    'SMTP_PORT' => 587,
    'SMTP_USER' => '',
    'SMTP_PASS' => '',
    'SMTP_FROM' => 'noreply@you.1tlt.ru',
];
