<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (config('services.shopify.public_app_api_key'))
            <meta name="shopify-api-key" content="{{ config('services.shopify.public_app_api_key') }}">
        @endif
        <title inertia>{{ config('app.name', 'SEO & AEO Content Generator') }}</title>
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
