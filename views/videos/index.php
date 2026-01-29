<?php
$title = 'Мои видео';
ob_start();

// Группировка видео по дате и группам
function groupVideosByDate($videos) {
    $grouped = [
        'today' => [],
        'yesterday' => [],
        'this_week' => [],
        'this_month' => [],
        'older' => []
    ];
    
    $now = new DateTime();
    $today = new DateTime('today');
    $yesterday = new DateTime('yesterday');
    $weekAgo = new DateTime('-7 days');
    $monthAgo = new DateTime('-30 days');
    
    foreach ($videos as $video) {
        $videoDate = new DateTime($video['created_at']);
        
        if ($videoDate >= $today) {
            $grouped['today'][] = $video;
        } elseif ($videoDate >= $yesterday) {
            $grouped['yesterday'][] = $video;
        } elseif ($videoDate >= $weekAgo) {
            $grouped['this_week'][] = $video;
        } elseif ($videoDate >= $monthAgo) {
            $grouped['this_month'][] = $video;
        } else {
            $grouped['older'][] = $video;
        }
    }
    
    return $grouped;
}

// Получаем группы для каждого видео
$groupFileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
$videoGroups = [];
foreach ($videos as $video) {
    $groups = $groupFileRepo->findGroupsByVideoId($video['id']);
    $videoGroups[$video['id']] = $groups;
}

// $videoPublications уже переданы из контроллера

// Группируем по дате
$groupedByDate = groupVideosByDate($videos);

// Группируем по группам контента
$groupedByContentGroup = [];
$groupRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupRepository();
$allGroups = $groupRepo->findByUserId($_SESSION['user_id']);

foreach ($allGroups as $group) {
    $groupVideos = [];
    foreach ($videos as $video) {
        if (isset($videoGroups[$video['id']])) {
            foreach ($videoGroups[$video['id']] as $vg) {
                if ($vg['group_id'] == $group['id']) {
                    $groupVideos[] = $video;
                    break;
                }
            }
        }
    }
    if (!empty($groupVideos)) {
        $groupedByContentGroup[$group['id']] = [
            'group' => $group,
            'videos' => $groupVideos
        ];
    }
}
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Мои видео</h1>
        <p class="page-subtitle">
            Управляйте загруженными видео, группами и расписаниями публикаций.
        </p>
    </div>
    <div class="page-header-actions">
        <a href="/videos/upload" class="btn btn-primary">
            <i class="fa-solid fa-cloud-arrow-up icon-inline" aria-hidden="true"></i>
            Загрузить видео
        </a>
        <button type="button"
                class="btn btn-secondary"
                onclick="toggleViewMode()"
                id="viewModeBtn">
            <?= \App\Helpers\IconHelper::render('copy', 20, 'icon-inline') ?>
            Вид: Каталог
        </button>
    </div>
</div>

<!-- Панель групповых действий (показывается при выборе) -->
<div id="videos-bulk-toolbar" class="videos-bulk-toolbar" style="display: none;">
    <form id="videos-bulk-form" method="post" action="/videos/bulk-delete">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((new \Core\Auth())->generateCsrfToken()) ?>">
        <span id="videos-bulk-count" class="videos-bulk-count">Выбрано: 0</span>
        <button type="button" id="videos-bulk-delete-btn" class="btn btn-danger btn-sm">Удалить выбранные</button>
        <button type="button" id="videos-bulk-clear" class="btn btn-secondary btn-sm">Снять выбор</button>
    </form>
</div>

