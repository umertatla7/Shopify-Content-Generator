<?php

namespace App\Http\Controllers;

use App\Jobs\RewriteBlogJob;
use App\Models\Blog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIBlogEditController extends Controller
{
    public function __invoke(Request $request, Blog $blog): RedirectResponse
    {
        $this->authorize('update', $blog);

        $validated = $request->validate([
            'instruction' => ['required', 'string', 'max:2000'],
        ]);

        RewriteBlogJob::dispatch($blog->id, $validated['instruction'], $request->user()->id);

        return back()->with('status', 'AI rewrite queued.');
    }
}
