<?php
$title = 'Группа: ' . htmlspecialchars($group['name']);
ob_start();
?>

<h1><?= htmlspecialchars($group['name']) ?></h1>

<?php if ($group['description']): ?>
    <p><?= htmlspecialchars($group['description']) ?></p>
<?php endif; ?>

<div class="info-card group-stats">
    <h3>Статистика группы</h3>
    <?php if (isset($group['stats'])): ?>
        <div class="group-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div class="stat-item">
                <div class="stat-label">Всего файлов:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #3498db;"><?= $group['stats']['total_files'] ?? 0 ?></div>
            </div>
            <div class="stat-item stat-success">
                <div class="stat-label">Опубликовано:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #27ae60;"><?= $group['stats']['published_count'] ?? 0 ?></div>
            </div>
            <div class="stat-item stat-warning">
                <div class="stat-label">В очереди:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #f39c12;"><?= $group['stats']['queued_count'] ?? 0 ?></div>
            </div>
            <div class="stat-item stat-danger">
                <div class="stat-label">Ошибки:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #e74c3c;"><?= $group['stats']['error_count'] ?? 0 ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Новых:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #95a5a6;"><?= $group['stats']['new_count'] ?? 0 ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="info-card group-info">
    <h3>Информация о группе</h3>
    <div class="group-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <div class="stat-item">
            <div class="stat-label">Текущий шаблон:</div>
            <?php if ($group['template_id']): ?>
                <?php 
                $currentTemplate = null;
                foreach ($templates as $template) {
                    if ($template['id'] == $group['template_id']) {
                        $currentTemplate = $template;
                        break;
                    }
                }
                ?>
                <?php if ($currentTemplate): ?>
                    <div class="stat-value" style="font-size: 1.1rem; color: #27ae60; word-break: break-word;"><?= htmlspecialchars($currentTemplate['name']) ?></div>
                <?php else: ?>
                    <div class="stat-value" style="font-size: 1rem; color: #e74c3c;">Шаблон не найден</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="stat-value" style="font-size: 1rem; color: #95a5a6;">Без шаблона</div>
            <?php endif; ?>
        </div>
        <div class="stat-item <?= $group['status'] === 'active' ? 'stat-success' : ($group['status'] === 'paused' ? 'stat-warning' : '') ?>">
            <div class="stat-label">Статус:</div>
            <div class="stat-value" style="font-size: 1.1rem;">
                <?= ucfirst($group['status']) ?>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Каналы публикации:</div>
            <div class="stat-value" style="font-size: 1rem;">
                <?php if (!empty($integrationAccounts)): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                        <?php 
                        $platformNames = [
                            'youtube' => 'YouTube',
                            'telegram' => 'Telegram',
                            'tiktok' => 'TikTok',
                            'instagram' => 'Instagram',
                            'pinterest' => 'Pinterest'
                        ];
                        foreach ($integrationAccounts as $integration): 
                            $platform = $integration['platform'];
                            $account = $integration['account'];
                            $platformName = $platformNames[$platform] ?? ucfirst($platform);
                            
                            // Формируем название канала
                            $channelName = $account['account_name']
                                ?? $account['channel_name']
                                ?? $account['channel_username']
                                ?? $account['username']
                                ?? 'Канал ' . $account['id'];
                            
                            if ($platform === 'telegram' && $account['channel_username']) {
                                $channelName = '@' . $account['channel_username'];
                            }
                        ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <?= \App\Helpers\IconHelper::render($platform, 16, 'icon-inline') ?>
                                <span style="font-weight: 500;"><?= htmlspecialchars($platformName) ?>:</span>
                                <span><?= htmlspecialchars($channelName) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span style="color: #95a5a6;">Не назначены</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($scheduleInfo)): ?>
        <div class="stat-item" style="grid-column: 1 / -1;">
            <div class="stat-label">Расписание публикации:</div>
            <div class="stat-value" style="font-size: 1rem; margin-top: 0.5rem;">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php if (!empty($scheduleInfo['name'])): ?>
                        <div style="font-weight: 600; color: #3498db;">
                            <?= htmlspecialchars($scheduleInfo['name']) ?>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                        <?php
                        $scheduleTypeNames = [
                            'fixed' => 'Фиксированное',
                            'interval' => 'Интервальное',
                            'batch' => 'Пакетное',
                            'random' => 'Случайное',
                            'wave' => 'Волновое'
                        ];
                        $scheduleTypeName = $scheduleTypeNames[$scheduleInfo['schedule_type']] ?? ucfirst($scheduleInfo['schedule_type']);
                        ?>
                        <span class="badge badge-info"><?= htmlspecialchars($scheduleTypeName) ?></span>
                        <?php if ($scheduleInfo['schedule_type'] === 'interval' && !empty($scheduleInfo['interval_minutes'])): ?>
                            <span style="color: #555;">Каждые <?= (int)$scheduleInfo['interval_minutes'] ?> минут</span>
                        <?php elseif ($scheduleInfo['schedule_type'] === 'batch' && !empty($scheduleInfo['batch_count']) && !empty($scheduleInfo['batch_window_hours'])): ?>
                            <span style="color: #555;"><?= (int)$scheduleInfo['batch_count'] ?> видео за <?= (int)$scheduleInfo['batch_window_hours'] ?> часов</span>
                        <?php elseif (!empty($scheduleInfo['publish_at'])): ?>
                            <span style="color: #555;">Начало: <?= date('d.m.Y H:i', strtotime($scheduleInfo['publish_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <a href="/content-groups/schedules/<?= (int)$scheduleInfo['id'] ?>" class="btn btn-xs btn-secondary" style="font-size: 0.85rem;">Просмотр расписания</a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="stat-item" style="grid-column: 1 / -1;">
            <div class="stat-label">Расписание публикации:</div>
            <div class="stat-value" style="font-size: 1rem; color: #95a5a6;">
                Не назначено
                <a href="/content-groups/schedules/create?group_id=<?= $group['id'] ?>" class="btn btn-xs btn-primary" style="margin-left: 0.5rem; font-size: 0.85rem;">Создать</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($publicationPlan)): ?>
<div class="info-card publication-plan" style="margin-top: 1.5rem;">
    <h3>План отправки по расписанию</h3>
    <div style="margin-top: 1rem;">
        <table class="data-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Видео</th>
                    <th style="width: 200px;">Дата и время</th>
                    <th style="width: 120px;">Платформа</th>
                    <th style="width: 100px;">Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($publicationPlan as $index => $item): 
                    $file = $item['file'];
                    $publishInfo = $item['publish_info'];
                    $publishTimestamp = $item['publish_timestamp'];
                    $now = time();
                ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <a href="/videos/<?= $file['video_id'] ?>">
                                <?= htmlspecialchars($file['title'] ?? $file['file_name'] ?? 'Без названия') ?>
                            </a>
                        </td>
                        <td>
                            <div style="font-weight: 500; color: #3498db;">
                                <?= date('d.m.Y H:i', $publishTimestamp) ?>
                            </div>
                            <?php if ($publishTimestamp > $now): ?>
                                <div style="font-size: 0.85rem; color: #95a5a6;">
                                    <?php
                                    $diff = $publishTimestamp - $now;
                                    $hours = floor($diff / 3600);
                                    $minutes = floor(($diff % 3600) / 60);
                                    if ($hours > 0) {
                                        echo "через {$hours} ч. {$minutes} мин.";
                                    } else {
                                        echo "через {$minutes} мин.";
                                    }
                                    ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.85rem; color: #e74c3c;">Просрочено</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="platform-badge platform-<?= $publishInfo['platform'] ?>">
                                <?php
                                $platformIcons = [
                                    'youtube' => \App\Helpers\IconHelper::render('youtube', 14, 'icon-inline'),
                                    'telegram' => \App\Helpers\IconHelper::render('telegram', 14, 'icon-inline'),
                                    'tiktok' => \App\Helpers\IconHelper::render('tiktok', 14, 'icon-inline'),
                                    'instagram' => \App\Helpers\IconHelper::render('instagram', 14, 'icon-inline'),
                                    'pinterest' => \App\Helpers\IconHelper::render('pinterest', 14, 'icon-inline')
                                ];
                                echo $platformIcons[$publishInfo['platform']] ?? '';
                                ?>
                                <?= ucfirst($publishInfo['platform']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $file['status'] ?>">
                                <?php
                                $statusLabels = [
                                    'new' => 'Новый',
                                    'queued' => 'В очереди',
                                    'published' => 'Опубликовано',
                                    'error' => 'Ошибка',
                                    'paused' => 'На паузе',
                                    'skipped' => 'Пропущено'
                                ];
                                echo $statusLabels[$file['status']] ?? ucfirst($file['status']);
                                ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($nextVideoPreview): ?>
<div class="info-card next-video-preview" style="margin-top: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
    <h3 style="color: white; margin-bottom: 1rem;">
        <?= \App\Helpers\IconHelper::render('video', 20, 'icon-inline') ?> 
        Следующий ролик в очереди
    </h3>
    <div style="background: rgba(255, 255, 255, 0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
        <div style="margin-bottom: 0.5rem;">
            <strong style="color: #fff;">Видео:</strong> 
            <span><?= htmlspecialchars($nextVideoPreview['file']['title'] ?? $nextVideoPreview['video']['title'] ?? $nextVideoPreview['video']['file_name'] ?? 'Без названия') ?></span>
        </div>
        <?php if ($nextVideoPreview['template_name']): ?>
            <div style="margin-bottom: 0.5rem;">
                <strong style="color: #fff;">Шаблон:</strong> 
                <span><?= htmlspecialchars($nextVideoPreview['template_name']) ?></span>
            </div>
        <?php endif; ?>
        <div style="margin-bottom: 0.5rem;">
            <strong style="color: #fff;">Платформа:</strong> 
            <span class="platform-badge platform-<?= $nextVideoPreview['platform'] ?>" style="background: rgba(255, 255, 255, 0.2); padding: 0.25rem 0.5rem; border-radius: 4px;">
                <?= ucfirst($nextVideoPreview['platform']) ?>
            </span>
        </div>
    </div>
    
    <div style="background: rgba(255, 255, 255, 0.95); color: #333; padding: 1rem; border-radius: 8px;">
        <h4 style="margin-top: 0; margin-bottom: 0.75rem; color: #667eea;">Как будет оформлен:</h4>
        
        <div style="margin-bottom: 1rem;">
            <div style="font-weight: 600; color: #555; margin-bottom: 0.25rem; font-size: 0.9rem;">Название:</div>
            <div style="padding: 0.5rem; background: #f8f9fa; border-left: 3px solid #667eea; border-radius: 4px; word-break: break-word;">
                <?= htmlspecialchars($nextVideoPreview['preview']['title'] ?? 'Без названия') ?>
            </div>
        </div>
        
        <?php if (!empty($nextVideoPreview['preview']['description'])): ?>
        <div style="margin-bottom: 1rem;">
            <div style="font-weight: 600; color: #555; margin-bottom: 0.25rem; font-size: 0.9rem;">Описание:</div>
            <div style="padding: 0.5rem; background: #f8f9fa; border-left: 3px solid #667eea; border-radius: 4px; word-break: break-word; white-space: pre-wrap; max-height: 150px; overflow-y: auto;">
                <?= htmlspecialchars($nextVideoPreview['preview']['description']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($nextVideoPreview['preview']['tags'])): ?>
        <div>
            <div style="font-weight: 600; color: #555; margin-bottom: 0.25rem; font-size: 0.9rem;">Теги:</div>
            <div style="padding: 0.5rem; background: #f8f9fa; border-left: 3px solid #667eea; border-radius: 4px; word-break: break-word;">
                <?= htmlspecialchars($nextVideoPreview['preview']['tags']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="form-actions group-actions">
    <a href="/content-groups" class="btn btn-secondary">Назад к списку</a>
    <a href="/content-groups/<?= $group['id'] ?>/edit" class="btn btn-primary">Редактировать группу</a>
    <button type="button" class="btn btn-info" onclick="shuffleGroup(<?= $group['id'] ?>)">Перемешать видео</button>
    <a href="/content-groups/schedules/create?group_id=<?= $group['id'] ?>" class="btn btn-success">Создать расписание</a>
</div>

<div class="group-files" style="margin-top: 2rem;">
    <h2>Видео в группе</h2>
    
    <?php if (empty($files)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('video', 64) ?></div>
            <h3>Нет видео в группе</h3>
            <p>Добавьте видео в эту группу для автоматической публикации</p>
            <a href="/videos" class="btn btn-primary">Добавить видео</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Статус</th>
                    <th>Порядок</th>
                    <th>Опубликовано</th>
                    <?php if ($group['status'] === 'active'): ?>
                        <th>Следующая публикация</th>
                        <th>Оформление</th>
                    <?php endif; ?>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td>
                            <a href="/videos/<?= $file['video_id'] ?>"><?= htmlspecialchars($file['title'] ?? $file['file_name'] ?? 'Без названия') ?></a>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $file['status'] ?>">
                                <?php
                                $statusLabels = [
                                    'new' => 'Новый',
                                    'queued' => 'В очереди',
                                    'published' => 'Опубликовано',
                                    'error' => 'Ошибка',
                                    'paused' => 'На паузе',
                                    'skipped' => 'Не публиковать'
                                ];
                                echo $statusLabels[$file['status']] ?? ucfirst($file['status']);
                                ?>
                            </span>
                        </td>
                        <td><?= $file['order_index'] ?></td>
                        <td>
                            <?php if (isset($filePublications[$file['video_id']])): 
                                $pub = $filePublications[$file['video_id']];
                            ?>
                                <div style="font-size: 0.9rem;">
                                    <div style="color: #27ae60; font-weight: 500; margin-bottom: 0.25rem;">
                                        <?= date('d.m.Y H:i', strtotime($pub['published_at'] ?? $file['published_at'])) ?>
                                    </div>
                                    <div style="margin-bottom: 0.25rem;">
                                        <span class="platform-badge platform-<?= $pub['platform'] ?? 'youtube' ?>" style="font-size: 0.75rem;">
                                            <?php
                                            $platformIcons = [
                                                'youtube' => \App\Helpers\IconHelper::render('youtube', 12, 'icon-inline'),
                                                'telegram' => \App\Helpers\IconHelper::render('telegram', 12, 'icon-inline'),
                                                'tiktok' => \App\Helpers\IconHelper::render('tiktok', 12, 'icon-inline'),
                                                'instagram' => \App\Helpers\IconHelper::render('instagram', 12, 'icon-inline'),
                                                'pinterest' => \App\Helpers\IconHelper::render('pinterest', 12, 'icon-inline')
                                            ];
                                            echo $platformIcons[$pub['platform'] ?? 'youtube'] ?? '';
                                            ?>
                                            <?= ucfirst($pub['platform'] ?? 'youtube') ?>
                                        </span>
                                    </div>
                                    <?php 
                                    $pubUrl = $pub['platform_url'] ?? '';
                                    if (!$pubUrl && $pub['platform_id']) {
                                        switch ($pub['platform']) {
                                            case 'youtube':
                                                $pubUrl = 'https://youtube.com/shorts/' . $pub['platform_id'];
                                                break;
                                            case 'telegram':
                                                $pubUrl = 'https://t.me/' . $pub['platform_id'];
                                                break;
                                            case 'tiktok':
                                                $pubUrl = 'https://www.tiktok.com/@' . $pub['platform_id'];
                                                break;
                                            case 'instagram':
                                                $pubUrl = 'https://www.instagram.com/p/' . $pub['platform_id'];
                                                break;
                                            case 'pinterest':
                                                $pubUrl = 'https://www.pinterest.com/pin/' . $pub['platform_id'];
                                                break;
                                        }
                                    }
                                    if ($pubUrl):
                                    ?>
                                        <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" style="font-size: 0.75rem; color: #3498db; text-decoration: none;">
                                            <?= \App\Helpers\IconHelper::render('publish', 12, 'icon-inline') ?> Перейти
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($file['published_at']): ?>
                                <div style="font-size: 0.9rem; color: #27ae60;">
                                    <?= date('d.m.Y H:i', strtotime($file['published_at'])) ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #95a5a6;">-</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($group['status'] === 'active'): ?>
                            <td>
                                <?php if (isset($nextPublishInfo[$file['id']])): 
                                    $nextInfo = $nextPublishInfo[$file['id']];
                                    $publishAt = strtotime($nextInfo['date']);
                                    $now = time();
                                ?>
                                    <div style="font-size: 0.9rem;">
                                        <div style="color: #3498db; font-weight: 500; margin-bottom: 0.25rem;">
                                            <?= date('d.m.Y H:i', $publishAt) ?>
                                        </div>
                                        <div style="margin-bottom: 0.25rem;">
                                            <span class="platform-badge platform-<?= $nextInfo['platform'] ?>" style="font-size: 0.75rem;">
                                                <?php
                                                $platformIcons = [
                                                    'youtube' => \App\Helpers\IconHelper::render('youtube', 12, 'icon-inline'),
                                                    'telegram' => \App\Helpers\IconHelper::render('telegram', 12, 'icon-inline'),
                                                    'tiktok' => \App\Helpers\IconHelper::render('tiktok', 12, 'icon-inline'),
                                                    'instagram' => \App\Helpers\IconHelper::render('instagram', 12, 'icon-inline'),
                                                    'pinterest' => \App\Helpers\IconHelper::render('pinterest', 12, 'icon-inline')
                                                ];
                                                echo $platformIcons[$nextInfo['platform']] ?? '';
                                                ?>
                                                <?= ucfirst($nextInfo['platform']) ?>
                                            </span>
                                        </div>
                                        <?php if ($publishAt > $now): ?>
                                            <div class="countdown-timer" 
                                                 data-publish-at="<?= date('Y-m-d H:i:s', $publishAt) ?>" 
                                                 style="font-size: 0.75rem; color: #3498db; font-weight: 500;">
                                                <span class="countdown-text">Осталось: </span>
                                                <span class="countdown-value">-</span>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 0.75rem; color: #e74c3c;">
                                                Время прошло
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($filePreviews[$file['id']])): 
                                    $preview = $filePreviews[$file['id']];
                                ?>
                                    <div style="max-width: 300px;">
                                        <div style="font-size: 0.85rem; margin-bottom: 0.25rem;">
                                            <strong style="color: #555;">Название:</strong>
                                            <div style="color: #2c3e50; word-break: break-word;">
                                                <?= htmlspecialchars($preview['title'] ?? 'Без названия') ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($preview['description'])): ?>
                                            <div style="font-size: 0.85rem; margin-bottom: 0.25rem;">
                                                <strong style="color: #555;">Описание:</strong>
                                                <div style="color: #666; word-break: break-word; max-height: 60px; overflow: hidden; text-overflow: ellipsis; white-space: pre-wrap;">
                                                    <?= htmlspecialchars(mb_substr($preview['description'], 0, 100)) ?><?= mb_strlen($preview['description']) > 100 ? '...' : '' ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($preview['tags'])): ?>
                                            <div style="font-size: 0.85rem;">
                                                <strong style="color: #555;">Теги:</strong>
                                                <div style="color: #666; word-break: break-word;">
                                                    <?= htmlspecialchars(mb_substr($preview['tags'], 0, 80)) ?><?= mb_strlen($preview['tags']) > 80 ? '...' : '' ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #95a5a6; font-size: 0.85rem;">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <div class="action-buttons">
                                <a href="/videos/<?= $file['video_id'] ?>" class="btn btn-xs btn-primary" title="Просмотр"><?= \App\Helpers\IconHelper::render('view', 14, 'icon-inline') ?></a>
                                <?php if (in_array($file['status'], ['new', 'queued', 'paused', 'error'], true)): ?>
                                    <a href="/content-groups/<?= $group['id'] ?>/files/<?= $file['id'] ?>/publish-now"
                                       class="btn btn-xs btn-success"
                                       title="Опубликовать сейчас"
                                       aria-label="Опубликовать сейчас">
                                        <?= \App\Helpers\IconHelper::render('publish', 14, 'icon-inline') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (isset($filePublications[$file['video_id']])): 
                                    $pub = $filePublications[$file['video_id']];
                                    $pubUrl = $pub['platform_url'] ?? '';
                                    if (!$pubUrl && $pub['platform_id']) {
                                        switch ($pub['platform']) {
                                            case 'youtube':
                                                $pubUrl = 'https://youtube.com/shorts/' . $pub['platform_id'];
                                                break;
                                            case 'telegram':
                                                $pubUrl = 'https://t.me/' . $pub['platform_id'];
                                                break;
                                            case 'tiktok':
                                                $pubUrl = 'https://www.tiktok.com/@' . $pub['platform_id'];
                                                break;
                                            case 'instagram':
                                                $pubUrl = 'https://www.instagram.com/p/' . $pub['platform_id'];
                                                break;
                                            case 'pinterest':
                                                $pubUrl = 'https://www.pinterest.com/pin/' . $pub['platform_id'];
                                                break;
                                        }
                                    }
                                    if ($pubUrl):
                                ?>
                                    <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn btn-xs btn-success" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>"><?= \App\Helpers\IconHelper::render('publish', 14, 'icon-inline') ?></a>
                                <?php endif; endif; ?>
                                <?php
                                $hasPublication = isset($filePublications[$file['video_id']]) || !empty($file['published_at']) || $file['status'] === 'published';
                                if ($hasPublication):
                                ?>
                                    <button type="button"
                                            class="btn btn-xs btn-warning"
                                            onclick="clearFilePublication(<?= $group['id'] ?>, <?= $file['id'] ?>)"
                                            title="Сбросить публикацию и статус для повторной публикации"
                                            aria-label="Сбросить публикацию">
                                        <?= \App\Helpers\IconHelper::render('copy', 14, 'icon-inline') ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($file['status'] === 'new' || $file['status'] === 'queued'): ?>
                                    <button type="button"
                                            class="btn btn-xs btn-danger"
                                            onclick="markDoNotPublish(<?= $group['id'] ?>, <?= $file['id'] ?>)"
                                            title="Не публиковать этот файл"
                                            aria-label="Не публиковать">
                                        <?= \App\Helpers\IconHelper::render('error', 14, 'icon-inline') ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-xs <?= ($file['status'] === 'new' || $file['status'] === 'queued') ? 'btn-warning' : 'btn-success' ?>" 
                                        onclick="toggleFileStatus(<?= $group['id'] ?>, <?= $file['id'] ?>, '<?= $file['status'] ?>')"
                                        title="<?= ($file['status'] === 'new' || $file['status'] === 'queued') ? 'Приостановить' : 'Возобновить' ?>">
                                    <?= ($file['status'] === 'new' || $file['status'] === 'queued') ? \App\Helpers\IconHelper::render('pause', 14, 'icon-inline') : \App\Helpers\IconHelper::render('play', 14, 'icon-inline') ?>
                                </button>
                                <button type="button" class="btn btn-xs btn-danger" onclick="removeFromGroup(<?= $group['id'] ?>, <?= $file['video_id'] ?>)" title="Удалить из группы"><?= \App\Helpers\IconHelper::render('delete', 14, 'icon-inline') ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
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

function removeFromGroup(groupId, videoId) {
    if (!confirm('Удалить видео из группы?')) {
        return;
    }
    
    fetch('/content-groups/' + groupId + '/videos/' + videoId, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Видео удалено из группы', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось удалить видео из группы'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
    });
}

function toggleFileStatus(groupId, fileId, currentStatus) {
    // Определяем новый статус: если файл активен (new, queued) - ставим paused, иначе - new
    let newStatus;
    if (currentStatus === 'new' || currentStatus === 'queued') {
        newStatus = 'paused';
    } else if (currentStatus === 'paused') {
        newStatus = 'new';
    } else {
        // Для published, error - возвращаем в new
        newStatus = 'new';
    }
    
    console.log('toggleFileStatus: groupId=' + groupId + ', fileId=' + fileId + ', currentStatus=' + currentStatus + ', newStatus=' + newStatus);
    
    fetch('/content-groups/' + groupId + '/files/' + fileId + '/toggle-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({status: newStatus})
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Ошибка сервера (HTTP ' + response.status + ')');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Статус файла изменен', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            const errorMsg = data.message || 'Не удалось изменить статус';
            console.error('Toggle file status error:', data);
            showToast('Ошибка: ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка: ' + error.message, 'error');
    });
}

