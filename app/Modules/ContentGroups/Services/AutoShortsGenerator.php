<?php

/**
 * AutoShortsGenerator - Автоматическая генерация контента для YouTube Shorts
 *
 * Принимает только базовую идею и генерирует полный набор элементов:
 * - title, description, emoji, tags, pinned comment
 * - с защитой от дубликатов и Shorts-оптимизацией
 */

namespace App\Modules\ContentGroups\Services;

class AutoShortsGenerator
{
    // Словари для анализа intent
    private const CONTENT_TYPES = [
        'vocal' => ['голос', 'вокал', 'поёт', 'пение', 'певец', 'певица', 'голосом', 'песня', 'пою'],
        'music' => ['музыка', 'мелодия', 'звук', 'аудио', 'трек', 'композиция', 'мелодия', 'песня', 'мотив'],
        'aesthetic' => ['неон', 'свет', 'красиво', 'эстетика', 'визуал', 'цвета', 'ярко', 'картинка'],
        'ambience' => ['атмосфера', 'настроение', 'спокойно', 'тихо', 'ночь', 'вечер', 'погружение', 'релакс'],
        'dance' => ['танец', 'танцы', 'танцевать', 'танцор', 'хореография', 'движение', 'ритм', 'пляска'],
        'comedy' => ['юмор', 'смех', 'смешно', 'комедия', 'прикол', 'шутка', 'весело', 'забавно'],
        'cooking' => ['готовка', 'рецепт', 'еда', 'кухня', 'приготовление', 'блюдо', 'повар', 'кулинария'],
        'fitness' => ['спорт', 'тренировка', 'фитнес', 'упражнение', 'зарядка', 'спортзал', 'тренироваться'],
        'beauty' => ['красота', 'макияж', 'косметика', 'уход', 'красиво', 'стиль', 'мода', 'укладка'],
        'gaming' => ['игра', 'гейминг', 'игры', 'геймер', 'прохождение', 'летсплей', 'стрим', 'киберспорт'],
        'pets' => ['животное', 'питомец', 'кот', 'собака', 'кошка', 'пес', 'пушистый', 'милый'],
        'travel' => ['путешествие', 'поездка', 'отпуск', 'отпуск', 'страна', 'город', 'туризм', 'приключение'],
        'diy' => ['сделай', 'своими', 'руками', 'рукоделие', 'поделка', 'мастер', 'класс', 'творчество'],
        'lifehack' => ['лайфхак', 'совет', 'полезно', 'хак', 'трюк', 'секрет', 'способ', 'метод'],
        'motivation' => ['мотивация', 'вдохновение', 'успех', 'цель', 'мечта', 'достижение', 'победа', 'сила'],
        'asmr' => ['асмр', 'релакс', 'успокаиваю', 'звуки', 'шепот', 'тихо', 'расслабление', 'медитация'],
        'prank' => ['пранк', 'розыгрыш', 'шутка', 'обман', 'сюрприз', 'прикол', 'подстава'],
        'challenge' => ['челлендж', 'вызов', 'испытание', 'задача', 'попробуй', 'сможешь', 'проверка'],
        'transformation' => ['трансформация', 'превращение', 'до', 'после', 'изменение', 'метаморфоза', 'перевоплощение'],
        'reaction' => ['реакция', 'реагирую', 'отзыв', 'мнение', 'впечатление', 'эмоция', 'ответ'],
        'tutorial' => ['обучение', 'урок', 'инструкция', 'как', 'сделать', 'объяснение', 'мастер', 'класс'],
        'vlog' => ['влог', 'блог', 'день', 'жизнь', 'повседневность', 'рутина', 'быт', 'личное'],
        'fashion' => ['мода', 'стиль', 'одежда', 'наряд', 'лук', 'образ', 'тренд', 'одеваться'],
        'tech' => ['технологии', 'гаджет', 'техника', 'устройство', 'новинка', 'обзор', 'тест', 'инновация']
    ];

    private const CONTENT_TYPES_EN = [
        'vocal' => ['voice', 'vocal', 'vocals', 'sing', 'singing', 'singer', 'song'],
        'music' => ['music', 'melody', 'track', 'beat', 'audio', 'sound'],
        'aesthetic' => ['neon', 'aesthetic', 'visual', 'colors', 'beautiful', 'pretty'],
        'ambience' => ['ambience', 'atmosphere', 'mood', 'vibe', 'calm', 'night', 'relax'],
        'dance' => ['dance', 'dancing', 'choreography', 'moves', 'rhythm', 'dancer'],
        'comedy' => ['comedy', 'funny', 'laugh', 'joke', 'humor', 'comic', 'hilarious'],
        'cooking' => ['cooking', 'recipe', 'food', 'kitchen', 'chef', 'cuisine', 'dish', 'meal'],
        'fitness' => ['fitness', 'workout', 'exercise', 'gym', 'training', 'sport', 'athletic'],
        'beauty' => ['beauty', 'makeup', 'cosmetics', 'skincare', 'style', 'glam', 'fashion'],
        'gaming' => ['gaming', 'game', 'gamer', 'playthrough', 'stream', 'esports', 'play'],
        'pets' => ['pet', 'animal', 'cat', 'dog', 'cute', 'fluffy', 'puppy', 'kitten'],
        'travel' => ['travel', 'trip', 'vacation', 'journey', 'adventure', 'tourist', 'explore'],
        'diy' => ['diy', 'craft', 'handmade', 'tutorial', 'make', 'create', 'project'],
        'lifehack' => ['lifehack', 'tip', 'trick', 'hack', 'secret', 'method', 'way'],
        'motivation' => ['motivation', 'inspiration', 'success', 'goal', 'dream', 'achievement', 'win'],
        'asmr' => ['asmr', 'relax', 'sounds', 'whisper', 'calm', 'meditation', 'peaceful'],
        'prank' => ['prank', 'joke', 'trick', 'surprise', 'funny', 'hilarious'],
        'challenge' => ['challenge', 'try', 'dare', 'test', 'attempt', 'can you', 'impossible'],
        'transformation' => ['transformation', 'before', 'after', 'change', 'metamorphosis', 'glow up'],
        'reaction' => ['reaction', 'react', 'review', 'opinion', 'impression', 'response'],
        'tutorial' => ['tutorial', 'how to', 'guide', 'lesson', 'instruction', 'explain'],
        'vlog' => ['vlog', 'blog', 'day', 'life', 'daily', 'routine', 'lifestyle'],
        'fashion' => ['fashion', 'style', 'outfit', 'look', 'trend', 'clothing', 'dress'],
        'tech' => ['tech', 'technology', 'gadget', 'device', 'review', 'test', 'innovation']
    ];

    private const MOODS = [
        'calm' => ['спокойно', 'тихо', 'плавно', 'мягко', 'нежно', 'умиротворение'],
        'emotional' => ['эмоционально', 'чувства', 'душа', 'сердце', 'глубоко', 'трогательно'],
        'romantic' => ['романтично', 'любовь', 'нежность', 'чувственно', 'интимно'],
        'mysterious' => ['загадочно', 'тайна', 'мистика', 'непонятно', 'интрига', 'секрет']
    ];

    private const MOODS_EN = [
        'calm' => ['calm', 'soft', 'gentle', 'smooth', 'chill'],
        'emotional' => ['emotional', 'touching', 'deep', 'heartfelt'],
        'romantic' => ['romantic', 'love', 'tender', 'sweet'],
        'mysterious' => ['mysterious', 'secret', 'enigmatic', 'intriguing']
    ];

