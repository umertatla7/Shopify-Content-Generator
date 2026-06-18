<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordPositionSnapshot extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'ctr' => 'decimal:6',
            'position' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(TrackedKeyword::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(SearchConsoleProperty::class, 'search_console_property_id');
    }
}
