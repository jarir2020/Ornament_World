<?php

declare(strict_types=1);

namespace App\Models;

use Nemesis\Core\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price',
        'compare_at_price',
        'stock_qty',
        'is_active',
        'sort_order',
    ];

    public function product()
    {
        return $this->belongsTo(CatalogProduct::class, 'product_id');
    }
}
