<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogProject extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'strategy' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }
}
