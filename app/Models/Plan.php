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
            'monthly_blog_limit' => 'integer',
            'monthly_topic_limit' => 'integer',
            'monthly_credit_allowance' => 'integer',
            'product_description_limit' => 'integer',
            'collection_description_limit' => 'integer',
            'monthly_seo_report_limit' => 'integer',
            'monthly_ai_visibility_report_limit' => 'integer',
            'tracked_keyword_limit' => 'integer',
            'max_blog_word_count' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
