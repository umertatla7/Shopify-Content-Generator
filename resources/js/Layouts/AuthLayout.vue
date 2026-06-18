<script setup>
const brandLogo = '/images/growthpilot-ai-logo.png';
</script>

<template>
    <div class="grid min-h-screen bg-zinc-50 lg:grid-cols-[1fr_460px]">
        <section class="hidden bg-zinc-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <img :src="brandLogo" alt="SEO & AEO Content Generator" class="h-12 w-12 rounded-md object-cover object-left" />
                <div class="font-bold">SEO & AEO Content Generator</div>
            </div>
            <div class="max-w-xl">
                <h1 class="text-4xl font-bold leading-tight">AI content. Higher rankings. Real growth.</h1>
                <p class="mt-4 text-sm leading-6 text-zinc-300">Built for Shopify store owners, agencies, and content teams turning store data into publish-ready SEO content.</p>
            </div>
            <div class="grid grid-cols-3 gap-3 text-xs text-zinc-300">
                <div class="rounded-md border border-white/10 p-3">Store sync</div>
                <div class="rounded-md border border-white/10 p-3">AI drafts</div>
                <div class="rounded-md border border-white/10 p-3">One-click publish</div>
            </div>
        </section>

        <main class="flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
                <slot />
            </div>
        </main>
    </div>
</template>
