<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\JWTManager;

/**
 * JWTAuthFilter - Xác thực JWT token trên mỗi request.
 *
 * Flow:
 * 1. Đọc header "Authorization: Bearer <token>"
 * 2. Validate token (signature + expiration + blacklist check)
 * 3. Kiểm tra role nếu có arguments (RBAC)
 * 4. Gắn user data vào request để controller sử dụng
 *
 * Cách dùng trong Routes:
 * - ['filter' => 'auth']              → Chỉ cần đăng nhập (bất kỳ role)
 * - ['filter' => 'auth:super_admin']  → Chỉ super_admin mới vào được
 * - ['filter' => 'auth:super_admin,workspace_admin'] → 2 role
 */
class JWTAuthFilter implements FilterInterface
{
    /**
     * Xử lý trước khi request đến controller.
     *
     * @param RequestInterface $request   HTTP request hiện tại
     * @param array|null       $arguments Danh sách role được phép (từ route config)
     * @return ResponseInterface|null null = pass, ResponseInterface = block
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        // Kiểm tra header Authorization có tồn tại và đúng format "Bearer xxx"
        if (empty($header) || ! str_starts_with($header, 'Bearer ')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Access token required.',
                ]);
        }

        // Tách token khỏi prefix "Bearer "
        $token   = substr($header, 7);
        $jwt     = new JWTManager();
        $decoded = $jwt->validateToken($token);

        // Token invalid, expired, hoặc đã bị blacklist
        if ($decoded === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Invalid or expired token.',
                ]);
        }

        // RBAC: kiểm tra role nếu route yêu cầu role cụ thể
        if (! empty($arguments)) {
            if (! in_array($decoded->role, $arguments, true)) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'Insufficient permissions.',
                    ]);
            }
        }

        // Gắn decoded JWT payload vào request object
        // Controller truy cập qua: $this->getAuthUser()
        $request->authUser = $decoded;

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
