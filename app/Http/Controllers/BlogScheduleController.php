<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Services\BlogSchedulingService;
use App\Support\PlanFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlogScheduleController extends Controller
{
    public function store(Request $request, Blog $blog, BlogSchedulingService $schedules): RedirectResponse
    {
        $this->authorize('approve', $blog);
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)['schedule'], 403);

        $validated = $request->validate([
            'scheduled_for' => ['required', 'date', 'after:now'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
        ]);

        if ($blog->status !== Blog::STATUS_APPROVED) {
            return back()->withErrors(['scheduled_for' => 'Only approved blogs can be scheduled.']);
        }

        $schedules->schedule($blog, $validated['scheduled_for'], $validated['recurrence_rule'] ?? null, $validated['timezone'] ?? null);

        return back()->with('status', 'Blog scheduled.');
    }
}
