<?php

namespace App\Libraries;

/**
 * SystemLogger - Ghi log vào bảng system_logs.
 *
 * Dùng static methods để gọi từ bất kỳ đâu mà không cần inject.
 * Nếu module system-log chưa được cài hoặc bảng chưa tồn tại,
 * sẽ fail silently (không throw exception).
 */
class SystemLogger
{
    public static function debug(string $title, array $context = []): void
    {
        self::write('debug', 'app', $title, null, $context);
    }

    public static function info(string $title, array $context = []): void
    {
        self::write('info', 'app', $title, null, $context);
    }

    public static function warning(string $title, string $message = null, array $context = []): void
    {
        self::write('warning', 'app', $title, $message, $context);
    }

    public static function error(string $title, string $message = null, array $context = []): void
    {
        self::write('error', 'app', $title, $message, $context);
    }

    public static function critical(string $title, string $message = null, array $context = []): void
    {
        self::write('critical', 'app', $title, $message, $context);
    }

    /**
     * Ghi log với channel tùy chỉnh.
     */
    public static function channel(
        string $channel,
        string $level,
        string $title,
        string $message = null,
        array $context = []
    ): void {
        self::write($level, $channel, $title, $message, $context);
    }

    /**
     * Ghi exception vào log.
     */
    public static function exception(\Throwable $e, string $channel = 'exception'): void
    {
        $title   = get_class($e) . ': ' . $e->getMessage();
        $message = $e->getTraceAsString();
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
        ];
        self::write('error', $channel, $title, $message, $context);
    }

    // =========================================================================

    private static function write(
        string $level,
        string $channel,
        string $title,
        ?string $message,
        array $context
    ): void {
        try {
            $request = \Config\Services::request();
            $userId  = null;

            // Lấy user_id từ token nếu có
            $authHeader = $request->getHeaderLine('Authorization');
            if ($authHeader) {
                try {
                    $parts = explode('.', str_replace('Bearer ', '', $authHeader));
                    if (count($parts) === 3) {
                        $jwtPayload = json_decode(base64_decode(
                            str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)
                        ), true);
                        $userId = $jwtPayload['sub'] ?? null;
                    }
                } catch (\Throwable) {}
            }

            // Tự động capture request params + payload
            $requestCtx = self::captureRequest($request);
            if ($requestCtx) {
                $context = array_merge(['_request' => $requestCtx], $context);
            }

            $db = \Config\Database::connect();
            $db->table('system_logs')->insert([
                'level'      => $level,
                'channel'    => $channel,
                'title'      => mb_substr($title, 0, 255),
                'message'    => $message,
                'context'    => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                'user_id'    => $userId,
                'ip_address' => $request->getIPAddress(),
                'url'        => mb_substr((string) $request->getUri(), 0, 500),
                'method'     => $request->getMethod(),
                'seen'       => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Fail silently — log không được làm crash ứng dụng
        }
    }

    /**
     * Capture GET params + request body, mask sensitive fields.
     */
    private static function captureRequest(\CodeIgniter\HTTP\RequestInterface $request): array
    {
        $ctx = [];

        // GET query params
        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            $get = $request->getGet() ?: [];
            if ($get) {
                $ctx['params'] = self::mask($get);
            }

            // POST (form) hoặc JSON body
            $contentType = $request->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $body = $request->getJSON(true) ?: [];
            } else {
                $body = $request->getPost() ?: [];
                // Fallback: raw input (PUT/PATCH form-encoded)
                if (!$body) {
                    parse_str($request->getBody() ?? '', $body);
                }
            }

            if ($body) {
                $ctx['payload'] = self::mask((array) $body);
            }
        }

        return $ctx;
    }

    /** Mask các field nhạy cảm. */
    private static function mask(array $data): array
    {
        static $sensitive = [
            'password', 'password_hash', 'new_password', 'current_password',
            'token', 'access_token', 'refresh_token', 'secret', 'api_key',
            'authorization', 'credit_card', 'cvv', 'pin',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $data[$key] = '[MASKED]';
            } elseif (is_array($value)) {
                $data[$key] = self::mask($value);
            }
        }

        return $data;
    }
}
