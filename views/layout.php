<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'YouPub' ?> - Автоматическая публикация видео</title>
    <?php
    $csrfToken = (new \Core\Auth())->generateCsrfToken();
    ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="navbar">
        <div class="container">
            <a href="/dashboard" class="logo">YouPub</a>
            <div class="nav-links">
                <a href="/videos">Видео</a>
                <a href="/content-groups">Группы</a>
                <a href="/content-groups/templates">Шаблоны</a>
                <a href="/content-groups/schedules">Расписания</a>
                <a href="/dashboard">Дашборд</a>
                <a href="/integrations">Интеграции</a>
                <a href="/statistics">Статистика</a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="/admin">Админка</a>
                <?php endif; ?>
                <a href="/profile">Профиль</a>
                <a href="/logout">Выход</a>
            </div>
            <div class="global-search">
                <div class="search-container">
                    <input type="text" 
                           id="global-search-input" 
                           class="search-input" 
                           placeholder="Поиск..." 
                           autocomplete="off">
                    <div id="search-results" class="search-results"></div>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="container">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            // Генерируем хлебные крошки, если они не переданы явно
            if (!isset($breadcrumbs)) {
                $breadcrumbs = \Core\Breadcrumbs::generateFromUrl();
            }
            echo \Core\Breadcrumbs::render($breadcrumbs ?? null);
            ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> YouPub. Все права защищены.</p>
        </div>
    </footer>

    <script src="/assets/js/icons.js"></script>
    <script src="/assets/js/main.js"></script>
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="/assets/js/search.js"></script>
    <?php endif; ?>
</body>
</html>
