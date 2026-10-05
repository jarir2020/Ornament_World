<?php

declare(strict_types=1);

namespace App\Models;

use Nemesis\Core\Model;

class CatalogDiscount extends Model
{
    protected $table = 'discounts';

    protected $fillable = [
        'product_id',
        'variant_id',
        'code',
        'discount_type',
        'value',
        'starts_at',
        'ends_at',
        'is_active',
    ];
}
