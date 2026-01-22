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

<h1>Мои видео</h1>
<div style="margin-bottom: 1rem;">
    <a href="/videos/upload" class="btn btn-primary">📤 Загрузить видео</a>
    <button type="button" class="btn btn-secondary" onclick="toggleViewMode()" id="viewModeBtn">📋 Вид: Каталог</button>
</div>

<div id="catalog-view" class="catalog-view">
    <div class="catalog-container">
        
        <!-- Группы контента -->
        <?php if (!empty($groupedByContentGroup)): ?>
            <div class="catalog-section">
                <h2 class="catalog-section-title">📁 Группы контента</h2>
                <?php foreach ($groupedByContentGroup as $item): ?>
                    <div class="catalog-folder">
                        <div class="folder-header" onclick="toggleFolder(this)">
                            <span class="folder-icon">📁</span>
                            <span class="folder-name"><?= htmlspecialchars($item['group']['name']) ?></span>
                            <span class="folder-count"><?= count($item['videos']) ?> видео</span>
                            <span class="folder-toggle">▼</span>
                        </div>
                        <div class="folder-content">
                            <?php foreach ($item['videos'] as $video): ?>
                                <div class="catalog-item">
                                    <span class="item-icon">🎬</span>
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
                                        <a href="/videos/<?= $video['id'] ?>" class="btn-action" title="Просмотр">👁</a>
                                        <?php if (isset($videoPublications[$video['id']])): 
                                            $pub = $videoPublications[$video['id']];
                                            $pubUrl = $pub['platform_url'] ?? '';
                                            if (!$pubUrl && $pub['platform_id']) {
                                                switch ($pub['platform']) {
                                                    case 'youtube':
                                                        $pubUrl = 'https://youtube.com/watch?v=' . $pub['platform_id'];
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
                                            <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn-action btn-action-publish" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>">🚀</a>
                                        <?php endif; endif; ?>
                                        <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn-action" title="Запланировать">📅</a>
                                        <button type="button" class="btn-action" onclick="showAddToGroupModal(<?= $video['id'] ?>)" title="В группу">📁</button>
                                        <button type="button" class="btn-action <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'btn-pause' : 'btn-play' ?>" 
                                                onclick="toggleVideoStatus(<?= $video['id'] ?>)" 
                                                title="<?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'Выключить' : 'Включить' ?>">
                                            <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? '⏸' : '▶' ?>
                                        </button>
                                        <button type="button" class="btn-action btn-delete" onclick="deleteVideo(<?= $video['id'] ?>)" title="Удалить">🗑</button>
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
            <h2 class="catalog-section-title">📅 По дате загрузки</h2>
            
            <?php if (!empty($groupedByDate['today'])): ?>
                <div class="catalog-folder">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span class="folder-icon">📅</span>
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
                        <span class="folder-icon">📅</span>
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
                        <span class="folder-icon">📅</span>
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
                        <span class="folder-icon">📅</span>
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
                        <span class="folder-icon">📅</span>
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
                <div class="empty-icon">📹</div>
                <h3>Нет загруженных видео</h3>
                <p>Начните с загрузки вашего первого видео</p>
                <a href="/videos/upload" class="btn btn-primary">Загрузить видео</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Табличный вид (скрыт по умолчанию) -->
<div id="table-view" class="table-view" style="display: none;">
    <?php if (empty($videos)): ?>
        <p>Нет загруженных видео</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Размер</th>
                    <th>Статус</th>
                    <th>Дата загрузки</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videos as $video): ?>
                <tr>
                    <td><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></td>
                    <td><?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB</td>
                    <td><?= ucfirst($video['status']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></td>
                    <td>
                        <a href="/videos/<?= $video['id'] ?>" class="btn btn-sm btn-primary">Просмотр</a>
                        <?php if (isset($videoPublications[$video['id']])): 
                            $pub = $videoPublications[$video['id']];
                            $pubUrl = $pub['platform_url'] ?? '';
                            if (!$pubUrl && $pub['platform_id']) {
                                switch ($pub['platform']) {
                                    case 'youtube':
                                        $pubUrl = 'https://youtube.com/watch?v=' . $pub['platform_id'];
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
                            <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn btn-sm btn-success" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>">🚀 Публикация</a>
                        <?php endif; endif; ?>
                        <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn btn-sm btn-success">Запланировать</a>
                        <button type="button" class="btn btn-sm btn-info" onclick="showAddToGroupModal(<?= $video['id'] ?>)">В группу</button>
                        <button type="button" class="btn btn-sm <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'btn-warning' : 'btn-success' ?>" 
                                onclick="toggleVideoStatus(<?= $video['id'] ?>)">
                            <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? '⏸ Выкл' : '▶ Вкл' ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteVideo(<?= $video['id'] ?>)">🗑 Удалить</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
                            <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
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
        btn.textContent = '📋 Вид: Каталог';
    } else {
        catalogView.style.display = 'none';
        tableView.style.display = 'block';
        btn.textContent = '📁 Вид: Таблица';
    }
}

function toggleFolder(header) {
    const folder = header.closest('.catalog-folder');
    const content = folder.querySelector('.folder-content');
    const toggle = header.querySelector('.folder-toggle');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        toggle.textContent = '▼';
        folder.classList.add('expanded');
    } else {
        content.style.display = 'none';
        toggle.textContent = '▶';
        folder.classList.remove('expanded');
    }
}

// Раскрыть все папки по умолчанию
document.addEventListener('DOMContentLoaded', function() {
    const folders = document.querySelectorAll('.catalog-folder');
    folders.forEach(folder => {
        const content = folder.querySelector('.folder-content');
        content.style.display = 'block';
        folder.classList.add('expanded');
    });
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
