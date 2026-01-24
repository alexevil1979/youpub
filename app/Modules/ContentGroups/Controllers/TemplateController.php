<?php

namespace App\Modules\ContentGroups\Controllers;

use Core\Controller;
use App\Modules\ContentGroups\Services\TemplateService;
use App\Modules\ContentGroups\Services\AutoShortsGenerator;

/**
 * Контроллер для управления шаблонами
 */
class TemplateController extends Controller
{
    private TemplateService $templateService;
    private AutoShortsGenerator $autoGenerator;

    public function __construct()
    {
        parent::__construct();
        $this->templateService = new TemplateService();
        $this->autoGenerator = new AutoShortsGenerator();
    }

    /**
     * Список шаблонов
     */
    public function index(): void
    {
        try {
            $timestamp = @date('Y-m-d H:i:s') ?: gmdate('Y-m-d H:i:s') . ' UTC';
        } catch (\Throwable $e) {
            $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        }
        error_log("TemplateController::index: START - " . $timestamp);
        
        try {
            // Проверяем сессию
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            error_log("TemplateController::index: userId = " . ($userId ?? 'NULL'));
            
            if (!$userId) {
                error_log("TemplateController::index: No user ID, redirecting to login");
                header('Location: /login');
                exit;
            }
            
            // Инициализируем переменные
            $templates = [];
            
            try {
                error_log("TemplateController::index: Loading templates for user {$userId}");
                $templates = $this->templateService->getUserTemplates($userId);
                error_log("TemplateController::index: Loaded " . count($templates) . " templates");
                
                if (!isset($templates) || !is_array($templates)) {
                    $templates = [];
                }
            } catch (\Exception $e) {
                error_log("TemplateController::index: Error loading templates: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $templates = [];
            }
            
            // Проверяем существование файла представления
            $viewPath = __DIR__ . '/../../../../views/content_groups/templates/index.php';
            if (!file_exists($viewPath)) {
                throw new \Exception("View file not found: {$viewPath}");
            }
            
            // Включаем представление
            include $viewPath;
            
        } catch (\Exception $e) {
            error_log("TemplateController::index: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Очищаем буфер вывода, если он был начат
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Показываем HTML страницу с ошибкой
            $title = 'Ошибка';
            ob_start();
            ?>
            <div class="alert alert-error">
                <h2>Ошибка при загрузке шаблонов</h2>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <p><a href="/dashboard" class="btn btn-secondary">Вернуться на главную</a></p>
            </div>
            <?php
            $content = ob_get_clean();
            
            $layoutPath = __DIR__ . '/../../../../views/layout.php';
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
        } catch (\Throwable $e) {
            error_log("TemplateController::index: FATAL - " . $e->getMessage());
            http_response_code(500);
            echo "Internal Server Error. Please check server logs.";
        }
    }

    /**
     * Показать форму создания шаблона (теперь показывает Shorts форму)
     */
    public function showCreate(): void
    {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../../../views/content_groups/templates/create_v2.php';
    }

    /**
     * Показать улучшенную форму создания шаблона для Shorts
     */
    public function showCreateShorts(): void
    {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../../../views/content_groups/templates/create_v2.php';
    }

    /**
     * Создать шаблон
     */
    public function create(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/templates/create');
                exit;
            }

            // Проверяем, используется ли автогенерация
            $useAutoGeneration = $this->getParam('use_auto_generation', false);

            if ($useAutoGeneration) {
                // Используем автогенерацию
                $videoIdea = trim($this->getParam('video_idea', ''));

                if (empty($videoIdea)) {
                    $_SESSION['error'] = 'Необходимо указать базовую идею видео для автогенерации';
                    header('Location: /content-groups/templates/create');
                    exit;
                }

                // Генерируем контент
                $generatedResult = $this->autoGenerator->generateFromIdea($videoIdea);

                // Заполняем поля на основе сгенерированного контента
                $content = $generatedResult['content'];

                $titleVariants = $content['title_variants'] ?? [$content['title']];
                $descriptionVariants = $content['description_variants'] ?? [];
                $emojiGroups = $content['emoji_groups'] ?? [];
                $baseTags = $content['base_tags'] ?? '';
                $tagVariants = $content['tag_variants'] ?? [];
                $questions = $content['questions'] ?? [];
                $pinnedComments = $content['pinned_comments'] ?? [];
                $focusPoints = $content['focus_points'] ?? [];
                $hookType = $content['hook_type'] ?? 'vocal';

            } else {
                // Обычная обработка полей
                $titleVariants = $this->getParam('title_variants', []);
                $descriptionTypes = $this->getParam('description_types', []);
                $descriptionTexts = $this->getParam('description_texts', []);

                // Группируем описания по типам
                $descriptionVariants = [];
                if (!empty($descriptionTypes) && !empty($descriptionTexts)) {
                    foreach ($descriptionTypes as $index => $type) {
                        if (!empty($descriptionTexts[$index])) {
                            $descriptionVariants[$type][] = $descriptionTexts[$index];
                        }
                    }
                }

                // Остальные поля для обычного режима
                $emojiGroups = [
                    'emotional' => $this->getParam('emoji_emotional', ''),
                    'intrigue' => $this->getParam('emoji_intrigue', ''),
                    'atmosphere' => $this->getParam('emoji_atmosphere', ''),
                    'question' => $this->getParam('emoji_question', ''),
                    'cta' => $this->getParam('emoji_cta', ''),
                ];
                $baseTags = $this->getParam('base_tags', '');
                $tagVariants = $this->getParam('tag_variants', []);
                $questions = array_filter(explode("\n", $this->getParam('questions', '')));
                $pinnedComments = array_filter(explode("\n", $this->getParam('pinned_comments', '')));
                $focusPoints = $this->getParam('focus_points', []);
                $hookType = $this->getParam('hook_type', 'emotional');
            }

            // Очистка emoji групп от пустых значений
            $emojiGroups = array_filter($emojiGroups);

            // Обратная совместимость: старые поля (только для обычного режима)
            $emojiList = '';
            $emojiArray = [];
            $variants = [];

            if (!$useAutoGeneration) {
                $emojiList = $this->getParam('emoji_list', '');
                $emojiArray = !empty($emojiList) ? array_filter(array_map('trim', explode(',', $emojiList))) : [];

                if ($this->getParam('variant_1')) {
                    $variants['description'][] = $this->getParam('variant_1');
                }
                if ($this->getParam('variant_2')) {
                    $variants['description'][] = $this->getParam('variant_2');
                }
                if ($this->getParam('variant_3')) {
                    $variants['description'][] = $this->getParam('variant_3');
                }
            }

            $data = [
                'name' => $this->getParam('name', ''),
                'description' => $this->getParam('description', ''),
                // Старые поля для обратной совместимости
                'title_template' => $this->getParam('title_template', ''),
                'description_template' => $this->getParam('description_template', ''),
                'tags_template' => $this->getParam('tags_template', ''),
                'emoji_list' => $emojiArray,
                'variants' => !empty($variants) ? $variants : null,
                // Новые поля для Shorts
                'hook_type' => $this->getParam('hook_type', 'emotional'),
                'focus_points' => $this->getParam('focus_points', []),
                'title_variants' => !empty($titleVariants) ? array_filter($titleVariants) : null,
                'description_variants' => !empty($descriptionVariants) ? $descriptionVariants : null,
                'emoji_groups' => !empty($emojiGroups) ? $emojiGroups : null,
                'base_tags' => $this->getParam('base_tags', ''),
                'tag_variants' => $this->getParam('tag_variants', []),
                'questions' => $this->getParam('questions', []),
                'pinned_comments' => $this->getParam('pinned_comments', []),
                'cta_types' => $this->getParam('cta_types', []),
                'enable_ab_testing' => $this->getParam('enable_ab_testing', '1') === '1',
                'is_active' => $this->getParam('is_active', '1') === '1',
            ];

            $result = $this->templateService->createTemplate($userId, $data);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'] ?? 'Шаблон успешно создан';
                header('Location: /content-groups/templates');
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Ошибка при создании шаблона';
                header('Location: /content-groups/templates/create');
            }
        } catch (\Exception $e) {
            error_log('Error creating template: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при сохранении шаблона: ' . $e->getMessage();
            header('Location: /content-groups/templates/create');
        }
        exit;
    }

