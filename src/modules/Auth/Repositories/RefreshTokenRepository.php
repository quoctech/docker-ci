<?php

namespace Modules\Auth\Repositories;

use Modules\Auth\Models\RefreshTokenModel;

/**
 * RefreshTokenRepository - Tầng truy xuất dữ liệu cho bảng refresh_tokens.
 */
class RefreshTokenRepository
{
    private RefreshTokenModel $model;

    public function __construct()
    {
        $this->model = new RefreshTokenModel();
    }

    public function store(string $userUuid, string $tokenHash, string $expiresAt, string $ip, ?string $userAgent): void
    {
        $this->model->storeToken($userUuid, $tokenHash, $expiresAt, $ip, $userAgent);
    }

    public function findValidToken(string $tokenHash): ?object
    {
        return $this->model->findValidToken($tokenHash);
    }

    public function revokeToken(string $tokenHash): void
    {
        $this->model->revokeToken($tokenHash);
    }

    public function revokeAllForUser(string $userUuid): void
    {
        $this->model->revokeAllForUser($userUuid);
    }

    public function purgeExpired(): int
    {
        return $this->model->purgeExpired();
    }
}
