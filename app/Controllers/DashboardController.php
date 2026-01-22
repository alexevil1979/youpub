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
            'videos_total' => count($this->videoRepo->findByUserId($userId)),
            'schedules_pending' => count($this->scheduleRepo->findByUserIdAndStatus($userId, 'pending')),
            'publications_success' => count($this->publicationRepo->findByUserIdAndStatus($userId, 'success')),
            'publications_failed' => count($this->publicationRepo->findByUserIdAndStatus($userId, 'failed')),
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
        include __DIR__ . '/../../views/dashboard/integrations.php';
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

        // Генерация state токена для безопасности
        $state = bin2hex(random_bytes(16));
        $_SESSION['youtube_oauth_state'] = $state;

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
        $userId = $_SESSION['user_id'];
        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        $error = $_GET['error'] ?? null;

        // Проверка state токена
        if (!isset($_SESSION['youtube_oauth_state']) || $state !== $_SESSION['youtube_oauth_state']) {
            $_SESSION['error'] = 'Неверный state токен. Попробуйте снова.';
            header('Location: /integrations');
            exit;
        }

        unset($_SESSION['youtube_oauth_state']);

        if ($error) {
            $_SESSION['error'] = 'Ошибка авторизации: ' . htmlspecialchars($error);
            header('Location: /integrations');
            exit;
        }

        if (!$code) {
            $_SESSION['error'] = 'Код авторизации не получен.';
            header('Location: /integrations');
            exit;
        }

        $clientId = $this->config['YOUTUBE_CLIENT_ID'];
        $clientSecret = $this->config['YOUTUBE_CLIENT_SECRET'];
        $redirectUri = $this->config['YOUTUBE_REDIRECT_URI'];

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
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $_SESSION['error'] = 'Ошибка получения токенов от Google.';
            header('Location: /integrations');
            exit;
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $_SESSION['error'] = 'Токен доступа не получен.';
            header('Location: /integrations');
            exit;
        }

        // Получение информации о канале
        $channelInfo = $this->getYouTubeChannelInfo($tokenData['access_token']);

        // Сохранение интеграции
        $integrationRepo = new YoutubeIntegrationRepository();
        $existing = $integrationRepo->findByUserId($userId);

        $integrationData = [
            'user_id' => $userId,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => isset($tokenData['expires_in']) 
                ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) 
                : null,
            'channel_id' => $channelInfo['channel_id'] ?? null,
            'channel_name' => $channelInfo['channel_name'] ?? null,
            'status' => 'connected',
        ];

        if ($existing) {
            $integrationRepo->update($existing['id'], $integrationData);
        } else {
            $integrationRepo->create($integrationData);
        }

        $_SESSION['success'] = 'YouTube успешно подключен!';
        header('Location: /integrations');
        exit;
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
     * Отключение YouTube
     */
    public function youtubeDisconnect(): void
    {
        $userId = $_SESSION['user_id'];
        $integrationRepo = new YoutubeIntegrationRepository();
        $existing = $integrationRepo->findByUserId($userId);

        if ($existing) {
            $integrationRepo->update($existing['id'], [
                'status' => 'disconnected',
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ]);
            $_SESSION['success'] = 'YouTube успешно отключен.';
        } else {
            $_SESSION['error'] = 'YouTube интеграция не найдена.';
        }

        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Telegram
     */
    public function telegramConnect(): void
    {
        // TODO: Реализовать подключение Telegram бота
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение TikTok
     */
    public function tiktokConnect(): void
    {
        // TODO: Реализовать OAuth flow для TikTok
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от TikTok
     */
    public function tiktokCallback(): void
    {
        // TODO: Обработка OAuth callback
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Instagram
     */
    public function instagramConnect(): void
    {
        // TODO: Реализовать OAuth flow для Instagram
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от Instagram
     */
    public function instagramCallback(): void
    {
        // TODO: Обработка OAuth callback
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Pinterest
     */
    public function pinterestConnect(): void
    {
        // TODO: Реализовать OAuth flow для Pinterest
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от Pinterest
     */
    public function pinterestCallback(): void
    {
        // TODO: Обработка OAuth callback
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
}
