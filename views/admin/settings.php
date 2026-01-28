<?php
/** @var array $config */

use App\Modules\ContentGroups\Services\TemplateService;
use App\Modules\ContentGroups\Services\SmartQueueService;

$title = 'Настройки системы';

// Инициализация CSRF токена уже сделана в layout через \Core\Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Получаем конфиг и текущие значения по умолчанию
$envConfig = require __DIR__ . '/../../config/env.php';

$appConfig = $envConfig;

// Попробуем прочитать значения из app_settings, если сервис доступен
try {
    $settingsService = new \App\Modules\ContentGroups\Services\TemplateService(); // заглушка для автозагрузки
} catch (\Throwable $e) {
    // игнорируем, реальные значения прочитываются в контроллере
}

ob_start();
?>

<h1>Настройки системы</h1>

<form method="post" action="/admin/settings" class="form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

    <fieldset>
        <legend>Сессии и безопасность</legend>

        <label>
            Время жизни сессии (в часах, минимум 2):
            <input type="number" name="session_lifetime_hours"
                   min="2"
                   step="1"
                   value="">
            <small>Рекомендуется 10 часов для удобной работы в админке.</small>
        </label>

        <label>
            <input type="checkbox" name="session_strict_ip" value="1">
            Жёстко привязывать сессию к IP (разлогинивать при смене IP)
        </label>
        <small>Отключите для работы из мобильных сетей/через прокси.</small>
    </fieldset>

    <fieldset>
        <legend>Общие настройки и SEO</legend>

        <label>
            Название проекта (APP_NAME):
            <input type="text" name="site_name"
                   value="<?= htmlspecialchars($envConfig['APP_NAME'] ?? 'YouPub', ENT_QUOTES) ?>">
        </label>

        <label>
            Базовый URL сайта (APP_URL):
            <input type="url" name="site_url"
                   value="<?= htmlspecialchars($envConfig['APP_URL'] ?? 'https://you.1tlt.ru', ENT_QUOTES) ?>">
            <small>Используется для ссылок, канонических URL и интеграций.</small>
        </label>

        <label>
            Суффикс к заголовку страницы (SEO Title suffix):
            <input type="text" name="seo_title_suffix"
                   value=" - Автоматическая публикация видео">
            <small>Например: <code> - Автоматическая публикация видео</code></small>
        </label>

        <label>
            Базовое описание (Meta Description по умолчанию):
            <textarea name="seo_default_description" rows="3" placeholder="Краткое описание сервиса YouPub..."></textarea>
            <small>Используется как запасное описание, если у страницы нет собственного.</small>
        </label>
    </fieldset>

    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

