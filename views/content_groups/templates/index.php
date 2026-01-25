<?php
$title = 'Шаблоны оформления';
ob_start();
?>

<h1>Шаблоны оформления</h1>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<a href="/content-groups/auto-shorts" class="btn btn-primary">🚀 Автогенерация Shorts</a>
<a href="/content-groups/templates/create-shorts" class="btn btn-primary">🎯 Создать Shorts шаблон</a>

<?php 
// Убеждаемся, что переменная определена
if (!isset($templates)) {
    $templates = [];
}

$searchQuery = trim((string)($_GET['q'] ?? ''));
$filterStatus = $_GET['status'] ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at_desc';

$allowedStatuses = ['all', 'active', 'inactive'];
$allowedSorts = ['created_at_desc', 'created_at_asc'];

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}
if (!in_array($sortBy, $allowedSorts, true)) {
    $sortBy = 'created_at_desc';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) {
    $filterDateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)) {
    $filterDateTo = '';
}

$filteredTemplates = array_filter($templates, function($template) use ($searchQuery, $filterStatus, $filterDateFrom, $filterDateTo) {
    try {
        // Проверяем, что $template является массивом
        if (!is_array($template)) {
            return false;
        }
        
        if ($filterStatus === 'active' && empty($template['is_active'])) {
            return false;
        }
        if ($filterStatus === 'inactive' && !empty($template['is_active'])) {
            return false;
        }
        if ($searchQuery !== '') {
            $name = (string)($template['name'] ?? '');
            $desc = (string)($template['description'] ?? '');
            // Используем stripos вместо mb_stripos для совместимости
            if (function_exists('mb_stripos')) {
                if (mb_stripos($name, $searchQuery) === false && mb_stripos($desc, $searchQuery) === false) {
                    return false;
                }
            } else {
                if (stripos($name, $searchQuery) === false && stripos($desc, $searchQuery) === false) {
                    return false;
                }
            }
        }
        if ($filterDateFrom !== '' || $filterDateTo !== '') {
            $createdAt = $template['created_at'] ?? null;
            $createdTs = $createdAt ? @strtotime($createdAt) : 0;
            if ($createdTs === false) {
                $createdTs = 0;
            }
            if ($filterDateFrom !== '' && $createdTs < @strtotime($filterDateFrom)) {
                return false;
            }
            if ($filterDateTo !== '' && $createdTs > @strtotime($filterDateTo . ' 23:59:59')) {
                return false;
            }
        }
        return true;
    } catch (\Throwable $e) {
        error_log("Error filtering template: " . $e->getMessage());
        return false;
    }
});

$filteredTemplates = array_values($filteredTemplates);
try {
    usort($filteredTemplates, function($a, $b) use ($sortBy) {
        try {
            $aTime = !empty($a['created_at']) ? @strtotime($a['created_at']) : 0;
            $bTime = !empty($b['created_at']) ? @strtotime($b['created_at']) : 0;
            if ($aTime === false) $aTime = 0;
            if ($bTime === false) $bTime = 0;
            if ($aTime === $bTime) {
                return 0;
            }
            if ($sortBy === 'created_at_asc') {
                return $aTime < $bTime ? -1 : 1;
            }
            return $aTime > $bTime ? -1 : 1;
        } catch (\Throwable $e) {
            error_log("Error sorting templates: " . $e->getMessage());
            return 0;
        }
    });
} catch (\Throwable $e) {
    error_log("Error in usort: " . $e->getMessage());
    // Продолжаем с неотсортированным массивом
}
?>

<div class="filters-panel" style="margin-top: 1rem;">
    <form method="GET" action="/content-groups/templates" class="filters-form" id="filtersForm">
        <div class="filter-group">
            <label for="filter_query">Поиск:</label>
            <input type="text" id="filter_query" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Название или описание">
        </div>
        <div class="filter-group">
            <label for="filter_status">Статус:</label>
            <select id="filter_status" name="status">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Все</option>
                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Активные</option>
                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Неактивные</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter_date_from">С:</label>
            <input type="date" id="filter_date_from" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
        </div>
        <div class="filter-group">
            <label for="filter_date_to">По:</label>
            <input type="date" id="filter_date_to" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
        </div>
        <div class="filter-group">
            <label for="filter_sort">Сортировка:</label>
            <select id="filter_sort" name="sort">
                <option value="created_at_desc" <?= $sortBy === 'created_at_desc' ? 'selected' : '' ?>>Сначала новые</option>
                <option value="created_at_asc" <?= $sortBy === 'created_at_asc' ? 'selected' : '' ?>>Сначала старые</option>
            </select>
        </div>
        <div class="filter-group" style="display: flex; gap: 0.5rem; align-items: flex-end;">
            <button type="submit" class="btn btn-sm btn-primary" title="Применить">
                <?= \App\Helpers\IconHelper::render('search', 14, 'icon-inline') ?>
            </button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="clearFilters()" title="Очистить">
                <?= \App\Helpers\IconHelper::render('delete', 14, 'icon-inline') ?>
            </button>
        </div>
    </form>
</div>

<?php if (empty($filteredTemplates)): ?>
    <div class="empty-state" style="margin-top: 2rem;">
        <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('file', 64) ?></div>
        <h3>Нет шаблонов</h3>
        <p><?= empty($templates) ? 'Создайте первый шаблон для автоматического оформления публикаций' : 'Попробуйте изменить фильтры или поиск' ?></p>
        <a href="/content-groups/templates/create-shorts" class="btn btn-primary">🎯 Создать шаблон для Shorts</a>
    </div>
<?php else: ?>
    <div style="margin-top: 2rem;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Создан</th>
                    <th>Статус</th>
                    <th style="width: 150px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredTemplates as $template): ?>
                    <?php if (!is_array($template)) continue; ?>
                    <tr>
                        <td><?= htmlspecialchars($template['name'] ?? 'Без названия') ?></td>
                        <td><?= htmlspecialchars($template['description'] ?? '') ?></td>
                        <td>
                            <?php 
                            $createdAt = $template['created_at'] ?? null;
                            if (!empty($createdAt)) {
                                $timestamp = @strtotime($createdAt);
                                echo $timestamp !== false ? date('d.m.Y H:i', $timestamp) : '-';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= !empty($template['is_active']) ? 'active' : 'inactive' ?>">
                                <?= !empty($template['is_active']) ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if (!empty($template['id'])): ?>
                                    <a href="/content-groups/templates/<?= (int)$template['id'] ?>/edit" class="btn btn-xs btn-primary" title="Редактировать"><?= \App\Helpers\IconHelper::render('edit', 14, 'icon-inline') ?></a>
                                    <button type="button" class="btn btn-xs btn-danger" onclick="deleteTemplate(<?= (int)$template['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 14, 'icon-inline') ?></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function clearFilters() {
    window.location.href = '/content-groups/templates';
}

function deleteTemplate(id) {
    if (!confirm('Удалить шаблон?')) {
        return;
    }
    
    fetch('/content-groups/templates/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Шаблон удален');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить шаблон'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
