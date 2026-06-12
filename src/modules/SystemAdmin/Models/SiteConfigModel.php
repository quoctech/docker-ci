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
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $redis = RedisService::getInstance();
        $cached = $redis->hget(REDIS_KEY_SITE_CONFIG, $key);

        if ($cached !== null) {
            return $this->castValue($cached, $this->getType($key));
        }

        $config = $this->where('key', $key)->first();

        if (! $config) {
            return $default;
        }

        $redis->hset(REDIS_KEY_SITE_CONFIG, $key, $config->value ?? '');

        return $this->castValue($config->value, $config->type);
    }

    /**
     * Ghi giá trị config vào DB + Redis.
     * Nếu key đã tồn tại → update, chưa có → insert.
     */
    public function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string', ?string $description = null): void
    {
        $stringValue = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        $existing = $this->where('key', $key)->first();

        if ($existing) {
            $this->skipValidation(true)->update($existing->id, [
                'value'       => $stringValue,
                'updated_at'  => now_datetime(),
            ]);
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

        // Đồng bộ Redis cache
        RedisService::getInstance()->hset(REDIS_KEY_SITE_CONFIG, $key, $stringValue);
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

    /**
     * Get type for a config key.
     */
    private function getType(string $key): string
    {
        $config = $this->where('key', $key)->first();
        return $config->type ?? 'string';
    }
}
