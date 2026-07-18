<?php

use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\OperationalHealthController;
use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/shopify', [ShopifyWebhookController::class, 'handle'])
    ->middleware(['shopify.webhook', 'throttle:120,1'])
    ->name('shopify.webhooks.handle');

Route::get('/health/ready', OperationalHealthController::class)
    ->middleware('throttle:30,1')
    ->name('health.ready');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/me', [WorkspaceController::class, 'me']);
    Route::get('/analytics/summary', [WorkspaceController::class, 'summary']);
    Route::get('/stores', [WorkspaceController::class, 'stores']);
    Route::post('/stores/{store}/sync', [WorkspaceController::class, 'syncStore']);
    Route::post('/stores/{store}/analysis', [WorkspaceController::class, 'analyzeStore']);
    Route::post('/stores/{store}/topics', [WorkspaceController::class, 'generateTopics']);
    Route::get('/blogs', [WorkspaceController::class, 'blogs']);
    Route::post('/topics/{topic}/generate-blog', [WorkspaceController::class, 'generateBlog']);
    Route::post('/blogs/{blog}/publish', [WorkspaceController::class, 'publishBlog']);
});
