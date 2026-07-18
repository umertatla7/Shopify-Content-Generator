<?php

namespace App\Jobs;

use App\Models\BlogSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PublishScheduledBlogsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 55;

    public function __construct()
    {
        $this->onQueue('shopify');
    }

    public function handle(): void
    {
        $this->recoverStaleClaims();

        BlogSchedule::query()
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->limit(50)
            ->pluck('id')
            ->each(function (int $scheduleId): void {
                if ($this->claim($scheduleId)) {
                    PublishScheduledBlogJob::dispatch($scheduleId);
                }
            });
    }

    private function claim(int $scheduleId): bool
    {
        return DB::transaction(function () use ($scheduleId): bool {
            $schedule = BlogSchedule::query()->lockForUpdate()->find($scheduleId);

            if (! $schedule || $schedule->status !== 'pending' || $schedule->scheduled_for->isFuture()) {
                return false;
            }

            $schedule->update([
                'status' => 'running',
                'last_attempt_at' => now(),
                'error_message' => null,
            ]);

            return true;
        });
    }

    private function recoverStaleClaims(): void
    {
        BlogSchedule::query()
            ->where('status', 'running')
            ->where('last_attempt_at', '<=', now()->subMinutes((int) config('operations.schedules.stale_after_minutes', 10)))
            ->update([
                'status' => 'pending',
                'error_message' => 'A previous publishing worker stopped before completion. The schedule was recovered automatically.',
            ]);
    }

    public function uniqueId(): string
    {
        return 'scheduled-publishing-dispatcher';
    }
}
