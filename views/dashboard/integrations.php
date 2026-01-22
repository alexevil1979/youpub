<?php
$title = 'Интеграции';
ob_start();
?>

<h1>Интеграции</h1>

<div class="integrations-grid" style="display: grid; gap: 1.5rem; margin-top: 2rem;">
    
    <!-- YouTube -->
    <div class="integration-card">
        <div class="integration-header">
            <h2>📺 YouTube</h2>
            <a href="/integrations/youtube" class="btn">Добавить канал</a>
        </div>
        
        <?php if (empty($youtubeAccounts)): ?>
            <p class="integration-status">Не подключено</p>
            <p class="integration-description">Подключите свой YouTube канал для автоматической публикации видео</p>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($youtubeAccounts as $account): ?>
                    <div class="account-item <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-info">
                            <div class="account-name">
                                <strong><?= htmlspecialchars($account['account_name'] ?? $account['channel_name'] ?? 'YouTube канал') ?></strong>
                                <?php if ($account['is_default']): ?>
                                    <span class="badge badge-success" style="margin-left: 0.5rem;">По умолчанию</span>
                                <?php endif; ?>
                            </div>
                            <div class="account-details">
                                <?php if ($account['channel_name']): ?>
                                    <span>Канал: <?= htmlspecialchars($account['channel_name']) ?></span>
                                <?php endif; ?>
                                <span class="account-status badge badge-<?= $account['status'] === 'connected' ? 'success' : ($account['status'] === 'error' ? 'danger' : 'secondary') ?>">
                                    <?= $account['status'] === 'connected' ? '✓ Подключено' : ucfirst($account['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="account-actions">
                            <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="setDefaultAccount('youtube', <?= $account['id'] ?>)">Сделать по умолчанию</button>
                            <?php endif; ?>
                            <?php if ($account['status'] === 'connected'): ?>
                                <button type="button" class="btn btn-sm btn-warning" onclick="disconnectAccount('youtube', <?= $account['id'] ?>)">Отключить</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteAccount('youtube', <?= $account['id'] ?>)">Удалить</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Telegram -->
    <div class="integration-card">
        <div class="integration-header">
            <h2>💬 Telegram</h2>
            <button type="button" class="btn btn-primary btn-sm" onclick="showTelegramForm()">+ Добавить канал</button>
        </div>
        
        <?php if (empty($telegramAccounts)): ?>
            <p class="integration-status">Не подключено</p>
            <p class="integration-description">Подключите Telegram бота для публикации в каналы</p>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($telegramAccounts as $account): ?>
                    <div class="account-item <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-info">
                            <div class="account-name">
                                <strong><?= htmlspecialchars($account['account_name'] ?? $account['channel_username'] ?? 'Telegram канал') ?></strong>
                                <?php if ($account['is_default']): ?>
                                    <span class="badge badge-success" style="margin-left: 0.5rem;">По умолчанию</span>
                                <?php endif; ?>
                            </div>
                            <div class="account-details">
                                <?php if ($account['channel_username']): ?>
                                    <span>@<?= htmlspecialchars($account['channel_username']) ?></span>
                                <?php endif; ?>
                                <span class="account-status badge badge-<?= $account['status'] === 'connected' ? 'success' : ($account['status'] === 'error' ? 'danger' : 'secondary') ?>">
                                    <?= $account['status'] === 'connected' ? '✓ Подключено' : ucfirst($account['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="account-actions">
                            <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="setDefaultAccount('telegram', <?= $account['id'] ?>)">Сделать по умолчанию</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteAccount('telegram', <?= $account['id'] ?>)">Удалить</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TikTok -->
    <div class="integration-card">
        <div class="integration-header">
            <h2>🎵 TikTok</h2>
            <a href="/integrations/tiktok" class="btn">Добавить аккаунт</a>
        </div>
        
        <?php if (empty($tiktokAccounts)): ?>
            <p class="integration-status">Не подключено</p>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($tiktokAccounts as $account): ?>
                    <div class="account-item">
                        <div class="account-info">
                            <strong><?= htmlspecialchars($account['account_name'] ?? $account['username'] ?? 'TikTok аккаунт') ?></strong>
                            <?php if ($account['is_default']): ?>
                                <span class="badge badge-success">По умолчанию</span>
                            <?php endif; ?>
                        </div>
                        <div class="account-actions">
                            <?php if (!$account['is_default']): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="setDefaultAccount('tiktok', <?= $account['id'] ?>)">По умолчанию</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteAccount('tiktok', <?= $account['id'] ?>)">Удалить</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Instagram -->
    <div class="integration-card">
        <div class="integration-header">
            <h2>📷 Instagram</h2>
            <a href="/integrations/instagram" class="btn">Добавить аккаунт</a>
        </div>
        
        <?php if (empty($instagramAccounts)): ?>
            <p class="integration-status">Не подключено</p>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($instagramAccounts as $account): ?>
                    <div class="account-item">
                        <div class="account-info">
                            <strong><?= htmlspecialchars($account['account_name'] ?? $account['username'] ?? 'Instagram аккаунт') ?></strong>
                            <?php if ($account['is_default']): ?>
                                <span class="badge badge-success">По умолчанию</span>
                            <?php endif; ?>
                        </div>
                        <div class="account-actions">
                            <?php if (!$account['is_default']): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="setDefaultAccount('instagram', <?= $account['id'] ?>)">По умолчанию</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteAccount('instagram', <?= $account['id'] ?>)">Удалить</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pinterest -->
    <div class="integration-card">
        <div class="integration-header">
            <h2>📌 Pinterest</h2>
            <a href="/integrations/pinterest" class="btn">Добавить аккаунт</a>
        </div>
        
        <?php if (empty($pinterestAccounts)): ?>
            <p class="integration-status">Не подключено</p>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($pinterestAccounts as $account): ?>
                    <div class="account-item">
                        <div class="account-info">
                            <strong><?= htmlspecialchars($account['account_name'] ?? $account['username'] ?? 'Pinterest аккаунт') ?></strong>
                            <?php if ($account['is_default']): ?>
                                <span class="badge badge-success">По умолчанию</span>
                            <?php endif; ?>
                        </div>
                        <div class="account-actions">
                            <?php if (!$account['is_default']): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="setDefaultAccount('pinterest', <?= $account['id'] ?>)">По умолчанию</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteAccount('pinterest', <?= $account['id'] ?>)">Удалить</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function setDefaultAccount(platform, accountId) {
    fetch('/integrations/' + platform + '/set-default', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'account_id=' + accountId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Аккаунт установлен по умолчанию');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось установить аккаунт по умолчанию'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function disconnectAccount(platform, accountId) {
    if (!confirm('Отключить этот аккаунт?')) {
        return;
    }
    
    fetch('/integrations/' + platform + '/disconnect', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'account_id=' + accountId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Аккаунт отключен');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось отключить аккаунт'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function deleteAccount(platform, accountId) {
    if (!confirm('Удалить этот аккаунт? Это действие нельзя отменить.')) {
        return;
    }
    
    fetch('/integrations/' + platform + '/delete', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'account_id=' + accountId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Аккаунт удален');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить аккаунт'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function showTelegramForm() {
    const form = prompt('Введите токен бота и ID канала через запятую (bot_token,channel_id):');
    if (form) {
        const parts = form.split(',');
        if (parts.length === 2) {
            // Отправка формы
            const formData = new FormData();
            formData.append('bot_token', parts[0].trim());
            formData.append('channel_id', parts[1].trim());
            
            fetch('/integrations/telegram', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Telegram подключен');
                    window.location.reload();
                } else {
                    alert('Ошибка: ' + (data.message || 'Не удалось подключить Telegram'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Произошла ошибка');
            });
        }
    }
}
</script>

<style>
.integrations-grid {
    display: grid;
    gap: 1.5rem;
}

.integration-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #3498db;
}

.integration-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.integration-header h2 {
    margin: 0;
    font-size: 1.25rem;
}

.integration-status {
    color: #95a5a6;
    font-weight: 500;
    margin: 0.5rem 0;
}

.integration-description {
    color: #666;
    font-size: 0.9rem;
    margin: 0.5rem 0;
}

.accounts-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.account-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    transition: all 0.2s ease;
}

.account-item:hover {
    background: #f0f0f0;
    border-color: #3498db;
}

.account-connected {
    border-left: 4px solid #27ae60;
}

.account-disconnected {
    border-left: 4px solid #95a5a6;
    opacity: 0.8;
}

.account-info {
    flex: 1;
}

.account-name {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.account-details {
    display: flex;
    gap: 1rem;
    align-items: center;
    font-size: 0.875rem;
    color: #666;
}

.account-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.account-actions .btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    text-decoration: none;
    white-space: nowrap;
}

.account-actions .btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.account-actions .btn-success {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(39, 174, 96, 0.3);
}

.account-actions .btn-success:hover {
    background: linear-gradient(135deg, #229954 0%, #1e8449 100%);
    box-shadow: 0 4px 8px rgba(39, 174, 96, 0.4);
    transform: translateY(-1px);
}

.account-actions .btn-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(243, 156, 18, 0.3);
}

.account-actions .btn-warning:hover {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
    box-shadow: 0 4px 8px rgba(243, 156, 18, 0.4);
    transform: translateY(-1px);
}

.account-actions .btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(231, 76, 60, 0.3);
}

.account-actions .btn-danger:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
    box-shadow: 0 4px 8px rgba(231, 76, 60, 0.4);
    transform: translateY(-1px);
}

.account-actions .btn:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.integration-header .btn {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.integration-header .btn:hover {
    background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    transform: translateY(-1px);
}

.integration-header .btn:active {
    transform: translateY(0);
    box-shadow: 0 1px 3px rgba(52, 152, 219, 0.3);
}

.integration-header .btn::before {
    content: '+';
    font-size: 1.1rem;
    font-weight: bold;
    line-height: 1;
}

@media (max-width: 768px) {
    .account-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .account-actions {
        width: 100%;
        margin-top: 0.75rem;
        justify-content: flex-start;
    }
    
    .account-actions .btn {
        flex: 1;
        justify-content: center;
        min-width: 120px;
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
