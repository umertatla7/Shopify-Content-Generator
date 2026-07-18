# Shopify Webhook Setup

GrowShopHigh exposes one HMAC-verified endpoint for Shopify app and privacy webhooks:

`https://app.growshophigh.com/api/webhooks/shopify`

## Required subscriptions

- `app/uninstalled`
- `customers/data_request`
- `customers/redact`
- `shop/redact`

The endpoint validates Shopify's raw-body HMAC with `SHOPIFY_PUBLIC_APP_CLIENT_SECRET`, requires a canonical `*.myshopify.com` shop header, and uses `X-Shopify-Webhook-Id` to make retries idempotent. Only a payload hash is retained; customer webhook payloads are not stored.

## Deployment steps

1. Deploy the code and run `php artisan migrate --force` to create `shopify_webhook_deliveries`.
2. Keep `SHOPIFY_MANUAL_CONNECTION_MODE=false` for public distribution.
3. Register the four topics in the Shopify Dev Dashboard or deploy the app configuration represented by `shopify.app.toml.example`.
4. Use Shopify's webhook test action for every topic and confirm each request returns `2xx`.
5. Confirm a bad HMAC returns `401`, a repeated webhook ID returns `200`, and uninstall changes the store to `disconnected` while removing its credential.
6. Schedule `php artisan app:prune-logs --days=90` so webhook delivery evidence and other operational logs follow the documented retention period.

Do not place the Shopify client secret in the TOML file. It remains an environment variable on the application server.
