<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ShopifyStore;
use App\Models\ShopifyWebhookDelivery;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $topic = strtolower(trim((string) $request->header('X-Shopify-Topic')));
        $shop = strtolower(trim((string) $request->header('X-Shopify-Shop-Domain')));
        $webhookId = trim((string) $request->header('X-Shopify-Webhook-Id'));

        abort_unless(in_array($topic, $this->supportedTopics(), true), 404);

        if (! Str::isUuid($webhookId)) {
            abort(400, 'Missing Shopify webhook ID.');
        }

        try {
            $delivery = ShopifyWebhookDelivery::query()->create([
                'webhook_id' => $webhookId,
                'topic' => $topic,
                'shop_domain' => $shop,
                'payload_hash' => hash('sha256', $request->getContent()),
                'status' => 'processing',
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateDelivery($exception)) {
                return response()->json(['status' => 'already_processed']);
            }

            throw $exception;
        }

        try {
            $this->process($topic, $shop);
            $delivery->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 2000),
                'processed_at' => now(),
            ]);

            throw $exception;
        }

        return response()->json(['status' => 'processed']);
    }

    private function process(string $topic, string $shop): void
    {
        if (in_array($topic, ['customers/data_request', 'customers/redact'], true)) {
            // This app does not request or persist Shopify customer records.
            return;
        }

        $store = ShopifyStore::query()->where('shop_domain', $shop)->first();

        if (! $store) {
            return;
        }

        if ($topic === 'app/uninstalled') {
            DB::transaction(function () use ($store): void {
                $store->credential()->delete();
                $store->update([
                    'status' => 'disconnected',
                    'validation_error' => 'Shopify app uninstalled.',
                    'metadata' => [
                        ...($store->metadata ?? []),
                        'uninstalled_at' => now()->toIso8601String(),
                    ],
                ]);
            });

            return;
        }

        if ($topic === 'shop/redact') {
            DB::transaction(function () use ($store): void {
                $account = $store->account()->with('users:id')->first();
                $userIds = collect($account?->users->pluck('id')->all() ?? [])
                    ->push($account?->owner_id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $store->delete();

                if ($account && ! $account->stores()->exists()) {
                    ActivityLog::query()->where('account_id', $account->id)->delete();
                    $account->delete();

                    User::query()
                        ->whereKey($userIds)
                        ->where('global_role', 'user')
                        ->whereDoesntHave('accounts')
                        ->delete();
                }
            });
        }
    }

    private function supportedTopics(): array
    {
        return [
            'app/uninstalled',
            'customers/data_request',
            'customers/redact',
            'shop/redact',
        ];
    }

    private function isDuplicateDelivery(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true);
    }
}