<div id="catalog-view" class="catalog-view">
    <div class="catalog-container">
        
        <!-- Группы контента -->
        <?php if (!empty($groupedByContentGroup)): ?>
            <div class="catalog-section">
                <h2 class="catalog-section-title"><?= \App\Helpers\IconHelper::render('folder', 24, 'icon-inline') ?> Группы контента</h2>
                <?php foreach ($groupedByContentGroup as $item): ?>
                    <div class="catalog-folder">
                        <div class="folder-header" onclick="toggleFolder(this)">
                            <span class="folder-icon"><?= \App\Helpers\IconHelper::render('folder', 20) ?></span>
                            <span class="folder-name"><?= htmlspecialchars($item['group']['name']) ?></span>
                            <span class="folder-count"><?= count($item['videos']) ?> видео</span>
                            <span class="folder-toggle">▼</span>
                        </div>
                        <div class="folder-content">
                            <?php foreach ($item['videos'] as $video): ?>
                                <div class="catalog-item" data-video-id="<?= (int)$video['id'] ?>">
                                    <label class="video-checkbox-wrap" onclick="event.stopPropagation();">
                                        <input type="checkbox" class="video-checkbox" name="video_ids[]" value="<?= (int)$video['id'] ?>" form="videos-bulk-form">
                                    </label>
                                    <span class="item-icon"><?= \App\Helpers\IconHelper::render('video', 20) ?></span>
                                    <div class="item-info">
                                        <div class="item-title"><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></div>
                                        <div class="item-meta">
                                            <span><?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB</span>
                                            <span>•</span>
                                            <span><?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></span>
                                            <span>•</span>
                                            <span class="status-badge status-<?= $video['status'] ?>"><?= ucfirst($video['status']) ?></span>
                                        </div>
                                    </div>
                                    <div class="item-actions">
                                        <a href="/videos/<?= $video['id'] ?>" class="btn-action" title="Просмотр"><?= \App\Helpers\IconHelper::render('view', 20) ?></a>
                                        <?php if (isset($videoPublications[$video['id']])): 
                                            $pub = $videoPublications[$video['id']];
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
                                            <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn-action btn-action-publish" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>"><?= \App\Helpers\IconHelper::render('publish', 20) ?></a>
                                        <?php endif; endif; ?>
                                        <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn-action" title="Запланировать"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></a>
                                        <button type="button" class="btn-action" onclick="showAddToGroupModal(<?= $video['id'] ?>)" title="В группу"><?= \App\Helpers\IconHelper::render('folder', 20) ?></button>
                                        <button type="button" class="btn-action <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'btn-pause' : 'btn-play' ?>" 
                                                onclick="toggleVideoStatus(<?= $video['id'] ?>)" 
                                                title="<?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'Выключить' : 'Включить' ?>">
                                            <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? \App\Helpers\IconHelper::render('pause', 20) : \App\Helpers\IconHelper::render('play', 20) ?>
                                        </button>
                                        <button type="button" class="btn-action btn-delete" onclick="deleteVideo(<?= $video['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 20) ?></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- По дате -->
        <div class="catalog-section">
            <h2 class="catalog-section-title"><?= \App\Helpers\IconHelper::render('calendar', 24, 'icon-inline') ?> По дате загрузки</h2>
            
            <?php if (!empty($groupedByDate['today'])): ?>
                <div class="catalog-folder">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span class="folder-icon"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></span>
                        <span class="folder-name">Сегодня</span>
                        <span class="folder-count"><?= count($groupedByDate['today']) ?> видео</span>
                        <span class="folder-toggle">▼</span>
                    </div>
                    <div class="folder-content">
                        <?php foreach ($groupedByDate['today'] as $video): ?>
                            <?php include __DIR__ . '/_video_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($groupedByDate['yesterday'])): ?>
                <div class="catalog-folder">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span class="folder-icon"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></span>
                        <span class="folder-name">Вчера</span>
                        <span class="folder-count"><?= count($groupedByDate['yesterday']) ?> видео</span>
                        <span class="folder-toggle">▼</span>
                    </div>
                    <div class="folder-content">
                        <?php foreach ($groupedByDate['yesterday'] as $video): ?>
                            <?php include __DIR__ . '/_video_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($groupedByDate['this_week'])): ?>
                <div class="catalog-folder">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span class="folder-icon"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></span>
                        <span class="folder-name">На этой неделе</span>
                        <span class="folder-count"><?= count($groupedByDate['this_week']) ?> видео</span>
                        <span class="folder-toggle">▼</span>
                    </div>
                    <div class="folder-content">
                        <?php foreach ($groupedByDate['this_week'] as $video): ?>
                            <?php include __DIR__ . '/_video_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($groupedByDate['this_month'])): ?>
                <div class="catalog-folder">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span class="folder-icon"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></span>
                        <span class="folder-name">В этом месяце</span>
                        <span class="folder-count"><?= count($groupedByDate['this_month']) ?> видео</span>
                        <span class="folder-toggle">▼</span>
                    </div>
                    <div class="folder-content">
                        <?php foreach ($groupedByDate['this_month'] as $video): ?>
                            <?php include __DIR__ . '/_video_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($groupedByDate['older'])): ?>
                <div class="catalog-folder">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span class="folder-icon"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></span>
                        <span class="folder-name">Ранее</span>
                        <span class="folder-count"><?= count($groupedByDate['older']) ?> видео</span>
                        <span class="folder-toggle">▼</span>
                    </div>
                    <div class="folder-content">
                        <?php foreach ($groupedByDate['older'] as $video): ?>
                            <?php include __DIR__ . '/_video_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($videos)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= \App\Helpers\IconHelper::render('video', 64) ?></div>
                <h3>Нет загруженных видео</h3>
                <p>Начните с загрузки вашего первого видео</p>
                <a href="/videos/upload" class="btn btn-primary">Загрузить видео</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Табличный вид (скрыт по умолчанию), оформление как в каталоге -->
