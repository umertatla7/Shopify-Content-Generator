# SEO & AEO Content Generator for Shopify

Laravel 12 + Inertia/Vue SaaS foundation for connecting Shopify stores, syncing store data, generating SEO blog topics and drafts with AI, reviewing/editing content, scheduling, and one-click Shopify publishing.

## Stack

- PHP 8.3+ / Laravel 12
- Laravel Sanctum, Policies/Gates, Queues, Scheduler, Horizon
- Inertia.js + Vue 3 + Tailwind CSS
- MySQL 8+ intended for production, SQLite works locally
- Redis intended for queues/cache in production

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Queue and schedule workers:

```bash
php artisan queue:work --queue=shopify,ai,default
php artisan schedule:work
php artisan horizon
```

## Configuration

The app runs with a local stub AI provider by default. Set these when using a real provider:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
```

Shopify credentials are entered per store in the UI and encrypted in `shopify_credentials`. The preferred connection flow is store URL + Shopify Client ID + Client Secret; the app exchanges them for a short-lived Admin API token and refreshes it before Shopify API calls. Manually pasted Admin API tokens are supported as a fallback. Global Shopify defaults:

```dotenv
SHOPIFY_API_VERSION=2026-04
SHOPIFY_DEFAULT_BLOG_ID=
```

## Phase 1 Modules

- Account/customer ownership and role-based permissions
- Shopify store connection with encrypted Admin GraphQL API credentials
- Shopify GraphQL sync jobs for products, collections, and existing blogs
- AI store analysis, topic generation, full blog generation, rewrite, and SEO scoring services
- Blog review, comments, revisions, approval, scheduling, and publishing logs
- Dashboard analytics for stores, products, topics, blogs, AI generations, and Shopify publishes
- REST-style Sanctum-protected API routes under `/api`
- Future module placeholders for product optimization, image generation, background removal, competitor analysis, and content calendar automation

## Verification

```bash
php artisan test
npm run build
php artisan route:list
```

## Shared Hosting Notes

For Hostinger-style deployments where the subdomain document root points at the project folder, keep the root `.htaccess` file in place so requests are routed to Laravel's `public/` directory.

Production `.env` values should be created on the server, not committed to git. At minimum set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shopifyautomation.hafizejaz.com
DB_CONNECTION=sqlite
DB_DATABASE=/home/u160003797/domains/hafizejaz.com/public_html/shopifyautomation/database/database.sqlite
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```
