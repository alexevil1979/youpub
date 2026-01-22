<?php
$title = 'Мои видео';
ob_start();
?>

<h1>Мои видео</h1>
<a href="/videos/upload" class="btn btn-primary">Загрузить видео</a>

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
                    <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn btn-sm btn-success">Запланировать</a>
                    <button type="button" class="btn btn-sm btn-info" onclick="showAddToGroupModal(<?= $video['id'] ?>)">В группу</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

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
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось добавить видео в группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
