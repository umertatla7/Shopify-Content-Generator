<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTopic;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTopicController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/Topics/Index', [
            'filters' => $filters,
            'topics' => BlogTopic::query()
                ->with(['account:id,name', 'store:id,name'])
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