<div id="table-view" class="table-view catalog-view" style="display: none;">
    <div class="catalog-container">
        <?php if (empty($videos)): ?>
            <p>Нет загруженных видео</p>
        <?php else: ?>
            <div class="table-sort-bar">
                <span class="table-sort-label">Сортировка:</span>
                <span class="table-sort-value">сначала новые</span>
            </div>
            <div class="table-wrapper">
                <table class="data-table videos-table" aria-label="Видео в табличном виде">
                    <thead>
                        <tr>
                            <th class="col-checkbox">
                                <label class="video-checkbox-wrap">
                                    <input type="checkbox" id="video-select-all" class="video-checkbox" form="videos-bulk-form" title="Выбрать все">
                                </label>
                            </th>
                            <th>Файл</th>
                            <th>Размер</th>
                            <th>Статус</th>
                            <th>Загружено</th>
                            <th class="col-actions">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): ?>
                        <tr data-video-id="<?= (int)$video['id'] ?>">
                            <td class="col-checkbox">
                                <label class="video-checkbox-wrap">
                                    <input type="checkbox" class="video-checkbox" name="video_ids[]" value="<?= (int)$video['id'] ?>" form="videos-bulk-form">
                                </label>
                            </td>
                            <td>
                                <div class="cell-main">
                                    <span class="cell-title"><?= htmlspecialchars($video['file_name'] ?? 'video') ?></span>
                                </div>
                            </td>
                            <td><?= number_format(($video['file_size'] ?? 0) / 1024 / 1024, 2) ?> MB</td>
                            <td><span class="status-badge status-<?= $video['status'] ?>"><?= ucfirst($video['status']) ?></span></td>
                            <td><?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></td>
                            <td class="col-actions item-actions">
                                <a href="/videos/<?= $video['id'] ?>" class="btn-action" title="Просмотр"><?= \App\Helpers\IconHelper::render('view', 20) ?></a>
                                <?php if (isset($videoPublications[$video['id']])): 
                                    $pub = $videoPublications[$video['id']];
                                    $pubUrl = $pub['platform_url'] ?? '';
                                    if (!$pubUrl && !empty($pub['platform_id'])) {
                                        switch ($pub['platform']) {
                                            case 'youtube': $pubUrl = 'https://youtube.com/shorts/' . $pub['platform_id']; break;
                                            case 'telegram': $pubUrl = 'https://t.me/' . $pub['platform_id']; break;
                                            case 'tiktok': $pubUrl = 'https://www.tiktok.com/@' . $pub['platform_id']; break;
                                            case 'instagram': $pubUrl = 'https://www.instagram.com/p/' . $pub['platform_id']; break;
                                            case 'pinterest': $pubUrl = 'https://www.pinterest.com/pin/' . $pub['platform_id']; break;
                                        }
                                    }
                                    if ($pubUrl):
                                ?>
                                    <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn-action btn-action-publish" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>"><?= \App\Helpers\IconHelper::render('publish', 20) ?></a>
                                <?php endif; endif; ?>
                                <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn-action" title="Запланировать"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></a>
                                <button type="button" class="btn-action" onclick="showAddToGroupModal(<?= $video['id'] ?>)" title="В группу"><?= \App\Helpers\IconHelper::render('folder', 20) ?></button>
                                <button type="button" class="btn-action <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'btn-pause' : 'btn-play' ?>" onclick="toggleVideoStatus(<?= $video['id'] ?>)" title="<?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'Выключить' : 'Включить' ?>">
                                    <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? \App\Helpers\IconHelper::render('pause', 20) : \App\Helpers\IconHelper::render('play', 20) ?>
                                </button>
                                <button type="button" class="btn-action btn-delete" onclick="deleteVideo(<?= $video['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 20) ?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для добавления в группу -->
<div id="addToGroupModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeAddToGroupModal()">&times;</span>
        <h2>Добавить видео в группу</h2>
        <?php if (empty($groups)): ?>
            <p>У вас нет групп. <a href="/content-groups/create">Создать группу</a></p>
        <?php else: ?>
            <form id="addToGroupForm">
                <div class="form-group">
                    <label for="group_id">Выберите группу:</label>
                    <select id="group_id" name="group_id" required>
                        <option value="">Выберите группу</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['id'] ?? '' ?>"><?= htmlspecialchars($group['name'] ?? 'Без названия') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Добавить</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddToGroupModal()">Отмена</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
