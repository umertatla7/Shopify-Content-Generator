<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name', 'GrowShopHigh') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900">
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-sm font-semibold text-teal-700">{{ $companyName }}</div>
                <h1 class="mt-1 text-2xl font-bold text-zinc-950">{{ $title }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600">{{ $intro }}</p>
            </div>

            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('public.privacy') }}" class="rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-100">Privacy</a>
                <a href="{{ route('public.terms') }}" class="rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-100">Terms</a>
                <a href="{{ route('public.support') }}" class="rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-100">Support</a>
                <a href="{{ route('public.billing-guide') }}" class="rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-100">Billing Guide</a>
                <a href="{{ route('public.review-guide') }}" class="rounded-md border border-zinc-200 px-3 py-2 hover:bg-zinc-100">Review Guide</a>
            </nav>
        </div>
    </div>

    <main class="mx-auto max-w-5xl px-6 py-8">
        <div class="grid gap-6 lg:grid-cols-[1.75fr_0.95fr]">
            <div class="space-y-5">
                @foreach ($sections as $section)
                    <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-bold text-zinc-950">{{ $section['title'] }}</h2>
                        <ul class="mt-3 space-y-2 text-sm leading-6 text-zinc-700">
                            @foreach ($section['points'] as $point)
                                <li class="flex gap-3">
                                    <span class="mt-2 size-1.5 shrink-0 rounded-full bg-teal-600"></span>
                                    <span>{{ $point }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            <aside class="space-y-5">
                <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-zinc-500">Contact</h2>
                    <div class="mt-4 space-y-4 text-sm text-zinc-700">
                        <div>
                            <div class="font-semibold text-zinc-950">Support email</div>
                            <div class="mt-1">{{ $supportEmail ?: 'Set APP_REVIEW_SUPPORT_EMAIL in .env' }}</div>
                        </div>
                        <div>
                            <div class="font-semibold text-zinc-950">Legal email</div>
                            <div class="mt-1">{{ $legalEmail ?: 'Set APP_REVIEW_LEGAL_EMAIL in .env' }}</div>
                        </div>
                        <div>
                            <div class="font-semibold text-zinc-950">Website</div>
                            <div class="mt-1 break-all">{{ $websiteUrl }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-zinc-500">Shopify review notes</h2>
                    <p class="mt-4 text-sm leading-6 text-zinc-700">
                        These pages are meant to stay public so merchants and Shopify reviewers can access policy, support, and setup information without logging in.
                    </p>
                </section>
            </aside>
        </div>
    </main>
</body>
</html>
