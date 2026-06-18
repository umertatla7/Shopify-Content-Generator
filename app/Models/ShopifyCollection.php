<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyCollection extends Model
{
    use BelongsToAccount;

    protected $table = 'collections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'payload' => 'array',
            'generated_benefits' => 'array',
            'generated_faq' => 'array',
            'last_synced_at' => 'datetime',
            'generated_at' => 'datetime',
            'last_optimized_at' => 'datetime',
            'shopify_pushed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }
}