let currentVideoId = null;
let viewMode = 'catalog';

function toggleViewMode() {
    viewMode = viewMode === 'catalog' ? 'table' : 'catalog';
    const catalogView = document.getElementById('catalog-view');
    const tableView = document.getElementById('table-view');
    const btn = document.getElementById('viewModeBtn');
    
    if (viewMode === 'catalog') {
        catalogView.style.display = 'block';
        tableView.style.display = 'none';
        btn.innerHTML = '<?= \App\Helpers\IconHelper::render('copy', 20, 'icon-inline') ?> Вид: Каталог';
    } else {
        catalogView.style.display = 'none';
        tableView.style.display = 'block';
        btn.innerHTML = '<?= \App\Helpers\IconHelper::render('folder', 20, 'icon-inline') ?> Вид: Таблица';
    }
}

function toggleFolder(header) {
    const folder = header.closest('.catalog-folder');
    const toggle = header.querySelector('.folder-toggle');
    if (folder.classList.contains('expanded')) {
        folder.classList.remove('expanded');
        if (toggle) toggle.textContent = '▶';
    } else {
        folder.classList.add('expanded');
        if (toggle) toggle.textContent = '▼';
    }
}

// Обновление панели групповых действий и «выбрать все»
function updateBulkToolbar() {
    const checkboxes = document.querySelectorAll('.video-checkbox:not(#video-select-all)');
    const checked = document.querySelectorAll('.video-checkbox:not(#video-select-all):checked');
    const toolbar = document.getElementById('videos-bulk-toolbar');
    const countEl = document.getElementById('videos-bulk-count');
    const selectAll = document.getElementById('video-select-all');
    if (toolbar) toolbar.style.display = checked.length ? 'block' : 'none';
    if (countEl) countEl.textContent = 'Выбрано: ' + checked.length;
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('videos-bulk-form');
    if (form) {
        form.addEventListener('change', updateBulkToolbar);
    }
    document.getElementById('video-select-all')?.addEventListener('change', function() {
        document.querySelectorAll('.video-checkbox:not(#video-select-all)').forEach(cb => { cb.checked = this.checked; });
        updateBulkToolbar();
    });
    document.getElementById('videos-bulk-clear')?.addEventListener('click', function() {
        document.querySelectorAll('.video-checkbox').forEach(cb => { cb.checked = false; });
        updateBulkToolbar();
    });
    document.getElementById('videos-bulk-delete-btn')?.addEventListener('click', function() {
        const ids = Array.from(document.querySelectorAll('.video-checkbox:not(#video-select-all):checked')).map(c => c.value);
        if (!ids.length) return;
        if (!confirm('Удалить выбранные видео (' + ids.length + ')? Действие нельзя отменить.')) return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form?.querySelector('input[name="csrf_token"]')?.value || '';
        fetch('/videos/bulk-delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ video_ids: ids })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                showToast(data.message || 'Удалено', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Ошибка: ' + (data.message || 'Не удалось удалить'), 'error');
            }
        }).catch(() => showToast('Произошла ошибка', 'error'));
    });
    updateBulkToolbar();
});

function showAddToGroupModal(videoId) {
    currentVideoId = videoId;
    document.getElementById('addToGroupModal').style.display = 'block';
}

function closeAddToGroupModal() {
    document.getElementById('addToGroupModal').style.display = 'none';
    currentVideoId = null;
}

window.onclick = function(event) {
    const modal = document.getElementById('addToGroupModal');
    if (event.target == modal) {
        closeAddToGroupModal();
    }
}

document.getElementById('addToGroupForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const groupId = document.getElementById('group_id').value;
    if (!groupId) {
        alert('Выберите группу');
        return;
    }
    
    fetch('/content-groups/' + groupId + '/add-video', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'video_id=' + currentVideoId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Видео добавлено в группу!');
            closeAddToGroupModal();
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось добавить видео в группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
});

function toggleVideoStatus(id) {
    fetch('/videos/' + id + '/toggle-status', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Статус видео изменен', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось изменить статус'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
    });
}

function deleteVideo(id) {
    if (!confirm('Удалить это видео?')) return;
    if (!confirm('Вы уверены? Это действие нельзя отменить.')) return;
    
    fetch('/videos/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Видео удалено', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось удалить'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
