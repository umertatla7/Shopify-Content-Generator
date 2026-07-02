<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\PlanFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::query()
                ->orderByRaw("case `key` when 'free' then 1 when 'growth' then 2 when 'pro' then 3 else 99 end")
                ->orderBy('id')
                ->get(),
            'featureOptions' => PlanFeatureGate::featureOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $this->validated($request);

        Plan::query()->create($validated);

        return back()->with('status', 'Plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $plan->update($this->validated($request, $plan));

        return back()->with('status', 'Plan updated.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:64', Rule::unique('plans', 'key')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'monthly_blog_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_ai_token_limit' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'monthly_credit_allowance' => ['required', 'integer', 'min:0', 'max:10000000'],
            'word_limit_estimate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'store_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'user_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'product_description_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'collection_description_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_seo_report_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_ai_visibility_report_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_image_optimization_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_image_alt_text_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tracked_keyword_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'credit_expires_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'shopify_billing_plan_handle' => ['nullable', 'string', 'max:128'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
