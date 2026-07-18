<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopifyStore extends Model
{
    use BelongsToAccount;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_validated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function credential(): HasOne
    {
        return $this->hasOne(ShopifyCredential::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ShopifySyncLog::class);
    }

    public function latestSyncLog(): HasOne
    {
        return $this->hasOne(ShopifySyncLog::class)->latestOfMany();
    }

    public function collections(): HasMany
    {
        return $this->hasMany(ShopifyCollection::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ShopifyPage::class);
    }

    public function knowledgeBase(): HasOne
    {
        return $this->hasOne(StoreKnowledgeBase::class);
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function existingBlogs(): HasMany
    {
        return $this->hasMany(ExistingShopifyBlog::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(StoreAnalysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(StoreAnalysis::class)->latestOfMany();
    }

    public function visibilityReports(): HasMany
    {
        return $this->hasMany(AeoGeoVisibilityReport::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function latestVisibilityReport(): HasOne
    {
        return $this->hasOne(AeoGeoVisibilityReport::class)->latestOfMany();
    }
}
