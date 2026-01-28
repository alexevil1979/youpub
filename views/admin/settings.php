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

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Настройки системы</h1>
        <p class="page-subtitle">
            Глобальные параметры сессий, безопасности и SEO для всего проекта.
        </p>
    </div>
</div>

<form method="post" action="/admin/settings" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

    <fieldset>
        <legend>Сессии и безопасность</legend>

        <div class="form-group">
            <label for="session_lifetime_hours">
                Время жизни сессии (в часах, минимум 2)
            </label>
            <input type="number"
                   id="session_lifetime_hours"
                   name="session_lifetime_hours"
                   min="2"
                   step="1"
                   value="<?= (int)$settings['session_lifetime_hours'] ?>">
            <small>Рекомендуется 10 часов для удобной работы в админке.</small>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="session_strict_ip" value="1"<?= !empty($settings['session_strict_ip']) ? ' checked' : '' ?>>
                Жёстко привязывать сессию к IP (разлогинивать при смене IP)
            </label>
            <small>Отключите для работы из мобильных сетей/через прокси.</small>
        </div>
    </fieldset>

    <fieldset>
        <legend>Общие настройки и SEO</legend>

        <div class="form-group">
            <label for="site_name">
                Название проекта (APP_NAME)
            </label>
            <input type="text"
                   id="site_name"
                   name="site_name"
                   value="<?= htmlspecialchars($settings['site_name'] ?? 'YouPub', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="site_url">
                Базовый URL сайта (APP_URL)
            </label>
            <input type="url"
                   id="site_url"
                   name="site_url"
                   value="<?= htmlspecialchars($settings['site_url'] ?? 'https://you.1tlt.ru', ENT_QUOTES) ?>">
            <small>Используется для ссылок, канонических URL и интеграций.</small>
        </div>

        <div class="form-group">
            <label for="seo_title_suffix">
                Суффикс к заголовку страницы (SEO Title suffix)
            </label>
            <input type="text"
                   id="seo_title_suffix"
                   name="seo_title_suffix"
                   value="<?= htmlspecialchars($settings['seo_title_suffix'] ?? ' - Автоматическая публикация видео', ENT_QUOTES) ?>">
            <small>Например: <code> - Автоматическая публикация видео</code></small>
        </div>

        <div class="form-group">
            <label for="seo_default_description">
                Базовое описание (Meta Description по умолчанию)
            </label>
            <textarea id="seo_default_description"
                      name="seo_default_description"
                      rows="3"
                      placeholder="Краткое описание сервиса YouPub..."><?= htmlspecialchars($settings['seo_default_description'] ?? '', ENT_QUOTES) ?></textarea>
            <small>Используется как запасное описание, если у страницы нет собственного.</small>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить настройки</button>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

