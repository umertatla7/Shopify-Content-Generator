<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\ShopifyStore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminActivityController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search', 'action', 'module', 'status', 'account_id', 'shopify_store_id', 'date_from', 'date_to']);

        return Inertia::render('Admin/Activity/Index', [
            'filters' => $filters,
            'activity' => ActivityLog::query()
                ->with(['user:id,name,email', 'account:id,name', 'store:id,name'])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                }))
                ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
                ->when($filters['module'] ?? null, function ($query, $module): void {
                    $query->where(function ($query) use ($module): void {
                        $query->where('action', 'like', "{$module}.%")
                            ->orWhere('entity_type', 'like', "%{$module}%");
                    });
                })
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
                ->when($filters['shopify_store_id'] ?? null, fn ($query, $storeId) => $query->where('shopify_store_id', $storeId))
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'actions' => ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'modules' => $this->modules(),
            'statuses' => ActivityLog::query()
                ->select('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
            'accounts' => Account::query()->orderBy('name')->get(['id', 'name']),
            'stores' => ShopifyStore::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function modules(): array
    {
        return ActivityLog::query()
            ->select('action')
            ->distinct()
            ->pluck('action')
            ->map(fn (string $action) => str($action)->before('.')->toString())
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
