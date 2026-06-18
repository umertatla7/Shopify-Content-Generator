<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\BlogSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BlogSchedulingService
{
    public function schedule(Blog $blog, CarbonInterface|string $scheduledFor, ?string $recurrenceRule = null, ?string $timezone = null): BlogSchedule
    {
        $blog->loadMissing(['store', 'account']);
        $timezone = $timezone ?: ($blog->store?->timezone ?: ($blog->account->timezone ?? config('app.timezone')));
        $scheduledForUtc = $scheduledFor instanceof CarbonInterface
            ? $scheduledFor->copy()->setTimezone('UTC')
            : Carbon::parse($scheduledFor, $timezone)->utc();

        $schedule = BlogSchedule::query()->updateOrCreate(
            ['blog_id' => $blog->id],
            [
                'account_id' => $blog->account_id,
                'shopify_store_id' => $blog->shopify_store_id,
                'scheduled_for' => $scheduledForUtc,
                'timezone' => $timezone,
                'recurrence_rule' => $recurrenceRule,
                'status' => 'pending',
                'error_message' => null,
            ]
        );

        $blog->update([
            'status' => Blog::STATUS_SCHEDULED,
            'scheduled_at' => $schedule->scheduled_for,
        ]);

        return $schedule;
    }
}
