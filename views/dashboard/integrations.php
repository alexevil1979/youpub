<?php
$title = 'Интеграции';
ob_start();

use App\Repositories\YoutubeIntegrationRepository;
use App\Repositories\TelegramIntegrationRepository;
use App\Repositories\TiktokIntegrationRepository;
use App\Repositories\InstagramIntegrationRepository;
use App\Repositories\PinterestIntegrationRepository;

$userId = $_SESSION['user_id'];
$youtubeRepo = new YoutubeIntegrationRepository();
$telegramRepo = new TelegramIntegrationRepository();
$tiktokRepo = new TiktokIntegrationRepository();
$instagramRepo = new InstagramIntegrationRepository();
$pinterestRepo = new PinterestIntegrationRepository();

$youtubeIntegration = $youtubeRepo->findByUserId($userId);
$telegramIntegration = $telegramRepo->findByUserId($userId);
$tiktokIntegration = $tiktokRepo->findByUserId($userId);
$instagramIntegration = $instagramRepo->findByUserId($userId);
$pinterestIntegration = $pinterestRepo->findByUserId($userId);
?>

<h1>Интеграции</h1>

<div class="integrations-grid">
    <!-- YouTube интеграция -->
    <div class="integration-card">
        <h2>YouTube</h2>
        <?php if ($youtubeIntegration && $youtubeIntegration['status'] === 'connected'): ?>
            <div class="integration-status connected">
                <p>✓ Подключено</p>
                <p>Канал: <?= htmlspecialchars($youtubeIntegration['channel_name'] ?? 'Не указан') ?></p>
                <a href="/integrations/youtube/disconnect" class="btn btn-danger">Отключить</a>
            </div>
        <?php else: ?>
            <div class="integration-status disconnected">
                <p>Не подключено</p>
                <p>Подключите свой YouTube канал для автоматической публикации видео</p>
                <a href="/integrations/youtube" class="btn btn-primary">Подключить YouTube</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Telegram интеграция -->
    <div class="integration-card">
        <h2>Telegram</h2>
        <?php if ($telegramIntegration && $telegramIntegration['status'] === 'connected'): ?>
            <div class="integration-status connected">
                <p>✓ Подключено</p>
                <p>Канал: <?= htmlspecialchars($telegramIntegration['channel_username'] ?? 'Не указан') ?></p>
                <a href="/integrations/telegram/disconnect" class="btn btn-danger">Отключить</a>
            </div>
        <?php else: ?>
            <div class="integration-status disconnected">
                <p>Не подключено</p>
                <p>Подключите Telegram бота для публикации в канал</p>
                <form method="POST" action="/integrations/telegram" class="telegram-form">
                    <input type="hidden" name="csrf_token" value="<?= (new \Core\Auth())->generateCsrfToken() ?>">
                    
                    <div class="form-group">
                        <label for="bot_token">Токен бота</label>
                        <input type="text" id="bot_token" name="bot_token" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" required>
                        <small>Получите токен у @BotFather в Telegram</small>
                    </div>

                    <div class="form-group">
                        <label for="channel_id">ID канала</label>
                        <input type="text" id="channel_id" name="channel_id" placeholder="@your_channel или -1001234567890" required>
                        <small>ID канала или username (начинается с @)</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Подключить Telegram</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- TikTok интеграция -->
    <div class="integration-card">
        <h2>TikTok</h2>
        <?php if ($tiktokIntegration && $tiktokIntegration['status'] === 'connected'): ?>
            <div class="integration-status connected">
                <p>✓ Подключено</p>
                <p>Аккаунт: <?= htmlspecialchars($tiktokIntegration['username'] ?? 'Не указан') ?></p>
                <a href="/integrations/tiktok/disconnect" class="btn btn-danger">Отключить</a>
            </div>
        <?php else: ?>
            <div class="integration-status disconnected">
                <p>Не подключено</p>
                <p>Подключите TikTok аккаунт для публикации видео</p>
                <a href="/integrations/tiktok" class="btn btn-primary">Подключить TikTok</a>
                <small>Требуется TikTok for Developers API</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Instagram интеграция -->
    <div class="integration-card">
        <h2>Instagram Reels</h2>
        <?php if ($instagramIntegration && $instagramIntegration['status'] === 'connected'): ?>
            <div class="integration-status connected">
                <p>✓ Подключено</p>
                <p>Аккаунт: <?= htmlspecialchars($instagramIntegration['username'] ?? 'Не указан') ?></p>
                <a href="/integrations/instagram/disconnect" class="btn btn-danger">Отключить</a>
            </div>
        <?php else: ?>
            <div class="integration-status disconnected">
                <p>Не подключено</p>
                <p>Подключите Instagram для публикации Reels</p>
                <a href="/integrations/instagram" class="btn btn-primary">Подключить Instagram</a>
                <small>Требуется Instagram Graph API</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pinterest интеграция -->
    <div class="integration-card">
        <h2>Pinterest</h2>
        <?php if ($pinterestIntegration && $pinterestIntegration['status'] === 'connected'): ?>
            <div class="integration-status connected">
                <p>✓ Подключено</p>
                <p>Доска: <?= htmlspecialchars($pinterestIntegration['board_name'] ?? 'Не указана') ?></p>
                <a href="/integrations/pinterest/disconnect" class="btn btn-danger">Отключить</a>
            </div>
        <?php else: ?>
            <div class="integration-status disconnected">
                <p>Не подключено</p>
                <p>Подключите Pinterest для публикации Idea Pins / Video Pins</p>
                <a href="/integrations/pinterest" class="btn btn-primary">Подключить Pinterest</a>
                <small>Требуется Pinterest API v5</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
