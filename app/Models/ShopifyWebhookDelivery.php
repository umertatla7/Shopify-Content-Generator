<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyWebhookDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
