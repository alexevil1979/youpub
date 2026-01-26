<?php

namespace App\Controllers;

use Core\Controller;
use App\Repositories\VideoRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\PublicationRepository;
use App\Repositories\YoutubeIntegrationRepository;

/**
 * Контроллер главной страницы
 */
class DashboardController extends Controller
{
    private VideoRepository $videoRepo;
    private ScheduleRepository $scheduleRepo;
    private PublicationRepository $publicationRepo;

    public function __construct()
    {
        parent::__construct();
        $this->videoRepo = new VideoRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->publicationRepo = new PublicationRepository();
    }

    /**
     * Главная страница дашборда
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'];
        
        $stats = [
            'videos_total' => $this->videoRepo->countByUserId($userId),
            'schedules_pending' => $this->scheduleRepo->countByUserIdAndStatus($userId, 'pending'),
            'publications_success' => $this->publicationRepo->countByUserIdAndStatus($userId, 'success'),
            'publications_failed' => $this->publicationRepo->countByUserIdAndStatus($userId, 'failed'),
        ];

        $recentVideos = $this->videoRepo->findByUserId($userId, ['created_at' => 'DESC'], 5);
        $upcomingSchedules = $this->scheduleRepo->findUpcoming($userId, 5);

        include __DIR__ . '/../../views/dashboard/index.php';
    }

    /**
     * Профиль пользователя
     */
    public function profile(): void
    {
        include __DIR__ . '/../../views/dashboard/profile.php';
    }

    /**
     * Интеграции
     */
    public function integrations(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                error_log('Integrations: User not authenticated');
                header('Location: /login');
                exit;
            }
            
            // Загружаем все интеграции для каждой платформы
            $youtubeAccounts = [];
            $telegramAccounts = [];
            $tiktokAccounts = [];
            $instagramAccounts = [];
            $pinterestAccounts = [];
            
            try {
                $youtubeRepo = new YoutubeIntegrationRepository();
                $youtubeAccounts = $youtubeRepo->findByUserId($userId);
            } catch (\Exception $e) {
                error_log('Integrations: Error loading YouTube accounts: ' . $e->getMessage());
            }
            
            try {
                $telegramRepo = new \App\Repositories\TelegramIntegrationRepository();
                $telegramAccounts = $telegramRepo->findByUserId($userId);
            } catch (\Exception $e) {
                error_log('Integrations: Error loading Telegram accounts: ' . $e->getMessage());
            }
            
            try {
                $tiktokRepo = new \App\Repositories\TiktokIntegrationRepository();
                $tiktokAccounts = $tiktokRepo->findByUserId($userId);
            } catch (\Exception $e) {
                error_log('Integrations: Error loading TikTok accounts: ' . $e->getMessage());
            }
            
            try {
                $instagramRepo = new \App\Repositories\InstagramIntegrationRepository();
                $instagramAccounts = $instagramRepo->findByUserId($userId);
            } catch (\Exception $e) {
                error_log('Integrations: Error loading Instagram accounts: ' . $e->getMessage());
            }
            
            try {
                $pinterestRepo = new \App\Repositories\PinterestIntegrationRepository();
                $pinterestAccounts = $pinterestRepo->findByUserId($userId);
            } catch (\Exception $e) {
                error_log('Integrations: Error loading Pinterest accounts: ' . $e->getMessage());
            }
            
