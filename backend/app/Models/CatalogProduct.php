<?php

declare(strict_types=1);

namespace App\Models;

use Nemesis\Core\Model;

class CatalogProduct extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'base_price',
        'compare_at_price',
        'stock_qty',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    public function category()
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function discounts()
    {
        return $this->hasMany(CatalogDiscount::class, 'product_id');
    }
}
