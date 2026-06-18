<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogRevision extends Model
{
    use BelongsToAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'internal_links' => 'array',
            'product_links' => 'array',
        ];
    }

    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
