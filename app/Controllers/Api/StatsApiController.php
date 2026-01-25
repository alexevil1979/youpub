<?php

namespace App\Controllers\Api;

use Core\Controller;
use App\Repositories\PublicationRepository;
use App\Repositories\StatisticsRepository;

/**
 * API контроллер для статистики
 */
class StatsApiController extends Controller
{
    private PublicationRepository $publicationRepo;
    private StatisticsRepository $statsRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->publicationRepo = new PublicationRepository();
        $this->statsRepo = new StatisticsRepository();
    }

    /**
     * Получить статистику
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'];
        $period = $this->getParam('period', 'week'); // day, week, month
        $limit = (int)$this->getParam('limit', 200);
        $limit = max(1, min($limit, 1000));
        $offset = (int)$this->getParam('offset', 0);
        $offset = max(0, $offset);

        $period = in_array($period, ['day', 'week', 'month'], true) ? $period : 'week';
        $since = new \DateTime('now');
        if ($period === 'day') {
            $since->modify('-1 day');
        } elseif ($period === 'month') {
            $since->modify('-1 month');
        } else {
            $since->modify('-1 week');
        }

        $publications = $this->publicationRepo->findByUserIdSince($userId, $since->format('Y-m-d H:i:s'), ['published_at' => 'DESC']);
        if ($offset > 0 || $limit < count($publications)) {
            $publications = array_slice($publications, $offset, $limit);
        }
        $stats = [];

        $publicationIds = array_map(static fn($publication) => (int)($publication['id'] ?? 0), $publications);
        $latestStats = $this->statsRepo->findLatestByPublicationIds($publicationIds);

        foreach ($publications as $publication) {
            $pubId = (int)($publication['id'] ?? 0);
            $stats[] = [
                'publication' => $publication,
                'latest_stats' => $latestStats[$pubId] ?? null,
            ];
        }

        $this->success($stats);
    }

    /**
     * Экспорт статистики
     */
    public function export(): void
    {
        $userId = $_SESSION['user_id'];
        $format = $this->getParam('format', 'json'); // json, csv

        $publications = $this->publicationRepo->findByUserId($userId);
        $data = [];

        foreach ($publications as $publication) {
            $stats = $this->statsRepo->findByPublicationId($publication['id']);
            $data[] = [
                'publication' => $publication,
                'statistics' => $stats,
            ];
        }

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="stats_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Publication ID', 'Platform', 'Views', 'Likes', 'Comments', 'Shares', 'Date']);
            
            foreach ($data as $item) {
                foreach ($item['statistics'] as $stat) {
                    fputcsv($output, [
                        $item['publication']['id'],
                        $item['publication']['platform'],
                        $stat['views'],
                        $stat['likes'],
                        $stat['comments'],
                        $stat['shares'],
                        $stat['collected_at'],
                    ]);
                }
            }
            
            fclose($output);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="stats_' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
}
