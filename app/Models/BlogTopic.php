<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogTopic extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'secondary_keywords' => 'array',
            'suggested_outline' => 'array',
            'related_products' => 'array',
            'related_collections' => 'array',
            'response' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }

    public function storeAnalysis(): BelongsTo
    {
        return $this->belongsTo(StoreAnalysis::class);
    }

    public function aiGeneration(): BelongsTo
    {
        return $this->belongsTo(AIGeneration::class);
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }
}
