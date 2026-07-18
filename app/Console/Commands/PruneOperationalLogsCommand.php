<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\PublishingLog;
use App\Models\ShopifySyncLog;
use App\Models\ShopifyWebhookDelivery;
use App\Models\UsageLog;
use Illuminate\Console\Command;

class PruneOperationalLogsCommand extends Command
{
    protected $signature = 'app:prune-logs {--days=90 : Delete logs older than this many days} {--include-usage : Also prune usage logs}';

    protected $description = 'Prune old operational logs to keep the support tables lean';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = [
            'activity_logs' => ActivityLog::query()->where('created_at', '<', $cutoff)->delete(),
            'publishing_logs' => PublishingLog::query()->where('created_at', '<', $cutoff)->delete(),
            'shopify_sync_logs' => ShopifySyncLog::query()->where('created_at', '<', $cutoff)->delete(),
            'shopify_webhook_deliveries' => ShopifyWebhookDelivery::query()->where('created_at', '<', $cutoff)->delete(),
        ];

        if ($this->option('include-usage')) {
            $deleted['usage_logs'] = UsageLog::query()->where('created_at', '<', $cutoff)->delete();
        }

        foreach ($deleted as $table => $count) {
            $this->line("{$table}: {$count} deleted");
        }

        $this->components->info("Log pruning completed for records older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
