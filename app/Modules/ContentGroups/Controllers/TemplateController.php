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
    private ?AutoShortsGenerator $autoGenerator = null;

    public function __construct()
    {
        parent::__construct();
        try {
            $this->templateService = new TemplateService();
        } catch (\Throwable $e) {
            error_log("TemplateController::__construct: Error initializing TemplateService: " . $e->getMessage());
            throw $e;
        }
        // AutoShortsGenerator инициализируем лениво, только когда нужно
    }
    
    private function getAutoGenerator(): AutoShortsGenerator
    {
        if ($this->autoGenerator === null) {
            try {
                $this->autoGenerator = new AutoShortsGenerator();
            } catch (\Throwable $e) {
                error_log("TemplateController::getAutoGenerator: Error initializing AutoShortsGenerator: " . $e->getMessage());
                throw $e;
            }
        }
        return $this->autoGenerator;
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
            error_log("TemplateController::index: View path: {$viewPath}");
            if (!file_exists($viewPath)) {
                error_log("TemplateController::index: View file not found: {$viewPath}");
                throw new \Exception("View file not found: {$viewPath}");
            }
            
            error_log("TemplateController::index: Including view file");
            // Включаем представление
            // Представление само управляет буферизацией и layout
            try {
                include $viewPath;
                error_log("TemplateController::index: View file included successfully");
            } catch (\Throwable $viewError) {
                error_log("TemplateController::index: Error in view: " . $viewError->getMessage() . " in " . $viewError->getFile() . ":" . $viewError->getLine());
                throw $viewError; // Пробрасываем дальше для обработки в catch блоке
            }
            
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
     * Отключенный старый формат: редирект на Shorts форму
     */
    public function showCreate(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['error'] = 'Создание шаблонов старого формата отключено. Используйте Shorts форму.';
        header('Location: /content-groups/templates/create-shorts');
        exit;
    }

    /**
     * Показать улучшенную форму создания шаблона для Shorts
     */
    public function showCreateShorts(): void
    {
        try {
            error_log("TemplateController::showCreateShorts: START");
            
            // Проверяем сессию
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            error_log("TemplateController::showCreateShorts: userId = " . ($userId ?? 'NULL'));
            
            if (!$userId) {
                error_log("TemplateController::showCreateShorts: No user ID, redirecting to login");
                header('Location: /login');
                exit;
            }
            
            error_log("TemplateController::showCreateShorts: Generating CSRF token");
            $csrfToken = (new \Core\Auth())->generateCsrfToken();
            error_log("TemplateController::showCreateShorts: CSRF token generated");
            
            $viewPath = __DIR__ . '/../../../../views/content_groups/templates/create_v2.php';
            error_log("TemplateController::showCreateShorts: View path: {$viewPath}");
            
            if (!file_exists($viewPath)) {
                error_log("TemplateController::showCreateShorts: View file not found: {$viewPath}");
                throw new \Exception("View file not found: {$viewPath}");
            }
            
            error_log("TemplateController::showCreateShorts: Including view file");
            include $viewPath;
            error_log("TemplateController::showCreateShorts: View file included successfully");
            
        } catch (\Exception $e) {
            error_log("TemplateController::showCreateShorts: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Очищаем буфер вывода, если он был начат
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Показываем HTML страницу с ошибкой
            $title = 'Ошибка';
            ob_start();
            ?>
            <div class="alert alert-error">
                <h2>Ошибка при загрузке формы создания шаблона</h2>
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
            error_log("TemplateController::showCreateShorts: FATAL - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo "Internal Server Error. Please check server logs.";
        }
    }

    /**
     * Создать шаблон
     */
    public function create(): void
    {
        try {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/content-groups/templates/create-shorts') === false) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['error'] = 'Создание шаблонов старого формата отключено. Используйте Shorts форму.';
                header('Location: /content-groups/templates/create-shorts');
                exit;
            }

            if (!$this->validateCsrf()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: /content-groups/templates/create-shorts');
                exit;
            }

            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/templates/create-shorts');
                exit;
            }

            // Проверяем, используется ли автогенерация
            $useAutoGeneration = $this->getParam('use_auto_generation', false);

            if ($useAutoGeneration) {
                // Используем автогенерацию
                $videoIdea = trim($this->getParam('video_idea', ''));

                if (empty($videoIdea)) {
                    $_SESSION['error'] = 'Необходимо указать базовую идею видео для автогенерации';
                    header('Location: /content-groups/templates/create-shorts');
                    exit;
                }

                // Генерируем множественные варианты контента (как в suggestContent)
                $variantCount = 25; // Генерируем 25 вариантов для богатого выбора
                error_log('TemplateController::create: Generating ' . $variantCount . ' variants for idea: "' . $videoIdea . '"');
                $variants = $this->getAutoGenerator()->generateMultipleVariants($videoIdea, $variantCount);
                error_log('TemplateController::create: Generated ' . count($variants) . ' variants');

                if (empty($variants)) {
                    $_SESSION['error'] = 'Не удалось сгенерировать варианты контента. Попробуйте другую идею.';
                    header('Location: /content-groups/templates/create-shorts');
                    exit;
                }

                // Берем первый вариант как базовый
                $firstVariant = $variants[0];

                // Собираем все уникальные варианты из всех генераций (как в suggestContent)
                $allTitles = [];
                $allDescriptions = [];
                $allTags = [];
                $allEmojis = [];
                $allPinnedComments = [];
                $descriptionVariants = [];
                $emojiGroups = [];
                
                // Маппинг настроения к ключам описаний для emoji групп
                $moodToDescriptionKey = [
                    'calm' => 'atmosphere',
                    'emotional' => 'emotional',
                    'romantic' => 'emotional',
                    'mysterious' => 'question'
                ];
                
                // Маппинг content_type к hook_type
                $contentTypeToHookType = [
                    'vocal' => 'emotional',
                    'music' => 'atmospheric',
                    'aesthetic' => 'visual',
                    'ambience' => 'atmospheric'
                ];

                foreach ($variants as $variant) {
                    $content = $variant['content'];
                    $intent = $variant['intent'];

                    // Собираем уникальные заголовки
                    if (!empty($content['title']) && !in_array($content['title'], $allTitles)) {
                        $allTitles[] = $content['title'];
                    }

                    // Собираем уникальные описания по настроению
                    if (!empty($content['description'])) {
                        $mood = $intent['mood'] ?? 'calm';
                        // Маппим mood к ключам description_variants (emotional, atmosphere, question)
                        $descKey = $moodToDescriptionKey[$mood] ?? 'atmosphere';
                        if (!isset($descriptionVariants[$descKey])) {
                            $descriptionVariants[$descKey] = [];
                        }
                        if (!in_array($content['description'], $descriptionVariants[$descKey])) {
                            $descriptionVariants[$descKey][] = $content['description'];
                        }
                    }

                    // Собираем уникальные теги
                    if (!empty($content['tags']) && is_array($content['tags'])) {
                        foreach ($content['tags'] as $tag) {
                            if (!in_array($tag, $allTags)) {
                                $allTags[] = $tag;
                            }
                        }
                    }

                    // Собираем emoji группы
                    if (!empty($content['emoji'])) {
                        $mood = $intent['mood'] ?? 'calm';
                        $emojiKey = $moodToDescriptionKey[$mood] ?? 'atmosphere';
                        if (!isset($emojiGroups[$emojiKey])) {
                            $emojiGroups[$emojiKey] = [];
                        }
                        // emoji может быть строкой (символы подряд) или массивом
                        if (is_array($content['emoji'])) {
                            $emojiList = $content['emoji'];
                        } else {
                            // Разбиваем строку на отдельные emoji символы (могут быть без разделителей)
                            $emojiString = trim($content['emoji']);
                            // Сначала пробуем разбить по запятым (если есть)
                            if (strpos($emojiString, ',') !== false) {
                                $emojiList = array_filter(array_map('trim', explode(',', $emojiString)));
                            } else {
                                // Если запятых нет, разбиваем по символам (каждый emoji - отдельный символ)
                                // Используем preg_split для правильной обработки UTF-8 emoji
                                $emojiList = preg_split('//u', $emojiString, -1, PREG_SPLIT_NO_EMPTY);
                                $emojiList = array_filter($emojiList, function($char) {
                                    // Фильтруем только emoji и пробелы (удаляем пробелы)
                                    return trim($char) !== '';
                                });
                            }
                        }
                        foreach ($emojiList as $emoji) {
                            $emoji = trim($emoji);
                            if (!empty($emoji) && !in_array($emoji, $emojiGroups[$emojiKey])) {
                                $emojiGroups[$emojiKey][] = $emoji;
                            }
                        }
                    }

                    // Собираем уникальные закрепленные комментарии
                    if (!empty($content['pinned_comment']) && !in_array($content['pinned_comment'], $allPinnedComments)) {
                        $allPinnedComments[] = $content['pinned_comment'];
                    }
                }
                
                // Определяем hook_type из первого варианта
                $firstContentType = $firstVariant['intent']['content_type'] ?? 'vocal';
                $hookType = $contentTypeToHookType[$firstContentType] ?? 'emotional';

                // Форматируем данные для сохранения
                $titleVariants = array_slice($allTitles, 0, 20); // Ограничиваем до 20 вариантов
                $baseTags = implode(', ', array_slice($allTags, 0, 10)); // Основные теги
                $tagVariants = [array_slice($allTags, 0, 15)]; // Варианты наборов тегов
                $questions = array_slice($allPinnedComments, 0, 10);
                $pinnedComments = array_slice($allPinnedComments, 0, 10);
                $focusPoints = [$firstVariant['intent']['visual_focus'] ?? 'neon'];
                
                // Преобразуем emojiGroups в строки (как ожидает форма)
                foreach ($emojiGroups as $key => $emojiArray) {
                    $emojiGroups[$key] = implode(',', $emojiArray);
                }
                
                error_log('TemplateController::create: Auto-generated data - titles: ' . count($titleVariants) . ', descriptions: ' . count($descriptionVariants) . ', tags: ' . count($allTags));

            } else {
                // Обычная обработка полей
                // Читаем title_variants из POST (может быть массивом из title_variants[])
                $titleVariantsRaw = $this->getParam('title_variants', []);
                $titleVariants = is_array($titleVariantsRaw) ? $titleVariantsRaw : [];
                // Удаляем пустые значения, но сохраняем непустые
                $titleVariants = array_filter(array_map('trim', $titleVariants), function($v) { return !empty($v); });
                
                $descriptionTypes = $this->getParam('description_types', []);
                $descriptionTexts = $this->getParam('description_texts', []);
                
                // Логируем для отладки
                error_log("TemplateController::create - title_variants count: " . count($titleVariants));
                error_log("TemplateController::create - description_types count: " . (is_array($descriptionTypes) ? count($descriptionTypes) : 0));
                error_log("TemplateController::create - description_texts count: " . (is_array($descriptionTexts) ? count($descriptionTexts) : 0));

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
                $questionsRaw = $this->getParam('questions', []);
                if (is_string($questionsRaw)) {
                    $questions = array_filter(array_map('trim', explode("\n", $questionsRaw)));
                } elseif (is_array($questionsRaw)) {
                    $questions = array_filter(array_map('trim', $questionsRaw));
                } else {
                    $questions = [];
                }

                $pinnedRaw = $this->getParam('pinned_comments', []);
                if (is_string($pinnedRaw)) {
                    $pinnedComments = array_filter(array_map('trim', explode("\n", $pinnedRaw)));
                } elseif (is_array($pinnedRaw)) {
                    $pinnedComments = array_filter(array_map('trim', $pinnedRaw));
                } else {
                    $pinnedComments = [];
                }
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

            // Логируем перед сохранением
            error_log("TemplateController::create - Final data before save:");
            error_log("  title_variants: " . (is_array($titleVariants) ? count($titleVariants) . " items" : "not array"));
            error_log("  description_variants: " . (is_array($descriptionVariants) ? count($descriptionVariants) . " types" : "not array"));
            error_log("  base_tags: " . ($baseTags ?: "empty"));
            error_log("  tag_variants: " . (is_array($tagVariants) ? count($tagVariants) . " items" : "not array"));
            
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
                'title_variants' => !empty($titleVariants) ? array_values($titleVariants) : null, // array_values для переиндексации
                'description_variants' => !empty($descriptionVariants) ? $descriptionVariants : null,
                'emoji_groups' => !empty($emojiGroups) ? $emojiGroups : null,
                'base_tags' => $this->getParam('base_tags', ''),
                'tag_variants' => is_array($tagVariants) ? array_filter(array_map('trim', $tagVariants), function($v) { return !empty($v); }) : [],
                'questions' => $questions,
                'pinned_comments' => $pinnedComments,
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
                header('Location: /content-groups/templates/create-shorts');
            }
        } catch (\Exception $e) {
            error_log('Error creating template: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при сохранении шаблона.';
            header('Location: /content-groups/templates/create-shorts');
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
        $isShortsTemplate = !empty($template['hook_type'])
            || !empty($template['title_variants'])
            || !empty($template['description_variants'])
            || !empty($template['emoji_groups']);

        if ($isShortsTemplate) {
            include __DIR__ . '/../../../../views/content_groups/templates/create_v2.php';
        } else {
            include __DIR__ . '/../../../../views/content_groups/templates/edit.php';
        }
    }

    /**
     * Обновить шаблон
     */
    public function update(int $id): void
    {
        try {
            if (!$this->validateCsrf()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: /content-groups/templates/' . $id . '/edit');
                exit;
            }

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
            // Читаем title_variants из POST (может быть массивом из title_variants[])
            $titleVariantsRaw = $this->getParam('title_variants', []);
            $titleVariants = is_array($titleVariantsRaw) ? $titleVariantsRaw : [];
            // Удаляем пустые значения, но сохраняем непустые
            $titleVariants = array_filter(array_map('trim', $titleVariants), function($v) { return !empty($v); });
            
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

            $questionsRaw = $this->getParam('questions', []);
            if (is_string($questionsRaw)) {
                $questions = array_filter(array_map('trim', explode("\n", $questionsRaw)));
            } elseif (is_array($questionsRaw)) {
                $questions = array_filter(array_map('trim', $questionsRaw));
            } else {
                $questions = [];
            }

            $pinnedRaw = $this->getParam('pinned_comments', []);
            if (is_string($pinnedRaw)) {
                $pinnedComments = array_filter(array_map('trim', explode("\n", $pinnedRaw)));
            } elseif (is_array($pinnedRaw)) {
                $pinnedComments = array_filter(array_map('trim', $pinnedRaw));
            } else {
                $pinnedComments = [];
            }

            $baseTags = $this->getParam('base_tags', '');
            $tagVariantsRaw = $this->getParam('tag_variants', []);
            $tagVariants = is_array($tagVariantsRaw) ? array_filter(array_map('trim', $tagVariantsRaw), function($v) { return !empty($v); }) : [];
            
            // Логируем перед сохранением
            error_log("TemplateController::update - Final data before save:");
            error_log("  title_variants: " . (is_array($titleVariants) ? count($titleVariants) . " items" : "not array"));
            error_log("  description_variants: " . (is_array($descriptionVariants) ? count($descriptionVariants) . " types" : "not array"));
            error_log("  base_tags: " . ($baseTags ?: "empty"));
            error_log("  tag_variants: " . (is_array($tagVariants) ? count($tagVariants) . " items" : "not array"));
            
            // Обрабатываем данные так же, как в createTemplate - кодируем массивы в JSON
            $data = [
                'name' => trim($this->getParam('name', '')),
                'description' => !empty($this->getParam('description', '')) ? trim($this->getParam('description', '')) : null,
                // Старые поля для обратной совместимости
                'title_template' => !empty($this->getParam('title_template', '')) ? trim($this->getParam('title_template', '')) : null,
                'description_template' => !empty($this->getParam('description_template', '')) ? trim($this->getParam('description_template', '')) : null,
                'tags_template' => !empty($this->getParam('tags_template', '')) ? trim($this->getParam('tags_template', '')) : null,
                'emoji_list' => !empty($emojiArray) && is_array($emojiArray) ? json_encode($emojiArray, JSON_UNESCAPED_UNICODE) : null,
                'variants' => !empty($variants) && is_array($variants) ? json_encode($variants, JSON_UNESCAPED_UNICODE) : null,
                // Новые поля для Shorts - кодируем массивы в JSON
                'hook_type' => $this->getParam('hook_type', 'emotional'),
                'focus_points' => !empty($this->getParam('focus_points', [])) && is_array($this->getParam('focus_points', [])) ? json_encode($this->getParam('focus_points', []), JSON_UNESCAPED_UNICODE) : null,
                'title_variants' => !empty($titleVariants) && is_array($titleVariants) ? json_encode(array_values($titleVariants), JSON_UNESCAPED_UNICODE) : null,
                'description_variants' => !empty($descriptionVariants) && is_array($descriptionVariants) ? json_encode($descriptionVariants, JSON_UNESCAPED_UNICODE) : null,
                'emoji_groups' => !empty($emojiGroups) && is_array($emojiGroups) ? json_encode($emojiGroups, JSON_UNESCAPED_UNICODE) : null,
                'base_tags' => !empty($baseTags) ? trim($baseTags) : null,
                'tag_variants' => !empty($tagVariants) && is_array($tagVariants) ? json_encode($tagVariants, JSON_UNESCAPED_UNICODE) : null,
                'questions' => !empty($questions) && is_array($questions) ? json_encode($questions, JSON_UNESCAPED_UNICODE) : null,
                'pinned_comments' => !empty($pinnedComments) && is_array($pinnedComments) ? json_encode($pinnedComments, JSON_UNESCAPED_UNICODE) : null,
                'cta_types' => !empty($this->getParam('cta_types', [])) && is_array($this->getParam('cta_types', [])) ? json_encode($this->getParam('cta_types', []), JSON_UNESCAPED_UNICODE) : null,
                'enable_ab_testing' => (int)($this->getParam('enable_ab_testing', '1') === '1'),
                'is_active' => (int)($this->getParam('is_active', '1') === '1'),
            ];

            $templateRepo->update($id, $data);

            $_SESSION['success'] = 'Шаблон успешно обновлен';
            header('Location: /content-groups/templates');
        } catch (\Exception $e) {
            error_log('Error updating template: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при обновлении шаблона.';
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
            if (!$this->validateCsrf()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit;
            }

            error_log('TemplateController::suggestContent: Starting content suggestion');

            // Инициализируем сессию, если не инициализирована
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $idea = trim($this->getParam('idea', ''));
            error_log('TemplateController::suggestContent: Idea received: "' . $idea . '"');

            if (empty($idea)) {
                error_log('TemplateController::suggestContent: Empty idea');
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Не указана идея для генерации']);
                exit;
            }

            if (strlen($idea) < 3) {
                error_log('TemplateController::suggestContent: Idea too short');
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Идея должна содержать минимум 3 символа']);
                exit;
            }

            // Проверяем, что autoGenerator инициализирован
            // Генерируем контент (20-30 вариантов)
            $variantCount = 25; // Генерируем 25 вариантов для богатого выбора
            error_log('TemplateController::suggestContent: Calling autoGenerator->generateMultipleVariants with ' . $variantCount . ' variants');
            $variants = $this->getAutoGenerator()->generateMultipleVariants($idea, $variantCount);
            error_log('TemplateController::suggestContent: Generation completed successfully, got ' . count($variants) . ' variants');

            if (empty($variants)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Не удалось сгенерировать варианты контента']);
                exit;
            }

            // Берем первый вариант как базовый для обратной совместимости
            $firstVariant = $variants[0];

            // Собираем все уникальные варианты из всех генераций
            $allTitles = [];
            $allDescriptions = [];
            $allTags = [];
            $allEmojis = [];
            $allPinnedComments = [];
            $descriptionVariants = [];
            $emojiGroups = [];
            
            // Маппинг настроения к ключам описаний для emoji групп
            $moodToDescriptionKey = [
                'calm' => 'atmosphere',
                'emotional' => 'emotional',
                'romantic' => 'emotional',
                'mysterious' => 'question'
            ];
            
            // Маппинг content_type к hook_type
            $contentTypeToHookType = [
                'vocal' => 'emotional',
                'music' => 'atmospheric',
                'aesthetic' => 'visual',
                'ambience' => 'atmospheric',
                'dance' => 'visual',
                'comedy' => 'emotional',
                'cooking' => 'educational',
                'fitness' => 'motivation',
                'beauty' => 'visual',
                'gaming' => 'emotional',
                'pets' => 'emotional',
                'travel' => 'atmospheric',
                'diy' => 'educational',
                'lifehack' => 'educational',
                'motivation' => 'emotional',
                'asmr' => 'atmospheric',
                'prank' => 'emotional',
                'challenge' => 'emotional',
                'transformation' => 'visual',
                'reaction' => 'emotional',
                'tutorial' => 'educational',
                'vlog' => 'atmospheric',
                'fashion' => 'visual',
                'tech' => 'educational'
            ];

            foreach ($variants as $variant) {
                $content = $variant['content'];
                $intent = $variant['intent'];

                // Собираем уникальные заголовки
                if (!empty($content['title']) && !in_array($content['title'], $allTitles)) {
                    $allTitles[] = $content['title'];
                }

                // Собираем уникальные описания
                if (!empty($content['description'])) {
                    $mood = $intent['mood'] ?? 'calm';
                    if (!isset($descriptionVariants[$mood])) {
                        $descriptionVariants[$mood] = [];
                    }
                    if (!in_array($content['description'], $descriptionVariants[$mood])) {
                        $descriptionVariants[$mood][] = $content['description'];
                    }
                }

                // Собираем уникальные теги
                if (!empty($content['tags']) && is_array($content['tags'])) {
                    foreach ($content['tags'] as $tag) {
                        if (!in_array($tag, $allTags)) {
                            $allTags[] = $tag;
                        }
                    }
                }

                // Собираем emoji группы
                if (!empty($content['emoji'])) {
                    $mood = $intent['mood'] ?? 'calm';
                    $emojiKey = $moodToDescriptionKey[$mood] ?? 'atmosphere';
                    if (!isset($emojiGroups[$emojiKey])) {
                        $emojiGroups[$emojiKey] = [];
                    }
                    $emojiList = array_filter(explode(',', $content['emoji']));
                    foreach ($emojiList as $emoji) {
                        if (!in_array($emoji, $emojiGroups[$emojiKey])) {
                            $emojiGroups[$emojiKey][] = $emoji;
                        }
                    }
                }

                // Собираем уникальные закрепленные комментарии
                if (!empty($content['pinned_comment']) && !in_array($content['pinned_comment'], $allPinnedComments)) {
                    $allPinnedComments[] = $content['pinned_comment'];
                }
            }
            
            // Определяем hook_type из первого варианта
            $firstContentType = $firstVariant['intent']['content_type'] ?? 'vocal';
            $hookType = $contentTypeToHookType[$firstContentType] ?? 'emotional';

            // Форматируем для автозаполнения формы
            $suggestion = [
                'success' => true,
                'idea' => $firstVariant['idea'],
                'intent' => $firstVariant['intent'],
                'variants_count' => count($variants),
                'content' => [
                    // Основные поля (для обратной совместимости)
                    'title_template' => $firstVariant['content']['title'] ?? '',
                    'description_template' => $firstVariant['content']['description'] ?? '',
                    'tags_template' => implode(', ', $firstVariant['content']['tags'] ?? []),
                    'emoji_list' => $firstVariant['content']['emoji'] ?? '',

                    // Новые поля для Shorts с множественными вариантами
                    'hook_type' => $hookType, // Используем маппинг content_type -> hook_type
                    'title_variants' => array_slice($allTitles, 0, 20), // Ограничиваем до 20 вариантов
                    'description_variants' => $descriptionVariants,
                    'emoji_groups' => $emojiGroups,
                    'base_tags' => implode(', ', array_slice($allTags, 0, 10)), // Основные теги
                    'tag_variants' => [array_slice($allTags, 0, 15)], // Варианты наборов тегов
                    'questions' => array_slice($allPinnedComments, 0, 10),
                    'pinned_comments' => array_slice($allPinnedComments, 0, 10),
                    'focus_points' => [$firstVariant['intent']['visual_focus'] ?? 'neon'],

                    // Дополнительная информация
                    'generated_variants' => count($variants),
                    'unique_titles' => count($allTitles),
                    'unique_descriptions' => count($descriptionVariants),
                    'unique_tags' => count($allTags)
                ]
            ];

            error_log('TemplateController::suggestContent: Returning successful response');
            header('Content-Type: application/json');
            echo json_encode($suggestion);
            exit;

        } catch (Exception $e) {
            error_log('TemplateController::suggestContent: Exception caught: ' . $e->getMessage());
            error_log('TemplateController::suggestContent: Stack trace: ' . $e->getTraceAsString());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ошибка генерации контента.']);
            exit;
        }
    }

    /**
     * Фильтрация русских слов из текста (для английских результатов)
     */
    private function filterRussianWordsFromText(string $text): string
    {
        // Разбиваем текст на слова
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $filteredWords = [];
        
        foreach ($words as $word) {
            // Проверяем, содержит ли слово кириллицу
            if (!preg_match('/[а-яё]/iu', $word)) {
                $filteredWords[] = $word;
            } else {
                error_log("TemplateController::filterRussianWordsFromText: Removed Russian word: '{$word}'");
            }
        }
        
        // Собираем обратно, сохраняя пробелы и знаки препинания
        $result = implode(' ', $filteredWords);
        
        // Очищаем множественные пробелы
        $result = preg_replace('/\s+/u', ' ', $result);
        $result = trim($result);
        
        return $result;
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
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

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
