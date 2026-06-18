<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBlogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/Blogs/Index', [
            'filters' => $filters,
            'statuses' => [
                Blog::STATUS_DRAFT,
                Blog::STATUS_NEEDS_REVIEW,
                Blog::STATUS_APPROVED,
                Blog::STATUS_SCHEDULED,
                Blog::STATUS_PUBLISHED,
                Blog::STATUS_FAILED,
                Blog::STATUS_REJECTED,
            ],
            'blogs' => Blog::query()
                ->with(['account:id,name', 'store:id,name', 'assignee:id,name'])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('primary_keyword', 'like', "%{$search}%");
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
