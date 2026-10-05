<?php

declare(strict_types=1);

namespace App\Models;

use Nemesis\Core\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';

    protected $fillable = [
        'product_id',
        'variant_id',
        'image_url',
        'alt_text',
        'sort_order',
        'is_primary',
    ];
}
