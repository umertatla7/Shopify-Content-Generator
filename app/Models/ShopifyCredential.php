<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyCredential extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected $hidden = [
        'admin_api_access_token',
        'refresh_token',
        'api_key',
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'admin_api_access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'api_key' => 'encrypted',
            'client_secret' => 'encrypted',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }
}
