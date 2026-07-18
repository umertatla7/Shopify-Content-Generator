<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateShopifySessionToken;
use App\Http\Middleware\EnsureCatalogSynced;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SyncShopifyAccountContext;
use App\Http\Middleware\VerifyShopifyWebhook;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'shopify.webhook' => VerifyShopifyWebhook::class,
        ]);

        $middleware->web(append: [
            AuthenticateShopifySessionToken::class,
            SyncShopifyAccountContext::class,
            EnsureCatalogSynced::class,
            HandleInertiaRequests::class,
            AddSecurityHeaders::class,
        ]);

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            AuthenticateShopifySessionToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
