<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Support\PlanFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlogWorkflowController extends Controller
{
    public function markNeedsReview(Blog $blog): RedirectResponse
    {
        $this->authorize('update', $blog);
        abort_unless(PlanFeatureGate::moduleAccess(request()->user()->currentAccount)['blogs'], 403);

        $blog->update(['status' => Blog::STATUS_NEEDS_REVIEW]);

        return back()->with('status', 'Blog marked for review.');
    }

    public function approve(Request $request, Blog $blog): RedirectResponse
    {
        $this->authorize('approve', $blog);
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)['blogs'], 403);

        $blog->update([
            'status' => Blog::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'failure_message' => null,
        ]);

        return back()->with('status', 'Blog approved.');
    }

    public function reject(Blog $blog): RedirectResponse
    {
        $this->authorize('approve', $blog);
        abort_unless(PlanFeatureGate::moduleAccess(request()->user()->currentAccount)['blogs'], 403);

        $blog->update(['status' => Blog::STATUS_REJECTED]);

        return back()->with('status', 'Blog rejected.');
    }

    public function assign(Request $request, Blog $blog): RedirectResponse
    {
        $this->authorize('update', $blog);
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)['blogs'], 403);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $blog->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        return back()->with('status', 'Blog assignment updated.');
    }
}
