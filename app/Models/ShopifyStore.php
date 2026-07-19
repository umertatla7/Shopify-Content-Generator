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
        return $this->hasOne(ShopifySyncLog::class)
            ->select([
                'shopify_sync_logs.id',
                'shopify_sync_logs.account_id',
                'shopify_sync_logs.shopify_store_id',
                'shopify_sync_logs.sync_type',
                'shopify_sync_logs.status',
                'shopify_sync_logs.counts',
                'shopify_sync_logs.metadata',
                'shopify_sync_logs.started_at',
                'shopify_sync_logs.completed_at',
                'shopify_sync_logs.error_message',
                'shopify_sync_logs.created_at',
                'shopify_sync_logs.updated_at',
            ])
            ->latestOfMany();
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
