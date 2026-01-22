<?php
$title = 'Вход';
ob_start();
?>

<div class="auth-container">
    <div class="auth-box">
        <h1>Вход в систему</h1>
        <form method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Войти</button>
        </form>

        <p class="auth-link">
            Нет аккаунта? <a href="/register">Зарегистрироваться</a>
        </p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
