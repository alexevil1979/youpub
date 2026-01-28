<?php
/** @var array $settings значения из AppSettingsService (передаёт контроллер) */

$title = 'Настройки системы';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback, если контроллер не передал $settings
if (!isset($settings)) {
    $env = require __DIR__ . '/../../config/env.php';
    $sessionSeconds = (int)($env['SESSION_LIFETIME'] ?? 36000);
    $settings = [
        'session_lifetime_hours' => max(2, (int)round($sessionSeconds / 3600)),
        'session_strict_ip'      => (bool)($env['SESSION_STRICT_IP'] ?? false),
        'site_name'              => $env['APP_NAME'] ?? 'YouPub',
        'site_url'               => $env['APP_URL'] ?? 'https://you.1tlt.ru',
        'seo_title_suffix'       => ' - Автоматическая публикация видео',
        'seo_default_description'=> '',
    ];
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
                   value="<?= (int)$settings['session_lifetime_hours'] ?>">
            <small>Рекомендуется 10 часов для удобной работы в админке.</small>
        </label>

        <label>
            <input type="checkbox" name="session_strict_ip" value="1"<?= !empty($settings['session_strict_ip']) ? ' checked' : '' ?>>
            Жёстко привязывать сессию к IP (разлогинивать при смене IP)
        </label>
        <small>Отключите для работы из мобильных сетей/через прокси.</small>
    </fieldset>

    <fieldset>
        <legend>Общие настройки и SEO</legend>

        <label>
            Название проекта (APP_NAME):
            <input type="text" name="site_name"
                   value="<?= htmlspecialchars($settings['site_name'] ?? 'YouPub', ENT_QUOTES) ?>">
        </label>

        <label>
            Базовый URL сайта (APP_URL):
            <input type="url" name="site_url"
                   value="<?= htmlspecialchars($settings['site_url'] ?? 'https://you.1tlt.ru', ENT_QUOTES) ?>">
            <small>Используется для ссылок, канонических URL и интеграций.</small>
        </label>

        <label>
            Суффикс к заголовку страницы (SEO Title suffix):
            <input type="text" name="seo_title_suffix"
                   value="<?= htmlspecialchars($settings['seo_title_suffix'] ?? ' - Автоматическая публикация видео', ENT_QUOTES) ?>">
            <small>Например: <code> - Автоматическая публикация видео</code></small>
        </label>

        <label>
            Базовое описание (Meta Description по умолчанию):
            <textarea name="seo_default_description" rows="3" placeholder="Краткое описание сервиса YouPub..."><?= htmlspecialchars($settings['seo_default_description'] ?? '', ENT_QUOTES) ?></textarea>
            <small>Используется как запасное описание, если у страницы нет собственного.</small>
        </label>
    </fieldset>

    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

