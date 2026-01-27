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
        'tech' => ['технологии', 'гаджет', 'техника', 'устройство', 'новинка', 'обзор', 'тест', 'инновация'],
        'art' => ['рисунок', 'рисую', 'арт', 'художник', 'картина', 'творчество', 'иллюстрация', 'скетч'],
        'photography' => ['фото', 'фотография', 'снимок', 'камера', 'фотограф', 'портрет', 'пейзаж', 'макросъемка'],
        'sports' => ['спорт', 'футбол', 'баскетбол', 'теннис', 'бокс', 'бег', 'плавание', 'олимпиада'],
        'cars' => ['машина', 'авто', 'автомобиль', 'драйв', 'гонка', 'тюнинг', 'мотоцикл', 'спорткар'],
        'food' => ['еда', 'блюдо', 'ресторан', 'десерт', 'завтрак', 'обед', 'ужин', 'перекус'],
        'drinks' => ['напиток', 'коктейль', 'кофе', 'чай', 'сок', 'смузи', 'лимонад', 'энергетик'],
        'home' => ['дом', 'интерьер', 'ремонт', 'дизайн', 'мебель', 'декор', 'уют', 'комната'],
        'garden' => ['сад', 'огород', 'растения', 'цветы', 'овощи', 'фрукты', 'рассада', 'урожай'],
        'health' => ['здоровье', 'медицина', 'лечение', 'врач', 'больница', 'таблетки', 'терапия', 'диагностика'],
        'education' => ['учеба', 'школа', 'университет', 'студент', 'экзамен', 'урок', 'лекция', 'знания'],
        'science' => ['наука', 'эксперимент', 'исследование', 'лаборатория', 'открытие', 'ученый', 'теория', 'практика'],
        'history' => ['история', 'прошлое', 'древность', 'событие', 'война', 'мир', 'цивилизация', 'эпоха'],
        'nature' => ['природа', 'лес', 'море', 'горы', 'река', 'озеро', 'животные', 'растения'],
        'weather' => ['погода', 'дождь', 'снег', 'солнце', 'ветер', 'туман', 'гроза', 'радуга'],
        'space' => ['космос', 'звезды', 'планета', 'галактика', 'ракета', 'астронавт', 'луна', 'солнце'],
        'animals' => ['животное', 'зверь', 'птица', 'рыба', 'насекомое', 'рептилия', 'млекопитающее', 'дикая природа'],
        'plants' => ['растение', 'дерево', 'куст', 'трава', 'цветок', 'лист', 'корень', 'семя'],
        'ocean' => ['океан', 'море', 'волна', 'пляж', 'песок', 'ракушка', 'коралл', 'рыба'],
        'mountains' => ['гора', 'вершина', 'скала', 'альпинизм', 'поход', 'тропа', 'кемпинг', 'природа'],
        'city' => ['город', 'улица', 'здание', 'небоскреб', 'площадь', 'парк', 'метро', 'транспорт'],
        'nightlife' => ['ночь', 'клуб', 'вечеринка', 'танцы', 'музыка', 'бар', 'дискотека', 'развлечения'],
        'wedding' => ['свадьба', 'жених', 'невеста', 'церемония', 'торт', 'платье', 'кольцо', 'любовь'],
        'birthday' => ['день рождения', 'праздник', 'торт', 'подарок', 'воздушные шары', 'конфетти', 'веселье', 'друзья'],
        'holiday' => ['праздник', 'отпуск', 'каникулы', 'выходной', 'отдых', 'развлечение', 'веселье', 'радость'],
        'celebration' => ['празднование', 'торжество', 'поздравление', 'тост', 'шампанское', 'радость', 'веселье', 'счастье'],
        'music_instrument' => ['инструмент', 'гитара', 'пианино', 'скрипка', 'барабан', 'флейта', 'саксофон', 'музыка'],
        'singing' => ['пение', 'песня', 'вокал', 'хор', 'концерт', 'выступление', 'микрофон', 'сцена'],
        'dancing' => ['танец', 'балет', 'хип-хоп', 'брейк-данс', 'сальса', 'вальс', 'танго', 'ритм'],
        'theater' => ['театр', 'спектакль', 'актер', 'сцена', 'представление', 'драма', 'комедия', 'музыкал'],
        'movie' => ['кино', 'фильм', 'актер', 'режиссер', 'сценарий', 'премьера', 'кинотеатр', 'попкорн'],
        'tv_show' => ['сериал', 'шоу', 'телевидение', 'эпизод', 'сезон', 'герой', 'сюжет', 'рейтинг'],
        'book' => ['книга', 'чтение', 'библиотека', 'автор', 'роман', 'повесть', 'рассказ', 'литература'],
        'poetry' => ['поэзия', 'стих', 'поэт', 'рифма', 'строфа', 'вдохновение', 'лирика', 'слово'],
        'writing' => ['письмо', 'текст', 'автор', 'рукопись', 'рассказ', 'статья', 'блог', 'дневник'],
        'drawing' => ['рисунок', 'карандаш', 'краски', 'кисть', 'холст', 'эскиз', 'набросок', 'иллюстрация'],
        'painting' => ['живопись', 'картина', 'масло', 'акварель', 'пастель', 'галерея', 'выставка', 'художник'],
        'sculpture' => ['скульптура', 'статуя', 'мрамор', 'бронза', 'глина', 'форма', 'объем', 'искусство'],
        'craft' => ['ремесло', 'рукоделие', 'вязание', 'шитье', 'вышивка', 'бисер', 'декор', 'творчество'],
        'jewelry' => ['украшение', 'кольцо', 'серьги', 'браслет', 'ожерелье', 'драгоценность', 'золото', 'серебро'],
        'fashion_style' => ['стиль', 'мода', 'тренд', 'лук', 'образ', 'гардероб', 'одежда', 'аксессуар'],
        'makeup' => ['макияж', 'косметика', 'помада', 'тушь', 'тональный', 'румяна', 'блеск', 'кисть'],
        'hairstyle' => ['прическа', 'стрижка', 'укладка', 'окрашивание', 'волосы', 'парикмахер', 'салон', 'стилист'],
        'nail_art' => ['маникюр', 'педикюр', 'лак', 'дизайн', 'ногти', 'нейл-арт', 'стразы', 'рисунок'],
        'skincare' => ['уход', 'кожа', 'крем', 'маска', 'сыворотка', 'очищение', 'увлажнение', 'омоложение'],
        'fitness_workout' => ['тренировка', 'упражнение', 'фитнес', 'спортзал', 'тренажер', 'гантели', 'кардио', 'силовая'],
        'yoga' => ['йога', 'медитация', 'растяжка', 'поза', 'дыхание', 'релакс', 'гибкость', 'баланс'],
        'running' => ['бег', 'марафон', 'тренировка', 'стадион', 'дорожка', 'кроссовки', 'выносливость', 'скорость'],
        'swimming' => ['плавание', 'бассейн', 'вода', 'брасс', 'кроль', 'баттерфляй', 'ныряние', 'тренировка'],
        'cycling' => ['велосипед', 'езда', 'велотренировка', 'шоссе', 'горы', 'спорт', 'выносливость', 'скорость'],
        'martial_arts' => ['боевые искусства', 'карате', 'дзюдо', 'бокс', 'тайский бокс', 'самбо', 'тренировка', 'техника'],
        'extreme_sports' => ['экстремальный спорт', 'сноуборд', 'скейт', 'паркур', 'альпинизм', 'рафтинг', 'бейсджампинг', 'адреналин']
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
        'tech' => ['tech', 'technology', 'gadget', 'device', 'review', 'test', 'innovation'],
        'art' => ['art', 'drawing', 'painting', 'artist', 'canvas', 'sketch', 'illustration', 'creative'],
        'photography' => ['photo', 'photography', 'camera', 'photographer', 'portrait', 'landscape', 'macro', 'shot'],
        'sports' => ['sports', 'football', 'basketball', 'tennis', 'boxing', 'running', 'swimming', 'olympics'],
        'cars' => ['car', 'auto', 'automobile', 'drive', 'race', 'tuning', 'motorcycle', 'sportscar'],
        'food' => ['food', 'dish', 'restaurant', 'dessert', 'breakfast', 'lunch', 'dinner', 'snack'],
        'drinks' => ['drink', 'cocktail', 'coffee', 'tea', 'juice', 'smoothie', 'lemonade', 'energy'],
        'home' => ['home', 'interior', 'renovation', 'design', 'furniture', 'decor', 'cozy', 'room'],
        'garden' => ['garden', 'plants', 'flowers', 'vegetables', 'fruits', 'seedling', 'harvest', 'growing'],
        'health' => ['health', 'medicine', 'treatment', 'doctor', 'hospital', 'pills', 'therapy', 'diagnosis'],
        'education' => ['education', 'school', 'university', 'student', 'exam', 'lesson', 'lecture', 'knowledge'],
        'science' => ['science', 'experiment', 'research', 'laboratory', 'discovery', 'scientist', 'theory', 'practice'],
        'history' => ['history', 'past', 'ancient', 'event', 'war', 'peace', 'civilization', 'era'],
        'nature' => ['nature', 'forest', 'sea', 'mountains', 'river', 'lake', 'animals', 'plants'],
        'weather' => ['weather', 'rain', 'snow', 'sun', 'wind', 'fog', 'storm', 'rainbow'],
        'space' => ['space', 'stars', 'planet', 'galaxy', 'rocket', 'astronaut', 'moon', 'sun'],
        'animals' => ['animal', 'beast', 'bird', 'fish', 'insect', 'reptile', 'mammal', 'wildlife'],
        'plants' => ['plant', 'tree', 'bush', 'grass', 'flower', 'leaf', 'root', 'seed'],
        'ocean' => ['ocean', 'sea', 'wave', 'beach', 'sand', 'shell', 'coral', 'fish'],
        'mountains' => ['mountain', 'peak', 'rock', 'climbing', 'hiking', 'trail', 'camping', 'nature'],
        'city' => ['city', 'street', 'building', 'skyscraper', 'square', 'park', 'metro', 'transport'],
        'nightlife' => ['night', 'club', 'party', 'dancing', 'music', 'bar', 'disco', 'entertainment'],
        'wedding' => ['wedding', 'bride', 'groom', 'ceremony', 'cake', 'dress', 'ring', 'love'],
        'birthday' => ['birthday', 'celebration', 'cake', 'gift', 'balloons', 'confetti', 'fun', 'friends'],
        'holiday' => ['holiday', 'vacation', 'break', 'day off', 'rest', 'entertainment', 'fun', 'joy'],
        'celebration' => ['celebration', 'festivity', 'congratulations', 'toast', 'champagne', 'joy', 'fun', 'happiness'],
        'music_instrument' => ['instrument', 'guitar', 'piano', 'violin', 'drum', 'flute', 'saxophone', 'music'],
        'singing' => ['singing', 'song', 'vocal', 'choir', 'concert', 'performance', 'microphone', 'stage'],
        'dancing' => ['dance', 'ballet', 'hip-hop', 'breakdance', 'salsa', 'waltz', 'tango', 'rhythm'],
        'theater' => ['theater', 'play', 'actor', 'stage', 'performance', 'drama', 'comedy', 'musical'],
        'movie' => ['movie', 'film', 'actor', 'director', 'script', 'premiere', 'cinema', 'popcorn'],
        'tv_show' => ['tv show', 'series', 'television', 'episode', 'season', 'character', 'plot', 'rating'],
        'book' => ['book', 'reading', 'library', 'author', 'novel', 'story', 'tale', 'literature'],
        'poetry' => ['poetry', 'poem', 'poet', 'rhyme', 'verse', 'inspiration', 'lyrics', 'word'],
        'writing' => ['writing', 'text', 'author', 'manuscript', 'story', 'article', 'blog', 'diary'],
        'drawing' => ['drawing', 'pencil', 'paint', 'brush', 'canvas', 'sketch', 'draft', 'illustration'],
        'painting' => ['painting', 'picture', 'oil', 'watercolor', 'pastel', 'gallery', 'exhibition', 'artist'],
        'sculpture' => ['sculpture', 'statue', 'marble', 'bronze', 'clay', 'form', 'volume', 'art'],
        'craft' => ['craft', 'handicraft', 'knitting', 'sewing', 'embroidery', 'beads', 'decor', 'creativity'],
        'jewelry' => ['jewelry', 'ring', 'earrings', 'bracelet', 'necklace', 'gem', 'gold', 'silver'],
        'fashion_style' => ['style', 'fashion', 'trend', 'look', 'outfit', 'wardrobe', 'clothing', 'accessory'],
        'makeup' => ['makeup', 'cosmetics', 'lipstick', 'mascara', 'foundation', 'blush', 'gloss', 'brush'],
        'hairstyle' => ['hairstyle', 'haircut', 'styling', 'coloring', 'hair', 'hairdresser', 'salon', 'stylist'],
        'nail_art' => ['manicure', 'pedicure', 'nail polish', 'design', 'nails', 'nail art', 'rhinestones', 'pattern'],
        'skincare' => ['skincare', 'skin', 'cream', 'mask', 'serum', 'cleansing', 'moisturizing', 'anti-aging'],
        'fitness_workout' => ['workout', 'exercise', 'fitness', 'gym', 'machine', 'dumbbells', 'cardio', 'strength'],
        'yoga' => ['yoga', 'meditation', 'stretching', 'pose', 'breathing', 'relax', 'flexibility', 'balance'],
        'running' => ['running', 'marathon', 'training', 'stadium', 'track', 'sneakers', 'endurance', 'speed'],
        'swimming' => ['swimming', 'pool', 'water', 'breaststroke', 'freestyle', 'butterfly', 'diving', 'training'],
        'cycling' => ['cycling', 'bike', 'riding', 'road', 'mountains', 'sport', 'endurance', 'speed'],
        'martial_arts' => ['martial arts', 'karate', 'judo', 'boxing', 'muay thai', 'sambo', 'training', 'technique'],
        'extreme_sports' => ['extreme sports', 'snowboard', 'skate', 'parkour', 'climbing', 'rafting', 'base jumping', 'adrenaline']
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
        ],
        'art' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = искусство',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать творить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'photography' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = кадр',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать снимать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'sports' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = адреналин',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать тренироваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'cars' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = скорость',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать ездить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'food' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = вкус',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать есть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'drinks' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = наслаждение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать пить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'home' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = уют',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать обустраивать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'garden' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = природа',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать выращивать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'health' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = здоровье',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать заботиться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'education' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = знания',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать учиться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'science' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = открытие',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать исследовать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'history' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = прошлое',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать изучать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'nature' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = красота',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать любоваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'weather' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = атмосфера',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать наблюдать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'space' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = космос',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать мечтать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'animals' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = милота',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'plants' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = жизнь',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать выращивать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'ocean' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = волны',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать слушать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'mountains' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = высота',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать подниматься',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'city' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = ритм',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать гулять',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'nightlife' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = веселье',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать танцевать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'wedding' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = любовь',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать радоваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'birthday' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = праздник',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать праздновать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'holiday' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = отдых',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать отдыхать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'celebration' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = радость',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать праздновать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'music_instrument' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = музыка',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать играть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'singing' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = голос',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать петь',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'dancing' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = движение',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать танцевать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'theater' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = сцена',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'movie' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = кино',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'tv_show' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = сериал',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать смотреть',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'book' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = история',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать читать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'poetry' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = слова',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать читать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'writing' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = текст',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать писать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'drawing' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = рисунок',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать рисовать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'painting' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = картина',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать рисовать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'sculpture' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = форма',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать творить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'craft' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = ремесло',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать мастерить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'jewelry' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = блеск',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать любоваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'fashion_style' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = стиль',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать экспериментировать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'makeup' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = красота',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать краситься',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'hairstyle' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = прическа',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать экспериментировать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'nail_art' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = дизайн',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать украшать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'skincare' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = уход',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать заботиться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'fitness_workout' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = сила',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать тренироваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'yoga' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = гармония',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать практиковать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'running' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = скорость',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать бегать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'swimming' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = вода',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать плавать',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'cycling' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = дорога',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать ездить',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'martial_arts' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = техника',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать тренироваться',
            '{visual} делает {content} {emotion}',
            'Этот {content} зацепил',
            '{emotion} {content} {visual}'
        ],
        'extreme_sports' => [
            'Этот {content} просто {emotion}',
            '{visual} + {content} = адреналин',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            'Не могу перестать рисковать',
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
        ],
        'art' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop creating', 'Who did it BEST?', 'This {content} is artistic', '{emotion} {content} in {visual}'],
        'photography' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop shooting', 'Who did it BEST?', 'This {content} is amazing', '{emotion} {content} in {visual}'],
        'sports' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop playing', 'Who did it BEST?', 'This {content} is intense', '{emotion} {content} in {visual}'],
        'cars' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop driving', 'Who did it BEST?', 'This {content} is fast', '{emotion} {content} in {visual}'],
        'food' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop eating', 'Who did it BEST?', 'This {content} looks delicious', '{emotion} {content} in {visual}'],
        'drinks' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop drinking', 'Who did it BEST?', 'This {content} is refreshing', '{emotion} {content} in {visual}'],
        'home' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop decorating', 'Who did it BEST?', 'This {content} is cozy', '{emotion} {content} in {visual}'],
        'garden' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop growing', 'Who did it BEST?', 'This {content} is beautiful', '{emotion} {content} in {visual}'],
        'health' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop caring', 'Who did it BEST?', 'This {content} is healthy', '{emotion} {content} in {visual}'],
        'education' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop learning', 'Who did it BEST?', 'This {content} is educational', '{emotion} {content} in {visual}'],
        'science' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop researching', 'Who did it BEST?', 'This {content} is fascinating', '{emotion} {content} in {visual}'],
        'history' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop studying', 'Who did it BEST?', 'This {content} is historical', '{emotion} {content} in {visual}'],
        'nature' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop admiring', 'Who did it BEST?', 'This {content} is beautiful', '{emotion} {content} in {visual}'],
        'weather' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop watching', 'Who did it BEST?', 'This {content} is atmospheric', '{emotion} {content} in {visual}'],
        'space' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop dreaming', 'Who did it BEST?', 'This {content} is cosmic', '{emotion} {content} in {visual}'],
        'animals' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop watching', 'Who did it BEST?', 'This {content} is cute', '{emotion} {content} in {visual}'],
        'plants' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop growing', 'Who did it BEST?', 'This {content} is alive', '{emotion} {content} in {visual}'],
        'ocean' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop listening', 'Who did it BEST?', 'This {content} is calming', '{emotion} {content} in {visual}'],
        'mountains' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop climbing', 'Who did it BEST?', 'This {content} is high', '{emotion} {content} in {visual}'],
        'city' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop walking', 'Who did it BEST?', 'This {content} is urban', '{emotion} {content} in {visual}'],
        'nightlife' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop dancing', 'Who did it BEST?', 'This {content} is fun', '{emotion} {content} in {visual}'],
        'wedding' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop celebrating', 'Who did it BEST?', 'This {content} is romantic', '{emotion} {content} in {visual}'],
        'birthday' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop partying', 'Who did it BEST?', 'This {content} is festive', '{emotion} {content} in {visual}'],
        'holiday' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop relaxing', 'Who did it BEST?', 'This {content} is vacation', '{emotion} {content} in {visual}'],
        'celebration' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop celebrating', 'Who did it BEST?', 'This {content} is joyful', '{emotion} {content} in {visual}'],
        'music_instrument' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop playing', 'Who did it BEST?', 'This {content} is musical', '{emotion} {content} in {visual}'],
        'singing' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop singing', 'Who did it BEST?', 'This {content} is vocal', '{emotion} {content} in {visual}'],
        'dancing' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop dancing', 'Who did it BEST?', 'This {content} is rhythmic', '{emotion} {content} in {visual}'],
        'theater' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop watching', 'Who did it BEST?', 'This {content} is dramatic', '{emotion} {content} in {visual}'],
        'movie' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop watching', 'Who did it BEST?', 'This {content} is cinematic', '{emotion} {content} in {visual}'],
        'tv_show' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop binging', 'Who did it BEST?', 'This {content} is addictive', '{emotion} {content} in {visual}'],
        'book' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop reading', 'Who did it BEST?', 'This {content} is literary', '{emotion} {content} in {visual}'],
        'poetry' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop reading', 'Who did it BEST?', 'This {content} is poetic', '{emotion} {content} in {visual}'],
        'writing' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop writing', 'Who did it BEST?', 'This {content} is creative', '{emotion} {content} in {visual}'],
        'drawing' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop drawing', 'Who did it BEST?', 'This {content} is artistic', '{emotion} {content} in {visual}'],
        'painting' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop painting', 'Who did it BEST?', 'This {content} is colorful', '{emotion} {content} in {visual}'],
        'sculpture' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop sculpting', 'Who did it BEST?', 'This {content} is sculptural', '{emotion} {content} in {visual}'],
        'craft' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop crafting', 'Who did it BEST?', 'This {content} is handmade', '{emotion} {content} in {visual}'],
        'jewelry' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop admiring', 'Who did it BEST?', 'This {content} is shiny', '{emotion} {content} in {visual}'],
        'fashion_style' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop styling', 'Who did it BEST?', 'This {content} is fashionable', '{emotion} {content} in {visual}'],
        'makeup' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop applying', 'Who did it BEST?', 'This {content} is glamorous', '{emotion} {content} in {visual}'],
        'hairstyle' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop styling', 'Who did it BEST?', 'This {content} is stylish', '{emotion} {content} in {visual}'],
        'nail_art' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop decorating', 'Who did it BEST?', 'This {content} is detailed', '{emotion} {content} in {visual}'],
        'skincare' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop caring', 'Who did it BEST?', 'This {content} is healthy', '{emotion} {content} in {visual}'],
        'fitness_workout' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop working out', 'Who did it BEST?', 'This {content} is intense', '{emotion} {content} in {visual}'],
        'yoga' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop practicing', 'Who did it BEST?', 'This {content} is peaceful', '{emotion} {content} in {visual}'],
        'running' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop running', 'Who did it BEST?', 'This {content} is fast', '{emotion} {content} in {visual}'],
        'swimming' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop swimming', 'Who did it BEST?', 'This {content} is refreshing', '{emotion} {content} in {visual}'],
        'cycling' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop cycling', 'Who did it BEST?', 'This {content} is energetic', '{emotion} {content} in {visual}'],
        'martial_arts' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop training', 'Who did it BEST?', 'This {content} is powerful', '{emotion} {content} in {visual}'],
        'extreme_sports' => ['This {content} is so {emotion}', '{visual} {content} vibes', 'Can\'t stop risking', 'Who did it BEST?', 'This {content} is extreme', '{emotion} {content} in {visual}']
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
        'tech' => ['#Shorts', '#Технологии', '#Гаджет', '#Обзор', '#Новинка'],
        'art' => ['#Shorts', '#Арт', '#Рисунок', '#Творчество', '#Искусство'],
        'photography' => ['#Shorts', '#Фото', '#Фотография', '#Камера', '#Снимок'],
        'sports' => ['#Shorts', '#Спорт', '#Тренировка', '#Игра', '#Соревнование'],
        'cars' => ['#Shorts', '#Машина', '#Авто', '#Драйв', '#Скорость'],
        'food' => ['#Shorts', '#Еда', '#Рецепт', '#Кухня', '#Вкусно'],
        'drinks' => ['#Shorts', '#Напиток', '#Коктейль', '#Кофе', '#Чай'],
        'home' => ['#Shorts', '#Дом', '#Интерьер', '#Дизайн', '#Уют'],
        'garden' => ['#Shorts', '#Сад', '#Растения', '#Цветы', '#Огород'],
        'health' => ['#Shorts', '#Здоровье', '#Медицина', '#Лечение', '#Уход'],
        'education' => ['#Shorts', '#Обучение', '#Школа', '#Университет', '#Знания'],
        'science' => ['#Shorts', '#Наука', '#Эксперимент', '#Исследование', '#Открытие'],
        'history' => ['#Shorts', '#История', '#Прошлое', '#Событие', '#Эпоха'],
        'nature' => ['#Shorts', '#Природа', '#Лес', '#Море', '#Горы'],
        'weather' => ['#Shorts', '#Погода', '#Дождь', '#Снег', '#Солнце'],
        'space' => ['#Shorts', '#Космос', '#Звезды', '#Планета', '#Галактика'],
        'animals' => ['#Shorts', '#Животные', '#Зверь', '#Птица', '#ДикаяПрирода'],
        'plants' => ['#Shorts', '#Растения', '#Дерево', '#Цветок', '#Сад'],
        'ocean' => ['#Shorts', '#Океан', '#Море', '#Волна', '#Пляж'],
        'mountains' => ['#Shorts', '#Горы', '#Альпинизм', '#Поход', '#Природа'],
        'city' => ['#Shorts', '#Город', '#Улица', '#Здание', '#Архитектура'],
        'nightlife' => ['#Shorts', '#Ночь', '#Клуб', '#Вечеринка', '#Танцы'],
        'wedding' => ['#Shorts', '#Свадьба', '#Любовь', '#Семья', '#Праздник'],
        'birthday' => ['#Shorts', '#ДеньРождения', '#Праздник', '#Торт', '#Подарок'],
        'holiday' => ['#Shorts', '#Праздник', '#Отпуск', '#Отдых', '#Каникулы'],
        'celebration' => ['#Shorts', '#Празднование', '#Торжество', '#Радость', '#Веселье'],
        'music_instrument' => ['#Shorts', '#Инструмент', '#Гитара', '#Пианино', '#Музыка'],
        'singing' => ['#Shorts', '#Пение', '#Вокал', '#Песня', '#Концерт'],
        'dancing' => ['#Shorts', '#Танец', '#Балет', '#ХипХоп', '#Ритм'],
        'theater' => ['#Shorts', '#Театр', '#Спектакль', '#Актер', '#Сцена'],
        'movie' => ['#Shorts', '#Кино', '#Фильм', '#Актер', '#Премьера'],
        'tv_show' => ['#Shorts', '#Сериал', '#Шоу', '#Телевидение', '#Эпизод'],
        'book' => ['#Shorts', '#Книга', '#Чтение', '#Автор', '#Литература'],
        'poetry' => ['#Shorts', '#Поэзия', '#Стих', '#Поэт', '#Рифма'],
        'writing' => ['#Shorts', '#Письмо', '#Текст', '#Автор', '#Блог'],
        'drawing' => ['#Shorts', '#Рисунок', '#Карандаш', '#Скетч', '#Иллюстрация'],
        'painting' => ['#Shorts', '#Живопись', '#Картина', '#Краски', '#Художник'],
        'sculpture' => ['#Shorts', '#Скульптура', '#Статуя', '#Искусство', '#Форма'],
        'craft' => ['#Shorts', '#Ремесло', '#Рукоделие', '#Вязание', '#Творчество'],
        'jewelry' => ['#Shorts', '#Украшение', '#Кольцо', '#Серьги', '#Драгоценность'],
        'fashion_style' => ['#Shorts', '#Мода', '#Стиль', '#Лук', '#Тренд'],
        'makeup' => ['#Shorts', '#Макияж', '#Косметика', '#Красота', '#Гламур'],
        'hairstyle' => ['#Shorts', '#Прическа', '#Стрижка', '#Укладка', '#Стиль'],
        'nail_art' => ['#Shorts', '#Маникюр', '#НейлАрт', '#Дизайн', '#Ногти'],
        'skincare' => ['#Shorts', '#Уход', '#Кожа', '#Крем', '#Красота'],
        'fitness_workout' => ['#Shorts', '#Тренировка', '#Фитнес', '#Спортзал', '#Упражнение'],
        'yoga' => ['#Shorts', '#Йога', '#Медитация', '#Растяжка', '#Гармония'],
        'running' => ['#Shorts', '#Бег', '#Марафон', '#Тренировка', '#Выносливость'],
        'swimming' => ['#Shorts', '#Плавание', '#Бассейн', '#Вода', '#Тренировка'],
        'cycling' => ['#Shorts', '#Велосипед', '#Езда', '#Спорт', '#Дорога'],
        'martial_arts' => ['#Shorts', '#БоевыеИскусства', '#Карате', '#Бокс', '#Техника'],
        'extreme_sports' => ['#Shorts', '#ЭкстремальныйСпорт', '#Адреналин', '#Риск', '#Скорость']
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
        'tech' => ['#Shorts', '#Tech', '#Technology', '#Gadget', '#Review'],
        'art' => ['#Shorts', '#Art', '#Drawing', '#Creative', '#Artist'],
        'photography' => ['#Shorts', '#Photo', '#Photography', '#Camera', '#Shot'],
        'sports' => ['#Shorts', '#Sports', '#Workout', '#Game', '#Competition'],
        'cars' => ['#Shorts', '#Car', '#Auto', '#Drive', '#Speed'],
        'food' => ['#Shorts', '#Food', '#Recipe', '#Kitchen', '#Delicious'],
        'drinks' => ['#Shorts', '#Drink', '#Cocktail', '#Coffee', '#Tea'],
        'home' => ['#Shorts', '#Home', '#Interior', '#Design', '#Cozy'],
        'garden' => ['#Shorts', '#Garden', '#Plants', '#Flowers', '#Growing'],
        'health' => ['#Shorts', '#Health', '#Medicine', '#Treatment', '#Care'],
        'education' => ['#Shorts', '#Education', '#School', '#University', '#Knowledge'],
        'science' => ['#Shorts', '#Science', '#Experiment', '#Research', '#Discovery'],
        'history' => ['#Shorts', '#History', '#Past', '#Event', '#Era'],
        'nature' => ['#Shorts', '#Nature', '#Forest', '#Sea', '#Mountains'],
        'weather' => ['#Shorts', '#Weather', '#Rain', '#Snow', '#Sun'],
        'space' => ['#Shorts', '#Space', '#Stars', '#Planet', '#Galaxy'],
        'animals' => ['#Shorts', '#Animals', '#Wildlife', '#Bird', '#Nature'],
        'plants' => ['#Shorts', '#Plants', '#Tree', '#Flower', '#Garden'],
        'ocean' => ['#Shorts', '#Ocean', '#Sea', '#Wave', '#Beach'],
        'mountains' => ['#Shorts', '#Mountains', '#Climbing', '#Hiking', '#Nature'],
        'city' => ['#Shorts', '#City', '#Street', '#Building', '#Architecture'],
        'nightlife' => ['#Shorts', '#Night', '#Club', '#Party', '#Dancing'],
        'wedding' => ['#Shorts', '#Wedding', '#Love', '#Family', '#Celebration'],
        'birthday' => ['#Shorts', '#Birthday', '#Party', '#Cake', '#Gift'],
        'holiday' => ['#Shorts', '#Holiday', '#Vacation', '#Rest', '#Break'],
        'celebration' => ['#Shorts', '#Celebration', '#Festivity', '#Joy', '#Fun'],
        'music_instrument' => ['#Shorts', '#Instrument', '#Guitar', '#Piano', '#Music'],
        'singing' => ['#Shorts', '#Singing', '#Vocal', '#Song', '#Concert'],
        'dancing' => ['#Shorts', '#Dance', '#Ballet', '#HipHop', '#Rhythm'],
        'theater' => ['#Shorts', '#Theater', '#Play', '#Actor', '#Stage'],
        'movie' => ['#Shorts', '#Movie', '#Film', '#Actor', '#Premiere'],
        'tv_show' => ['#Shorts', '#TVShow', '#Series', '#Television', '#Episode'],
        'book' => ['#Shorts', '#Book', '#Reading', '#Author', '#Literature'],
        'poetry' => ['#Shorts', '#Poetry', '#Poem', '#Poet', '#Rhyme'],
        'writing' => ['#Shorts', '#Writing', '#Text', '#Author', '#Blog'],
        'drawing' => ['#Shorts', '#Drawing', '#Pencil', '#Sketch', '#Illustration'],
        'painting' => ['#Shorts', '#Painting', '#Picture', '#Paint', '#Artist'],
        'sculpture' => ['#Shorts', '#Sculpture', '#Statue', '#Art', '#Form'],
        'craft' => ['#Shorts', '#Craft', '#Handicraft', '#Knitting', '#Creative'],
        'jewelry' => ['#Shorts', '#Jewelry', '#Ring', '#Earrings', '#Gem'],
        'fashion_style' => ['#Shorts', '#Fashion', '#Style', '#Look', '#Trend'],
        'makeup' => ['#Shorts', '#Makeup', '#Cosmetics', '#Beauty', '#Glam'],
        'hairstyle' => ['#Shorts', '#Hairstyle', '#Haircut', '#Styling', '#Style'],
        'nail_art' => ['#Shorts', '#Manicure', '#NailArt', '#Design', '#Nails'],
        'skincare' => ['#Shorts', '#Skincare', '#Skin', '#Cream', '#Beauty'],
        'fitness_workout' => ['#Shorts', '#Workout', '#Fitness', '#Gym', '#Exercise'],
        'yoga' => ['#Shorts', '#Yoga', '#Meditation', '#Stretching', '#Harmony'],
        'running' => ['#Shorts', '#Running', '#Marathon', '#Training', '#Endurance'],
        'swimming' => ['#Shorts', '#Swimming', '#Pool', '#Water', '#Training'],
        'cycling' => ['#Shorts', '#Cycling', '#Bike', '#Sport', '#Road'],
        'martial_arts' => ['#Shorts', '#MartialArts', '#Karate', '#Boxing', '#Technique'],
        'extreme_sports' => ['#Shorts', '#ExtremeSports', '#Adrenaline', '#Risk', '#Speed']
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
        ],
        'art' => ['Как тебе рисунок?', 'Арт зацепил?', 'Хочешь ещё такого творчества?', 'Залип на искусство?', 'Стоит продолжать творить?'],
        'photography' => ['Как тебе фото?', 'Снимок зацепил?', 'Хочешь ещё таких кадров?', 'Залип на фотографию?', 'Стоит продолжать снимать?'],
        'sports' => ['Как тебе спорт?', 'Тренировка зацепила?', 'Хочешь ещё таких упражнений?', 'Залип на игру?', 'Стоит продолжать тренироваться?'],
        'cars' => ['Как тебе машина?', 'Авто зацепило?', 'Хочешь ещё таких обзоров?', 'Залип на скорость?', 'Стоит продолжать ездить?'],
        'food' => ['Как тебе блюдо?', 'Еда зацепила?', 'Хочешь ещё таких рецептов?', 'Залип на готовку?', 'Стоит продолжать готовить?'],
        'drinks' => ['Как тебе напиток?', 'Коктейль зацепил?', 'Хочешь ещё таких рецептов?', 'Залип на вкус?', 'Стоит продолжать пробовать?'],
        'home' => ['Как тебе интерьер?', 'Дизайн зацепил?', 'Хочешь ещё таких идей?', 'Залип на уют?', 'Стоит продолжать обустраивать?'],
        'garden' => ['Как тебе сад?', 'Растения зацепили?', 'Хочешь ещё таких советов?', 'Залип на выращивание?', 'Стоит продолжать сажать?'],
        'health' => ['Как тебе совет?', 'Здоровье зацепило?', 'Хочешь ещё таких рекомендаций?', 'Залип на уход?', 'Стоит продолжать заботиться?'],
        'education' => ['Как тебе урок?', 'Обучение зацепило?', 'Хочешь ещё таких знаний?', 'Залип на учебу?', 'Стоит продолжать учиться?'],
        'science' => ['Как тебе эксперимент?', 'Наука зацепила?', 'Хочешь ещё таких опытов?', 'Залип на исследования?', 'Стоит продолжать изучать?'],
        'history' => ['Как тебе история?', 'Прошлое зацепило?', 'Хочешь ещё таких фактов?', 'Залип на события?', 'Стоит продолжать изучать?'],
        'nature' => ['Как тебе природа?', 'Красота зацепила?', 'Хочешь ещё таких видов?', 'Залип на пейзаж?', 'Стоит продолжать снимать?'],
        'weather' => ['Как тебе погода?', 'Атмосфера зацепила?', 'Хочешь ещё таких кадров?', 'Залип на небо?', 'Стоит продолжать наблюдать?'],
        'space' => ['Как тебе космос?', 'Звезды зацепили?', 'Хочешь ещё таких видео?', 'Залип на вселенную?', 'Стоит продолжать мечтать?'],
        'animals' => ['Как тебе животное?', 'Зверь зацепил?', 'Хочешь ещё таких видео?', 'Залип на милоту?', 'Стоит продолжать снимать?'],
        'plants' => ['Как тебе растение?', 'Цветок зацепил?', 'Хочешь ещё таких советов?', 'Залип на сад?', 'Стоит продолжать выращивать?'],
        'ocean' => ['Как тебе океан?', 'Волны зацепили?', 'Хочешь ещё таких звуков?', 'Залип на море?', 'Стоит продолжать слушать?'],
        'mountains' => ['Как тебе горы?', 'Вершина зацепила?', 'Хочешь ещё таких походов?', 'Залип на природу?', 'Стоит продолжать подниматься?'],
        'city' => ['Как тебе город?', 'Улица зацепила?', 'Хочешь ещё таких прогулок?', 'Залип на архитектуру?', 'Стоит продолжать гулять?'],
        'nightlife' => ['Как тебе клуб?', 'Вечеринка зацепила?', 'Хочешь ещё таких ночей?', 'Залип на танцы?', 'Стоит продолжать веселиться?'],
        'wedding' => ['Как тебе свадьба?', 'Любовь зацепила?', 'Хочешь ещё таких моментов?', 'Залип на праздник?', 'Стоит продолжать радоваться?'],
        'birthday' => ['Как тебе день рождения?', 'Праздник зацепил?', 'Хочешь ещё таких тортов?', 'Залип на веселье?', 'Стоит продолжать праздновать?'],
        'holiday' => ['Как тебе отпуск?', 'Отдых зацепил?', 'Хочешь ещё таких каникул?', 'Залип на расслабление?', 'Стоит продолжать отдыхать?'],
        'celebration' => ['Как тебе празднование?', 'Торжество зацепило?', 'Хочешь ещё таких моментов?', 'Залип на радость?', 'Стоит продолжать праздновать?'],
        'music_instrument' => ['Как тебе инструмент?', 'Музыка зацепила?', 'Хочешь ещё таких мелодий?', 'Залип на звук?', 'Стоит продолжать играть?'],
        'singing' => ['Как тебе пение?', 'Вокал зацепил?', 'Хочешь ещё таких песен?', 'Залип на голос?', 'Стоит продолжать петь?'],
        'dancing' => ['Как тебе танец?', 'Движение зацепило?', 'Хочешь ещё таких танцев?', 'Залип на ритм?', 'Стоит продолжать танцевать?'],
        'theater' => ['Как тебе спектакль?', 'Сцена зацепила?', 'Хочешь ещё таких представлений?', 'Залип на актера?', 'Стоит продолжать смотреть?'],
        'movie' => ['Как тебе фильм?', 'Кино зацепило?', 'Хочешь ещё таких премьер?', 'Залип на сюжет?', 'Стоит продолжать смотреть?'],
        'tv_show' => ['Как тебе сериал?', 'Шоу зацепило?', 'Хочешь ещё таких эпизодов?', 'Залип на героя?', 'Стоит продолжать смотреть?'],
        'book' => ['Как тебе книга?', 'Чтение зацепило?', 'Хочешь ещё таких историй?', 'Залип на сюжет?', 'Стоит продолжать читать?'],
        'poetry' => ['Как тебе стих?', 'Поэзия зацепила?', 'Хочешь ещё таких строк?', 'Залип на рифму?', 'Стоит продолжать читать?'],
        'writing' => ['Как тебе текст?', 'Письмо зацепило?', 'Хочешь ещё таких статей?', 'Залип на автора?', 'Стоит продолжать писать?'],
        'drawing' => ['Как тебе рисунок?', 'Скетч зацепил?', 'Хочешь ещё таких иллюстраций?', 'Залип на технику?', 'Стоит продолжать рисовать?'],
        'painting' => ['Как тебе картина?', 'Живопись зацепила?', 'Хочешь ещё таких работ?', 'Залип на краски?', 'Стоит продолжать рисовать?'],
        'sculpture' => ['Как тебе скульптура?', 'Форма зацепила?', 'Хочешь ещё таких работ?', 'Залип на искусство?', 'Стоит продолжать творить?'],
        'craft' => ['Как тебе поделка?', 'Ремесло зацепило?', 'Хочешь ещё таких идей?', 'Залип на творчество?', 'Стоит продолжать мастерить?'],
        'jewelry' => ['Как тебе украшение?', 'Блеск зацепил?', 'Хочешь ещё таких изделий?', 'Залип на драгоценности?', 'Стоит продолжать любоваться?'],
        'fashion_style' => ['Как тебе стиль?', 'Мода зацепила?', 'Хочешь ещё таких образов?', 'Залип на тренды?', 'Стоит продолжать экспериментировать?'],
        'makeup' => ['Как тебе макияж?', 'Красота зацепила?', 'Хочешь ещё таких образов?', 'Залип на косметику?', 'Стоит продолжать краситься?'],
        'hairstyle' => ['Как тебе прическа?', 'Стиль зацепил?', 'Хочешь ещё таких укладок?', 'Залип на волосы?', 'Стоит продолжать экспериментировать?'],
        'nail_art' => ['Как тебе маникюр?', 'Дизайн зацепил?', 'Хочешь ещё таких идей?', 'Залип на нейл-арт?', 'Стоит продолжать украшать?'],
        'skincare' => ['Как тебе уход?', 'Кожа зацепила?', 'Хочешь ещё таких советов?', 'Залип на красоту?', 'Стоит продолжать заботиться?'],
        'fitness_workout' => ['Как тебе тренировка?', 'Фитнес зацепил?', 'Хочешь ещё таких упражнений?', 'Залип на спорт?', 'Стоит продолжать тренироваться?'],
        'yoga' => ['Как тебе йога?', 'Медитация зацепила?', 'Хочешь ещё таких практик?', 'Залип на гармонию?', 'Стоит продолжать практиковать?'],
        'running' => ['Как тебе бег?', 'Скорость зацепила?', 'Хочешь ещё таких тренировок?', 'Залип на марафон?', 'Стоит продолжать бегать?'],
        'swimming' => ['Как тебе плавание?', 'Вода зацепила?', 'Хочешь ещё таких тренировок?', 'Залип на бассейн?', 'Стоит продолжать плавать?'],
        'cycling' => ['Как тебе велосипед?', 'Езда зацепила?', 'Хочешь ещё таких поездок?', 'Залип на дорогу?', 'Стоит продолжать ездить?'],
        'martial_arts' => ['Как тебе техника?', 'Боевые искусства зацепили?', 'Хочешь ещё таких тренировок?', 'Залип на силу?', 'Стоит продолжать тренироваться?'],
        'extreme_sports' => ['Как тебе экстрим?', 'Адреналин зацепил?', 'Хочешь ещё таких трюков?', 'Залип на риск?', 'Стоит продолжать рисковать?']
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
            'How\'s the dance?',
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
            'How\'s the recipe?',
            'Does it look good?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'fitness' => [
            'How\'s the workout?',
            'Did it motivate you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'beauty' => [
            'How\'s the look?',
            'Does it look amazing?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'gaming' => [
            'How\'s the game?',
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
            'How\'s the trip?',
            'Do you want to go there?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'diy' => [
            'How\'s the craft?',
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
            'How\'s the day?',
            'Do you relate?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'fashion' => [
            'How\'s the style?',
            'Do you like the outfit?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'tech' => [
            'How\'s the gadget?',
            'Do you want this?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'art' => ['How\'s the art?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'photography' => ['How\'s the photo?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'sports' => ['How\'s the game?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'cars' => ['How\'s the car?', 'Do you want this?', 'Want more like this?', 'Who did it BEST?'],
        'food' => ['How\'s the food?', 'Does it look good?', 'Want more like this?', 'Who did it BEST?'],
        'drinks' => ['How\'s the drink?', 'Does it look refreshing?', 'Want more like this?', 'Who did it BEST?'],
        'home' => ['How\'s the interior?', 'Do you like it?', 'Want more like this?', 'Who did it BEST?'],
        'garden' => ['How\'s the garden?', 'Do you like it?', 'Want more like this?', 'Who did it BEST?'],
        'health' => ['How\'s the tip?', 'Is it helpful?', 'Want more like this?', 'Who did it BEST?'],
        'education' => ['How\'s the lesson?', 'Is it helpful?', 'Want more like this?', 'Who did it BEST?'],
        'science' => ['How\'s the experiment?', 'Is it fascinating?', 'Want more like this?', 'Who did it BEST?'],
        'history' => ['How\'s the story?', 'Is it interesting?', 'Want more like this?', 'Who did it BEST?'],
        'nature' => ['How\'s the view?', 'Is it beautiful?', 'Want more like this?', 'Who did it BEST?'],
        'weather' => ['How\'s the weather?', 'Is it atmospheric?', 'Want more like this?', 'Who did it BEST?'],
        'space' => ['How\'s the space?', 'Is it cosmic?', 'Want more like this?', 'Who did it BEST?'],
        'animals' => ['How cute is this?', 'Did it make you smile?', 'Want more like this?', 'Who did it BEST?'],
        'plants' => ['How\'s the plant?', 'Is it beautiful?', 'Want more like this?', 'Who did it BEST?'],
        'ocean' => ['How\'s the ocean?', 'Is it calming?', 'Want more like this?', 'Who did it BEST?'],
        'mountains' => ['How\'s the view?', 'Is it amazing?', 'Want more like this?', 'Who did it BEST?'],
        'city' => ['How\'s the city?', 'Is it urban?', 'Want more like this?', 'Who did it BEST?'],
        'nightlife' => ['How\'s the party?', 'Is it fun?', 'Want more like this?', 'Who did it BEST?'],
        'wedding' => ['How\'s the wedding?', 'Is it romantic?', 'Want more like this?', 'Who did it BEST?'],
        'birthday' => ['How\'s the party?', 'Is it festive?', 'Want more like this?', 'Who did it BEST?'],
        'holiday' => ['How\'s the vacation?', 'Is it relaxing?', 'Want more like this?', 'Who did it BEST?'],
        'celebration' => ['How\'s the celebration?', 'Is it joyful?', 'Want more like this?', 'Who did it BEST?'],
        'music_instrument' => ['How\'s the music?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'singing' => ['How\'s the voice?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'dancing' => ['How\'s the dance?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'theater' => ['How\'s the play?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'movie' => ['How\'s the movie?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'tv_show' => ['How\'s the show?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'book' => ['How\'s the book?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'poetry' => ['How\'s the poem?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'writing' => ['How\'s the text?', 'Is it helpful?', 'Want more like this?', 'Who did it BEST?'],
        'drawing' => ['How\'s the drawing?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'painting' => ['How\'s the painting?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'sculpture' => ['How\'s the sculpture?', 'Did it hook you?', 'Want more like this?', 'Who did it BEST?'],
        'craft' => ['How\'s the craft?', 'Do you want to try?', 'Want more like this?', 'Who did it BEST?'],
        'jewelry' => ['How\'s the jewelry?', 'Do you like it?', 'Want more like this?', 'Who did it BEST?'],
        'fashion_style' => ['How\'s the style?', 'Do you like it?', 'Want more like this?', 'Who did it BEST?'],
        'makeup' => ['How\'s the look?', 'Does it look amazing?', 'Want more like this?', 'Who did it BEST?'],
        'hairstyle' => ['How\'s the hair?', 'Do you like it?', 'Want more like this?', 'Who did it BEST?'],
        'nail_art' => ['How\'s the design?', 'Do you like it?', 'Want more like this?', 'Who did it BEST?'],
        'skincare' => ['How\'s the tip?', 'Is it helpful?', 'Want more like this?', 'Who did it BEST?'],
        'fitness_workout' => ['How\'s the workout?', 'Did it motivate you?', 'Want more like this?', 'Who did it BEST?'],
        'yoga' => ['How\'s the practice?', 'Is it peaceful?', 'Want more like this?', 'Who did it BEST?'],
        'running' => ['How\'s the run?', 'Is it fast?', 'Want more like this?', 'Who did it BEST?'],
        'swimming' => ['How\'s the swim?', 'Is it refreshing?', 'Want more like this?', 'Who did it BEST?'],
        'cycling' => ['How\'s the ride?', 'Is it energetic?', 'Want more like this?', 'Who did it BEST?'],
        'martial_arts' => ['How\'s the technique?', 'Is it powerful?', 'Want more like this?', 'Who did it BEST?'],
        'extreme_sports' => ['How\'s the extreme?', 'Is it adrenaline?', 'Want more like this?', 'Who did it BEST?']
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
            
            // Получаем шаблоны для типа контента
            if ($language === 'en') {
                $templates = self::TITLE_TEMPLATES_EN[$contentType] ?? null;
                if (!$templates || empty($templates)) {
                    $templates = self::TITLE_TEMPLATES_EN['vocal'] ?? ['This {content} is so {emotion}'];
                }
            } else {
                $templates = self::TITLE_TEMPLATES[$contentType] ?? null;
                if (!$templates || empty($templates)) {
                    $templates = self::TITLE_TEMPLATES['vocal'] ?? ['Этот {content} просто {emotion}'];
                }
            }

            // Проверяем, что шаблоны не пустые
            if (empty($templates) || !is_array($templates)) {
                error_log("AutoShortsGenerator::generateTitle: No templates found for content_type: {$contentType}, language: {$language}, using fallback");
                $templates = $language === 'en' 
                    ? ['This {content} is so {emotion}']
                    : ['Этот {content} просто {emotion}'];
            }

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
            
            // Получаем шаблоны для типа описания
            if ($language === 'en') {
                $templates = self::DESCRIPTION_TEMPLATES_EN[$descType] ?? null;
                if (!$templates || empty($templates)) {
                    $templates = self::DESCRIPTION_TEMPLATES_EN['question'] ?? ['Watch this video! 🎬'];
                }
            } else {
                $templates = self::DESCRIPTION_TEMPLATES[$descType] ?? null;
                if (!$templates || empty($templates)) {
                    $templates = self::DESCRIPTION_TEMPLATES['question'] ?? ['Посмотрите это видео! 🎬'];
                }
            }

            // Проверяем, что шаблоны не пустые
            if (empty($templates) || !is_array($templates)) {
                error_log("AutoShortsGenerator::generateDescription: No templates found for desc_type: {$descType}, language: {$language}, using fallback");
                $templates = $language === 'en' 
                    ? ['Watch this video! 🎬']
                    : ['Посмотрите это видео! 🎬'];
            }

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
        $contentType = $intent['content_type'] ?? 'vocal';
        
        // Получаем теги для типа контента
        if ($language === 'en') {
            $baseTags = self::TAG_SETS_EN[$contentType] ?? null;
            if (!$baseTags || empty($baseTags)) {
                $baseTags = self::TAG_SETS_EN['vocal'] ?? ['#Shorts', '#Content'];
            }
        } else {
            $baseTags = self::TAG_SETS[$contentType] ?? null;
            if (!$baseTags || empty($baseTags)) {
                $baseTags = self::TAG_SETS['vocal'] ?? ['#Shorts', '#Контент'];
            }
        }
        
        // Проверяем, что теги не пустые
        if (empty($baseTags) || !is_array($baseTags)) {
            error_log("AutoShortsGenerator::generateTags: No tags found for content_type: {$contentType}, language: {$language}, using fallback");
            $baseTags = $language === 'en' 
                ? ['#Shorts', '#Content']
                : ['#Shorts', '#Контент'];
        }

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
        $contentType = $intent['content_type'] ?? 'vocal';
        
        // Получаем вопросы для типа контента
        if ($language === 'en') {
            $questions = self::ENGAGEMENT_QUESTIONS_EN[$contentType] ?? null;
            if (!$questions || empty($questions)) {
                $questions = self::ENGAGEMENT_QUESTIONS_EN['vocal'] ?? ['Want more like this?'];
            }
        } else {
            $questions = self::ENGAGEMENT_QUESTIONS[$contentType] ?? null;
            if (!$questions || empty($questions)) {
                $questions = self::ENGAGEMENT_QUESTIONS['vocal'] ?? ['Хочешь ещё такого?'];
            }
        }
        
        // Проверяем, что вопросы не пустые
        if (empty($questions) || !is_array($questions)) {
            error_log("AutoShortsGenerator::generatePinnedComment: No questions found for content_type: {$contentType}, language: {$language}, using fallback");
            $questions = $language === 'en' 
                ? ['Want more like this?']
                : ['Хочешь ещё такого?'];
        }
        
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
                'tech' => ['gadget', 'device', 'tech', 'innovation'],
                'art' => ['art', 'drawing', 'painting', 'creation'],
                'photography' => ['photo', 'shot', 'picture', 'image'],
                'sports' => ['sport', 'game', 'match', 'competition'],
                'cars' => ['car', 'auto', 'vehicle', 'machine'],
                'food' => ['food', 'dish', 'meal', 'cuisine'],
                'drinks' => ['drink', 'beverage', 'cocktail', 'liquid'],
                'home' => ['home', 'house', 'interior', 'space'],
                'garden' => ['garden', 'plants', 'flowers', 'nature'],
                'health' => ['health', 'wellness', 'care', 'treatment'],
                'education' => ['education', 'learning', 'knowledge', 'study'],
                'science' => ['science', 'experiment', 'research', 'discovery'],
                'history' => ['history', 'past', 'story', 'event'],
                'nature' => ['nature', 'wildlife', 'landscape', 'scenery'],
                'weather' => ['weather', 'climate', 'sky', 'atmosphere'],
                'space' => ['space', 'universe', 'cosmos', 'galaxy'],
                'animals' => ['animal', 'creature', 'beast', 'wildlife'],
                'plants' => ['plant', 'tree', 'flower', 'vegetation'],
                'ocean' => ['ocean', 'sea', 'wave', 'water'],
                'mountains' => ['mountain', 'peak', 'hill', 'summit'],
                'city' => ['city', 'urban', 'street', 'building'],
                'nightlife' => ['night', 'party', 'club', 'entertainment'],
                'wedding' => ['wedding', 'ceremony', 'celebration', 'love'],
                'birthday' => ['birthday', 'party', 'celebration', 'festivity'],
                'holiday' => ['holiday', 'vacation', 'break', 'rest'],
                'celebration' => ['celebration', 'festivity', 'party', 'joy'],
                'music_instrument' => ['instrument', 'music', 'sound', 'melody'],
                'singing' => ['singing', 'vocal', 'song', 'voice'],
                'dancing' => ['dance', 'movement', 'rhythm', 'choreography'],
                'theater' => ['theater', 'play', 'drama', 'performance'],
                'movie' => ['movie', 'film', 'cinema', 'picture'],
                'tv_show' => ['show', 'series', 'episode', 'program'],
                'book' => ['book', 'novel', 'story', 'literature'],
                'poetry' => ['poetry', 'poem', 'verse', 'rhyme'],
                'writing' => ['writing', 'text', 'article', 'story'],
                'drawing' => ['drawing', 'sketch', 'illustration', 'art'],
                'painting' => ['painting', 'picture', 'artwork', 'canvas'],
                'sculpture' => ['sculpture', 'statue', 'art', 'form'],
                'craft' => ['craft', 'handicraft', 'creation', 'art'],
                'jewelry' => ['jewelry', 'gem', 'treasure', 'ornament'],
                'fashion_style' => ['style', 'fashion', 'outfit', 'look'],
                'makeup' => ['makeup', 'cosmetics', 'beauty', 'glam'],
                'hairstyle' => ['hair', 'hairstyle', 'cut', 'style'],
                'nail_art' => ['nails', 'design', 'art', 'decoration'],
                'skincare' => ['skin', 'care', 'beauty', 'health'],
                'fitness_workout' => ['workout', 'exercise', 'training', 'fitness'],
                'yoga' => ['yoga', 'practice', 'meditation', 'harmony'],
                'running' => ['running', 'jogging', 'sprint', 'race'],
                'swimming' => ['swimming', 'pool', 'water', 'sport'],
                'cycling' => ['cycling', 'bike', 'ride', 'road'],
                'martial_arts' => ['martial arts', 'technique', 'training', 'power'],
                'extreme_sports' => ['extreme', 'adrenaline', 'risk', 'thrill']
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
                'tech' => ['гаджет', 'устройство', 'технологии', 'новинка'],
                'art' => ['арт', 'рисунок', 'картина', 'творчество'],
                'photography' => ['фото', 'снимок', 'кадр', 'изображение'],
                'sports' => ['спорт', 'игра', 'матч', 'соревнование'],
                'cars' => ['машина', 'авто', 'транспорт', 'механизм'],
                'food' => ['еда', 'блюдо', 'кухня', 'кулинария'],
                'drinks' => ['напиток', 'коктейль', 'жидкость', 'бeverage'],
                'home' => ['дом', 'жилье', 'интерьер', 'пространство'],
                'garden' => ['сад', 'растения', 'цветы', 'природа'],
                'health' => ['здоровье', 'благополучие', 'уход', 'лечение'],
                'education' => ['образование', 'обучение', 'знания', 'учеба'],
                'science' => ['наука', 'эксперимент', 'исследование', 'открытие'],
                'history' => ['история', 'прошлое', 'событие', 'рассказ'],
                'nature' => ['природа', 'дикая природа', 'пейзаж', 'ландшафт'],
                'weather' => ['погода', 'климат', 'небо', 'атмосфера'],
                'space' => ['космос', 'вселенная', 'галактика', 'пространство'],
                'animals' => ['животное', 'существо', 'зверь', 'дикая природа'],
                'plants' => ['растение', 'дерево', 'цветок', 'растительность'],
                'ocean' => ['океан', 'море', 'волна', 'вода'],
                'mountains' => ['гора', 'вершина', 'холм', 'пик'],
                'city' => ['город', 'урбан', 'улица', 'здание'],
                'nightlife' => ['ночь', 'вечеринка', 'клуб', 'развлечение'],
                'wedding' => ['свадьба', 'церемония', 'празднование', 'любовь'],
                'birthday' => ['день рождения', 'праздник', 'торжество', 'веселье'],
                'holiday' => ['праздник', 'отпуск', 'выходной', 'отдых'],
                'celebration' => ['празднование', 'торжество', 'вечеринка', 'радость'],
                'music_instrument' => ['инструмент', 'музыка', 'звук', 'мелодия'],
                'singing' => ['пение', 'вокал', 'песня', 'голос'],
                'dancing' => ['танец', 'движение', 'ритм', 'хореография'],
                'theater' => ['театр', 'спектакль', 'драма', 'представление'],
                'movie' => ['кино', 'фильм', 'картина', 'премьера'],
                'tv_show' => ['шоу', 'сериал', 'эпизод', 'программа'],
                'book' => ['книга', 'роман', 'история', 'литература'],
                'poetry' => ['поэзия', 'стих', 'стихотворение', 'рифма'],
                'writing' => ['письмо', 'текст', 'статья', 'рассказ'],
                'drawing' => ['рисунок', 'скетч', 'иллюстрация', 'арт'],
                'painting' => ['живопись', 'картина', 'произведение', 'холст'],
                'sculpture' => ['скульптура', 'статуя', 'искусство', 'форма'],
                'craft' => ['ремесло', 'рукоделие', 'творчество', 'арт'],
                'jewelry' => ['украшение', 'драгоценность', 'сокровище', 'орнамент'],
                'fashion_style' => ['стиль', 'мода', 'лук', 'образ'],
                'makeup' => ['макияж', 'косметика', 'красота', 'гламур'],
                'hairstyle' => ['волосы', 'прическа', 'стрижка', 'стиль'],
                'nail_art' => ['ногти', 'дизайн', 'арт', 'украшение'],
                'skincare' => ['кожа', 'уход', 'красота', 'здоровье'],
                'fitness_workout' => ['тренировка', 'упражнение', 'спорт', 'фитнес'],
                'yoga' => ['йога', 'практика', 'медитация', 'гармония'],
                'running' => ['бег', 'пробежка', 'спринт', 'гонка'],
                'swimming' => ['плавание', 'бассейн', 'вода', 'спорт'],
                'cycling' => ['велосипед', 'езда', 'дорога', 'поездка'],
                'martial_arts' => ['боевые искусства', 'техника', 'тренировка', 'сила'],
                'extreme_sports' => ['экстрим', 'адреналин', 'риск', 'острые ощущения']
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