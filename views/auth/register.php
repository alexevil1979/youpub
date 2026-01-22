<?php
$title = 'Регистрация';
ob_start();
?>

<div class="auth-container">
    <div class="auth-box">
        <h1>Регистрация</h1>
        <form method="POST" action="/register">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="form-group">
                <label for="name">Имя</label>
                <input type="text" id="name" name="name">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
        </form>

        <p class="auth-link">
            Уже есть аккаунт? <a href="/login">Войти</a>
        </p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
