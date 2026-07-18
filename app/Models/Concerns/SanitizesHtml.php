<?php

namespace App\Models\Concerns;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SanitizesHtml
{
    protected function sanitizedHtmlAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => HtmlSanitizer::clean($value),
            set: fn (?string $value): ?string => HtmlSanitizer::clean($value),
        );
    }
}
