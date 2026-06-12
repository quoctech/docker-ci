<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SecurityHeadersFilter - Thêm HTTP security headers vào mọi response.
 *
 * Defense-in-depth: ngay cả khi code có lỗ hổng,
 * browser sẽ áp dụng các chính sách bảo mật từ headers.
 *
 * Headers được thêm:
 * - X-Content-Type-Options: nosniff    → Chống MIME sniffing attack
 * - X-Frame-Options: DENY              → Chống clickjacking (iframe)
 * - X-XSS-Protection: 0               → Tắt XSS auditor cũ (gây thêm lỗ hổng)
 * - Referrer-Policy                    → Kiểm soát thông tin referrer
 * - Permissions-Policy                 → Chặn truy cập camera/mic/GPS
 * - HSTS (production only)             → Bắt buộc HTTPS
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    /**
     * Gắn security headers SAU khi controller xử lý xong.
     *
     * @return ResponseInterface Response đã thêm headers
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Chống MIME type sniffing (browser không đoán content type)
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // Chống clickjacking: không cho phép nhúng site trong iframe
        $response->setHeader('X-Frame-Options', 'DENY');

        // Tắt XSS auditor cũ của browser (deprecated, có thể bị exploit ngược)
        $response->setHeader('X-XSS-Protection', '0');

        // Chỉ gửi origin (không gửi full URL) khi navigate cross-origin
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Chặn truy cập hardware: camera, microphone, geolocation
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Xóa header tiết lộ phiên bản PHP
        $response->removeHeader('X-Powered-By');

        // HSTS: bắt buộc HTTPS (chỉ bật trên production, tránh lỗi dev)
        if (env('CI_ENVIRONMENT') === 'production') {
            $response->setHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
