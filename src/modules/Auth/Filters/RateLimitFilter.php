<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\RedisService;

/**
 * RateLimitFilter - Giới hạn số request / thời gian / IP.
 *
 * Bảo vệ API khỏi:
 * - Brute force attack (thử đăng nhập liên tục)
 * - DDoS layer 7 (flood request)
 * - Web scraping / scanner tự động
 *
 * Cơ chế: Fixed Window Counter trên Redis.
 * - Key = "rate:{IP}:{path}"
 * - Value = số request trong window hiện tại
 * - TTL = window size → auto-reset khi hết window
 *
 * Cách dùng trong Routes:
 * - ['filter' => 'ratelimit']        → Mặc định: 60 req / 60 giây
 * - ['filter' => 'ratelimit:10,60']  → Custom: 10 req / 60 giây
 */
class RateLimitFilter implements FilterInterface
{
    /**
     * Kiểm tra rate limit trước khi cho request đi tiếp.
     *
     * @param RequestInterface $request   HTTP request
     * @param array|null       $arguments [maxRequests, windowSeconds] (optional)
     * @return ResponseInterface|null null = pass, 429 = rate limited
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Lấy config từ arguments hoặc dùng default
        $maxRequests = (int) ($arguments[0] ?? 60);  // Mặc định: 60 request
        $window      = (int) ($arguments[1] ?? 60);  // Mặc định: 60 giây

        $ip        = $request->getIPAddress();
        $path      = $request->getUri()->getPath();
        $key       = "{$ip}:{$path}";
        $remaining = RedisService::rateLimit($key, $maxRequests, $window);

        // -1 = đã vượt giới hạn → trả 429 Too Many Requests
        if ($remaining === -1) {
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $window)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Too many requests. Please slow down.',
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