    /**
     * Превью шаблона
     */
    public function preview(int $id): void
    {
        $sampleData = [
            'title' => $this->getParam('sample_title', 'Пример видео'),
            'group_name' => $this->getParam('sample_group', 'Пример группы'),
            'index' => $this->getParam('sample_index', '1'),
            'platform' => $this->getParam('sample_platform', 'youtube'),
        ];

        $result = $this->templateService->previewTemplate($id, $sampleData);
        
        if ($result['success']) {
            $this->success($result['data'], 'Template preview');
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Показать форму редактирования шаблона
     */
    public function showEdit(int $id): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: /login');
            exit;
        }
        
        $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
        $template = $templateRepo->findById($id);

        if (!$template || $template['user_id'] !== $userId) {
            $_SESSION['error'] = 'Шаблон не найден';
            header('Location: /content-groups/templates');
            exit;
        }

        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../../../views/content_groups/templates/edit.php';
    }

    /**
     * Обновить шаблон
     */
    public function update(int $id): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/templates');
                exit;
            }
            
            $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
            $template = $templateRepo->findById($id);

            if (!$template || $template['user_id'] !== $userId) {
                $_SESSION['error'] = 'Шаблон не найден';
                header('Location: /content-groups/templates');
                exit;
            }
            
            // Обработка новых полей для Shorts (аналогично create)
            $titleVariants = $this->getParam('title_variants', []);
            $descriptionTypes = $this->getParam('description_types', []);
            $descriptionTexts = $this->getParam('description_texts', []);

            // Группируем описания по типам
            $descriptionVariants = [];
            if (!empty($descriptionTypes) && !empty($descriptionTexts)) {
                foreach ($descriptionTypes as $index => $type) {
                    if (!empty($descriptionTexts[$index])) {
                        $descriptionVariants[$type][] = $descriptionTexts[$index];
                    }
                }
            }

            // Emoji группы
            $emojiGroups = [
                'emotional' => $this->getParam('emoji_emotional', ''),
                'intrigue' => $this->getParam('emoji_intrigue', ''),
                'atmosphere' => $this->getParam('emoji_atmosphere', ''),
                'question' => $this->getParam('emoji_question', ''),
                'cta' => $this->getParam('emoji_cta', ''),
            ];

            // Убираем пустые группы
            $emojiGroups = array_filter($emojiGroups);

            // Обратная совместимость: старые поля
            $emojiList = $this->getParam('emoji_list', '');
            $emojiArray = !empty($emojiList) ? array_filter(array_map('trim', explode(',', $emojiList))) : [];

            $variants = [];
            if ($this->getParam('variant_1')) {
                $variants['description'][] = $this->getParam('variant_1');
            }
            if ($this->getParam('variant_2')) {
                $variants['description'][] = $this->getParam('variant_2');
            }
            if ($this->getParam('variant_3')) {
                $variants['description'][] = $this->getParam('variant_3');
            }

            $data = [
                'name' => $this->getParam('name', ''),
                'description' => $this->getParam('description', ''),
                // Старые поля для обратной совместимости
                'title_template' => $this->getParam('title_template', ''),
                'description_template' => $this->getParam('description_template', ''),
                'tags_template' => $this->getParam('tags_template', ''),
                'emoji_list' => $emojiArray,
                'variants' => !empty($variants) ? $variants : null,
                // Новые поля для Shorts
                'hook_type' => $this->getParam('hook_type', 'emotional'),
                'focus_points' => $this->getParam('focus_points', []),
                'title_variants' => !empty($titleVariants) ? array_filter($titleVariants) : null,
                'description_variants' => !empty($descriptionVariants) ? $descriptionVariants : null,
                'emoji_groups' => !empty($emojiGroups) ? $emojiGroups : null,
                'base_tags' => $this->getParam('base_tags', ''),
                'tag_variants' => $this->getParam('tag_variants', []),
                'questions' => $this->getParam('questions', []),
                'pinned_comments' => $this->getParam('pinned_comments', []),
                'cta_types' => $this->getParam('cta_types', []),
                'enable_ab_testing' => $this->getParam('enable_ab_testing', '1') === '1',
                'is_active' => $this->getParam('is_active', '1') === '1',
            ];

            $templateRepo->update($id, $data);

            $_SESSION['success'] = 'Шаблон успешно обновлен';
            header('Location: /content-groups/templates');
        } catch (\Exception $e) {
            error_log('Error updating template: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при обновлении шаблона: ' . $e->getMessage();
            header('Location: /content-groups/templates/' . $id . '/edit');
        }
        exit;
    }

    /**
     * Предложить контент для автозаполнения формы
     */
    public function suggestContent(): void
    {
        try {
            error_log('TemplateController::suggestContent: Starting content suggestion');

            $idea = trim($this->getParam('idea', ''));
            error_log('TemplateController::suggestContent: Idea received: "' . $idea . '"');

            if (empty($idea)) {
                error_log('TemplateController::suggestContent: Empty idea');
                $this->jsonResponse(['success' => false, 'message' => 'Не указана идея для генерации']);
                return;
            }

            if (strlen($idea) < 3) {
                error_log('TemplateController::suggestContent: Idea too short');
                $this->jsonResponse(['success' => false, 'message' => 'Идея должна содержать минимум 3 символа']);
                return;
            }

            // Генерируем контент
            error_log('TemplateController::suggestContent: Calling autoGenerator->generateFromIdea');
            $result = $this->autoGenerator->generateFromIdea($idea);
            error_log('TemplateController::suggestContent: Generation completed successfully');

            // Форматируем для автозаполнения формы
            $suggestion = [
                'success' => true,
                'idea' => $result['idea'],
                'intent' => $result['intent'],
                'content' => [
                    'title_template' => $result['content']['title'],
                    'description_template' => $result['content']['description'],
                    'tags_template' => implode(', ', $result['content']['tags']),
                    'emoji_list' => $result['content']['emoji'],

                    // Новые поля для Shorts
                    'hook_type' => $result['intent']['content_type'],
                    'title_variants' => [$result['content']['title']],
                    'description_variants' => [
                        $result['intent']['mood'] => [$result['content']['description']]
                    ],
                    'emoji_groups' => [
                        $result['intent']['mood'] => array_filter(explode(',', $result['content']['emoji']))
                    ],
                    'base_tags' => implode(', ', $result['content']['tags']),
                    'tag_variants' => [$result['content']['tags']],
                    'questions' => [$result['content']['pinned_comment']],
                    'pinned_comments' => [$result['content']['pinned_comment']],
                    'focus_points' => [$result['intent']['visual_focus']]
                ]
            ];

            error_log('TemplateController::suggestContent: Returning successful response');
            $this->jsonResponse($suggestion);

        } catch (Exception $e) {
            error_log('TemplateController::suggestContent: Exception caught: ' . $e->getMessage());
            error_log('TemplateController::suggestContent: Stack trace: ' . $e->getTraceAsString());
            $this->jsonResponse(['success' => false, 'message' => 'Ошибка генерации контента: ' . $e->getMessage()]);
        }
    }

    /**
     * Создать Shorts шаблон
     */
    public function createShorts(): void
    {
        $this->create(); // Используем существующую логику создания
    }

    /**
     * Удалить шаблон
     */
    public function delete(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
        $template = $templateRepo->findById($id);

        if (!$template || $template['user_id'] !== $userId) {
            $this->error('Template not found', 404);
            return;
        }

        $templateRepo->delete($id);
        $this->success([], 'Template deleted successfully');
    }
}
