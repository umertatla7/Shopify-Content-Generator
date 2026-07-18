<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Notifications\OperationalFailureNotification;
use App\Policies\AccountPolicy;
use App\Policies\BlogPolicy;
use App\Policies\BlogTopicPolicy;
use App\Policies\ShopifyStorePolicy;
use App\Policies\StoreAnalysisPolicy;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\OpenAIProviderService;
use App\Services\AI\StubAIProviderService;
use App\Services\SystemSettingService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function () {
            $provider = app(SystemSettingService::class)->get('ai_provider', config('services.ai.provider'));

            return $provider === 'openai'
                ? app(OpenAIProviderService::class)
                : app(StubAIProviderService::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(ShopifyStore::class, ShopifyStorePolicy::class);
        Gate::policy(StoreAnalysis::class, StoreAnalysisPolicy::class);
        Gate::policy(BlogTopic::class, BlogTopicPolicy::class);
        Gate::policy(Blog::class, BlogPolicy::class);

        Queue::looping(function (): void {
            $lastSeen = Cache::get('operations:queue-worker-heartbeat');

            if (! $lastSeen || now()->diffInSeconds($lastSeen) >= 30) {
                Cache::put('operations:queue-worker-heartbeat', now(), now()->addMinutes(10));
            }
        });

        Queue::failing(function (JobFailed $event): void {
            $job = $event->job->resolveName();
            $queue = $event->job->getQueue();
            $message = $event->exception->getMessage();

            Log::critical('Queue job exhausted its retries.', [
                'job' => $job,
                'queue' => $queue,
                'exception' => $event->exception,
            ]);

            $email = config('operations.alert_email');

            if (! $email) {
                return;
            }

            try {
                Notification::route('mail', $email)
                    ->notify(new OperationalFailureNotification($job, $queue, $message));
            } catch (Throwable $exception) {
                Log::error('Operational failure notification could not be delivered.', [
                    'job' => $job,
                    'notification_error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