            include __DIR__ . '/../../views/dashboard/integrations.php';
            
        } catch (\Throwable $e) {
            error_log('Integrations: Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Integrations: Stack trace: ' . $e->getTraceAsString());
            
            $_SESSION['error'] = 'Произошла ошибка при загрузке страницы интеграций.';
            header('Location: /dashboard');
            exit;
        }
    }

    /**
     * Подключение YouTube
     */
    public function youtubeConnect(): void
    {
        $userId = $_SESSION['user_id'];
        $clientId = $this->config['YOUTUBE_CLIENT_ID'];
        $redirectUri = $this->config['YOUTUBE_REDIRECT_URI'];

        if (empty($clientId)) {
            $_SESSION['error'] = 'YouTube Client ID не настроен. Обратитесь к администратору.';
            header('Location: /integrations');
            exit;
        }

        if (empty($redirectUri)) {
            $_SESSION['error'] = 'YouTube Redirect URI не настроен. Обратитесь к администратору.';
            header('Location: /integrations');
            exit;
        }

        // Логирование для отладки (можно убрать в production)
        error_log('YouTube OAuth: Client ID = ' . substr($clientId, 0, 20) . '..., Redirect URI = ' . $redirectUri);

        // Генерация state токена для безопасности и сохранение в БД
        $stateToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 минут
        
        // Сохраняем state токен в БД
        $db = \Core\Database::getInstance();
        try {
            $stmt = $db->prepare("
                INSERT INTO oauth_state_tokens (token, user_id, provider, expires_at) 
                VALUES (?, ?, 'youtube', ?)
            ");
            $stmt->execute([$stateToken, $userId, $expiresAt]);
        } catch (\Exception $e) {
            error_log('YouTube OAuth: Error saving state token: ' . $e->getMessage());
            $_SESSION['error'] = 'Ошибка инициализации авторизации. Попробуйте снова.';
            header('Location: /integrations');
            exit;
        }
        
        $state = $stateToken;

        // Формирование URL для авторизации Google OAuth
        $scopes = [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/userinfo.profile',
        ];

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        // Логирование для отладки (можно убрать в production)
        error_log('YouTube OAuth Request:');
        error_log('  Client ID: ' . substr($clientId, 0, 30) . '...');
        error_log('  Redirect URI: ' . $redirectUri);
        error_log('  Full Auth URL: ' . $authUrl);
        
        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * Callback от YouTube
     */
    public function youtubeCallback(): void
    {
        try {
            // Инициализация сессии
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $code = filter_input(INPUT_GET, 'code', FILTER_UNSAFE_RAW) ?: null;
            $state = filter_input(INPUT_GET, 'state', FILTER_UNSAFE_RAW) ?: null;
            $error = filter_input(INPUT_GET, 'error', FILTER_UNSAFE_RAW) ?: null;

            // Восстановление user_id из state токена из БД
            $userId = null;
            if ($state) {
                $db = \Core\Database::getInstance();
                try {
                    $stmt = $db->prepare("
                        SELECT user_id, expires_at 
                        FROM oauth_state_tokens 
                        WHERE token = ? AND provider = 'youtube' AND expires_at > NOW()
                    ");
                    $stmt->execute([$state]);
                    $stateRecord = $stmt->fetch();
                    
                    if ($stateRecord) {
                        $userId = (int)$stateRecord['user_id'];
                        // Удаляем использованный токен
                        $deleteStmt = $db->prepare("DELETE FROM oauth_state_tokens WHERE token = ?");
                        $deleteStmt->execute([$state]);
                        error_log('YouTube Callback: State token validated, user_id: ' . $userId);
                    } else {
                        error_log('YouTube Callback: Invalid or expired state token');
                    }
                } catch (\Exception $e) {
                    error_log('YouTube Callback: Error validating state token: ' . $e->getMessage());
                }
            }

            if (!$userId) {
                error_log('YouTube Callback: Cannot restore user_id from state token');
                $_SESSION['error'] = 'Неверный или истекший state токен. Попробуйте снова.';
                header('Location: /login');
                exit;
            }

            // Восстанавливаем сессию пользователя
            $auth = new \Core\Auth();
            $user = $auth->getUserById($userId);
            if (!$user) {
                error_log('YouTube Callback: User not found. ID: ' . $userId);
                $_SESSION['error'] = 'Пользователь не найден.';
                header('Location: /login');
                exit;
            }

            // Восстанавливаем сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['user_name'] = $user['name'] ?? '';
            
            // Создаем запись сессии в БД, если её нет
            if (!isset($_SESSION['session_id'])) {
                $sessionId = bin2hex(random_bytes(32));
                $lifetime = (int)($this->config['SESSION_LIFETIME'] ?? 3600);
                $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                try {
                    $db = \Core\Database::getInstance();
                    $stmt = $db->prepare("
                        INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$sessionId, $userId, $clientIp, $userAgent, $expiresAt]);
                    $_SESSION['session_id'] = $sessionId;
                    error_log('YouTube Callback: Session created for user ID: ' . $userId);
                } catch (\Exception $e) {
                    error_log('YouTube Callback: Error creating session: ' . $e->getMessage());
                    // Продолжаем без session_id, так как это не критично для OAuth callback
                }
            }

            error_log('YouTube Callback: User ID = ' . $userId . ', Code = ' . ($code ? 'present' : 'missing') . ', State = ' . ($state ? 'present' : 'missing'));

            if ($error) {
                error_log('YouTube Callback: OAuth error: ' . $error);
                $_SESSION['error'] = 'Ошибка авторизации. Попробуйте снова.';
                header('Location: /integrations');
                exit;
            }

            if (!$code) {
                error_log('YouTube Callback: Authorization code not received');
                $_SESSION['error'] = 'Код авторизации не получен.';
                header('Location: /integrations');
                exit;
            }

            $clientId = $this->config['YOUTUBE_CLIENT_ID'] ?? null;
            $clientSecret = $this->config['YOUTUBE_CLIENT_SECRET'] ?? null;
            $redirectUri = $this->config['YOUTUBE_REDIRECT_URI'] ?? null;

            if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
                error_log('YouTube Callback: Missing configuration. Client ID: ' . ($clientId ? 'set' : 'missing') . ', Secret: ' . ($clientSecret ? 'set' : 'missing') . ', Redirect URI: ' . ($redirectUri ?: 'missing'));
                $_SESSION['error'] = 'YouTube интеграция не настроена. Обратитесь к администратору.';
                header('Location: /integrations');
                exit;
            }

            // Обмен кода на токены
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $tokenData = [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ];

            $ch = curl_init($tokenUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($tokenData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log('YouTube Callback: cURL error: ' . $curlError);
                $_SESSION['error'] = 'Ошибка соединения с Google.';
                header('Location: /integrations');
                exit;
            }

            if ($httpCode !== 200) {
                error_log('YouTube Callback: Token exchange failed. HTTP Code: ' . $httpCode . ', Response: ' . substr($response, 0, 500));
                $_SESSION['error'] = 'Ошибка получения токенов от Google (HTTP ' . $httpCode . ').';
                header('Location: /integrations');
                exit;
            }

            $tokenData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('YouTube Callback: JSON decode error: ' . json_last_error_msg() . ', Response: ' . substr($response, 0, 500));
                $_SESSION['error'] = 'Ошибка обработки ответа от Google.';
                header('Location: /integrations');
                exit;
            }

            if (!isset($tokenData['access_token'])) {
                error_log('YouTube Callback: Access token not in response. Response: ' . json_encode($tokenData));
                $_SESSION['error'] = 'Токен доступа не получен.';
                header('Location: /integrations');
                exit;
            }

            // Получение информации о канале
            $channelInfo = $this->getYouTubeChannelInfo($tokenData['access_token']);

            // Сохранение интеграции (поддержка мультиаккаунтов)
            $integrationRepo = new YoutubeIntegrationRepository();
            $accountName = trim((string)$this->getParam('account_name', ''));
            if ($accountName !== '') {
                $accountName = mb_substr($accountName, 0, 100);
            }
            
            // Проверяем, есть ли уже такой канал
            $channelId = $channelInfo['channel_id'] ?? null;
            $existing = null;
            if ($channelId) {
                try {
                    $allIntegrations = $integrationRepo->findByUserId($userId);
                    foreach ($allIntegrations as $integration) {
                        if ($integration['channel_id'] === $channelId) {
                            $existing = $integration;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('YouTube Callback: Error finding existing integration: ' . $e->getMessage());
                    // Продолжаем создание новой интеграции
                }
            }

            $integrationData = [
                'user_id' => $userId,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in']) 
                    ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) 
                    : null,
                'channel_id' => $channelId,
                'channel_name' => $channelInfo['channel_name'] ?? null,
                'account_name' => !empty($accountName) ? $accountName : ($channelInfo['channel_name'] ?? 'YouTube канал'),
                'status' => 'connected',
                'is_default' => 0, // По умолчанию не устанавливаем, если есть другие аккаунты
            ];

            // Если это первый аккаунт, делаем его по умолчанию
            try {
                $allIntegrations = $integrationRepo->findByUserId($userId);
                if (empty($allIntegrations) || (count($allIntegrations) === 1 && $existing)) {
                    $integrationData['is_default'] = 1;
                }
            } catch (\Exception $e) {
                error_log('YouTube Callback: Error checking existing integrations: ' . $e->getMessage());
                // Если это первая попытка, делаем по умолчанию
                $integrationData['is_default'] = 1;
            }

            try {
                if ($existing) {
                    $integrationRepo->update($existing['id'], $integrationData);
                    error_log('YouTube Callback: Integration updated. ID: ' . $existing['id']);
                } else {
                    $newId = $integrationRepo->create($integrationData);
                    error_log('YouTube Callback: Integration created. ID: ' . $newId);
                }
            } catch (\Exception $e) {
                error_log('YouTube Callback: Database error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $_SESSION['error'] = 'Ошибка сохранения интеграции.';
                header('Location: /integrations');
                exit;
            }

            $_SESSION['success'] = 'YouTube успешно подключен!';
            header('Location: /integrations');
            exit;

        } catch (\Throwable $e) {
            error_log('YouTube Callback: Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('YouTube Callback: Stack trace: ' . $e->getTraceAsString());
            
            $_SESSION['error'] = 'Произошла ошибка при подключении YouTube. Попробуйте снова.';
            header('Location: /integrations');
            exit;
        }
    }

    /**
     * Получить информацию о канале YouTube
     */
    private function getYouTubeChannelInfo(string $accessToken): array
    {
        $url = 'https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Логирование для отладки
        error_log('YouTube Channel Info Request:');
        error_log('  HTTP Code: ' . $httpCode);
        if ($curlError) {
            error_log('  cURL Error: ' . $curlError);
        }
        error_log('  Response: ' . substr($response, 0, 500));

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (!empty($data['items'])) {
                $channel = $data['items'][0];
                $channelName = $channel['snippet']['title'] ?? null;
                $channelId = $channel['id'] ?? null;
                
                error_log('  Channel ID: ' . $channelId);
                error_log('  Channel Name: ' . $channelName);
                
                return [
                    'channel_id' => $channelId,
                    'channel_name' => $channelName,
                ];
            } else {
                error_log('  No channels found in response');
            }
        } else {
            error_log('  Failed to get channel info. HTTP Code: ' . $httpCode);
        }

        return [];
    }

    /**
     * Отключение YouTube (старый метод для совместимости)
     */
    public function youtubeDisconnect(): void
    {
        $userId = $_SESSION['user_id'];
        $integrationRepo = new YoutubeIntegrationRepository();
        $accounts = $integrationRepo->findByUserId($userId);
        
        // Отключаем все аккаунты
        foreach ($accounts as $account) {
            if ($account['status'] === 'connected') {
                $integrationRepo->update($account['id'], [
                    'status' => 'disconnected',
                    'access_token' => null,
                    'refresh_token' => null,
                    'token_expires_at' => null,
                ]);
            }
        }

        $_SESSION['success'] = 'YouTube успешно отключен.';
        header('Location: /integrations');
        exit;
    }

    /**
     * Установить аккаунт YouTube по умолчанию
     */
    public function youtubeSetDefault(): void
    {
        $this->setDefaultAccount('youtube');
    }

    /**
     * Отключить конкретный аккаунт YouTube
     */
    public function youtubeDisconnectAccount(): void
    {
        $this->disconnectAccount('youtube', [
            'status' => 'disconnected',
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);
    }

    /**
     * Удалить аккаунт YouTube
     */
    public function youtubeDelete(): void
    {
        $this->deleteAccount('youtube');
    }

    /**
     * Подключение Telegram (поддержка мультиаккаунтов)
     */
    public function telegramConnect(): void
    {
        $userId = $_SESSION['user_id'];
        $botToken = trim((string)$this->getParam('bot_token', ''));
        $channelId = trim((string)$this->getParam('channel_id', ''));
        $accountName = trim((string)$this->getParam('account_name', ''));
        if ($accountName !== '') {
            $accountName = mb_substr($accountName, 0, 100);
        }

        if (empty($botToken) || empty($channelId)) {
            $_SESSION['error'] = 'Bot token and channel ID are required';
            header('Location: /integrations');
            exit;
        }

        if (mb_strlen($botToken) > 200 || mb_strlen($channelId) > 200) {
            $_SESSION['error'] = 'Bot token or channel ID is too long';
            header('Location: /integrations');
            exit;
        }

        $integrationRepo = new \App\Repositories\TelegramIntegrationRepository();
        
        // Проверяем, есть ли уже такой канал
        $allIntegrations = $integrationRepo->findByUserId($userId);
        $existing = null;
        foreach ($allIntegrations as $integration) {
            if ($integration['channel_id'] === $channelId) {
                $existing = $integration;
                break;
            }
        }

        $integrationData = [
            'user_id' => $userId,
            'bot_token' => $botToken,
            'channel_id' => $channelId,
            'account_name' => !empty($accountName) ? $accountName : ('Telegram: ' . $channelId),
            'status' => 'connected',
            'is_default' => 0,
        ];

        // Если это первый аккаунт, делаем его по умолчанию
        if (empty($allIntegrations) || (count($allIntegrations) === 1 && $existing)) {
            $integrationData['is_default'] = 1;
        }

        if ($existing) {
            $integrationRepo->update($existing['id'], $integrationData);
        } else {
            $integrationRepo->create($integrationData);
        }

        $_SESSION['success'] = 'Telegram подключен успешно!';
        header('Location: /integrations');
        exit;
    }

    /**
     * Установить аккаунт Telegram по умолчанию
     */
    public function telegramSetDefault(): void
    {
        $this->setDefaultAccount('telegram');
    }

    /**
     * Удалить аккаунт Telegram
     */
    public function telegramDelete(): void
    {
        $this->deleteAccount('telegram');
    }

    /**
     * Установить аккаунт TikTok по умолчанию
     */
    public function tiktokSetDefault(): void
    {
        $this->setDefaultAccount('tiktok');
    }

    /**
     * Удалить аккаунт TikTok
     */
    public function tiktokDelete(): void
    {
        $this->deleteAccount('tiktok');
    }

    /**
     * Установить аккаунт Instagram по умолчанию
     */
    public function instagramSetDefault(): void
    {
        $this->setDefaultAccount('instagram');
    }

    /**
     * Удалить аккаунт Instagram
     */
    public function instagramDelete(): void
    {
        $this->deleteAccount('instagram');
    }

    /**
     * Установить аккаунт Pinterest по умолчанию
     */
    public function pinterestSetDefault(): void
    {
        $this->setDefaultAccount('pinterest');
    }

    /**
     * Удалить аккаунт Pinterest
     */
    public function pinterestDelete(): void
    {
        $this->deleteAccount('pinterest');
    }

    /**
     * Подключение TikTok
     */
    public function tiktokConnect(): void
    {
        $_SESSION['error'] = 'Интеграция TikTok пока недоступна.';
        http_response_code(501);
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от TikTok
     */
    public function tiktokCallback(): void
    {
        $_SESSION['error'] = 'Интеграция TikTok пока недоступна.';
        http_response_code(501);
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Instagram
     */
    public function instagramConnect(): void
    {
        $_SESSION['error'] = 'Интеграция Instagram пока недоступна.';
        http_response_code(501);
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от Instagram
     */
    public function instagramCallback(): void
    {
        $_SESSION['error'] = 'Интеграция Instagram пока недоступна.';
        http_response_code(501);
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Pinterest
     */
    public function pinterestConnect(): void
    {
        $_SESSION['error'] = 'Интеграция Pinterest пока недоступна.';
        http_response_code(501);
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от Pinterest
     */
    public function pinterestCallback(): void
    {
        $_SESSION['error'] = 'Интеграция Pinterest пока недоступна.';
        http_response_code(501);
        header('Location: /integrations');
        exit;
    }

    /**
     * Статистика
     */
    public function statistics(): void
    {
        include __DIR__ . '/../../views/dashboard/statistics.php';
    }

    /**
     * История публикаций
     */
    public function publications(): void
    {
        $userId = $_SESSION['user_id'];
        $publications = $this->publicationRepo->findByUserId($userId, ['published_at' => 'DESC']);
        
        include __DIR__ . '/../../views/dashboard/publications.php';
    }

    private function getAccountId(): int
    {
        $accountId = (int)($this->getParam('account_id', 0) ?: ($_POST['account_id'] ?? 0));
        return $accountId > 0 ? $accountId : 0;
    }

    private function getIntegrationRepository(string $platform)
    {
        $map = [
            'youtube' => \App\Repositories\YoutubeIntegrationRepository::class,
            'telegram' => \App\Repositories\TelegramIntegrationRepository::class,
            'tiktok' => \App\Repositories\TiktokIntegrationRepository::class,
            'instagram' => \App\Repositories\InstagramIntegrationRepository::class,
            'pinterest' => \App\Repositories\PinterestIntegrationRepository::class,
        ];

        if (!isset($map[$platform])) {
            return null;
        }

        $class = $map[$platform];
        return new $class();
    }

    private function setDefaultAccount(string $platform): void
    {
        $userId = $_SESSION['user_id'];
        $accountId = $this->getAccountId();
        if (!$accountId) {
            $this->error('Account ID is required', 400);
            return;
        }

        $repo = $this->getIntegrationRepository($platform);
        if (!$repo || !method_exists($repo, 'findByIdAndUserId') || !method_exists($repo, 'setDefault')) {
            $this->error('Integration not supported', 400);
            return;
        }

        $account = $repo->findByIdAndUserId($accountId, $userId);
        if (!$account) {
            $this->error('Account not found', 404);
            return;
        }

        if ($repo->setDefault($accountId, $userId)) {
            $this->success([], 'Аккаунт установлен по умолчанию');
            return;
        }

        $this->error('Failed to set default account', 400);
    }

    private function deleteAccount(string $platform): void
    {
        $userId = $_SESSION['user_id'];
        $accountId = $this->getAccountId();
        if (!$accountId) {
            $this->error('Account ID is required', 400);
            return;
        }

        $repo = $this->getIntegrationRepository($platform);
        if (!$repo || !method_exists($repo, 'findByIdAndUserId')) {
            $this->error('Integration not supported', 400);
            return;
        }

        $account = $repo->findByIdAndUserId($accountId, $userId);
        if (!$account) {
            $this->error('Account not found', 404);
            return;
        }

        $repo->delete($accountId);
        $this->success([], 'Аккаунт удален');
    }

    private function disconnectAccount(string $platform, array $updateData): void
    {
        $userId = $_SESSION['user_id'];
        $accountId = $this->getAccountId();
        if (!$accountId) {
            $this->error('Account ID is required', 400);
            return;
        }

        $repo = $this->getIntegrationRepository($platform);
        if (!$repo || !method_exists($repo, 'findByIdAndUserId')) {
            $this->error('Integration not supported', 400);
            return;
        }

        $account = $repo->findByIdAndUserId($accountId, $userId);
        if (!$account) {
            $this->error('Account not found', 404);
            return;
        }

        $repo->update($accountId, $updateData);
        $this->success([], 'Аккаунт отключен');
    }
}
