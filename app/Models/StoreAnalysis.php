<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAnalysis extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'main_product_categories' => 'array',
            'seo_opportunities' => 'array',
            'content_gaps' => 'array',
            'suggested_keywords' => 'array',
            'suggested_blog_categories' => 'array',
            'region_specific_opportunities' => 'array',
            'response' => 'array',
            'token_usage' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
