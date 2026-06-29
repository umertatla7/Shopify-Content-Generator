<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Policies\AccountPolicy;
use App\Policies\BlogPolicy;
use App\Policies\BlogTopicPolicy;
use App\Policies\ShopifyStorePolicy;
use App\Policies\StoreAnalysisPolicy;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\OpenAIProviderService;
use App\Services\AI\StubAIProviderService;
use App\Services\SystemSettingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