    private const VISUAL_FOCUS = [
        'neon' => ['неон', 'свет', 'ярко', 'цвета', 'разноцветный', 'переливы'],
        'night' => ['ночь', 'темно', 'тень', 'луна', 'звёзды', 'тёмный'],
        'closeup' => ['близко', 'крупно', 'лицо', 'глаза', 'взгляд', 'детали'],
        'atmosphere' => ['атмосфера', 'окружение', 'пространство', 'воздух', 'погружение']
    ];

    private const VISUAL_FOCUS_EN = [
        'neon' => ['neon', 'glow', 'bright', 'colors', 'lights'],
        'night' => ['night', 'dark', 'moon', 'stars', 'shadow'],
        'closeup' => ['closeup', 'close', 'face', 'eyes', 'details'],
        'atmosphere' => ['atmosphere', 'space', 'ambient', 'surroundings']
    ];

    // Шаблоны генерации
    private const TITLE_TEMPLATES = [
        'vocal' => [
            '{visual} + {emotion} {content}',
            '{emotion} {content} {visual}',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            '{visual} {content} {emotion}',
            'Этот {content} просто {emotion}',
            'Не могу перестать слушать {content}',
            '{visual} делает {content} {emotion}'
        ],
        'music' => [
            '{visual} {content} {emotion}',
            '{emotion} {content} в {visual}',
            '{content} которое {emotion}',
            'Просто {content} и {visual}',
            '{emotion} мелодия {visual}',
            '{content} {visual} {emotion}'
        ],
        'aesthetic' => [
            '{visual} {content} {emotion}',
            '{emotion} {visual} {content}',
            'Когда {visual} {emotion}',
            '{content} в {visual} {emotion}',
            'Это {visual} {content}',
            '{emotion} {visual} момент'
        ],
        'ambience' => [
            '{visual} {content} {emotion}',
            '{emotion} {visual} атмосфера',
            'Погружение в {visual} {content}',
            '{content} {visual} {emotion}',
            'Чувствую {emotion} {visual}',
            '{visual} {content} внутри'
        ],
        'dance' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = {emotion}',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'comedy' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = смех',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смеяться',
            '{visual} делает {content} {emotion}',
            'Этот {content} убил',
            '{emotion} {content} {visual}'
        ],
        'cooking' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = вкус',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать готовить {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'fitness' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = сила',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать тренироваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'beauty' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = красота',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'gaming' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = адреналин',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать играть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'pets' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = милота',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'travel' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = приключение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать путешествовать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'diy' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = творчество',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать творить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'lifehack' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = решение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать использовать {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'motivation' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = вдохновение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать вдохновляться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'asmr' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = релакс',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать слушать {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'prank' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = смех',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смеяться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'challenge' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = вызов',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать пробовать {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'transformation' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = изменение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу поверить в {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'reaction' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = реакция',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать реагировать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'tutorial' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = обучение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать учиться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'vlog' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = жизнь',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать снимать {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'fashion' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = стиль',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'tech' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = технологии',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать тестировать {content}',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ]
    ];

    private const TITLE_TEMPLATES_EN = [
        'vocal' => [
            '{visual} {content} feels {emotion}',
            '{emotion} {content} in {visual}',
            'This {content} is so {emotion}',
            'Can’t stop listening to this {content}',
            'She’s SO FLEXIBLE!',
            'Who did it BEST?'
        ],
        'music' => [
            '{emotion} {content} with {visual}',
            'This {content} hits different',
            '{visual} {content} vibes',
            'Who did it BEST?'
        ],
        'aesthetic' => [
            '{visual} {content} moment',
            'So {emotion} in this {visual} scene',
            'Who did it BEST?',
            'She’s SO FLEXIBLE!'
        ],
        'ambience' => [
            '{emotion} {visual} atmosphere',
            'Lost in the {visual} {content}',
            'Who did it BEST?'
        ],
        'dance' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop watching this {content}',
            'Who did it BEST?',
            'She\'s SO FLEXIBLE!',
            '{emotion} {content} in {visual}'
        ],
        'comedy' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop laughing',
            'Who did it BEST?',
            'This {content} killed me',
            '{emotion} {content} in {visual}'
        ],
        'cooking' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop cooking this {content}',
            'Who did it BEST?',
            'This {content} looks amazing',
            '{emotion} {content} in {visual}'
        ],
        'fitness' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop working out',
            'Who did it BEST?',
            'This {content} is intense',
            '{emotion} {content} in {visual}'
        ],
        'beauty' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop watching this {content}',
            'Who did it BEST?',
            'This {content} looks amazing',
            '{emotion} {content} in {visual}'
        ],
        'gaming' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop playing',
            'Who did it BEST?',
            'This {content} is insane',
            '{emotion} {content} in {visual}'
        ],
        'pets' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop watching this {content}',
            'Who did it BEST?',
            'This {content} is so cute',
            '{emotion} {content} in {visual}'
        ],
        'travel' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop traveling',
            'Who did it BEST?',
            'This {content} is amazing',
            '{emotion} {content} in {visual}'
        ],
        'diy' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop creating',
            'Who did it BEST?',
            'This {content} is creative',
            '{emotion} {content} in {visual}'
        ],
        'lifehack' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop using this {content}',
            'Who did it BEST?',
            'This {content} is genius',
            '{emotion} {content} in {visual}'
        ],
        'motivation' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop being inspired',
            'Who did it BEST?',
            'This {content} is powerful',
            '{emotion} {content} in {visual}'
        ],
        'asmr' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop listening to this {content}',
            'Who did it BEST?',
            'This {content} is so relaxing',
            '{emotion} {content} in {visual}'
        ],
        'prank' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop laughing',
            'Who did it BEST?',
            'This {content} is hilarious',
            '{emotion} {content} in {visual}'
        ],
        'challenge' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can you do this?',
            'Who did it BEST?',
            'This {content} is impossible',
            '{emotion} {content} in {visual}'
        ],
        'transformation' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t believe this {content}',
            'Who did it BEST?',
            'This {content} is incredible',
            '{emotion} {content} in {visual}'
        ],
        'reaction' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop reacting',
            'Who did it BEST?',
            'This {content} is shocking',
            '{emotion} {content} in {visual}'
        ],
        'tutorial' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop learning',
            'Who did it BEST?',
            'This {content} is helpful',
            '{emotion} {content} in {visual}'
        ],
        'vlog' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop filming {content}',
            'Who did it BEST?',
            'This {content} is real',
            '{emotion} {content} in {visual}'
        ],
        'fashion' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop watching this {content}',
            'Who did it BEST?',
            'This {content} is stylish',
            '{emotion} {content} in {visual}'
        ],
        'tech' => [
            'This {content} is so {emotion}',
            '{visual} {content} vibes',
            'Can\'t stop testing this {content}',
            'Who did it BEST?',
            'This {content} is innovative',
            '{emotion} {content} in {visual}'
        ]
    ];

    private const DESCRIPTION_TEMPLATES = [
        'question' => [
            '{emotion_emoji} {question} {cta_emoji}',
            'Как тебе {content}? {emotion_emoji}',
            'Залип? {emotion_emoji}',
            'Стоит продолжать? {cta_emoji}',
            '{question} {emotion_emoji}',
            'Досмотрел до конца? {cta_emoji}'
        ],
        'emotional' => [
            'Ничего лишнего. Просто {emotion} {emotion_emoji}',
            'Чувствую {emotion} {emotion_emoji}',
            '{content} {visual} {emotion_emoji}',
            'Момент {emotion} {emotion_emoji}',
            'Это {emotion} {content} {emotion_emoji}'
        ],
        'mysterious' => [
            'Что-то особенное {emotion_emoji}',
            'Загадочная {emotion} {emotion_emoji}',
            'Не могу объяснить {emotion_emoji}',
            'Просто посмотри {cta_emoji}',
            'Особенная {emotion} {emotion_emoji}'
        ]
    ];

    private const DESCRIPTION_TEMPLATES_EN = [
        'question' => [
            '{emotion_emoji} {question} {cta_emoji}',
            'Did you feel that? {emotion_emoji}',
            'Who did it BEST? {cta_emoji}',
            'Would you watch again? {emotion_emoji}'
        ],
        'emotional' => [
            'Nothing extra. Just {emotion} vibes {emotion_emoji}',
            'This {content} feels {emotion} {emotion_emoji}',
            'So {emotion}. Just watch {emotion_emoji}'
        ],
        'mysterious' => [
            'Something special here {emotion_emoji}',
            'Can’t explain it {emotion_emoji}',
            'Just watch {cta_emoji}'
        ]
    ];

    // Emoji по настроениям
    private const EMOJI_SETS = [
        'calm' => ['✨', '🌙', '💫', '🌌', '🌠', '🌸'],
        'emotional' => ['💖', '🫶', '😢', '🥺', '💕', '❤️'],
        'romantic' => ['💕', '❤️', '💫', '🌹', '🌙', '🫶'],
        'mysterious' => ['🌌', '👁️', '🌑', '🔮', '🌙', '❓']
    ];

    // Теги по типам контента
    private const TAG_SETS = [
        'vocal' => ['#Shorts', '#Вокал', '#Голос', '#Пение', '#Музыка'],
        'music' => ['#Shorts', '#Музыка', '#Мелодия', '#Звук', '#Аудио'],
        'aesthetic' => ['#Shorts', '#Красиво', '#Эстетика', '#Визуал', '#Арт'],
        'ambience' => ['#Shorts', '#Атмосфера', '#Настроение', '#Спокойно', '#Релакс'],
        'dance' => ['#Shorts', '#Танец', '#Танцы', '#Хореография', '#Движение'],
        'comedy' => ['#Shorts', '#Юмор', '#Смех', '#Комедия', '#Прикол'],
        'cooking' => ['#Shorts', '#Готовка', '#Рецепт', '#Еда', '#Кухня'],
        'fitness' => ['#Shorts', '#Спорт', '#Тренировка', '#Фитнес', '#Упражнения'],
        'beauty' => ['#Shorts', '#Красота', '#Макияж', '#Косметика', '#Стиль'],
        'gaming' => ['#Shorts', '#Игры', '#Гейминг', '#Геймер', '#Играю'],
        'pets' => ['#Shorts', '#Животные', '#Питомец', '#Кот', '#Собака'],
        'travel' => ['#Shorts', '#Путешествие', '#Поездка', '#Отпуск', '#Туризм'],
        'diy' => ['#Shorts', '#СвоимиРуками', '#Рукоделие', '#Поделка', '#Творчество'],
        'lifehack' => ['#Shorts', '#Лайфхак', '#Совет', '#Полезно', '#Трюк'],
        'motivation' => ['#Shorts', '#Мотивация', '#Вдохновение', '#Успех', '#Цель'],
        'asmr' => ['#Shorts', '#АСМР', '#Релакс', '#Звуки', '#Успокаиваю'],
        'prank' => ['#Shorts', '#Пранк', '#Розыгрыш', '#Шутка', '#Прикол'],
        'challenge' => ['#Shorts', '#Челлендж', '#Вызов', '#Испытание', '#Попробуй'],
        'transformation' => ['#Shorts', '#Трансформация', '#ДоИПосле', '#Изменение', '#Превращение'],
        'reaction' => ['#Shorts', '#Реакция', '#Реагирую', '#Отзыв', '#Мнение'],
        'tutorial' => ['#Shorts', '#Обучение', '#Урок', '#Инструкция', '#КакСделать'],
        'vlog' => ['#Shorts', '#Влог', '#Блог', '#ДеньИзЖизни', '#Повседневность'],
        'fashion' => ['#Shorts', '#Мода', '#Стиль', '#Одежда', '#Лук'],
        'tech' => ['#Shorts', '#Технологии', '#Гаджет', '#Обзор', '#Новинка']
    ];

    private const TAG_SETS_EN = [
        'vocal' => ['#Shorts', '#Singing', '#Vocal', '#Voice', '#Music'],
        'music' => ['#Shorts', '#Music', '#Melody', '#Sound', '#Audio'],
        'aesthetic' => ['#Shorts', '#Aesthetic', '#Visual', '#Beautiful', '#Art'],
        'ambience' => ['#Shorts', '#Atmosphere', '#Mood', '#Calm', '#Relax'],
        'dance' => ['#Shorts', '#Dance', '#Dancing', '#Choreography', '#Moves'],
        'comedy' => ['#Shorts', '#Comedy', '#Funny', '#Laugh', '#Humor'],
        'cooking' => ['#Shorts', '#Cooking', '#Recipe', '#Food', '#Kitchen'],
        'fitness' => ['#Shorts', '#Fitness', '#Workout', '#Exercise', '#Gym'],
        'beauty' => ['#Shorts', '#Beauty', '#Makeup', '#Cosmetics', '#Style'],
        'gaming' => ['#Shorts', '#Gaming', '#Game', '#Gamer', '#Play'],
        'pets' => ['#Shorts', '#Pets', '#Animals', '#Cat', '#Dog'],
        'travel' => ['#Shorts', '#Travel', '#Trip', '#Vacation', '#Adventure'],
        'diy' => ['#Shorts', '#DIY', '#Craft', '#Handmade', '#Tutorial'],
        'lifehack' => ['#Shorts', '#Lifehack', '#Tip', '#Trick', '#Hack'],
        'motivation' => ['#Shorts', '#Motivation', '#Inspiration', '#Success', '#Goal'],
        'asmr' => ['#Shorts', '#ASMR', '#Relax', '#Sounds', '#Whisper'],
        'prank' => ['#Shorts', '#Prank', '#Joke', '#Trick', '#Funny'],
        'challenge' => ['#Shorts', '#Challenge', '#Try', '#Dare', '#Test'],
        'transformation' => ['#Shorts', '#Transformation', '#BeforeAfter', '#Change', '#GlowUp'],
        'reaction' => ['#Shorts', '#Reaction', '#React', '#Review', '#Opinion'],
        'tutorial' => ['#Shorts', '#Tutorial', '#HowTo', '#Guide', '#Lesson'],
        'vlog' => ['#Shorts', '#Vlog', '#Blog', '#DayInLife', '#Lifestyle'],
        'fashion' => ['#Shorts', '#Fashion', '#Style', '#Outfit', '#Look'],
        'tech' => ['#Shorts', '#Tech', '#Technology', '#Gadget', '#Review']
    ];

    // Вопросы для вовлечённости
    private const ENGAGEMENT_QUESTIONS = [
        'vocal' => [
            'Как тебе голос?',
            'Залип на голос?',
            'Хочешь ещё такого вокала?',
            'Голос зацепил?',
            'Стоит продолжать петь?'
        ],
        'music' => [
            'Как тебе мелодия?',
            'Музыка зацепила?',
            'Хочешь ещё такой музыки?',
            'Залип на звук?',
            'Стоит продолжать?'
        ],
        'aesthetic' => [
            'Как тебе визуал?',
            'Красиво, да?',
            'Залип на картинку?',
            'Хочешь ещё такого?',
            'Стоит продолжать снимать?'
        ],
        'ambience' => [
            'Чувствуешь атмосферу?',
            'Залип на настроение?',
            'Как тебе погружение?',
            'Хочешь ещё такой атмосферы?',
            'Стоит продолжать?'
        ],
        'dance' => [
            'Как тебе танец?',
            'Танцы зацепили?',
            'Хочешь ещё таких танцев?',
            'Залип на движения?',
            'Стоит продолжать танцевать?'
        ],
        'comedy' => [
            'Как тебе юмор?',
            'Смешно было?',
            'Хочешь ещё такого юмора?',
            'Залип на приколы?',
            'Стоит продолжать смеяться?'
        ],
        'cooking' => [
            'Как тебе рецепт?',
            'Готовка зацепила?',
            'Хочешь ещё таких рецептов?',
            'Залип на готовку?',
            'Стоит продолжать готовить?'
        ],
        'fitness' => [
            'Как тебе тренировка?',
            'Спорт зацепил?',
            'Хочешь ещё таких упражнений?',
            'Залип на фитнес?',
            'Стоит продолжать тренироваться?'
        ],
        'beauty' => [
            'Как тебе макияж?',
            'Красота зацепила?',
            'Хочешь ещё таких образов?',
            'Залип на стиль?',
            'Стоит продолжать экспериментировать?'
        ],
        'gaming' => [
            'Как тебе игра?',
            'Гейминг зацепил?',
            'Хочешь ещё таких игр?',
            'Залип на прохождение?',
            'Стоит продолжать играть?'
        ],
        'pets' => [
            'Как тебе питомец?',
            'Животное зацепило?',
            'Хочешь ещё таких видео?',
            'Залип на милоту?',
            'Стоит продолжать снимать?'
        ],
        'travel' => [
            'Как тебе путешествие?',
            'Поездка зацепила?',
            'Хочешь ещё таких видео?',
            'Залип на приключения?',
            'Стоит продолжать путешествовать?'
        ],
        'diy' => [
            'Как тебе поделка?',
            'Творчество зацепило?',
            'Хочешь ещё таких идей?',
            'Залип на рукоделие?',
            'Стоит продолжать творить?'
        ],
        'lifehack' => [
            'Как тебе лайфхак?',
            'Совет зацепил?',
            'Хочешь ещё таких трюков?',
            'Залип на хитрости?',
            'Стоит продолжать делиться?'
        ],
        'motivation' => [
            'Как тебе мотивация?',
            'Вдохновение зацепило?',
            'Хочешь ещё такого контента?',
            'Залип на успех?',
            'Стоит продолжать вдохновляться?'
        ],
        'asmr' => [
            'Как тебе звуки?',
            'АСМР зацепил?',
            'Хочешь ещё такого релакса?',
            'Залип на успокаивающие звуки?',
            'Стоит продолжать слушать?'
        ],
        'prank' => [
            'Как тебе пранк?',
            'Розыгрыш зацепил?',
            'Хочешь ещё таких приколов?',
            'Залип на пранки?',
            'Стоит продолжать розыгрывать?'
        ],
        'challenge' => [
            'Как тебе челлендж?',
            'Вызов зацепил?',
            'Хочешь ещё таких испытаний?',
            'Залип на челленджи?',
            'Стоит продолжать пробовать?'
        ],
        'transformation' => [
            'Как тебе трансформация?',
            'Изменение зацепило?',
            'Хочешь ещё таких видео?',
            'Залип на превращения?',
            'Стоит продолжать снимать?'
        ],
        'reaction' => [
            'Как тебе реакция?',
            'Отзыв зацепил?',
            'Хочешь ещё таких реакций?',
            'Залип на мнения?',
            'Стоит продолжать реагировать?'
        ],
        'tutorial' => [
            'Как тебе урок?',
            'Обучение зацепило?',
            'Хочешь ещё таких инструкций?',
            'Залип на мастер-классы?',
            'Стоит продолжать учиться?'
        ],
        'vlog' => [
            'Как тебе влог?',
            'Блог зацепил?',
            'Хочешь ещё таких видео?',
            'Залип на повседневность?',
            'Стоит продолжать снимать?'
        ],
        'fashion' => [
            'Как тебе стиль?',
            'Мода зацепила?',
            'Хочешь ещё таких образов?',
            'Залип на луки?',
            'Стоит продолжать экспериментировать?'
        ],
        'tech' => [
            'Как тебе гаджет?',
            'Технологии зацепили?',
            'Хочешь ещё таких обзоров?',
            'Залип на новинки?',
            'Стоит продолжать тестировать?'
        ]
    ];

    private const ENGAGEMENT_QUESTIONS_EN = [
        'vocal' => [
            'How is the voice?',
            'Did the vocals hook you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'music' => [
            'How is the melody?',
            'This track hits?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'aesthetic' => [
            'How’s the visual?',
            'Does this look amazing?',
            'Want more like this?'
        ],
        'ambience' => [
            'Feel the atmosphere?',
            'Do you like the vibe?',
            'Want more like this?'
        ],
        'dance' => [
            'How's the dance?',
            'Did the moves hook you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'comedy' => [
            'Was it funny?',
            'Did it make you laugh?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'cooking' => [
            'How's the recipe?',
            'Does it look good?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'fitness' => [
            'How's the workout?',
            'Did it motivate you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'beauty' => [
            'How's the look?',
            'Does it look amazing?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'gaming' => [
            'How's the game?',
            'Did it hook you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'pets' => [
            'How cute is this?',
            'Did it make you smile?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'travel' => [
            'How's the trip?',
            'Do you want to go there?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'diy' => [
            'How's the craft?',
            'Do you want to try this?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'lifehack' => [
            'How useful is this?',
            'Will you try this?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'motivation' => [
            'How inspiring is this?',
            'Did it motivate you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'asmr' => [
            'How relaxing is this?',
            'Did it calm you down?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'prank' => [
            'Was it funny?',
            'Did it make you laugh?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'challenge' => [
            'Can you do this?',
            'Will you try this?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'transformation' => [
            'How amazing is this?',
            'Can you believe it?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'reaction' => [
            'How was the reaction?',
            'Did you agree?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'tutorial' => [
            'How helpful is this?',
            'Will you try this?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'vlog' => [
            'How's the day?',
            'Do you relate?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'fashion' => [
            'How's the style?',
            'Do you like the outfit?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'tech' => [
            'How's the gadget?',
            'Do you want this?',
            'Want more like this?',
            'Who did it BEST?'
        ]
    ];

    // История генераций для защиты от дубликатов
    private static array $generationHistory = [];

    /**
     * Генерировать полный Shorts контент из одной идеи
     */
    /**
     * Генерация одного варианта контента (legacy method)
     */
    public function generateFromIdea(string $idea): array
    {
        $variants = $this->generateMultipleVariants($idea, 1);
        return $variants[0] ?? [];
    }

    /**
     * Генерация 20 различных вариантов оформления видео
     */
    public function generateMultipleVariants(string $idea, int $count = 20): array
    {
        try {
            error_log('AutoShortsGenerator::generateMultipleVariants: Starting generation for idea: "' . $idea . '" with ' . $count . ' variants');

            // 1. Анализ intent
            error_log('AutoShortsGenerator::generateMultipleVariants: Analyzing intent');
            $intent = $this->analyzeIntent($idea);
            error_log('AutoShortsGenerator::generateMultipleVariants: Intent analyzed - ' . json_encode($intent));

            // 2. Генерация смысловых углов
            error_log('AutoShortsGenerator::generateMultipleVariants: Generating content angles');
            $angles = $this->generateContentAngles($intent, $idea);
            error_log('AutoShortsGenerator::generateMultipleVariants: Angles generated - ' . count($angles) . ' angles');

            $variants = [];
            $usedTitles = [];
            $usedDescriptions = [];

            // 3. Генерация множества вариантов
            for ($i = 0; $i < $count; $i++) {
                error_log('AutoShortsGenerator::generateMultipleVariants: Generating variant ' . ($i + 1));

                // Создаем уникальный вариант с разными параметрами
                $variantIntent = $this->modifyIntentForVariant($intent, $i);
                $variantAngles = $this->selectAnglesForVariant($angles, $i);

                // Генерируем контент для этого варианта
                $content = $this->generateContent($variantIntent, $variantAngles);

                // Убеждаемся в уникальности
                $content = $this->ensureVariantUniqueness($content, $usedTitles, $usedDescriptions);

                // Добавляем в историю для защиты от глобальных дубликатов
                $this->addToHistory($content);

                $variant = [
                    'idea' => $idea,
                    'intent' => $variantIntent,
                    'content' => $content,
                    'variant_number' => $i + 1,
                    'generated_at' => date('Y-m-d H:i:s')
                ];

                $variants[] = $variant;

                // Сохраняем использованные заголовки и описания для уникальности
                if (isset($content['title'])) {
                    $usedTitles[] = $content['title'];
                }
                if (isset($content['description'])) {
                    $usedDescriptions[] = $content['description'];
                }
            }

            error_log('AutoShortsGenerator::generateMultipleVariants: Generated ' . count($variants) . ' variants successfully');
            return $variants;

        } catch (Exception $e) {
            error_log('AutoShortsGenerator::generateMultipleVariants: Exception: ' . $e->getMessage());
            error_log('AutoShortsGenerator::generateMultipleVariants: Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Анализ intent из текста идеи
     */
    private function analyzeIntent(string $idea): array
    {
        $language = $this->detectLanguage($idea);
        $idea = mb_strtolower($idea);

        // Определение типа контента
        $contentType = 'vocal'; // дефолт
        $maxWeight = 0;

        $contentTypes = $language === 'en' ? self::CONTENT_TYPES_EN : self::CONTENT_TYPES;
        foreach ($contentTypes as $type => $keywords) {
            $weight = 0;
            foreach ($keywords as $keyword) {
                if (strpos($idea, $keyword) !== false) {
                    $weight += 1;
                }
            }
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $contentType = $type;
            }
        }

        // Определение настроения
        $mood = 'calm'; // дефолт
        $maxWeight = 0;

        $moods = $language === 'en' ? self::MOODS_EN : self::MOODS;
        foreach ($moods as $moodType => $keywords) {
            $weight = 0;
            foreach ($keywords as $keyword) {
                if (strpos($idea, $keyword) !== false) {
                    $weight += 1;
                }
            }
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $mood = $moodType;
            }
        }

        // Определение визуального фокуса
        $visualFocus = 'neon'; // дефолт
        $maxWeight = 0;

        $visuals = $language === 'en' ? self::VISUAL_FOCUS_EN : self::VISUAL_FOCUS;
        foreach ($visuals as $focus => $keywords) {
            $weight = 0;
            foreach ($keywords as $keyword) {
                if (strpos($idea, $keyword) !== false) {
                    $weight += 1;
                }
            }
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $visualFocus = $focus;
            }
        }

        return [
            'content_type' => $contentType,
            'mood' => $mood,
            'visual_focus' => $visualFocus,
            'language' => $language,
            'platform' => 'shorts'
        ];
    }

    private function detectLanguage(string $idea): string
    {
        $hasLatin = (bool)preg_match('/[a-z]/i', $idea);
        $hasCyrillic = (bool)preg_match('/[а-яё]/iu', $idea);
        if ($hasLatin && !$hasCyrillic) {
            return 'en';
        }
        return 'ru';
    }

    /**
     * Генерация смысловых углов для разнообразия
     */
    private function generateContentAngles(array $intent, string $idea): array
    {
        $language = $intent['language'] ?? 'ru';
        $angles = [];

        // Разные углы в зависимости от типа контента и языка
        if ($language === 'en') {
            switch ($intent['content_type']) {
                case 'vocal':
                    $angles = [
                        'voice', 'vocal', 'singing', 'tone', 'intonation',
                        'voice_emotion', 'sound_purity', 'singing_style',
                        'inner_world', 'singer_feelings'
                    ];
                    break;
                case 'music':
                    $angles = [
                        'melody', 'rhythm', 'sound', 'composition', 'instruments',
                        'musical_mood', 'sound_space',
                        'musical_texture', 'sound', 'musical_atmosphere'
                    ];
                    break;
                case 'aesthetic':
                    $angles = [
                        'visual', 'colors', 'light', 'composition', 'aesthetic',
                        'visual_harmony', 'color_transitions',
                        'light_effects', 'visual_rhythm', 'aesthetic_pleasure'
                    ];
                    break;
                case 'ambience':
                    $angles = [
                        'atmosphere', 'mood', 'immersion', 'surroundings',
                        'emotional_background', 'spatial_feeling',
                        'atmospheric_immersion', 'emotional_aura',
                        'environment', 'atmospheric_mood'
                    ];
                    break;
                default:
                    // Для новых типов используем общие углы
                    $angles = [
                        'content', 'style', 'vibe', 'energy', 'feeling',
                        'moment', 'experience', 'quality', 'essence', 'spirit'
                    ];
                    break;
            }
        } else {
            switch ($intent['content_type']) {
                case 'vocal':
                    $angles = [
                        'голос', 'вокал', 'пение', 'тембр', 'интонация',
                        'эмоция_голоса', 'чистота_звука', 'манера_пения',
                        'внутренний_мир', 'чувства_певца'
                    ];
                    break;
                case 'music':
                    $angles = [
                        'мелодия', 'ритм', 'звук', 'композиция', 'инструменты',
                        'музыкальное_настроение', 'звуковое_пространство',
                        'музыкальная_ткань', 'звучание', 'музыкальная_атмосфера'
                    ];
                    break;
                case 'aesthetic':
                    $angles = [
                        'визуал', 'цвета', 'свет', 'композиция', 'эстетика',
                        'визуальная_гармония', 'цветовые_переходы',
                        'световые_эффекты', 'визуальный_ритм', 'эстетическое_наслаждение'
                    ];
                    break;
                case 'ambience':
                    $angles = [
                        'атмосфера', 'настроение', 'погружение', 'окружение',
                        'эмоциональный_фон', 'пространственное_ощущение',
                        'атмосферное_погружение', 'эмоциональная_аура',
                        'окружающая_среда', 'атмосферное_настроение'
                    ];
                    break;
                default:
                    // Для новых типов используем общие углы
                    $angles = [
                        'контент', 'стиль', 'настроение', 'энергия', 'чувство',
                        'момент', 'опыт', 'качество', 'суть', 'дух'
                    ];
                    break;
            }
        }

        // Перемешиваем и выбираем 6-8 углов
        shuffle($angles);
        return array_slice($angles, 0, rand(6, 8));
    }

    /**
     * Модификация интента для варианта (для разнообразия)
     */
    private function modifyIntentForVariant(array $baseIntent, int $variantIndex): array
    {
        $intent = $baseIntent;

        // Циклически меняем настроение для разнообразия
        $moods = ['calm', 'emotional', 'atmospheric', 'intense', 'dreamy'];
        $intent['mood'] = $moods[$variantIndex % count($moods)];

        // Циклически меняем визуальный фокус
        $visualFocuses = ['neon', 'lights', 'shadows', 'colors', 'silhouette'];
        $intent['visual_focus'] = $visualFocuses[$variantIndex % count($visualFocuses)];

        return $intent;
    }

    /**
     * Выбор углов для варианта
     */
    private function selectAnglesForVariant(array $allAngles, int $variantIndex): array
    {
        // Для каждого варианта выбираем разные комбинации углов
        $angleCount = count($allAngles);
        $startIndex = $variantIndex * 3 % $angleCount; // Сдвиг на 3 угла для каждого варианта
        $selectedCount = rand(4, 6); // 4-6 углов на вариант

        $selectedAngles = [];
        for ($i = 0; $i < $selectedCount; $i++) {
            $index = ($startIndex + $i) % $angleCount;
            $selectedAngles[] = $allAngles[$index];
        }

        return $selectedAngles;
    }

    /**
     * Обеспечение уникальности варианта внутри батча
     */
    private function ensureVariantUniqueness(array $content, array &$usedTitles, array &$usedDescriptions): array
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $isUnique = true;

            // Проверяем уникальность заголовка
            if (isset($content['title']) && in_array($content['title'], $usedTitles)) {
                // Регенерируем заголовок с учетом языка
                $language = $content['language'] ?? 'ru';
                $alternativeAngle = $language === 'en' ? 'alternative_angle' : 'альтернативный_угол';
                $content['title'] = $this->generateTitle([
                    'content_type' => 'vocal',
                    'mood' => 'calm',
                    'language' => $language
                ], $alternativeAngle);
                $isUnique = false;
            }

            // Проверяем уникальность описания
            if (isset($content['description']) && in_array($content['description'], $usedDescriptions)) {
                // Регенерируем описание с учетом языка
                $language = $content['language'] ?? 'ru';
                $content['description'] = $this->generateDescription([
                    'content_type' => 'vocal',
                    'mood' => 'calm',
                    'language' => $language
                ]);
                $isUnique = false;
            }

            if ($isUnique) {
                break;
            }

            $attempt++;
        }

        return $content;
    }

    /**
     * Генерация полного контента
     */
    private function generateContent(array $intent, array $angles): array
    {
        try {
            $angle = $angles[array_rand($angles)]; // Случайный угол
            error_log("AutoShortsGenerator::generateContent: Selected angle: {$angle}");

            // Генерация названия
            error_log("AutoShortsGenerator::generateContent: Generating title...");
            $title = $this->generateTitle($intent, $angle);
            error_log("AutoShortsGenerator::generateContent: Title generated: '{$title}'");
            
            // Фильтрация русских слов из английских результатов
            $language = $intent['language'] ?? 'ru';
            if ($language === 'en') {
                $title = $this->filterRussianWords($title);
                error_log("AutoShortsGenerator::generateContent: Title after Russian filter: '{$title}'");
            }

            // Генерация описания
            error_log("AutoShortsGenerator::generateContent: Generating description...");
            $description = $this->generateDescription($intent);
            error_log("AutoShortsGenerator::generateContent: Description generated: '{$description}'");
            
            // Фильтрация русских слов из английских результатов
            if ($language === 'en') {
                $description = $this->filterRussianWords($description);
                error_log("AutoShortsGenerator::generateContent: Description after Russian filter: '{$description}'");
            }

            // Генерация emoji
            error_log("AutoShortsGenerator::generateContent: Generating emoji...");
            $emoji = $this->generateEmoji($intent);
            error_log("AutoShortsGenerator::generateContent: Emoji generated: '{$emoji}'");

            // Генерация тегов
            error_log("AutoShortsGenerator::generateContent: Generating tags...");
            $tags = $this->generateTags($intent);
            error_log("AutoShortsGenerator::generateContent: Tags generated: " . json_encode($tags));
            
            // Фильтрация русских слов из английских тегов
            if ($language === 'en') {
                $filteredTags = [];
                foreach ($tags as $tag) {
                    $filteredTag = $this->filterRussianWords($tag);
                    if (!empty($filteredTag)) {
                        $filteredTags[] = $filteredTag;
                    }
                }
                $tags = $filteredTags;
                error_log("AutoShortsGenerator::generateContent: Tags after Russian filter: " . json_encode($tags));
            }

            // Генерация закрепленного комментария
            error_log("AutoShortsGenerator::generateContent: Generating pinned comment...");
            $pinnedComment = $this->generatePinnedComment($intent);
            error_log("AutoShortsGenerator::generateContent: Pinned comment generated: '{$pinnedComment}'");
            
            // Фильтрация русских слов из английских комментариев
            if ($language === 'en') {
                $pinnedComment = $this->filterRussianWords($pinnedComment);
                error_log("AutoShortsGenerator::generateContent: Pinned comment after Russian filter: '{$pinnedComment}'");
            }

            $result = [
                'title' => $title,
                'description' => $description,
                'emoji' => $emoji,
                'tags' => $tags,
                'pinned_comment' => $pinnedComment,
                'angle' => $angle,
                'language' => $intent['language'] ?? 'ru'
            ];

            error_log("AutoShortsGenerator::generateContent: Content generation completed successfully");
            return $result;

        } catch (Exception $e) {
            error_log("AutoShortsGenerator::generateContent: Exception: " . $e->getMessage());
            error_log("AutoShortsGenerator::generateContent: Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Генерация уникального названия
     */
    private function generateTitle(array $intent, string $angle): string
    {
        try {
            $contentType = $intent['content_type'] ?? 'vocal';
            $language = $intent['language'] ?? 'ru';
            $templates = $language === 'en'
                ? (self::TITLE_TEMPLATES_EN[$contentType] ?? self::TITLE_TEMPLATES_EN['vocal'])
                : (self::TITLE_TEMPLATES[$contentType] ?? self::TITLE_TEMPLATES['vocal']);

            error_log("AutoShortsGenerator::generateTitle: Content type: {$contentType}, available templates: " . count($templates));

            // Замены для шаблонов
            $replacements = [
                '{content}' => $this->getContentWord($contentType, $language),
                '{emotion}' => $this->getEmotionWord($intent['mood'] ?? 'calm', $language),
                '{visual}' => $this->getVisualWord($intent['visual_focus'] ?? 'neon', $language),
                '{angle}' => $angle
            ];

            error_log("AutoShortsGenerator::generateTitle: Replacements: " . json_encode($replacements));

            // Выбираем случайный шаблон
            $template = $templates[array_rand($templates)];
            error_log("AutoShortsGenerator::generateTitle: Selected template: '{$template}'");

            // Применяем замены
            $title = str_replace(array_keys($replacements), array_values($replacements), $template);
            error_log("AutoShortsGenerator::generateTitle: After replacements: '{$title}'");

            // Ограничиваем длину
            if (mb_strlen($title) > 80) {
                $title = mb_substr($title, 0, 77) . '...';
            }

            error_log("AutoShortsGenerator::generateTitle: Final title: '{$title}'");
            return $language === 'en' ? ucfirst($title) : ucfirst($title);

        } catch (Exception $e) {
            error_log("AutoShortsGenerator::generateTitle: Exception: " . $e->getMessage());
            return "Автоматически сгенерированное название"; // fallback
        }
    }

    /**
     * Генерация описания
     */
    private function generateDescription(array $intent): string
    {
        try {
            $language = $intent['language'] ?? 'ru';
            $descType = ['question', 'emotional', 'mysterious'][array_rand(['question', 'emotional', 'mysterious'])];
            $templates = $language === 'en'
                ? (self::DESCRIPTION_TEMPLATES_EN[$descType] ?? self::DESCRIPTION_TEMPLATES_EN['question'])
                : self::DESCRIPTION_TEMPLATES[$descType];

            error_log("AutoShortsGenerator::generateDescription: Desc type: {$descType}, available templates: " . count($templates));

            $template = $templates[array_rand($templates)];
            error_log("AutoShortsGenerator::generateDescription: Selected template: '{$template}'");

            $replacements = [
                '{emotion}' => $this->getEmotionWord($intent['mood'] ?? 'calm', $language),
                '{content}' => $this->getContentWord($intent['content_type'] ?? 'vocal', $language),
                '{visual}' => $this->getVisualWord($intent['visual_focus'] ?? 'neon', $language),
                '{question}' => $this->getQuestionWord($intent['content_type'] ?? 'vocal', $language),
                '{emotion_emoji}' => $this->getRandomEmoji($intent['mood'] ?? 'calm', 1),
                '{cta_emoji}' => ['▶️', '👆', '💬', '❤️'][array_rand(['▶️', '👆', '💬', '❤️'])]
            ];

            error_log("AutoShortsGenerator::generateDescription: Replacements: " . json_encode($replacements));

            $result = str_replace(array_keys($replacements), array_values($replacements), $template);
            error_log("AutoShortsGenerator::generateDescription: Final description: '{$result}'");

            return $result;

        } catch (Exception $e) {
            error_log("AutoShortsGenerator::generateDescription: Exception: " . $e->getMessage());
            $language = $intent['language'] ?? 'ru';
            return $language === 'en' ? "Auto-generated description" : "Автоматически сгенерированное описание"; // fallback
        }
    }

    /**
     * Генерация emoji
     */
    private function generateEmoji(array $intent): string
    {
        // 0-2 emoji в зависимости от настроения
        $count = rand(0, 2);
        if ($count === 0) return '';

        return $this->getRandomEmoji($intent['mood'], $count);
    }

    /**
     * Генерация тегов
     */
    private function generateTags(array $intent): array
    {
        $language = $intent['language'] ?? 'ru';
        $baseTags = $language === 'en'
            ? (self::TAG_SETS_EN[$intent['content_type']] ?? self::TAG_SETS_EN['vocal'])
            : (self::TAG_SETS[$intent['content_type']] ?? self::TAG_SETS['vocal']);

        // Добавляем mood-специфичные теги
        $moodTags = $language === 'en'
            ? [
                'calm' => ['#Calm', '#Relax'],
                'emotional' => ['#Emotions', '#Feelings'],
                'romantic' => ['#Romance', '#Love'],
                'mysterious' => ['#Mystery', '#Vibes']
            ]
            : [
            'calm' => ['#Спокойно', '#Релакс'],
            'emotional' => ['#Эмоции', '#Чувства'],
            'romantic' => ['#Романтика', '#Любовь'],
            'mysterious' => ['#Загадка', '#Мистика']
        ];

        $tags = array_merge($baseTags, $moodTags[$intent['mood']] ?? []);

        // Перемешиваем и выбираем 3-5 тегов
        shuffle($tags);
        return array_slice($tags, 0, rand(3, 5));
    }

    /**
     * Генерация закрепленного комментария
     */
    private function generatePinnedComment(array $intent): string
    {
        $language = $intent['language'] ?? 'ru';
        $questions = $language === 'en'
            ? (self::ENGAGEMENT_QUESTIONS_EN[$intent['content_type']] ?? self::ENGAGEMENT_QUESTIONS_EN['vocal'])
            : (self::ENGAGEMENT_QUESTIONS[$intent['content_type']] ?? self::ENGAGEMENT_QUESTIONS['vocal']);
        return $questions[array_rand($questions)];
    }

    /**
     * Проверка на дубликаты и обеспечение уникальности
     */
    private function ensureUniqueness(array $content): array
    {
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            if (!$this->isDuplicate($content)) {
                return $content;
            }

            // Перегенерация
            $content['title'] = $this->regenerateTitle($content);
            $content['description'] = $this->regenerateDescription($content);
            $content['emoji'] = $this->regenerateEmoji($content);

            $attempt++;
        }

        // Если не удалось сгенерировать уникальный, возвращаем как есть
        return $content;
    }

    /**
     * Проверка на дубликат
     */
    private function isDuplicate(array $content): bool
    {
        foreach (self::$generationHistory as $previous) {
            // Проверяем совпадение первых слов в названии
            $titleWords1 = explode(' ', mb_strtolower($previous['title']));
            $titleWords2 = explode(' ', mb_strtolower($content['title']));

            if (!empty($titleWords1) && !empty($titleWords2) &&
                $titleWords1[0] === $titleWords2[0]) {
                return true;
            }

            // Проверяем полное совпадение описания
            if ($previous['description'] === $content['description']) {
                return true;
            }
        }

        return false;
    }

    // Вспомогательные методы

    private function getContentWord(string $contentType, string $language = 'ru'): string
    {
        $words = $language === 'en'
            ? [
                'vocal' => ['voice', 'vocals', 'singing', 'song'],
                'music' => ['melody', 'music', 'track', 'sound'],
                'aesthetic' => ['visual', 'beauty', 'aesthetic', 'light'],
                'ambience' => ['atmosphere', 'mood', 'vibe', 'ambience'],
                'dance' => ['dance', 'dancing', 'moves', 'choreography'],
                'comedy' => ['comedy', 'joke', 'humor', 'fun'],
                'cooking' => ['recipe', 'dish', 'food', 'meal'],
                'fitness' => ['workout', 'exercise', 'training', 'fitness'],
                'beauty' => ['look', 'style', 'makeup', 'beauty'],
                'gaming' => ['game', 'play', 'gaming', 'stream'],
                'pets' => ['pet', 'animal', 'friend', 'companion'],
                'travel' => ['trip', 'journey', 'adventure', 'destination'],
                'diy' => ['craft', 'project', 'creation', 'handmade'],
                'lifehack' => ['tip', 'trick', 'hack', 'secret'],
                'motivation' => ['inspiration', 'success', 'goal', 'dream'],
                'asmr' => ['sound', 'whisper', 'relax', 'calm'],
                'prank' => ['prank', 'joke', 'trick', 'surprise'],
                'challenge' => ['challenge', 'dare', 'test', 'try'],
                'transformation' => ['change', 'transformation', 'glow up', 'makeover'],
                'reaction' => ['reaction', 'review', 'opinion', 'thought'],
                'tutorial' => ['tutorial', 'guide', 'lesson', 'how to'],
                'vlog' => ['day', 'life', 'vlog', 'blog'],
                'fashion' => ['outfit', 'style', 'look', 'fashion'],
                'tech' => ['gadget', 'device', 'tech', 'innovation']
            ]
            : [
                'vocal' => ['голос', 'вокал', 'пение', 'звук'],
                'music' => ['мелодия', 'музыка', 'композиция', 'звук'],
                'aesthetic' => ['визуал', 'красота', 'эстетика', 'свет'],
                'ambience' => ['атмосфера', 'настроение', 'погружение', 'ощущение'],
                'dance' => ['танец', 'танцы', 'движение', 'хореография'],
                'comedy' => ['юмор', 'шутка', 'прикол', 'смех'],
                'cooking' => ['рецепт', 'блюдо', 'еда', 'кухня'],
                'fitness' => ['тренировка', 'упражнение', 'спорт', 'фитнес'],
                'beauty' => ['образ', 'стиль', 'макияж', 'красота'],
                'gaming' => ['игра', 'гейминг', 'прохождение', 'стрим'],
                'pets' => ['питомец', 'животное', 'друг', 'компаньон'],
                'travel' => ['поездка', 'путешествие', 'приключение', 'отпуск'],
                'diy' => ['поделка', 'проект', 'творчество', 'рукоделие'],
                'lifehack' => ['совет', 'трюк', 'лайфхак', 'секрет'],
                'motivation' => ['вдохновение', 'успех', 'цель', 'мечта'],
                'asmr' => ['звук', 'шепот', 'релакс', 'спокойствие'],
                'prank' => ['пранк', 'шутка', 'трюк', 'сюрприз'],
                'challenge' => ['челлендж', 'вызов', 'испытание', 'попробуй'],
                'transformation' => ['изменение', 'трансформация', 'превращение', 'метаморфоза'],
                'reaction' => ['реакция', 'отзыв', 'мнение', 'мысль'],
                'tutorial' => ['урок', 'инструкция', 'обучение', 'как сделать'],
                'vlog' => ['день', 'жизнь', 'влог', 'блог'],
                'fashion' => ['лук', 'стиль', 'образ', 'мода'],
                'tech' => ['гаджет', 'устройство', 'технологии', 'новинка']
            ];
        $list = $words[$contentType] ?? $words['vocal'];
        return $list[array_rand($list)];
    }

    private function getEmotionWord(string $mood, string $language = 'ru'): string
    {
        $words = $language === 'en'
            ? [
                'calm' => ['calm', 'soft', 'gentle', 'peaceful'],
                'emotional' => ['emotional', 'touching', 'deep', 'heartfelt'],
                'romantic' => ['romantic', 'tender', 'sweet', 'dreamy'],
                'mysterious' => ['mysterious', 'enigmatic', 'secret', 'haunting']
            ]
            : [
                'calm' => ['спокойный', 'мягкий', 'нежный', 'умиротворяющий'],
                'emotional' => ['эмоциональный', 'трогательный', 'глубокий', 'душевный'],
                'romantic' => ['романтический', 'нежный', 'чувственный', 'лирический'],
                'mysterious' => ['загадочный', 'мистический', 'таинственный', 'непонятный']
            ];
        $list = $words[$mood] ?? $words['calm'];
        return $list[array_rand($list)];
    }

    private function getVisualWord(string $visualFocus, string $language = 'ru'): string
    {
        $words = $language === 'en'
            ? [
                'neon' => ['neon', 'bright', 'colorful', 'glowing'],
                'night' => ['night', 'dark', 'moonlit', 'starry'],
                'closeup' => ['close', 'intimate', 'detailed', 'tight'],
                'atmosphere' => ['atmospheric', 'spacious', 'immersive', 'ambient']
            ]
            : [
                'neon' => ['неоновый', 'яркий', 'цветной', 'светящийся'],
                'night' => ['ночной', 'тёмный', 'лунный', 'звёздный'],
                'closeup' => ['крупный', 'близкий', 'детальный', 'интимный'],
                'atmosphere' => ['атмосферный', 'пространственный', 'объёмный', 'погружающий']
            ];
        $list = $words[$visualFocus] ?? $words['neon'];
        return $list[array_rand($list)];
    }

    private function getQuestionWord(string $contentType, string $language = 'ru'): string
    {
        $questions = $language === 'en'
            ? [
                'vocal' => ['How is the voice?', 'Did the vocals hook you?', 'Loved the singing?'],
                'music' => ['How is the melody?', 'Does the music hit?', 'Sound good?'],
                'aesthetic' => ['Love the visuals?', 'Looks amazing?', 'Aesthetic on point?'],
                'ambience' => ['Feel the atmosphere?', 'Did the vibe land?', 'Immersive enough?']
            ]
            : [
                'vocal' => ['Как голос?', 'Залип на пение?', 'Вокал зацепил?'],
                'music' => ['Мелодия хороша?', 'Музыка цепляет?', 'Звук нравится?'],
                'aesthetic' => ['Визуал красивый?', 'Картинка зацепила?', 'Эстетика понравилась?'],
                'ambience' => ['Атмосфера чувствуется?', 'Настроение передалось?', 'Погружение удалось?']
            ];
        $list = $questions[$contentType] ?? $questions['vocal'];
        return $list[array_rand($list)];
    }

    private function getRandomEmoji(string $mood, int $count = 1): string
    {
        $emojis = self::EMOJI_SETS[$mood] ?? self::EMOJI_SETS['calm'];
        shuffle($emojis);
        return implode('', array_slice($emojis, 0, $count));
    }

    /**
     * Фильтрация русских слов из текста (для английских результатов)
     */
    private function filterRussianWords(string $text): string
    {
        // Разбиваем текст на слова
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $filteredWords = [];
        
        foreach ($words as $word) {
            // Проверяем, содержит ли слово кириллицу
            if (!preg_match('/[а-яё]/iu', $word)) {
                $filteredWords[] = $word;
            } else {
                error_log("AutoShortsGenerator::filterRussianWords: Removed Russian word: '{$word}'");
            }
        }
        
        // Собираем обратно, сохраняя пробелы и знаки препинания
        $result = implode(' ', $filteredWords);
        
        // Очищаем множественные пробелы
        $result = preg_replace('/\s+/u', ' ', $result);
        $result = trim($result);
        
        return $result;
    }

    private function regenerateTitle(array $content): string
    {
        // Простая перегенерация - добавляем вариацию с учетом языка
        $language = $content['language'] ?? 'ru';
        $variations = $language === 'en'
            ? ['just', 'very', 'such', 'this', 'real']
            : ['просто', 'очень', 'такой', 'этот', 'настоящий'];
        $variation = $variations[array_rand($variations)];

        return $variation . ' ' . lcfirst($content['title']);
    }

    private function regenerateDescription(array $content): string
    {
        // Меняем тип описания
        $types = ['question', 'emotional', 'mysterious'];
        $newType = $types[array_rand($types)];

        $language = $content['language'] ?? 'ru';
        $templates = $language === 'en'
            ? (self::DESCRIPTION_TEMPLATES_EN[$newType] ?? self::DESCRIPTION_TEMPLATES_EN['question'])
            : self::DESCRIPTION_TEMPLATES[$newType];
        return $templates[array_rand($templates)];
    }

    private function regenerateEmoji(array $content): string
    {
        return rand(0, 1) ? $this->getRandomEmoji('calm', rand(1, 2)) : '';
    }

    private function addToHistory(array $content): void
    {
        self::$generationHistory[] = $content;

        // Ограничиваем историю последними 100 генерациями
        if (count(self::$generationHistory) > 100) {
            array_shift(self::$generationHistory);
        }
    }
}