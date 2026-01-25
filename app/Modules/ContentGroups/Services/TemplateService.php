<?php

namespace App\Modules\ContentGroups\Services;

use Core\Service;
use App\Modules\ContentGroups\Repositories\PublicationTemplateRepository;

/**
 * Сервис для работы с шаблонами публикаций
 */
class TemplateService extends Service
{
    private PublicationTemplateRepository $templateRepo;

    public function __construct()
    {
        parent::__construct();
        $this->templateRepo = new PublicationTemplateRepository();
    }

    /**
     * Создать шаблон
     */
    public function createTemplate(int $userId, array $data): array
    {
        try {
            // Валидация обязательных полей
            if (empty($data['name'])) {
                return [
                    'success' => false,
                    'message' => 'Название шаблона обязательно'
                ];
            }

            $templateId = $this->templateRepo->create([
                'user_id' => $userId,
                'name' => trim($data['name'] ?? ''),
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                // Старые поля для обратной совместимости
                'title_template' => !empty($data['title_template']) ? trim($data['title_template']) : null,
                'description_template' => !empty($data['description_template']) ? trim($data['description_template']) : null,
                'tags_template' => !empty($data['tags_template']) ? trim($data['tags_template']) : null,
                'emoji_list' => !empty($data['emoji_list']) && is_array($data['emoji_list']) ? json_encode($data['emoji_list'], JSON_UNESCAPED_UNICODE) : null,
                'variants' => !empty($data['variants']) && is_array($data['variants']) ? json_encode($data['variants'], JSON_UNESCAPED_UNICODE) : null,
                // Новые поля для Shorts
                'hook_type' => $data['hook_type'] ?? 'emotional',
                'focus_points' => !empty($data['focus_points']) && is_array($data['focus_points']) ? json_encode($data['focus_points'], JSON_UNESCAPED_UNICODE) : null,
                'title_variants' => !empty($data['title_variants']) && is_array($data['title_variants']) ? json_encode($data['title_variants'], JSON_UNESCAPED_UNICODE) : null,
                'description_variants' => !empty($data['description_variants']) && is_array($data['description_variants']) ? json_encode($data['description_variants'], JSON_UNESCAPED_UNICODE) : null,
                'emoji_groups' => !empty($data['emoji_groups']) && is_array($data['emoji_groups']) ? json_encode($data['emoji_groups'], JSON_UNESCAPED_UNICODE) : null,
                'base_tags' => !empty($data['base_tags']) ? trim($data['base_tags']) : null,
                'tag_variants' => !empty($data['tag_variants']) && is_array($data['tag_variants']) ? json_encode($data['tag_variants'], JSON_UNESCAPED_UNICODE) : null,
                'questions' => !empty($data['questions']) && is_array($data['questions']) ? json_encode($data['questions'], JSON_UNESCAPED_UNICODE) : null,
                'pinned_comments' => !empty($data['pinned_comments']) && is_array($data['pinned_comments']) ? json_encode($data['pinned_comments'], JSON_UNESCAPED_UNICODE) : null,
                'cta_types' => !empty($data['cta_types']) && is_array($data['cta_types']) ? json_encode($data['cta_types'], JSON_UNESCAPED_UNICODE) : null,
                'enable_ab_testing' => isset($data['enable_ab_testing']) ? (int)(bool)$data['enable_ab_testing'] : 1,
                'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            ]);

            if (!$templateId) {
                return [
                    'success' => false,
                    'message' => 'Не удалось создать шаблон. Попробуйте снова.'
                ];
            }

            return [
                'success' => true,
                'data' => ['id' => $templateId],
                'message' => 'Шаблон успешно создан'
            ];
        } catch (\Exception $e) {
            error_log('Error in createTemplate: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка при создании шаблона: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Применить шаблон к видео (улучшенная версия для YouTube Shorts)
     */
    public function applyTemplate(?int $templateId, array $video, array $context = []): array
    {
        if (!$templateId) {
            return [
                'title' => $video['title'] ?? '',
                'description' => $video['description'] ?: 'Посмотрите это видео! 🎬',
                'tags' => $video['tags'] ?? '',
                'question' => '',
                'pinned_comment' => '',
                'hook_type' => 'emotional',
            ];
        }

        $template = $this->templateRepo->findById($templateId);
        if (!$template) {
            return [
                'title' => $video['title'] ?? '',
                'description' => $video['description'] ?: 'Посмотрите это видео! 🎬',
                'tags' => $video['tags'] ?? '',
                'question' => '',
                'pinned_comment' => '',
                'hook_type' => 'emotional',
            ];
        }

        // Подготовка контекста для переменных
        // Добавляем случайное число для дополнительной рандомизации при перегенерации
        $vars = array_merge([
            'title' => $video['title'] ?? '',
            'group_name' => $context['group_name'] ?? '',
            'index' => $context['index'] ?? '',
            'date' => date('d.m.Y'),
            'platform' => $context['platform'] ?? 'youtube',
            'random' => mt_rand(1, 1000), // Для дополнительной рандомизации в шаблонах
        ], $context);

        $result = [
            'title' => '',
            'description' => '',
            'tags' => '',
            'question' => '',
            'pinned_comment' => '',
            'hook_type' => $template['hook_type'] ?? 'emotional',
        ];

        // НОВЫЙ ПОДХОД: Работа с массивами вариантов для Shorts
        // Инициализируем генератор случайных чисел для лучшей рандомизации при перегенерации
        // Используем микросекунды для гарантированной уникальности seed
        mt_srand((int)(microtime(true) * 1000000) % PHP_INT_MAX);

        // 1. ГЕНЕРАЦИЯ НАЗВАНИЯ (A/B тестирование)
        $titleVariants = !empty($template['title_variants']) ? json_decode($template['title_variants'], true) : [];
        $hasTitleVariants = !empty($titleVariants);
        $hasTitleTemplate = !empty($template['title_template']);

        if ($hasTitleVariants && ($template['enable_ab_testing'] ?? true)) {
            // A/B тестирование: случайный выбор с учётом уникальности начал
            $usedTitles = $context['used_titles'] ?? []; // Массив уже использованных начал
            $availableVariants = $this->filterUniqueStartTitles($titleVariants, $usedTitles);

            if (!empty($availableVariants)) {
                // Перемешиваем для гарантированной рандомизации
                shuffle($availableVariants);
                $result['title'] = $availableVariants[mt_rand(0, count($availableVariants) - 1)];
            } else {
                // Если все начала использованы, перемешиваем и выбираем случайный из всех вариантов
                shuffle($titleVariants);
                $result['title'] = $titleVariants[mt_rand(0, count($titleVariants) - 1)];
            }
        } elseif ($hasTitleVariants) {
            // Без A/B тестирования: полная рандомизация для перегенерации
            shuffle($titleVariants);
            $result['title'] = $titleVariants[mt_rand(0, count($titleVariants) - 1)];
        } else {
            // Обратная совместимость: старый подход
            $emojiList = !empty($template['emoji_list']) ? json_decode($template['emoji_list'], true) : ['🎬'];

            // Гарантируем, что emojiList является массивом
            if (!is_array($emojiList) || empty($emojiList)) {
                $emojiList = ['🎬'];
            }

            // Полная рандомизация emoji для старого подхода
            shuffle($emojiList);
            $vars['random_emoji'] = $emojiList[array_rand($emojiList)];
            $result['title'] = $this->processTemplate($template['title_template'] ?? '', $vars, $video['title'] ?? '');
        }

        if (!$hasTitleVariants && !$hasTitleTemplate) {
            $fallbackName = trim((string)($template['name'] ?? ''));
            if ($fallbackName !== '') {
                $fallbackName = preg_replace('/^Auto:\s*/i', '', $fallbackName);
                if ($fallbackName !== '') {
                    $result['title'] = $fallbackName;
                }
            }
        }

        // 2. ГЕНЕРАЦИЯ ОПИСАНИЯ (по типам триггеров)
        $descriptionVariants = !empty($template['description_variants']) ? json_decode($template['description_variants'], true) : [];
        $hookType = $template['hook_type'] ?? 'emotional';
        $descriptionGenerated = false;

        if (!empty($descriptionVariants) && isset($descriptionVariants[$hookType])) {
            // Новый подход: варианты по типам триггеров
            $hookVariants = $descriptionVariants[$hookType];

            // Гарантируем, что hookVariants является непустым массивом
            if (!is_array($hookVariants) || empty($hookVariants)) {
                $hookVariants = ['Посмотрите это видео!'];
            }

            // Перемешиваем для гарантированной рандомизации при перегенерации
            shuffle($hookVariants);
            $selectedVariant = $hookVariants[mt_rand(0, count($hookVariants) - 1)];

            // Добавляем emoji из соответствующей группы с полной рандомизацией
            $emojiGroups = !empty($template['emoji_groups']) ? json_decode($template['emoji_groups'], true) : [];
            if (isset($emojiGroups[$hookType])) {
                $emojiList = array_filter(array_map('trim', explode(',', $emojiGroups[$hookType])));
                if (!empty($emojiList)) {
                    // Полная рандомизация emoji
                    shuffle($emojiList);
                    // Выбираем случайное количество emoji (1-2)
                    $emojiCount = min(mt_rand(1, 2), count($emojiList));
                    $selectedEmojis = array_slice($emojiList, 0, $emojiCount);
                    if (!empty($selectedEmojis)) {
                        $selectedVariant .= ' ' . implode(' ', $selectedEmojis);
                    }
                }
            }

            $result['description'] = $this->processTemplate($selectedVariant, $vars, $video['description'] ?? '');
            $descriptionGenerated = !empty($result['description']);
            error_log("TemplateService::applyTemplate: Generated description from variants (hookType: {$hookType}), length: " . mb_strlen($result['description']));
        } else {
            // Обратная совместимость: старый подход
            $emojiList = !empty($template['emoji_list']) ? json_decode($template['emoji_list'], true) : ['🎬'];

            // Гарантируем, что emojiList является массивом
            if (!is_array($emojiList) || empty($emojiList)) {
                $emojiList = ['🎬'];
            }

            // Полная рандомизация emoji для старого подхода
            shuffle($emojiList);
            $vars['random_emoji'] = $emojiList[mt_rand(0, count($emojiList) - 1)];
            $descriptionTemplate = $template['description_template'] ?? '';
            $result['description'] = $this->processTemplate($descriptionTemplate, $vars, $video['description'] ?? '');
            $descriptionGenerated = !empty($result['description']);
            error_log("TemplateService::applyTemplate: Generated description from template, template length: " . mb_strlen($descriptionTemplate) . ", result length: " . mb_strlen($result['description']));
        }

        // Fallback: если описание не сгенерировано, используем исходное или дефолтное
        if (empty(trim($result['description']))) {
            $originalDescription = trim($video['description'] ?? '');
            $result['description'] = !empty($originalDescription) ? $originalDescription : 'Посмотрите это видео! 🎬';
            error_log("TemplateService::applyTemplate: Using fallback description (original was empty: " . (empty($originalDescription) ? 'yes' : 'no') . "), length: " . mb_strlen($result['description']));
        }

        // 3. ГЕНЕРАЦИЯ ТЕГОВ (ротация с рандомизацией)
        $baseTags = !empty($template['base_tags']) ? array_map('trim', explode(',', $template['base_tags'])) : [];
        $tagVariants = !empty($template['tag_variants']) ? json_decode($template['tag_variants'], true) : [];

        $finalTags = $baseTags; // Начинаем с основных тегов

        if (!empty($tagVariants)) {
            // Ротация: выбираем дополнительные теги из вариантов с полной рандомизацией
            // Перемешиваем массив вариантов для случайного порядка
            $shuffledVariants = $tagVariants;
            shuffle($shuffledVariants);
            
            $additionalTags = [];
            foreach ($shuffledVariants as $tagSet) {
                $tags = array_map('trim', explode(',', $tagSet));
                // Перемешиваем теги внутри каждого набора
                shuffle($tags);
                $additionalTags = array_merge($additionalTags, $tags);
                if (count($additionalTags) >= 10) break; // Собираем больше тегов для лучшей рандомизации
            }

            // Перемешиваем все собранные дополнительные теги
            shuffle($additionalTags);
            
            // Выбираем случайное количество дополнительных тегов (от 2 до 5)
            $maxAdditional = max(2, min(5, 10 - count($baseTags)));
            $countAdditional = count($baseTags) > 0 ? min($maxAdditional, count($additionalTags)) : min(5, count($additionalTags));
            $selectedAdditional = array_slice($additionalTags, 0, $countAdditional);
            $finalTags = array_merge($finalTags, $selectedAdditional);
        }

        // Перемешиваем финальный список тегов для случайного порядка
        shuffle($finalTags);
        
        // Очищаем и форматируем теги
        $finalTags = array_unique(array_filter($finalTags));
        $result['tags'] = implode(', ', $finalTags);

        // 4. ВОПРОСЫ ДЛЯ ВОВЛЕЧЁННОСТИ
        $questions = !empty($template['questions']) ? json_decode($template['questions'], true) : [];
        if (!empty($questions)) {
            $result['question'] = $questions[array_rand($questions)];
        }

        // 5. ЗАКРЕПЛЁННЫЙ КОММЕНТАРИЙ
        $pinnedComments = !empty($template['pinned_comments']) ? json_decode($template['pinned_comments'], true) : [];
        if (!empty($pinnedComments)) {
            $result['pinned_comment'] = $pinnedComments[array_rand($pinnedComments)];
        }

        // Финальная проверка: описание всегда должно быть заполнено
        if (empty(trim($result['description']))) {
            $result['description'] = 'Посмотрите это видео! 🎬';
            error_log("TemplateService::applyTemplate: Final fallback applied - description was empty");
        }

        return $result;
    }

    /**
     * Фильтровать названия с уникальными началами (для A/B тестирования)
     */
    private function filterUniqueStartTitles(array $titles, array $usedStarts): array
    {
        $available = [];

        foreach ($titles as $title) {
            $start = $this->getTitleStart($title);
            if (!in_array($start, $usedStarts)) {
                $available[] = $title;
            }
        }

        return $available;
    }

    /**
     * Получить начало названия (первое слово)
     */
    private function getTitleStart(string $title): string
    {
        $words = explode(' ', trim($title));
        return strtolower($words[0] ?? '');
    }

    /**
     * Обработать шаблон с переменными
     */
    private function processTemplate(string $template, array $vars, string $default = ''): string
    {
        if (empty($template)) {
            return $default;
        }

        // Замена переменных {var}
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        // Очистка неиспользованных переменных
        $template = preg_replace('/\{[^}]+\}/', '', $template);

        return trim($template);
    }

    /**
     * Получить шаблоны пользователя
     */
    public function getUserTemplates(int $userId, bool $activeOnly = false): array
    {
        try {
            error_log("TemplateService::getUserTemplates: userId={$userId}, activeOnly=" . ($activeOnly ? 'true' : 'false'));
            
            $templates = $this->templateRepo->findByUserId($userId, $activeOnly);
            
            if (!is_array($templates)) {
                error_log("TemplateService::getUserTemplates: Repository returned non-array, returning empty array");
                return [];
            }
            
            error_log("TemplateService::getUserTemplates: Found " . count($templates) . " templates");
            return $templates;
        } catch (\Exception $e) {
            error_log("TemplateService::getUserTemplates: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [];
        }
    }

    /**
     * Превью шаблона
     */
    public function previewTemplate(int $templateId, array $sampleData): array
    {
        $template = $this->templateRepo->findById($templateId);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found'];
        }

        $context = [
            'title' => $sampleData['title'] ?? 'Пример видео',
            'group_name' => $sampleData['group_name'] ?? 'Пример группы',
            'index' => $sampleData['index'] ?? '1',
            'date' => date('d.m.Y'),
            'platform' => $sampleData['platform'] ?? 'youtube',
        ];

        $result = $this->applyTemplate($templateId, $sampleData, $context);

        return [
            'success' => true,
            'data' => $result
        ];
    }
}
