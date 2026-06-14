<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use App\Libraries\RedisService;

/**
 * ServerStatusController - Trạng thái hệ thống theo thời gian thực.
 */
class ServerStatusController extends ApiController
{
    /**
     * GET /api/admin/server-status
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond([
            'status' => 'success',
            'data'   => [
                'php'      => $this->phpInfo(),
                'memory'   => $this->memoryInfo(),
                'disk'     => $this->diskInfo(),
                'database' => $this->dbInfo(),
                'redis'    => $this->redisInfo(),
                'app'      => $this->appInfo(),
            ],
        ]);
    }

    // =========================================================================

    private function phpInfo(): array
    {
        return [
            'version'      => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'extensions'   => implode(', ', array_intersect(
                ['mysqli', 'redis', 'json', 'mbstring', 'openssl', 'curl'],
                get_loaded_extensions()
            )),
        ];
    }

    private function memoryInfo(): array
    {
        $used  = memory_get_usage(true);
        $peak  = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));

        return [
            'used'       => $this->formatBytes($used),
            'peak'       => $this->formatBytes($peak),
            'limit'      => ini_get('memory_limit'),
            'used_bytes' => $used,
            'limit_bytes'=> $limit,
            'percent'    => $limit > 0 ? round($used / $limit * 100, 1) : 0,
        ];
    }

    private function diskInfo(): array
    {
        $free  = disk_free_space('/');
        $total = disk_total_space('/');
        $used  = $total - $free;

        return [
            'total'   => $this->formatBytes($total),
            'used'    => $this->formatBytes($used),
            'free'    => $this->formatBytes($free),
            'percent' => round($used / $total * 100, 1),
        ];
    }

    private function dbInfo(): array
    {
        try {
            $db  = \Config\Database::connect();
            $db->connect();
            $ver = $db->getVersion();
            return ['status' => 'online', 'version' => $ver, 'driver' => 'MariaDB'];
        } catch (\Throwable $e) {
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }

    private function redisInfo(): array
    {
        try {
            $client = RedisService::getInstance();
            $pong   = $client->ping();
            return ['status' => 'online', 'response' => (string) $pong];
        } catch (\Throwable $e) {
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }

    private function appInfo(): array
    {
        $env     = env('CI_ENVIRONMENT', 'production');
        $version = env('APP_VERSION', '1.0.0');

        $load = [];
        if (function_exists('sys_getloadavg')) {
            $avg  = sys_getloadavg();
            $load = [
                '1min'  => round($avg[0], 2),
                '5min'  => round($avg[1], 2),
                '15min' => round($avg[2], 2),
            ];
        }

        return [
            'environment' => $env,
            'version'     => $version,
            'server'      => $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx',
            'timezone'    => date_default_timezone_get(),
            'load'        => $load,
        ];
    }

    // =========================================================================

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)   return round($bytes / 1048576, 2)   . ' MB';
        if ($bytes >= 1024)      return round($bytes / 1024, 2)      . ' KB';
        return $bytes . ' B';
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') return 0;
        $last  = strtolower($limit[-1]);
        $value = (int) $limit;
        return match($last) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
