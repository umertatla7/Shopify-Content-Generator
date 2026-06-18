<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AeoGeoPromptCheck extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AeoGeoVisibilityReport::class, 'aeo_geo_visibility_report_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }
}
