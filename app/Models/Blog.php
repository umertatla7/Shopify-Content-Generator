<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use BelongsToAccount;
    use HasFactory;
    use SanitizesHtml;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'internal_links' => 'array',
            'product_links' => 'array',
            'secondary_keywords' => 'array',
            'featured_image_payload' => 'array',
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'featured_image_generated_at' => 'datetime',
        ];
    }

    protected function body(): Attribute
    {
        return $this->sanitizedHtmlAttribute();
    }

    protected function excerpt(): Attribute
    {
        return $this->sanitizedHtmlAttribute();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class, 'shopify_store_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(BlogProject::class, 'blog_project_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(BlogTopic::class, 'blog_topic_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BlogRevision::class)->latest('version');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(BlogSchedule::class);
    }

    public function canPublish(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHED,
        ], true);
    }

    public function hasPublishableBody(): bool
    {
        return trim(html_entity_decode(strip_tags((string) $this->body))) !== '';
    }
}
