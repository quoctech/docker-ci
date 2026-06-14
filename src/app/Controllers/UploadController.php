<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * UploadController - Serve file uploads từ writable/uploads/.
 *
 * File được lưu NGOÀI webroot (public/) vì lý do bảo mật.
 * Controller này kiểm tra file tồn tại rồi trả về nội dung.
 */
class UploadController extends BaseController
{
    /**
     * GET /uploads/avatars/(:any)
     * Serve avatar file.
     */
    public function avatar(string $filename): ResponseInterface
    {
        // Chặn path traversal: chỉ cho phép tên file đơn giản
        if (str_contains($filename, '/') || str_contains($filename, '..')) {
            return $this->response->setStatusCode(400);
        }

        $path = WRITEPATH . 'uploads/avatars/' . $filename;

        if (! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        // Trả file với MIME type đúng + cache 1 ngày
        return $this->response
            ->setHeader('Content-Type', mime_content_type($path))
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(file_get_contents($path));
    }
}
