<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'monthly_price' => 'decimal:2',
            'trial_days' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
