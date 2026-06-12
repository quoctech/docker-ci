<?php

/**
 * App Helpers - Hàm tiện ích dùng chung toàn hệ thống.
 *
 * Load tự động qua BaseController hoặc Autoload config.
 */

// ==========================================================================
// DATE / TIME
// ==========================================================================

if (! function_exists('now_datetime')) {
    /**
     * Trả về datetime hiện tại theo format DB (Y-m-d H:i:s).
     * Tránh lặp date('Y-m-d H:i:s') khắp nơi.
     *
     * @return string "2026-06-11 12:00:00"
     */
    function now_datetime(): string
    {
        return date(APP_DATETIME_FORMAT);
    }
}

if (! function_exists('future_datetime')) {
    /**
     * Trả về datetime trong tương lai.
     *
     * @param string $offset Offset dạng strtotime ('+30 minutes', '+7 days')
     * @return string Datetime format DB
     */
    function future_datetime(string $offset): string
    {
        return date(APP_DATETIME_FORMAT, strtotime($offset));
    }
}

if (! function_exists('timestamp_to_datetime')) {
    /**
     * Chuyển unix timestamp thành datetime format DB.
     *
     * @param int $timestamp Unix timestamp
     * @return string Datetime format DB
     */
    function timestamp_to_datetime(int $timestamp): string
    {
        return date(APP_DATETIME_FORMAT, $timestamp);
    }
}

// ==========================================================================
// PASSWORD
// ==========================================================================

if (! function_exists('hash_password')) {
    /**
     * Hash password bằng Argon2id với config chuẩn.
     * Tập trung config hash 1 chỗ — đổi algorithm chỉ sửa ở đây.
     *
     * @param string $password Password plaintext
     * @return string Hashed password
     */
    function hash_password(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => APP_HASH_MEMORY_COST,
            'time_cost'   => APP_HASH_TIME_COST,
            'threads'     => APP_HASH_THREADS,
        ]);
    }
}

if (! function_exists('verify_password')) {
    /**
     * Verify password với hash đã lưu.
     *
     * @param string $password  Password plaintext từ user input
     * @param string $hash      Hash đã lưu trong DB
     * @return bool true nếu khớp
     */
    function verify_password(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
