<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Services\BlogImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlogImageController extends Controller
{
    public function store(Request $request, Blog $blog, BlogImageService $images): RedirectResponse
    {
        $this->authorize('update', $blog);

        $images->createBrief($blog, $request->user());

        return back()->with('status', 'Blog image brief generated.');
    }
}
