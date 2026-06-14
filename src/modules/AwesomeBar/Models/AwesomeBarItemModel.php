<?php

namespace Modules\AwesomeBar\Models;

use CodeIgniter\Model;

class AwesomeBarItemModel extends Model
{
    protected $table      = 'awesome_bar_items';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'type', 'title', 'subtitle', 'url', 'icon',
        'keywords', 'module_slug', 'is_active', 'sort_order',
    ];

    public function getActiveForModules(array $enabledSlugs): array
    {
        $builder = $this->where('is_active', 1)->orderBy('sort_order', 'ASC');
        $all     = $builder->findAll();

        return array_values(array_filter($all, function ($item) use ($enabledSlugs) {
            return $item->module_slug === null || in_array($item->module_slug, $enabledSlugs);
        }));
    }
}
