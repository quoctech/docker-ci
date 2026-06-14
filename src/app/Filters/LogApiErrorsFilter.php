<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\SystemLogger;

/**
 * LogApiErrorsFilter — Tự động ghi log khi API trả về lỗi.
 *
 * Áp dụng cho tất cả routes /api/*.
 * - 4xx (trừ 401, 404): level 'warning'
 * - 5xx: level 'error'
 * - 401, 404: bỏ qua (quá nhiều, không có giá trị debug)
 */
class LogApiErrorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $status = $response->getStatusCode();

        // Chỉ log lỗi thực sự — bỏ qua 401 (auth expired) và 404 (route miss)
        if ($status < 400 || $status === 401 || $status === 404) {
            return;
        }

        $level   = $status >= 500 ? 'error' : 'warning';
        $body    = json_decode($response->getBody() ?? '', true);
        $message = $body['message'] ?? null;
        $context = ['status_code' => $status];

        if (!empty($body['errors'])) {
            $context['errors'] = $body['errors'];
        }

        $path  = $request->getUri()->getPath();
        $title = 'HTTP ' . $status . ' ' . strtoupper($request->getMethod()) . ' ' . $path;

        SystemLogger::channel('api', $level, $title, $message, $context);
    }
}