function clearFilePublication(groupId, fileId) {
    if (!confirm('Сбросить публикацию и статус для повторной публикации?')) {
        return;
    }

    fetch('/content-groups/' + groupId + '/files/' + fileId + '/clear-publication', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Ошибка сервера (HTTP ' + response.status + ')');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Публикация сброшена', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            const errorMsg = data.message || 'Не удалось сбросить публикацию';
            showToast('Ошибка: ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка: ' + error.message, 'error');
    });
}

function markDoNotPublish(groupId, fileId) {
    if (!confirm('Пометить файл как "не публиковать"?')) {
        return;
    }

    fetch('/content-groups/' + groupId + '/files/' + fileId + '/toggle-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({status: 'skipped'})
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Ошибка сервера (HTTP ' + response.status + ')');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Файл помечен как "не публиковать"', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            const errorMsg = data.message || 'Не удалось обновить статус';
            showToast('Ошибка: ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка: ' + error.message, 'error');
    });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Обратный отсчет до публикации для видео в группе
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-timer');
    
    countdowns.forEach(timer => {
        const publishAtStr = timer.getAttribute('data-publish-at');
        if (!publishAtStr) return;
        
        const publishAt = new Date(publishAtStr.replace(' ', 'T'));
        const now = new Date();
        const diff = publishAt - now;
        
        if (diff <= 0) {
            timer.querySelector('.countdown-text').textContent = '';
            timer.querySelector('.countdown-value').textContent = 'Время прошло';
            timer.style.color = '#e74c3c';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `${days}д ${hours}ч ${minutes}м`;
        } else if (hours > 0) {
            countdownText = `${hours}ч ${minutes}м ${seconds}с`;
        } else if (minutes > 0) {
            countdownText = `${minutes}м ${seconds}с`;
        } else {
            countdownText = `${seconds}с`;
        }
        
        timer.querySelector('.countdown-text').textContent = 'Осталось: ';
        timer.querySelector('.countdown-value').textContent = countdownText;
        
        // Меняем цвет при приближении времени
        if (diff < 3600000) { // Меньше часа
            timer.style.color = '#e74c3c';
        } else if (diff < 86400000) { // Меньше суток
            timer.style.color = '#f39c12';
        } else {
            timer.style.color = '#3498db';
        }
    });
}

// Обновляем обратный отсчет каждую секунду
setInterval(updateCountdowns, 1000);
updateCountdowns(); // Первый запуск сразу
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
