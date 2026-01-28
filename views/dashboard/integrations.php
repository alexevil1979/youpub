<?php
$title = 'Интеграции';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Интеграции</h1>
        <p class="page-subtitle">
            Подключайте каналы и аккаунты на платформах, где будут публиковаться ваши видео.
        </p>
    </div>
</div>

<div class="integrations-grid">
    
    <!-- YouTube -->
    <div class="integration-card">
        <div class="integration-header">
            <h2><?= \App\Helpers\IconHelper::render('youtube', 24, 'icon-inline') ?> YouTube</h2>
            <a href="/integrations/youtube" class="btn">Добавить канал</a>
        </div>
        
        <?php if (empty($youtubeAccounts)): ?>
            <div class="integration-empty-state">
                <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('youtube', 48) ?></div>
                <p class="integration-status">Нет подключенных каналов</p>
                <p class="integration-description">Подключите свой YouTube канал для автоматической публикации видео</p>
            </div>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($youtubeAccounts as $account): ?>
                    <div class="account-card <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-card-body">
                            <div class="account-left-section">
                                <div class="account-icon-wrapper">
                                    <div class="account-platform-icon"><?= \App\Helpers\IconHelper::render('youtube', 40) ?></div>
                                    <?php if ($account['status'] === 'connected'): ?>
                                        <div class="account-status-indicator connected"></div>
                                    <?php else: ?>
                                        <div class="account-status-indicator disconnected"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="account-info-content">
                                    <div class="account-header-row">
                                        <h3 class="account-title"><?= htmlspecialchars($account['account_name'] ?? $account['channel_name'] ?? 'YouTube канал') ?></h3>
                                        <div class="account-badges-row">
                                            <?php if ($account['is_default']): ?>
                                                <span class="badge badge-default"><?= \App\Helpers\IconHelper::render('star', 16, 'icon-inline') ?> По умолчанию</span>
                                            <?php endif; ?>
                                            <span class="account-status-badge status-<?= $account['status'] === 'connected' ? 'connected' : ($account['status'] === 'error' ? 'error' : 'disconnected') ?>">
                                                <?php 
                                                $statusText = 'Не подключено';
                                                if ($account['status'] === 'connected') {
                                                    $statusText = 'Подключено';
                                                } elseif ($account['status'] === 'error') {
                                                    $statusText = 'Ошибка';
                                                } elseif ($account['status'] === 'pending') {
                                                    $statusText = 'Ожидание';
                                                } elseif ($account['status'] === 'disconnected') {
                                                    $statusText = 'Отключено';
                                                }
                                                ?>
                                                <span class="status-dot"></span> <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($account['channel_name'] && $account['channel_name'] !== ($account['account_name'] ?? '')): ?>
                                        <p class="account-subtitle"><?= htmlspecialchars($account['channel_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="account-actions-compact">
                                <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                    <button type="button" class="btn-action-icon btn-action-success" onclick="setDefaultAccount('youtube', <?= $account['id'] ?>)" title="Сделать по умолчанию"><?= \App\Helpers\IconHelper::render('star', 20) ?></button>
                                <?php endif; ?>
                                <?php if ($account['status'] === 'connected'): ?>
                                    <button type="button" class="btn-action-icon btn-action-warning" onclick="disconnectAccount('youtube', <?= $account['id'] ?>)" title="Отключить"><?= \App\Helpers\IconHelper::render('pause', 20) ?></button>
                                <?php endif; ?>
                                <button type="button" class="btn-action-icon btn-action-danger" onclick="deleteAccount('youtube', <?= $account['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 20) ?></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Telegram -->
    <div class="integration-card">
        <div class="integration-header">
            <h2><?= \App\Helpers\IconHelper::render('telegram', 24, 'icon-inline') ?> Telegram</h2>
            <button type="button" class="btn" onclick="showTelegramForm()">Добавить канал</button>
        </div>
        
        <?php if (empty($telegramAccounts)): ?>
            <div class="integration-empty-state">
                <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('telegram', 48) ?></div>
                <p class="integration-status">Нет подключенных каналов</p>
                <p class="integration-description">Подключите Telegram бота для публикации в каналы</p>
            </div>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($telegramAccounts as $account): ?>
                    <div class="account-card <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-card-body">
                            <div class="account-left-section">
                                <div class="account-icon-wrapper">
                                    <div class="account-platform-icon"><?= \App\Helpers\IconHelper::render('telegram', 40) ?></div>
                                    <?php if ($account['status'] === 'connected'): ?>
                                        <div class="account-status-indicator connected"></div>
                                    <?php else: ?>
                                        <div class="account-status-indicator disconnected"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="account-info-content">
                                    <div class="account-header-row">
                                        <h3 class="account-title"><?= htmlspecialchars($account['account_name'] ?? $account['channel_username'] ?? 'Telegram канал') ?></h3>
                                        <div class="account-badges-row">
                                            <?php if ($account['is_default']): ?>
                                                <span class="badge badge-default"><?= \App\Helpers\IconHelper::render('star', 16, 'icon-inline') ?> По умолчанию</span>
                                            <?php endif; ?>
                                            <span class="account-status-badge status-<?= $account['status'] === 'connected' ? 'connected' : ($account['status'] === 'error' ? 'error' : 'disconnected') ?>">
                                                <?php 
                                                $statusText = 'Не подключено';
                                                if ($account['status'] === 'connected') {
                                                    $statusText = 'Подключено';
                                                } elseif ($account['status'] === 'error') {
                                                    $statusText = 'Ошибка';
                                                } elseif ($account['status'] === 'pending') {
                                                    $statusText = 'Ожидание';
                                                } elseif ($account['status'] === 'disconnected') {
                                                    $statusText = 'Отключено';
                                                }
                                                ?>
                                                <span class="status-dot"></span> <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($account['channel_username']): ?>
                                        <p class="account-subtitle">@<?= htmlspecialchars($account['channel_username']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="account-actions-compact">
                                <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                    <button type="button" class="btn-action-icon btn-action-success" onclick="setDefaultAccount('telegram', <?= $account['id'] ?>)" title="Сделать по умолчанию">⭐</button>
                                <?php endif; ?>
                                <button type="button" class="btn-action-icon btn-action-danger" onclick="deleteAccount('telegram', <?= $account['id'] ?>)" title="Удалить">🗑</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TikTok -->
    <div class="integration-card">
        <div class="integration-header">
            <h2><?= \App\Helpers\IconHelper::render('tiktok', 24, 'icon-inline') ?> TikTok</h2>
            <a href="/integrations/tiktok" class="btn">Добавить аккаунт</a>
        </div>
        
        <?php if (empty($tiktokAccounts)): ?>
            <div class="integration-empty-state">
                <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('tiktok', 48) ?></div>
                <p class="integration-status">Нет подключенных аккаунтов</p>
                <p class="integration-description">Подключите TikTok аккаунт для публикации видео</p>
            </div>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($tiktokAccounts as $account): ?>
                    <div class="account-card <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-card-body">
                            <div class="account-left-section">
                                <div class="account-icon-wrapper">
                                    <div class="account-platform-icon"><?= \App\Helpers\IconHelper::render('tiktok', 40) ?></div>
                                    <?php if ($account['status'] === 'connected'): ?>
                                        <div class="account-status-indicator connected"></div>
                                    <?php else: ?>
                                        <div class="account-status-indicator disconnected"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="account-info-content">
                                    <div class="account-header-row">
                                        <h3 class="account-title"><?= htmlspecialchars($account['account_name'] ?? $account['username'] ?? 'TikTok аккаунт') ?></h3>
                                        <div class="account-badges-row">
                                            <?php if ($account['is_default']): ?>
                                                <span class="badge badge-default"><?= \App\Helpers\IconHelper::render('star', 16, 'icon-inline') ?> По умолчанию</span>
                                            <?php endif; ?>
                                            <span class="account-status-badge status-<?= $account['status'] === 'connected' ? 'connected' : ($account['status'] === 'error' ? 'error' : 'disconnected') ?>">
                                                <?php 
                                                $statusText = 'Не подключено';
                                                if ($account['status'] === 'connected') {
                                                    $statusText = 'Подключено';
                                                } elseif ($account['status'] === 'error') {
                                                    $statusText = 'Ошибка';
                                                } elseif ($account['status'] === 'pending') {
                                                    $statusText = 'Ожидание';
                                                } elseif ($account['status'] === 'disconnected') {
                                                    $statusText = 'Отключено';
                                                }
                                                ?>
                                                <span class="status-dot"></span> <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($account['username']): ?>
                                        <p class="account-subtitle">@<?= htmlspecialchars($account['username']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="account-actions-compact">
                                <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                    <button type="button" class="btn-action-icon btn-action-success" onclick="setDefaultAccount('tiktok', <?= $account['id'] ?>)" title="Сделать по умолчанию">⭐</button>
                                <?php endif; ?>
                                <button type="button" class="btn-action-icon btn-action-danger" onclick="deleteAccount('tiktok', <?= $account['id'] ?>)" title="Удалить">🗑</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Instagram -->
    <div class="integration-card">
        <div class="integration-header">
            <h2><?= \App\Helpers\IconHelper::render('instagram', 24, 'icon-inline') ?> Instagram</h2>
            <a href="/integrations/instagram" class="btn">Добавить аккаунт</a>
        </div>
        
        <?php if (empty($instagramAccounts)): ?>
            <div class="integration-empty-state">
                <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('instagram', 48) ?></div>
                <p class="integration-status">Нет подключенных аккаунтов</p>
                <p class="integration-description">Подключите Instagram аккаунт для публикации Reels</p>
            </div>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($instagramAccounts as $account): ?>
                    <div class="account-card <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-card-body">
                            <div class="account-left-section">
                                <div class="account-icon-wrapper">
                                    <div class="account-platform-icon"><?= \App\Helpers\IconHelper::render('instagram', 40) ?></div>
                                    <?php if ($account['status'] === 'connected'): ?>
                                        <div class="account-status-indicator connected"></div>
                                    <?php else: ?>
                                        <div class="account-status-indicator disconnected"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="account-info-content">
                                    <div class="account-header-row">
                                        <h3 class="account-title"><?= htmlspecialchars($account['account_name'] ?? $account['username'] ?? 'Instagram аккаунт') ?></h3>
                                        <div class="account-badges-row">
                                            <?php if ($account['is_default']): ?>
                                                <span class="badge badge-default"><?= \App\Helpers\IconHelper::render('star', 16, 'icon-inline') ?> По умолчанию</span>
                                            <?php endif; ?>
                                            <span class="account-status-badge status-<?= $account['status'] === 'connected' ? 'connected' : ($account['status'] === 'error' ? 'error' : 'disconnected') ?>">
                                                <?php 
                                                $statusText = 'Не подключено';
                                                if ($account['status'] === 'connected') {
                                                    $statusText = 'Подключено';
                                                } elseif ($account['status'] === 'error') {
                                                    $statusText = 'Ошибка';
                                                } elseif ($account['status'] === 'pending') {
                                                    $statusText = 'Ожидание';
                                                } elseif ($account['status'] === 'disconnected') {
                                                    $statusText = 'Отключено';
                                                }
                                                ?>
                                                <span class="status-dot"></span> <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($account['username']): ?>
                                        <p class="account-subtitle">@<?= htmlspecialchars($account['username']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="account-actions-compact">
                                <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                    <button type="button" class="btn-action-icon btn-action-success" onclick="setDefaultAccount('instagram', <?= $account['id'] ?>)" title="Сделать по умолчанию">⭐</button>
                                <?php endif; ?>
                                <button type="button" class="btn-action-icon btn-action-danger" onclick="deleteAccount('instagram', <?= $account['id'] ?>)" title="Удалить">🗑</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pinterest -->
    <div class="integration-card">
        <div class="integration-header">
            <h2><?= \App\Helpers\IconHelper::render('pinterest', 24, 'icon-inline') ?> Pinterest</h2>
            <a href="/integrations/pinterest" class="btn">Добавить аккаунт</a>
        </div>
        
        <?php if (empty($pinterestAccounts)): ?>
            <div class="integration-empty-state">
                <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('pinterest', 48) ?></div>
                <p class="integration-status">Нет подключенных аккаунтов</p>
                <p class="integration-description">Подключите Pinterest аккаунт для публикации Idea Pins и Video Pins</p>
            </div>
        <?php else: ?>
            <div class="accounts-list">
                <?php foreach ($pinterestAccounts as $account): ?>
                    <div class="account-card <?= $account['status'] === 'connected' ? 'account-connected' : 'account-disconnected' ?>">
                        <div class="account-card-body">
                            <div class="account-left-section">
                                <div class="account-icon-wrapper">
                                    <div class="account-platform-icon"><?= \App\Helpers\IconHelper::render('pinterest', 40) ?></div>
                                    <?php if ($account['status'] === 'connected'): ?>
                                        <div class="account-status-indicator connected"></div>
                                    <?php else: ?>
                                        <div class="account-status-indicator disconnected"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="account-info-content">
                                    <div class="account-header-row">
                                        <h3 class="account-title"><?= htmlspecialchars($account['account_name'] ?? $account['username'] ?? 'Pinterest аккаунт') ?></h3>
                                        <div class="account-badges-row">
                                            <?php if ($account['is_default']): ?>
                                                <span class="badge badge-default"><?= \App\Helpers\IconHelper::render('star', 16, 'icon-inline') ?> По умолчанию</span>
                                            <?php endif; ?>
                                            <span class="account-status-badge status-<?= $account['status'] === 'connected' ? 'connected' : ($account['status'] === 'error' ? 'error' : 'disconnected') ?>">
                                                <?php 
                                                $statusText = 'Не подключено';
                                                if ($account['status'] === 'connected') {
                                                    $statusText = 'Подключено';
                                                } elseif ($account['status'] === 'error') {
                                                    $statusText = 'Ошибка';
                                                } elseif ($account['status'] === 'pending') {
                                                    $statusText = 'Ожидание';
                                                } elseif ($account['status'] === 'disconnected') {
                                                    $statusText = 'Отключено';
                                                }
                                                ?>
                                                <span class="status-dot"></span> <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($account['username']): ?>
                                        <p class="account-subtitle">@<?= htmlspecialchars($account['username']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="account-actions-compact">
                                <?php if ($account['status'] === 'connected' && !$account['is_default']): ?>
                                    <button type="button" class="btn-action-icon btn-action-success" onclick="setDefaultAccount('pinterest', <?= $account['id'] ?>)" title="Сделать по умолчанию">⭐</button>
                                <?php endif; ?>
                                <button type="button" class="btn-action-icon btn-action-danger" onclick="deleteAccount('pinterest', <?= $account['id'] ?>)" title="Удалить">🗑</button>
                            </div>
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
            showToast('Аккаунт установлен по умолчанию', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось установить аккаунт по умолчанию'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
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
            showToast('Аккаунт отключен', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось отключить аккаунт'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
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
            showToast('Аккаунт удален', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось удалить аккаунт'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
    });
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || document.createElement('div');
    toastContainer.id = 'toast-container';
    toastContainer.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 0.5rem;';
    if (!document.getElementById('toast-container')) {
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = 'padding: 1rem 1.5rem; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid ' + 
        (type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db') + '; min-width: 300px; animation: slideIn 0.3s ease;';
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Добавляем стили для анимации
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

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
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.integration-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.integration-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #3498db 0%, #2980b9 100%);
}

.integration-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.integration-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.integration-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.integration-empty-state {
    text-align: center;
    padding: 2rem 1rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    border: 2px dashed #dee2e6;
    margin: 1rem 0;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    opacity: 0.6;
}

.integration-status {
    color: #6c757d;
    font-weight: 600;
    font-size: 1rem;
    margin: 0.5rem 0;
}

.integration-description {
    color: #868e96;
    font-size: 0.9rem;
    margin: 0.5rem 0 0 0;
    line-height: 1.5;
}

.accounts-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.account-card {
    background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
    border-radius: 16px;
    border: 1px solid #e1e8ed;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.account-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #95a5a6 0%, #7f8c8d 100%);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
}

.account-card.account-connected::before {
    background: linear-gradient(180deg, #27ae60 0%, #229954 100%);
    box-shadow: 2px 0 12px rgba(39, 174, 96, 0.3);
}

.account-card.account-disconnected::before {
    background: linear-gradient(180deg, #95a5a6 0%, #7f8c8d 100%);
    opacity: 0.5;
}

.account-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(52, 152, 219, 0.05) 0%, transparent 70%);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.account-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    border-color: #3498db;
}

.account-card:hover::after {
    opacity: 1;
}

.account-card-body {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem;
    gap: 1.5rem;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}

.account-left-section {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
    flex: 1;
    min-width: 0;
    overflow: visible;
}

.account-icon-wrapper {
    position: relative;
    flex-shrink: 0;
}

.account-platform-icon {
    font-size: 2.75rem;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 16px;
    border: 2px solid #e1e8ed;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.account-platform-icon::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(52, 152, 219, 0.1) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.account-card:hover .account-platform-icon {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    border-color: #3498db;
}

.account-card:hover .account-platform-icon::before {
    opacity: 1;
}

.account-status-indicator {
    position: absolute;
    bottom: -3px;
    right: -3px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 3px 8px rgba(0,0,0,0.25);
    transition: all 0.3s ease;
    z-index: 2;
}

.account-card:hover .account-status-indicator {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.account-status-indicator.connected {
    background: #27ae60;
}

.account-status-indicator.disconnected {
    background: #95a5a6;
}

.account-info-content {
    flex: 1;
    min-width: 0;
    overflow: visible;
}

.account-header-row {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
    min-width: 0;
    width: 100%;
    flex-wrap: wrap;
}

.account-badges-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
    flex-shrink: 0;
    justify-content: flex-end;
}

.account-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1.3;
    word-break: break-word;
    letter-spacing: -0.01em;
    flex: 1;
    min-width: 0;
}

.account-subtitle {
    margin: 0;
    font-size: 0.875rem;
    color: #64748b;
    line-height: 1.5;
    font-weight: 400;
}

.account-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    border-radius: 24px;
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    flex-shrink: 0;
    min-width: fit-content;
}

.account-card:hover .account-status-badge {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
}

.account-status-badge.status-connected {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border: 1px solid #c3e6cb;
}

.account-status-badge.status-error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.account-status-badge.status-disconnected {
    background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
    color: #383d41;
    border: 1px solid #d6d8db;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.account-status-badge.status-connected .status-dot {
    background: #27ae60;
}

.account-status-badge.status-error .status-dot {
    background: #e74c3c;
}

.account-status-badge.status-disconnected .status-dot {
    background: #95a5a6;
}

.badge-default {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border: 1px solid #fde68a;
    padding: 0.375rem 0.75rem;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 2px 4px rgba(146, 64, 14, 0.15);
    transition: all 0.2s ease;
}

.account-card:hover .badge-default {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(146, 64, 14, 0.2);
}

.account-actions-compact {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    align-items: stretch;
    flex-shrink: 0;
    padding-left: 1rem;
    border-left: 1px solid #e1e8ed;
}

.btn-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 3px 8px rgba(0,0,0,0.12);
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.btn-action-icon::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
}

.btn-action-icon:hover::before {
    width: 100px;
    height: 100px;
}

.btn-action-icon:hover {
    transform: translateY(-3px) scale(1.08);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.btn-action-icon:active {
    transform: translateY(-1px) scale(1.02);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.btn-action-success {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
}

.btn-action-success:hover {
    background: linear-gradient(135deg, #229954 0%, #1e8449 100%);
}

.btn-action-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
}

.btn-action-warning:hover {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
}

.btn-action-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.btn-action-danger:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
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
    .account-card-body {
        flex-direction: column;
        align-items: stretch;
    }
    
    .account-left-section {
        width: 100%;
    }
    
    .account-actions-compact {
        width: 100%;
        flex-direction: row;
        padding-left: 0;
        padding-top: 1rem;
        border-left: none;
        border-top: 1px solid #e1e8ed;
        justify-content: flex-start;
    }
    
    .integration-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .integration-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .account-header-row {
        flex-direction: column;
        gap: 0.75rem;
        align-items: flex-start;
    }
    
    .account-badges-row {
        justify-content: flex-start;
        width: 100%;
    }
    
    .account-title {
        width: 100%;
    }
    
    .integrations-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
