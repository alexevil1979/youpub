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

        $publications = $this->publicationRepo->findByUserId($userId);
        $stats = [];

        foreach ($publications as $publication) {
            $publicationStats = $this->statsRepo->findByPublicationId($publication['id'], ['collected_at' => 'DESC']);
            if (!empty($publicationStats)) {
                $stats[] = [
                    'publication' => $publication,
                    'latest_stats' => $publicationStats[0],
                    'history' => $publicationStats,
                ];
            }
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
