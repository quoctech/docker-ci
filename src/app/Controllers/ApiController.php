<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;

/**
 * ApiController - Base controller cho tất cả API endpoints.
 *
 * Cung cấp:
 * - Response format chuẩn hóa (success/error)
 * - Helper lấy thông tin user đang đăng nhập từ JWT payload
 *
 * Tất cả controller trong modules/ PHẢI extend class này (không extend BaseController trực tiếp).
 */
abstract class ApiController extends BaseController
{
    use ResponseTrait;

    /**
     * Trả response thành công với format chuẩn.
     *
     * Format: { "status": "success", "message": "...", "data": {...} }
     *
     * @param mixed  $data    Dữ liệu trả về (array, object, hoặc null)
     * @param string $message Thông báo ngắn gọn
     * @param int    $code    HTTP status code (200, 201, etc.)
     * @return ResponseInterface
     */
    protected function success(mixed $data = null, string $message = 'OK', int $code = 200): ResponseInterface
    {
        return $this->respond([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Trả response lỗi với format chuẩn.
     *
     * Format: { "status": "error", "message": "...", "errors": {...} }
     *
     * @param string $message Thông báo lỗi chính
     * @param int    $code    HTTP status code (400, 401, 403, 404, 422, 500, etc.)
     * @param array  $errors  Chi tiết lỗi validation (optional)
     * @return ResponseInterface
     */
    protected function error(string $message, int $code = 400, array $errors = []): ResponseInterface
    {
        $response = [
            'status'  => 'error',
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return $this->respond($response, $code);
    }

    /**
     * Lấy JWT payload của user đang đăng nhập.
     *
     * Dữ liệu được gắn vào request bởi JWTAuthFilter sau khi verify token.
     * Chứa: sub (user_id), uuid, role, jti, exp, iat.
     *
     * @return object|null JWT decoded payload, null nếu chưa authenticate
     */
    protected function getAuthUser(): ?object
    {
        return $this->request->authUser ?? null;
    }

    /**
     * Lấy user UUID từ JWT payload (shortcut).
     *
     * @return string|null User UUID hoặc null nếu chưa login
     */
    protected function getAuthUserId(): ?string
    {
        return $this->getAuthUser()?->sub ?? null;
    }
}
