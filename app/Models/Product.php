<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'collections' => 'array',
            'payload' => 'array',
            'published_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'content_generated_at' => 'datetime',
            'shopify_content_pushed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }
}
