<?php
$title = 'Группы контента';
ob_start();
?>

<h1>Группы контента</h1>

<a href="/content-groups/create" class="btn btn-primary">Создать группу</a>

<?php if (empty($groups)): ?>
    <p style="margin-top: 2rem;">Нет созданных групп</p>
<?php else: ?>
    <?php 
    // Получаем все шаблоны для отображения
    $templateService = new \App\Modules\ContentGroups\Services\TemplateService();
    $allTemplates = $templateService->getUserTemplates($_SESSION['user_id'], true);
    $templatesMap = [];
    foreach ($allTemplates as $template) {
        $templatesMap[$template['id']] = $template;
    }
    $groupIds = array_map(static fn($group) => (int)($group['id'] ?? 0), $groups);
    $scheduleRepo = new \App\Repositories\ScheduleRepository();
    $latestSchedules = $scheduleRepo->findLatestByGroupIds($groupIds);
    $youtubeAccount = null;
    $telegramAccount = null;
    $tiktokAccount = null;
    $instagramAccount = null;
    $pinterestAccount = null;
    try {
        $youtubeAccount = (new \App\Repositories\YoutubeIntegrationRepository())->findDefaultByUserId($_SESSION['user_id']);
    } catch (\Throwable $e) {
        $youtubeAccount = null;
    }
    try {
        $telegramAccount = (new \App\Repositories\TelegramIntegrationRepository())->findDefaultByUserId($_SESSION['user_id']);
    } catch (\Throwable $e) {
        $telegramAccount = null;
    }
    try {
        $tiktokAccount = (new \App\Repositories\TiktokIntegrationRepository())->findDefaultByUserId($_SESSION['user_id']);
    } catch (\Throwable $e) {
        $tiktokAccount = null;
    }
    try {
        $instagramAccount = (new \App\Repositories\InstagramIntegrationRepository())->findDefaultByUserId($_SESSION['user_id']);
    } catch (\Throwable $e) {
        $instagramAccount = null;
    }
    try {
        $pinterestAccount = (new \App\Repositories\PinterestIntegrationRepository())->findDefaultByUserId($_SESSION['user_id']);
    } catch (\Throwable $e) {
        $pinterestAccount = null;
    }
    ?>
    <div class="groups-grid">
        <?php foreach ($groups as $group): ?>
            <?php
            $groupId = (int)($group['id'] ?? 0);
            $latestSchedule = $latestSchedules[$groupId] ?? null;
            $platform = $latestSchedule['platform'] ?? null;
            $formatAccountName = static function (string $platformName, ?array $account): string {
                if (!$account) {
                    return $platformName . ': не подключен';
                }
                $name = $account['account_name']
                    ?? $account['channel_name']
                    ?? $account['channel_username']
                    ?? $account['username']
                    ?? null;
                if ($name) {
                    if ($platformName === 'Telegram' && $account['channel_username']) {
                        return $platformName . ': @' . $account['channel_username'];
                    }
                    return $platformName . ': ' . $name;
                }
                return $platformName . ': подключен';
            };
            $channelLabel = 'Канал: не назначен';
            if ($platform === 'youtube') {
                $channelLabel = $formatAccountName('YouTube', $youtubeAccount);
            } elseif ($platform === 'telegram') {
                $channelLabel = $formatAccountName('Telegram', $telegramAccount);
            } elseif ($platform === 'tiktok') {
                $channelLabel = $formatAccountName('TikTok', $tiktokAccount);
            } elseif ($platform === 'instagram') {
                $channelLabel = $formatAccountName('Instagram', $instagramAccount);
            } elseif ($platform === 'pinterest') {
                $channelLabel = $formatAccountName('Pinterest', $pinterestAccount);
            } elseif ($platform === 'both') {
                $channelLabel = $formatAccountName('YouTube', $youtubeAccount) . ' • ' . $formatAccountName('Telegram', $telegramAccount);
            }
            ?>
            <div class="group-card <?= $group['status'] === 'active' ? 'group-card-active' : 'group-card-paused' ?>">
                <div class="group-card-header">
                    <h3 class="group-title"><?= htmlspecialchars($group['name']) ?></h3>
                    <span class="group-status-badge badge-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'paused' ? 'warning' : 'secondary') ?>">
                        <?= $group['status'] === 'active' ? '● Активна' : \App\Helpers\IconHelper::render('pause', 16, 'icon-inline') . ' На паузе' ?>
                    </span>
                </div>
                
                <?php if ($group['description']): ?>
                    <p class="group-description"><?= htmlspecialchars($group['description']) ?></p>
                <?php endif; ?>
                
                <div class="group-info-box">
                    <div class="group-info-item">
                        <span class="info-label">Шаблон:</span>
                        <?php if ($group['template_id'] && isset($templatesMap[$group['template_id']])): ?>
                            <span class="info-value info-value-success"><?= \App\Helpers\IconHelper::render('check', 16, 'icon-inline') ?> <?= htmlspecialchars($templatesMap[$group['template_id']]['name']) ?></span>
                        <?php else: ?>
                            <span class="info-value info-value-muted">Без шаблона</span>
                        <?php endif; ?>
                    </div>
                    <div class="group-info-item">
                        <span class="info-label">Канал:</span>
                        <span class="info-value info-value-muted"><?= htmlspecialchars($channelLabel) ?></span>
                    </div>
                </div>
                
                <div class="group-stats-grid">
                    <?php if (isset($group['stats'])): ?>
                        <div class="stat-item">
                            <div class="stat-value"><?= $group['stats']['total_files'] ?? 0 ?></div>
                            <div class="stat-label">Всего</div>
                        </div>
                        <div class="stat-item stat-success">
                            <div class="stat-value"><?= $group['stats']['published_count'] ?? 0 ?></div>
                            <div class="stat-label">Опубликовано</div>
                        </div>
                        <div class="stat-item stat-warning">
                            <div class="stat-value"><?= $group['stats']['queued_count'] ?? 0 ?></div>
                            <div class="stat-label">В очереди</div>
                        </div>
                        <div class="stat-item stat-danger">
                            <div class="stat-value"><?= $group['stats']['error_count'] ?? 0 ?></div>
                            <div class="stat-label">Ошибки</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="group-actions">
                    <a href="/content-groups/<?= $group['id'] ?>" class="btn-action-icon btn-action-primary" title="Открыть группу"><?= \App\Helpers\IconHelper::render('view', 20) ?></a>
                    <a href="/content-groups/<?= $group['id'] ?>/edit" class="btn-action-icon btn-action-info" title="Редактировать"><?= \App\Helpers\IconHelper::render('edit', 20) ?></a>
                    <button type="button" class="btn-action-icon btn-action-success" 
                            onclick="publishAllUnpublished(<?= $group['id'] ?>)" 
                            title="Опубликовать все неопубликованные видео">
                        <?= \App\Helpers\IconHelper::render('publish', 20) ?>
                    </button>
                    <button type="button" class="btn-action-icon btn-action-warning" 
                            onclick="clearAllPublication(<?= $group['id'] ?>)" 
                            title="Сбросить опубликованность всех элементов">
                        <?= \App\Helpers\IconHelper::render('refresh', 20) ?>
                    </button>
                    <button type="button" class="btn-action-icon btn-action-<?= $group['status'] === 'active' ? 'warning' : 'success' ?>" 
                            onclick="toggleGroupStatus(<?= $group['id'] ?>, '<?= $group['status'] ?>')" 
                            title="<?= $group['status'] === 'active' ? 'Приостановить' : 'Включить' ?>">
                        <?= $group['status'] === 'active' ? \App\Helpers\IconHelper::render('pause', 20) : \App\Helpers\IconHelper::render('play', 20) ?>
                    </button>
                    <button type="button" class="btn-action-icon btn-action-secondary" onclick="duplicateGroup(<?= $group['id'] ?>)" title="Копировать"><?= \App\Helpers\IconHelper::render('copy', 20) ?></button>
                    <button type="button" class="btn-action-icon btn-action-secondary" onclick="shuffleGroup(<?= $group['id'] ?>)" title="Перемешать"><?= \App\Helpers\IconHelper::render('shuffle', 20) ?></button>
                    <button type="button" class="btn-action-icon btn-action-danger" onclick="deleteGroup(<?= $group['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 20) ?></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleGroupStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'выключить' : 'включить';
    if (!confirm('Вы уверены, что хотите ' + action + ' эту группу?')) {
        return;
    }
    
    fetch('/content-groups/' + id + '/toggle-status', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Статус группы изменен');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось изменить статус группы'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function duplicateGroup(id) {
    if (!confirm('Создать копию этой группы? Все видео из группы будут скопированы.')) {
        return;
    }
    
    fetch('/content-groups/' + id + '/duplicate', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Группа успешно скопирована!');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось скопировать группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function deleteGroup(id) {
    if (!confirm('Вы уверены, что хотите удалить эту группу? Это действие нельзя отменить.')) {
        return;
    }
    
    if (!confirm('ВНИМАНИЕ: Все видео останутся, но будут удалены из группы. Продолжить?')) {
        return;
    }
    
    fetch('/content-groups/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Группа удалена');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function shuffleGroup(id) {
    if (!confirm('Перемешать видео в группе?')) {
        return;
    }
    
    fetch('/content-groups/' + id + '/shuffle', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Группа перемешана успешно');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось перемешать группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function publishAllUnpublished(id) {
    if (!confirm('Опубликовать все неопубликованные видео в группе сейчас?')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalContent = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
    }
    
    fetch('/content-groups/' + id + '/publish-all-unpublished', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
        if (data.success) {
            const message = data.data ? 
                `Опубликовано: ${data.data.success || 0}, Ошибок: ${data.data.errors || 0}` : 
                (data.message || 'Видео опубликованы');
            alert(message);
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось опубликовать видео'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
        alert('Произошла ошибка при публикации');
    });
}

function clearAllPublication(id) {
    if (!confirm('Сбросить статус опубликованности для всех элементов группы?')) {
        return;
    }
    
    if (!confirm('ВНИМАНИЕ: Это действие сбросит статус опубликованности для всех файлов в группе. Продолжить?')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalContent = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
    }
    
    fetch('/content-groups/' + id + '/clear-all-publication', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
        if (data.success) {
            alert(data.message || 'Статус опубликованности сброшен');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось сбросить статус'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
        alert('Произошла ошибка');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
