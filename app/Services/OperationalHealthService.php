<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalHealthService
{
    public function inspect(): array
    {
        $checks = [
            'database' => $this->database(),
            'scheduler' => $this->heartbeat('operations:scheduler-heartbeat', 'require_scheduler'),
            'queue_worker' => $this->heartbeat('operations:queue-worker-heartbeat', 'require_queue_worker'),
            'queue_backlog' => $this->queueBacklog(),
            'failed_jobs' => $this->failedJobs(),
            'mail' => $this->mail(),
        ];

        return [
            'status' => collect($checks)->every(fn (array $check): bool => $check['ok']) ? 'ok' : 'degraded',
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true];
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Database connection failed.'];
        }
    }

    private function heartbeat(string $key, string $requirement): array
    {
        if (! config("operations.health.{$requirement}")) {
            return ['ok' => true, 'required' => false];
        }

        try {
            $lastSeen = Cache::get($key);
        } catch (Throwable) {
            return [
                'ok' => false,
                'required' => true,
                'age_seconds' => null,
                'message' => 'Heartbeat storage is unavailable.',
            ];
        }
        $age = $lastSeen ? now()->diffInSeconds($lastSeen) : null;
        $ok = $age !== null && $age <= (int) config('operations.health.heartbeat_ttl_seconds', 180);

        return [
            'ok' => $ok,
            'required' => true,
            'age_seconds' => $age,
            'message' => $ok ? null : 'Heartbeat is missing or stale.',
        ];
    }

    private function queueBacklog(): array
    {
        if (! Schema::hasTable('jobs')) {
            return ['ok' => true, 'available' => false];
        }

        $oldest = DB::table('jobs')->min('created_at');
        $age = $oldest ? max(0, now()->timestamp - (int) $oldest) : 0;
        $limit = (int) config('operations.health.max_queue_age_seconds', 600);

        return [
            'ok' => $age <= $limit,
            'available' => true,
            'pending' => DB::table('jobs')->count(),
            'oldest_age_seconds' => $age,
            'message' => $age <= $limit ? null : 'The queue backlog is older than the configured limit.',
        ];
    }

    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['ok' => true, 'available' => false];
        }

        $count = DB::table('failed_jobs')->count();
        $limit = (int) config('operations.health.max_failed_jobs', 0);

        return [
            'ok' => $count <= $limit,
            'available' => true,
            'count' => $count,
            'message' => $count <= $limit ? null : 'Failed jobs require review.',
        ];
    }

    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $required = (bool) config('operations.health.require_real_mail');
        $ok = ! $required || ! in_array($mailer, ['log', 'array', 'null'], true);

        return [
            'ok' => $ok,
            'required' => $required,
            'configured' => ! in_array($mailer, ['log', 'array', 'null'], true),
            'message' => $ok ? null : 'Transactional mail delivery is not configured.',
        ];
    }
}
