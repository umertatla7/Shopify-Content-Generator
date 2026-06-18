<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreKnowledgeBase extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'brand_profile' => 'array',
            'audience_profile' => 'array',
            'product_insights' => 'array',
            'collection_insights' => 'array',
            'content_insights' => 'array',
            'seo_opportunities' => 'array',
            'source_snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }
}
