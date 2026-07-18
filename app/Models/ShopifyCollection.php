<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyCollection extends Model
{
    use BelongsToAccount;
    use SanitizesHtml;

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

    protected function description(): Attribute
    {
        return $this->sanitizedHtmlAttribute();
    }

    protected function generatedDescription(): Attribute
    {
        return $this->sanitizedHtmlAttribute();
    }

    protected function generatedAeoContent(): Attribute
    {
        return $this->sanitizedHtmlAttribute();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }
}
