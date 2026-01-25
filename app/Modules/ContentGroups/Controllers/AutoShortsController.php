<?php

namespace App\Modules\ContentGroups\Controllers;

use Core\Controller;
use App\Modules\ContentGroups\Services\AutoShortsGenerator;

/**
 * Контроллер для автогенерации Shorts контента
 */
class AutoShortsController extends Controller
{
    private AutoShortsGenerator $autoGenerator;

    public function __construct()
    {
        parent::__construct();
        $this->autoGenerator = new AutoShortsGenerator();
    }

    /**
     * Показать форму автогенерации
     */
    public function showGenerate(): void
    {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();

        $title = '🎯 Автогенерация Shorts';
        ob_start();
        ?>

        <h1>🎯 Автогенерация Shorts контента</h1>

        <div class="auto-shorts-intro">
            <p><strong>Введи только базовую идею видео - система сгенерирует всё остальное автоматически:</strong></p>
            <ul>
                <li>✅ Уникальное название</li>
                <li>✅ Привлекательное описание</li>
                <li>✅ Подходящие emoji</li>
                <li>✅ Оптимизированные теги</li>
                <li>✅ Вопрос для вовлечённости</li>
                <li>✅ Защита от дубликатов</li>
            </ul>
        </div>

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

        <form method="POST" action="/content-groups/auto-shorts/generate" class="auto-shorts-form" id="autoShortsForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-section">
                <h3>💡 Базовая идея видео</h3>
                <p class="form-hint">Опиши суть видео в 3-7 словах. Не используй "часть", индексы или технические детали.</p>

                <div class="form-group">
                    <label for="video_idea">Идея видео *</label>
                    <input type="text"
                           id="video_idea"
                           name="video_idea"
                           required
                           maxlength="100"
                           placeholder="Например: Девушка поёт под неоном"
                           value="<?= htmlspecialchars($_POST['video_idea'] ?? '') ?>">
                    <small>
                        Примеры: "Атмосферный вокал ночью", "Спокойный голос и неон", "Мистический шепот в темноте"
                    </small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    🎯 Сгенерировать Shorts контент
                </button>
                <a href="/content-groups/templates" class="btn btn-secondary">
                    📝 Ручное создание шаблона
                </a>
            </div>
        </form>

        <style>
        .auto-shorts-intro {
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .auto-shorts-intro ul {
            margin: 1rem 0 0 0;
            padding-left: 1.5rem;
        }

        .auto-shorts-intro li {
            margin-bottom: 0.5rem;
        }

        .form-hint {
            color: #666;
            font-style: italic;
            margin-bottom: 1rem;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .auto-shorts-form {
            max-width: 600px;
        }

        .form-section {
            background: #fafafa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        </style>

        <?php
        $content = ob_get_clean();
        include __DIR__ . '/../../../../views/layout.php';
    }

    /**
     * Обработать генерацию контента
     */
    public function generate(): void
    {
        try {
            if (!$this->validateCsrf()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            $videoIdea = trim($this->getParam('video_idea', ''));

            if (empty($videoIdea)) {
                $_SESSION['error'] = 'Необходимо указать базовую идею видео';
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            if (strlen($videoIdea) < 3) {
                $_SESSION['error'] = 'Идея видео должна содержать минимум 3 символа';
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            // Генерируем контент
            $result = $this->autoGenerator->generateFromIdea($videoIdea);

            // Сохраняем результат в сессии для отображения
            $_SESSION['auto_shorts_result'] = $result;

            header('Location: /content-groups/auto-shorts/result');
            exit;

        } catch (\Exception $e) {
            error_log('AutoShorts generation error: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при генерации контента.';
            header('Location: /content-groups/auto-shorts');
            exit;
        }
    }

    /**
     * Показать результат генерации
     */
    public function showResult(): void
    {
        $result = $_SESSION['auto_shorts_result'] ?? null;

        if (!$result) {
            header('Location: /content-groups/auto-shorts');
            exit;
        }

        $csrfToken = (new \Core\Auth())->generateCsrfToken();

        $title = '🎯 Сгенерированный Shorts контент';
        ob_start();
        ?>

        <h1>🎯 Сгенерированный контент для Shorts</h1>

        <div class="generation-result">
            <div class="idea-summary">
                <h3>💡 Исходная идея:</h3>
                <p class="idea-text">"<?= htmlspecialchars($result['idea']) ?>"</p>
            </div>

            <div class="intent-analysis">
                <h3>🔍 Автоанализ:</h3>
                <div class="intent-tags">
                    <span class="tag content-type">Тип: <?= htmlspecialchars($result['intent']['content_type']) ?></span>
                    <span class="tag mood">Настроение: <?= htmlspecialchars($result['intent']['mood']) ?></span>
                    <span class="tag visual">Визуал: <?= htmlspecialchars($result['intent']['visual_focus']) ?></span>
                </div>
            </div>

            <div class="generated-content">
                <h3>📝 Сгенерированный контент:</h3>

                <div class="content-preview">
                    <div class="preview-item">
                        <label>Название:</label>
                        <div class="preview-value title">
                            <?= htmlspecialchars($result['content']['title']) ?>
                        </div>
                    </div>

                    <div class="preview-item">
                        <label>Описание:</label>
                        <div class="preview-value description">
                            <?= htmlspecialchars($result['content']['description']) ?>
                        </div>
                    </div>

                    <div class="preview-item">
                        <label>Emoji:</label>
                        <div class="preview-value emoji">
                            <?= !empty($result['content']['emoji']) ? htmlspecialchars($result['content']['emoji']) : '<em>(без emoji)</em>' ?>
                        </div>
                    </div>

                    <div class="preview-item">
                        <label>Теги:</label>
                        <div class="preview-value tags">
                            <?= implode(' ', array_map('htmlspecialchars', $result['content']['tags'])) ?>
                        </div>
                    </div>

                    <div class="preview-item">
                        <label>Закреплённый комментарий:</label>
                        <div class="preview-value comment">
                            <?= htmlspecialchars($result['content']['pinned_comment']) ?>
                        </div>
                    </div>

                    <div class="preview-item">
                        <label>Смысловой угол:</label>
                        <div class="preview-value angle">
                            <?= htmlspecialchars($result['content']['angle']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="/content-groups/auto-shorts/save" class="save-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="generation_data" value="<?= htmlspecialchars(json_encode($result)) ?>">

            <div class="form-actions">
                <button type="submit" name="action" value="save_template" class="btn btn-primary">
                    💾 Сохранить как шаблон
                </button>
                <button type="submit" name="action" value="regenerate" class="btn btn-secondary">
                    🔄 Сгенерировать заново
                </button>
                <a href="/content-groups/auto-shorts" class="btn btn-outline">
                    🔙 Новая идея
                </a>
            </div>
        </form>

        <style>
        .generation-result {
            background: #fafafa;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .idea-summary {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .idea-text {
            font-size: 1.2rem;
            font-style: italic;
            color: #2c3e50;
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .intent-analysis {
            margin-bottom: 2rem;
        }

        .intent-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .tag {
            background: #3498db;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .tag.mood { background: #e74c3c; }
        .tag.visual { background: #27ae60; }

        .content-preview {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e0e0e0;
        }

        .preview-item {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .preview-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .preview-item label {
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
        }

        .preview-value {
            background: #f8f9fa;
            padding: 0.8rem;
            border-radius: 6px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .preview-value.title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #2c3e50;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .preview-value.description {
            font-style: italic;
            color: #34495e;
            background: #d4edda;
            border-left: 4px solid #28a745;
        }

        .preview-value.emoji {
            font-size: 1.2rem;
            text-align: center;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .preview-value.tags {
            font-family: monospace;
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }

        .preview-value.comment {
            color: #6c757d;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .preview-value.angle {
            font-size: 0.9rem;
            color: #6c757d;
            background: #e2e3e5;
            border-left: 4px solid #6c757d;
        }

        .save-form {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e0e0e0;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
        }

        .btn-outline:hover {
            background: #6c757d;
            color: white;
        }
        </style>

        <?php
        $content = ob_get_clean();
        include __DIR__ . '/../../../../views/layout.php';
    }

    /**
     * Сохранить сгенерированный контент как шаблон
     */
    public function save(): void
    {
        try {
            if (!$this->validateCsrf()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            $userId = $_SESSION['user_id'] ?? null;
            $action = $this->getParam('action', '');

            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            if ($action === 'regenerate') {
                // Регенерация - возвращаемся к форме
                header('Location: /content-groups/auto-shorts');
                exit;
            }

            if ($action === 'save_template') {
                $generationData = json_decode($this->getParam('generation_data', '{}'), true);

                if (empty($generationData)) {
                    $_SESSION['error'] = 'Данные генерации не найдены';
                    header('Location: /content-groups/auto-shorts');
                    exit;
                }

                // Создаем шаблон на основе сгенерированных данных
                $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();

                $templateData = [
                    'user_id' => $userId,
                    'name' => 'Auto: ' . $generationData['idea'],
                    'description' => 'Автоматически сгенерированный шаблон для: ' . $generationData['idea'],

                    // Генерируем простые шаблоны на основе результатов
                    'title_template' => $generationData['content']['title'],
                    'description_template' => $generationData['content']['description'],
                    'tags_template' => implode(', ', $generationData['content']['tags']),
                    'emoji_list' => '', // Пустой, так как emoji генерируются автоматически

                    // Новые поля для Shorts
                    'hook_type' => $generationData['intent']['content_type'],
                    'focus_points' => json_encode([$generationData['intent']['visual_focus']]),
                    'title_variants' => json_encode([$generationData['content']['title']]),
                    'description_variants' => json_encode([
                        $generationData['intent']['mood'] => [$generationData['content']['description']]
                    ]),
                    'emoji_groups' => json_encode([
                        $generationData['intent']['mood'] => explode(',', $generationData['content']['emoji'])
                    ]),
                    'base_tags' => implode(', ', $generationData['content']['tags']),
                    'tag_variants' => json_encode([$generationData['content']['tags']]),
                    'questions' => json_encode([$generationData['content']['pinned_comment']]),
                    'pinned_comments' => json_encode([$generationData['content']['pinned_comment']]),
                    'cta_types' => json_encode(['subscribe', 'like', 'comment']),
                    'enable_ab_testing' => 1,
                    'is_active' => 1
                ];

                $templateId = $templateRepo->create($templateData);

                if ($templateId) {
                    $_SESSION['success'] = 'Шаблон успешно сохранён! Теперь вы можете использовать его для публикаций.';
                    header('Location: /content-groups/templates');
                } else {
                    $_SESSION['error'] = 'Ошибка при сохранении шаблона';
                    header('Location: /content-groups/auto-shorts/result');
                }
                exit;
            }

            header('Location: /content-groups/auto-shorts');

        } catch (\Exception $e) {
            error_log('AutoShorts save error: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при сохранении.';
            header('Location: /content-groups/auto-shorts/result');
            exit;
        }
    }
}