<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminBlogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminTopicController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AeoGeoVisibilityController;
use App\Http\Controllers\AIBlogEditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlogBodyGenerationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogImageController;
use App\Http\Controllers\BlogPublishController;
use App\Http\Controllers\BlogScheduleController;
use App\Http\Controllers\BlogTopicController;
use App\Http\Controllers\BlogWorkflowController;
use App\Http\Controllers\CollectionContentController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductContentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchConsoleController;
use App\Http\Controllers\ShopifyInstallController;
use App\Http\Controllers\ShopifyStoreController;
use App\Http\Controllers\StoreAnalysisController;
use App\Http\Controllers\StoreKnowledgeBaseController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:3,1');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::get('/shopify/app', [ShopifyInstallController::class, 'app'])->name('shopify.app');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::get('/accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [AdminAccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [AdminAccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}', [AdminAccountController::class, 'show'])->name('accounts.show');
        Route::patch('/accounts/{account}', [AdminAccountController::class, 'update'])->name('accounts.update');
        Route::patch('/accounts/{account}/package', [AdminAccountController::class, 'updatePackage'])->name('accounts.package.update');
        Route::post('/accounts/{account}/credits', [AdminAccountController::class, 'adjustCredits'])->name('accounts.credits.adjust');
        Route::patch('/accounts/{account}/stores/{store}', [AdminAccountController::class, 'updateStore'])->name('accounts.stores.update');
        Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('/stores', [AdminStoreController::class, 'index'])->name('stores.index');
        Route::get('/topics', [AdminTopicController::class, 'index'])->name('topics.index');
        Route::get('/blogs', [AdminBlogController::class, 'index'])->name('blogs.index');
        Route::get('/activity', [AdminActivityController::class, 'index'])->name('activity.index');
    });

    Route::get('/stores', [ShopifyStoreController::class, 'index'])->name('stores.index');
    Route::get('/shopify/install/start', [ShopifyInstallController::class, 'start'])->name('shopify.install.start');
    Route::get('/shopify/oauth/callback', [ShopifyInstallController::class, 'callback'])->name('shopify.oauth.callback');
    Route::post('/stores', [ShopifyStoreController::class, 'store'])->name('stores.store')->middleware('throttle:10,1');
    Route::post('/stores/{store}/sync', [ShopifyStoreController::class, 'sync'])->name('stores.sync');
    Route::post('/stores/{store}/analysis', [StoreAnalysisController::class, 'store'])->name('stores.analysis.store');
    Route::get('/stores/{store}/knowledge-base', [StoreKnowledgeBaseController::class, 'show'])->name('stores.knowledge-base.show');
    Route::post('/stores/{store}/knowledge-base', [StoreKnowledgeBaseController::class, 'generate'])->name('stores.knowledge-base.generate');
    Route::patch('/stores/{store}/knowledge-base', [StoreKnowledgeBaseController::class, 'update'])->name('stores.knowledge-base.update');
    Route::delete('/stores/{store}', [ShopifyStoreController::class, 'destroy'])->name('stores.destroy');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/plans/{plan}/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::get('/billing/confirm', [BillingController::class, 'confirm'])->name('billing.confirm');
    Route::post('/billing/sync', [BillingController::class, 'sync'])->name('billing.sync');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products/{product}/generate-content', [ProductContentController::class, 'generate'])->name('products.generate-content');
    Route::post('/products/{product}/push-content', [ProductContentController::class, 'push'])->name('products.push-content');

    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/collections/{collection}/generate-content', [CollectionContentController::class, 'generate'])->name('collections.generate-content');
    Route::post('/collections/{collection}/push-content', [CollectionContentController::class, 'push'])->name('collections.push-content');

    Route::get('/rank-tracking', [SearchConsoleController::class, 'index'])->name('rank-tracking.index');
    Route::get('/search-console/connect', [SearchConsoleController::class, 'connect'])->name('search-console.connect');
    Route::get('/search-console/callback', [SearchConsoleController::class, 'callback'])->name('search-console.callback');
    Route::post('/rank-tracking/search-console/properties/sync', [SearchConsoleController::class, 'syncProperties'])->name('search-console.properties.sync');
    Route::patch('/rank-tracking/search-console/properties/{property}', [SearchConsoleController::class, 'updateProperty'])->name('search-console.properties.update');
    Route::post('/rank-tracking/search-console/sync', [SearchConsoleController::class, 'syncPerformance'])->name('search-console.performance.sync');
    Route::post('/tracked-keywords', [SearchConsoleController::class, 'storeTrackedKeyword'])->name('tracked-keywords.store');
    Route::delete('/tracked-keywords/{trackedKeyword}', [SearchConsoleController::class, 'destroyTrackedKeyword'])->name('tracked-keywords.destroy');

    Route::get('/ai-visibility', [AeoGeoVisibilityController::class, 'index'])->name('visibility.index');
    Route::post('/ai-visibility/reports', [AeoGeoVisibilityController::class, 'store'])->name('visibility.reports.store');

    Route::get('/topics', [BlogTopicController::class, 'index'])->name('topics.index');
    Route::post('/stores/{store}/topics', [BlogTopicController::class, 'generate'])->name('topics.generate');
    Route::patch('/topics/{topic}', [BlogTopicController::class, 'update'])->name('topics.update');
    Route::post('/topics/{topic}/approve', [BlogTopicController::class, 'approve'])->name('topics.approve');
    Route::post('/topics/{topic}/reject', [BlogTopicController::class, 'reject'])->name('topics.reject');
    Route::post('/topics/{topic}/generate-blog', [BlogTopicController::class, 'generateBlog'])->name('topics.generate-blog');
    Route::post('/topics/generate-selected-blogs', [BlogTopicController::class, 'generateSelectedBlogs'])->name('topics.generate-selected-blogs');

    Route::get('/blogs', [BlogController::class, 'index'])->name('blogs.index');
    Route::get('/blogs/{blog}/edit', [BlogController::class, 'edit'])->name('blogs.edit');
    Route::patch('/blogs/{blog}', [BlogController::class, 'update'])->name('blogs.update');
    Route::post('/blogs/{blog}/sync-shopify', [BlogController::class, 'syncFromShopify'])->name('blogs.sync-shopify');
    Route::delete('/blogs/{blog}', [BlogController::class, 'destroy'])->name('blogs.destroy');
    Route::post('/blogs/{blog}/comments', [BlogController::class, 'comment'])->name('blogs.comments.store');
    Route::post('/blogs/{blog}/generate-body', BlogBodyGenerationController::class)->name('blogs.generate-body');
    Route::post('/blogs/{blog}/needs-review', [BlogWorkflowController::class, 'markNeedsReview'])->name('blogs.needs-review');
    Route::post('/blogs/{blog}/approve', [BlogWorkflowController::class, 'approve'])->name('blogs.approve');
    Route::post('/blogs/{blog}/reject', [BlogWorkflowController::class, 'reject'])->name('blogs.reject');
    Route::post('/blogs/{blog}/assign', [BlogWorkflowController::class, 'assign'])->name('blogs.assign');
    Route::post('/blogs/{blog}/ai-edit', AIBlogEditController::class)->name('blogs.ai-edit')->middleware('throttle:20,1');
    Route::post('/blogs/{blog}/image', [BlogImageController::class, 'store'])->name('blogs.image.store')->middleware('throttle:20,1');
    Route::post('/blogs/{blog}/schedule', [BlogScheduleController::class, 'store'])->name('blogs.schedule');
    Route::post('/blogs/{blog}/publish', [BlogPublishController::class, 'publish'])->name('blogs.publish');
    Route::post('/blogs/publish-selected', [BlogPublishController::class, 'publishSelected'])->name('blogs.publish-selected');
    Route::post('/blogs/publish-approved', [BlogPublishController::class, 'publishAllApproved'])->name('blogs.publish-approved');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
});
