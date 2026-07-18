<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueBacklogReportCommand extends Command
{
    protected $signature = 'app:queue-report {--limit=100 : Maximum pending jobs to display}';

    protected $description = 'Display a read-only report of pending and failed queue jobs';

    public function handle(): int
    {
        if (! Schema::hasTable('jobs')) {
            $this->components->warn('The jobs table does not exist for the current connection.');

            return self::SUCCESS;
        }

        $totalPending = DB::table('jobs')->count();
        $jobs = DB::table('jobs')
            ->orderBy('id')
            ->limit(max(1, min((int) $this->option('limit'), 500)))
            ->get();

        $this->table(
            ['ID', 'Queue', 'Job', 'Attempts', 'Age', 'References'],
            $jobs->map(function (object $job): array {
                $payload = json_decode($job->payload, true) ?: [];
                $command = (string) data_get($payload, 'data.command', '');

                return [
                    $job->id,
                    $job->queue,
                    class_basename((string) ($payload['displayName'] ?? 'Unknown job')),
                    $job->attempts,
                    now()->diffForHumans(now()->setTimestamp((int) $job->created_at), true),
                    $this->references($command),
                ];
            })->all(),
        );

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $this->components->info("Pending jobs: {$totalPending}; displayed: {$jobs->count()}; failed jobs: {$failed}.");
        $this->line('This command is read-only. Review old jobs before starting a worker or retrying anything.');

        return self::SUCCESS;
    }

    private function references(string $command): string
    {
        $references = [];

        foreach (['storeId', 'blogId', 'topicId', 'scheduleId', 'userId', 'syncLogId'] as $property) {
            if (preg_match('/s:\\d+:"'.preg_quote($property, '/').'";i:(\\d+);/', $command, $matches)) {
                $references[] = "{$property}={$matches[1]}";
            }
        }

        return implode(', ', $references) ?: '-';
    }
}
