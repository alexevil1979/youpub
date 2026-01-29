<?php
/**
 * Конфигурация окружения
 * Скопируйте в env.php и заполните значения
 */

return [
    // Приложение
    'APP_NAME' => 'YouPub',
    'APP_URL' => 'https://your-domain.example.com',
    'APP_ENV' => 'production', // development, production
    'APP_DEBUG' => false,      // Никогда не включайте в production
    
    // База данных
    'DB_HOST' => '127.0.0.1', // Используйте 127.0.0.1 вместо localhost для TCP/IP
    'DB_NAME' => 'youpub',
    'DB_USER' => 'youpub_user',
    'DB_PASS' => 'CHANGE_ME_DB_PASSWORD', // Замените на пароль БД в локальной конфигурации env.php
    'DB_CHARSET' => 'utf8mb4',
    
    // Безопасность
    // ОБЯЗАТЕЛЬНО сгенерируйте случайные строки длиной 32+ символов
    // с использованием криптографически стойкого генератора (например, random_bytes)
    'SECRET_KEY' => 'CHANGE_THIS_TO_RANDOM_RANDOM_STRING_32_CHARS_MIN',
    'JWT_SECRET' => 'CHANGE_THIS_TO_RANDOM_RANDOM_STRING_32_CHARS_MIN',
    'SESSION_LIFETIME' => 7200, // 2 часа (минимум), можно увеличить до 86400 (24 часа) или больше
    // Если true — сессия жёстко привязана к IP и при его смене пользователь разлогинивается.
    // Если false — несоответствие IP только логируется (рекомендуется для мобильных/динамических сетей).
    'SESSION_STRICT_IP' => false,
    'TRUSTED_PROXIES' => [], // список доверенных прокси IP
    
    // Загрузка файлов
    'UPLOAD_DIR' => __DIR__ . '/../storage/uploads',
    'UPLOAD_MAX_SIZE' => 5368709120, // 5GB
    'ALLOWED_VIDEO_TYPES' => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
    
    // YouTube API
    'YOUTUBE_CLIENT_ID' => '',
    'YOUTUBE_CLIENT_SECRET' => '',
    'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
    'YOUTUBE_CATEGORY_ID' => '22',
    
    // Telegram
    'TELEGRAM_API_URL' => 'https://api.telegram.org/bot',
    
    // TikTok API
    'TIKTOK_CLIENT_KEY' => '',
    'TIKTOK_CLIENT_SECRET' => '',
    'TIKTOK_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/tiktok/callback',
    
    // Instagram API
    'INSTAGRAM_APP_ID' => '',
    'INSTAGRAM_APP_SECRET' => '',
    'INSTAGRAM_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/instagram/callback',
    
    // Pinterest API
    'PINTEREST_APP_ID' => '',
    'PINTEREST_APP_SECRET' => '',
    'PINTEREST_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/pinterest/callback',
    
    // Очереди
    'WORKER_INTERVAL' => 60, // секунды
    'WORKER_LOG_DIR' => __DIR__ . '/../storage/logs/workers',
    
    // Часовой пояс
    'TIMEZONE' => 'Europe/Samara', // Часовой пояс для PHP и MySQL (UTC+4)
    
    // Лимиты Rate Limiting
    'RATE_LIMIT_REQUESTS' => 100,        // Общий лимит запросов
    'RATE_LIMIT_WINDOW' => 3600,         // Окно времени в секундах (1 час)
    'RATE_LIMIT_API_REQUESTS' => 200,    // Лимит для API endpoints
    'RATE_LIMIT_API_WINDOW' => 3600,     // Окно для API (1 час)
    'RATE_LIMIT_AUTH_REQUESTS' => 10,    // Лимит для авторизации (более строгий)
    'RATE_LIMIT_AUTH_WINDOW' => 600,     // Окно для авторизации (10 минут)
    'RATE_LIMIT_UPLOAD_REQUESTS' => 20,  // Лимит для загрузки файлов
    'RATE_LIMIT_UPLOAD_WINDOW' => 3600,  // Окно для загрузки (1 час)
    
    // Email (опционально)
    'SMTP_HOST' => '',
    'SMTP_PORT' => 587,
    'SMTP_USER' => '',
    'SMTP_PASS' => '',
    'SMTP_FROM' => 'noreply@your-domain.example.com',

    // Debug API (по умолчанию отключён; включайте ТОЛЬКО в защищённой среде)
    'ENABLE_DEBUG_API' => false,

    // Логирование (Monolog)
    'LOG_DIR' => __DIR__ . '/../storage/logs',
    'LOG_LEVEL' => 'debug', // debug, info, notice, warning, error, critical, alert, emergency
];
