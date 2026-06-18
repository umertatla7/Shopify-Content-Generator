<?php

namespace App\Models;

use App\Services\AICostService;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AIGeneration extends Model
{
    use BelongsToAccount;

    protected $table = 'ai_generations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_usage' => 'array',
            'metadata' => 'array',
            'cost' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AIGeneration $generation): void {
            if ($generation->token_usage && $generation->model && $generation->cost === null) {
                $generation->cost = app(AICostService::class)->calculate($generation->model, $generation->token_usage);
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generatable(): MorphTo
    {
        return $this->morphTo();
    }
}
