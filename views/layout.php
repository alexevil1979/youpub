<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'YouPub' ?> - Автоматическая публикация видео</title>
    <?php
    // Убеждаемся, что сессия инициализирована
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    try {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
    } catch (\Throwable $e) {
        error_log("Layout: Error generating CSRF token: " . $e->getMessage());
        $csrfToken = '';
    }
    ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <!-- Основные стили приложения -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- Иконки Font Awesome для действий и навигации -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script>
        // Моментально применяем состояние свёрнутого сайдбара до рендера контента
        (function() {
            try {
                if (window.localStorage && localStorage.getItem('youpub_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {
                // игнорируем ошибки доступа к localStorage
            }
        })();
    </script>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Основная оболочка админ-панели с сайдбаром -->
        <div class="app-layout">
            <aside class="sidebar" id="sidebar" aria-label="Основная навигация">
                <div class="sidebar-header">
                    <a href="/dashboard" class="sidebar-logo">
                        <span class="sidebar-logo-mark">Y</span>
                        <span class="sidebar-logo-text">YouPub</span>
                    </a>
                    <button type="button"
                            class="sidebar-collapse-toggle"
                            aria-label="Свернуть или развернуть меню"
                            aria-pressed="false">
                        <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <a href="/dashboard" class="sidebar-link <?= ($_SERVER['REQUEST_URI'] ?? '') === '/dashboard' ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-gauge-high sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Дашборд</span>
                    </a>
                    <a href="/videos" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/videos') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-video sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Видео</span>
                    </a>
                    <a href="/content-groups" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/content-groups') && !str_contains($_SERVER['REQUEST_URI'], '/templates') && !str_contains($_SERVER['REQUEST_URI'], '/schedules') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-folder-tree sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Группы контента</span>
                    </a>
                    <a href="/content-groups/templates" class="sidebar-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/content-groups/templates') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-layer-group sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Шаблоны</span>
                    </a>
                    <a href="/content-groups/schedules" class="sidebar-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/content-groups/schedules') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-calendar-check sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Умные расписания</span>
                    </a>
                    <a href="/integrations" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/integrations') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-plug sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Интеграции</span>
                    </a>
                    <a href="/statistics" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/statistics') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-chart-line sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Статистика</span>
                    </a>
                    <?php if (($_SESSION['user_role'] ?? null) === 'admin'): ?>
                        <a href="/admin" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin') ? 'is-active' : '' ?>">
                            <i class="fa-solid fa-shield-halved sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text">Админка</span>
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="sidebar-footer">
                    <a href="/profile" class="sidebar-link sidebar-link-secondary <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/profile') ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-user sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Профиль</span>
                    </a>
                    <a href="/logout" class="sidebar-link sidebar-link-secondary sidebar-logout">
                        <i class="fa-solid fa-right-from-bracket sidebar-link-icon" aria-hidden="true"></i>
                        <span class="sidebar-link-text">Выход</span>
                    </a>
                </div>
            </aside>

            <div class="app-main">
                <header class="topbar" role="banner">
                    <button class="sidebar-toggle"
                            type="button"
                            aria-label="Переключить боковое меню"
                            aria-expanded="false"
                            aria-controls="sidebar">
                        <span class="sidebar-toggle-bar"></span>
                        <span class="sidebar-toggle-bar"></span>
                        <span class="sidebar-toggle-bar"></span>
                    </button>

                    <div class="topbar-title">
                        <span class="topbar-page-title"><?= htmlspecialchars($title ?? 'Панель управления') ?></span>
                    </div>

                    <div class="global-search" role="search">
                        <div class="search-container">
                            <input type="text"
                                   id="global-search-input"
                                   class="search-input"
                                   placeholder="Поиск по видео, группам, расписаниям..."
                                   autocomplete="off"
                                   aria-label="Глобальный поиск">
                            <div id="search-results" class="search-results" role="listbox"></div>
                        </div>
                    </div>
                </header>

                <main class="main-content" role="main">
                    <?php
                    // Генерируем хлебные крошки, если они не переданы явно
                    try {
                        if (!isset($breadcrumbs)) {
                            $breadcrumbs = \Core\Breadcrumbs::generateFromUrl();
                        }
                        echo \Core\Breadcrumbs::render($breadcrumbs ?? null);
                    } catch (\Throwable $breadcrumbError) {
                        error_log("Layout: Error generating breadcrumbs: " . $breadcrumbError->getMessage());
                    }
                    ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success" role="status"><?= htmlspecialchars($_SESSION['success']) ?></div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-error" role="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?= $content ?? '' ?>
                </main>

                <footer class="app-footer">
                    <div class="app-footer-inner">
                        <p>&copy; <?= date('Y') ?> YouPub. Все права защищены.</p>
                    </div>
                </footer>
            </div>
        </div>
    <?php else: ?>
        <!-- Макет для неавторизованных страниц (логин/регистрация и т.п.) -->
        <main class="auth-main">
            <div class="container">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success" role="status"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error" role="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?= $content ?? '' ?>
            </div>
        </main>

        <footer class="app-footer">
            <div class="app-footer-inner">
                <p>&copy; <?= date('Y') ?> YouPub. Все права защищены.</p>
            </div>
        </footer>
    <?php endif; ?>

    <script src="/assets/js/icons.js"></script>
    <script src="/assets/js/main.js"></script>
    <?php if (isset($_SESSION['user_id'])): ?>
        <script src="/assets/js/search.js"></script>
    <?php endif; ?>
</body>
</html>
