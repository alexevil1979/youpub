<?php
$title = 'Профиль';
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
ob_start();
?>

<h1>Мой профиль</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="profile-section">
    <h2>Информация о пользователе</h2>
    <table class="profile-table">
        <tr>
            <th>Email:</th>
            <td><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Имя:</th>
            <td><?= htmlspecialchars($_SESSION['user_name'] ?? 'Не указано') ?></td>
        </tr>
        <tr>
            <th>Роль:</th>
            <td><?= htmlspecialchars(ucfirst($_SESSION['user_role'] ?? 'user')) ?></td>
        </tr>
    </table>
</div>

<div class="profile-section">
    <h2>Изменить пароль</h2>
    <form method="POST" action="/profile/change-password" class="profile-form">
        <input type="hidden" name="csrf_token" value="<?= (new \Core\Auth())->generateCsrfToken() ?>">
        
        <div class="form-group">
            <label for="current_password">Текущий пароль</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
            <label for="new_password">Новый пароль</label>
            <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password">
            <small class="form-text">Минимум 12 символов, заглавные и строчные буквы, цифры.</small>
        </div>

        <div class="form-group">
            <label for="confirm_password">Подтвердите пароль</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="12" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Изменить пароль</button>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
