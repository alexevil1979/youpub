<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'YouPub' ?> - Автоматическая публикация видео</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="navbar">
        <div class="container">
            <a href="/dashboard" class="logo">YouPub</a>
            <div class="nav-links">
                <a href="/dashboard">Дашборд</a>
                <a href="/videos">Видео</a>
                <a href="/schedules">Расписания</a>
                <a href="/content-groups">Группы</a>
                <a href="/integrations">Интеграции</a>
                <a href="/statistics">Статистика</a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="/admin">Админка</a>
                <?php endif; ?>
                <a href="/profile">Профиль</a>
                <a href="/logout">Выход</a>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="container">
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

    <script src="/assets/js/main.js"></script>
</body>
</html>
