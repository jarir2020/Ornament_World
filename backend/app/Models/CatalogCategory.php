<?php

declare(strict_types=1);

namespace App\Models;

use Nemesis\Core\Model;

class CatalogCategory extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    public function products()
    {
        return $this->hasMany(CatalogProduct::class, 'category_id');
    }
}
