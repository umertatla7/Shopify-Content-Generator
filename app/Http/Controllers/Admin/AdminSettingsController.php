<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function index(Request $request, SystemSettingService $settings): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return Inertia::render('Admin/Settings/Index', [
            'groups' => collect($this->definitions())->map(function (array $group) use ($settings): array {
                $group['fields'] = collect($group['fields'])->map(function (array $field) use ($settings): array {
                    $fallback = $this->configFallback($field['key']);
                    $isSecret = (bool) ($field['secret'] ?? true);

                    return [
                        ...$field,
                        'configured' => $settings->configured($field['key'], $fallback),
                        'source' => $settings->source($field['key'], $fallback),
                        'value' => $isSecret ? '' : (string) $settings->get($field['key'], $fallback),
                    ];
                })->values()->all();

                return $group;
            })->values()->all(),
        ]);
    }

    public function update(Request $request, SystemSettingService $settings): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $definitions = collect($this->definitions())
            ->flatMap(fn (array $group): array => $group['fields'])
            ->keyBy('key');

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $definition = $definitions->get($key);

            if (! $definition) {
                continue;
            }

            $isSecret = (bool) ($definition['secret'] ?? true);

            if ($isSecret && blank($value)) {
                continue;
            }

            $settings->set($key, $value, $isSecret, [
                'label' => $definition['label'],
                'group' => $definition['group'] ?? null,
            ]);
        }

        return back()->with('status', 'Provider settings saved.');
    }

    private function definitions(): array
    {
        return [
            [
                'label' => 'AI providers',
                'description' => 'Keys and defaults used for AI content generation.',
                'fields' => [
                    ['key' => 'ai_provider', 'label' => 'AI provider', 'secret' => false, 'placeholder' => 'openai or stub'],
                    ['key' => 'openai_api_key', 'label' => 'OpenAI API key', 'secret' => true, 'placeholder' => 'sk-...'],
                    ['key' => 'openai_model', 'label' => 'OpenAI model', 'secret' => false, 'placeholder' => 'gpt-4.1-mini'],
                    ['key' => 'openai_base_url', 'label' => 'OpenAI base URL', 'secret' => false, 'placeholder' => 'https://api.openai.com/v1'],
                    ['key' => 'gemini_api_key', 'label' => 'Gemini API key', 'secret' => true, 'placeholder' => 'Future provider key'],
                ],
            ],
            [
                'label' => 'SEO data providers',
                'description' => 'Keys used for technical audits and keyword/rank data.',
                'fields' => [
                    ['key' => 'pagespeed_insights_api_key', 'label' => 'PageSpeed Insights API key', 'secret' => true, 'placeholder' => 'Google API key'],
                    ['key' => 'dataforseo_login', 'label' => 'DataForSEO login', 'secret' => true, 'placeholder' => 'DataForSEO account email'],
                    ['key' => 'dataforseo_password', 'label' => 'DataForSEO password', 'secret' => true, 'placeholder' => 'DataForSEO API password'],
                ],
            ],
            [
                'label' => 'Google Search Console',
                'description' => 'OAuth client used when customers connect Search Console.',
                'fields' => [
                    ['key' => 'google_search_console_client_id', 'label' => 'Client ID', 'secret' => true, 'placeholder' => 'Google OAuth client ID'],
                    ['key' => 'google_search_console_client_secret', 'label' => 'Client Secret', 'secret' => true, 'placeholder' => 'Google OAuth client secret'],
                    ['key' => 'google_search_console_redirect_uri', 'label' => 'Redirect URI', 'secret' => false, 'placeholder' => url('/search-console/callback')],
                ],
            ],
        ];
    }

    private function configFallback(string $key): mixed
    {
        return match ($key) {
            'ai_provider' => config('services.ai.provider'),
            'openai_api_key' => config('services.openai.api_key'),
            'openai_model' => config('services.openai.model'),
            'openai_base_url' => config('services.openai.base_url'),
            'pagespeed_insights_api_key' => config('services.pagespeed.api_key'),
            'google_search_console_client_id' => config('services.google_search_console.client_id'),
            'google_search_console_client_secret' => config('services.google_search_console.client_secret'),
            'google_search_console_redirect_uri' => config('services.google_search_console.redirect_uri'),
            default => null,
        };
    }
}
