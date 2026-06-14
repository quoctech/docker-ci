<?php

namespace Modules\SystemAdmin\Models;

use CodeIgniter\Model;
use App\Libraries\RedisService;

class SiteConfigModel extends Model
{
    protected $table         = 'site_configs';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'updated_at',
    ];

    protected $validationRules = [
        'key' => 'required|alpha_dash|max_length[100]|is_unique[site_configs.key,id,{id}]',
    ];

    /**
     * Get config value by key (Redis-cached).
     *
     * Cache lưu dạng JSON {"v":"...","t":"string"} để tránh query DB lấy type
     * khi cache hit (trước đây là N+1: cache value nhưng vẫn SELECT type).
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $redis  = RedisService::getInstance();
        $cached = $redis->hget(REDIS_KEY_SITE_CONFIG, $key);

        if ($cached !== null) {
            $item = json_decode($cached, true);
            if (is_array($item) && isset($item['v'], $item['t'])) {
                return $this->castValue($item['v'], $item['t']);
            }
            // Stale entry (old plain-string format) → fall through to rebuild
        }

        $config = $this->where('key', $key)->first();

        if (! $config) {
            return $default;
        }

        $redis->hset(REDIS_KEY_SITE_CONFIG, $key, json_encode([
            'v' => $config->value ?? '',
            't' => $config->type,
        ]));

        return $this->castValue($config->value, $config->type);
    }

    /**
     * Ghi giá trị config vào DB + Redis.
     * Nếu key đã tồn tại → update (giữ nguyên type từ DB), chưa có → insert.
     */
    public function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string', ?string $description = null): void
    {
        $stringValue = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        $existing = $this->where('key', $key)->first();

        if ($existing) {
            $this->skipValidation(true)->update($existing->id, [
                'value'      => $stringValue,
                'updated_at' => now_datetime(),
            ]);
            $type = $existing->type; // Luôn giữ type từ DB, tránh ghi sai type vào cache
        } else {
            $this->insert([
                'key'         => $key,
                'value'       => $stringValue,
                'group'       => $group,
                'type'        => $type,
                'description' => $description,
                'updated_at'  => now_datetime(),
            ]);
        }

        // Cache value + type cùng nhau → getValue() không cần query DB khi cache hit
        RedisService::getInstance()->hset(REDIS_KEY_SITE_CONFIG, $key, json_encode([
            'v' => $stringValue,
            't' => $type,
        ]));
    }

    /**
     * Get all configs grouped.
     */
    public function getAllGrouped(): array
    {
        $configs = $this->orderBy('group')->orderBy('key')->findAll();
        $grouped = [];

        foreach ($configs as $config) {
            $grouped[$config->group][] = $config;
        }

        return $grouped;
    }

    /**
     * Get configs by group.
     */
    public function getByGroup(string $group): array
    {
        return $this->where('group', $group)->orderBy('key')->findAll();
    }

    /**
     * Invalidate config cache.
     */
    public function invalidateCache(): void
    {
        RedisService::getInstance()->del(REDIS_KEY_SITE_CONFIG);
    }

    /**
     * Cast value to proper type.
     */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes'], true),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

}
