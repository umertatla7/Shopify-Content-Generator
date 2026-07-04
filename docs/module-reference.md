# GrowthShopHigh Module Reference

This document is for internal use. It explains what each major module does, what data it depends on, and which parts of the codebase are responsible for it.

## 1. Store Audit

Purpose:
- Give a merchant a quick SEO/content snapshot of their store.

Main data sources:
- Synced Shopify products
- Synced Shopify collections
- Synced Shopify pages
- Existing Shopify blogs
- Portal-generated blogs
- Store knowledge base
- Optional Google PageSpeed / PSI signal if configured

Main code:
- Controller: `app/Http/Controllers/StoreAnalysisController.php`
- Service: `app/Services/StoreAnalysisService.php`
- Store screen: `resources/js/Pages/Stores/Index.vue`

What happens:
- The app builds a prompt using the synced Shopify data.
- AI returns a store-level report with opportunities, gaps, and recommendations.
- The result is stored in `store_analyses`.

## 2. AI Visibility

Purpose:
- Estimate whether the store content is ready for AI-first discovery across tools like ChatGPT, Gemini, Perplexity, and Claude.

Main data sources:
- Store knowledge base
- Store analysis output
- Shopify pages
- Portal-generated blogs
- Existing Shopify blogs
- Products and collections

Main code:
- Controller: `app/Http/Controllers/AeoGeoVisibilityController.php`
- Service: `app/Services/AeoGeoVisibilityService.php`
- Page: `resources/js/Pages/Visibility/Index.vue`

What happens:
- The service creates a store snapshot from current synced content.
- It scores:
  - prompt coverage
  - answer-page coverage
  - brand clarity
  - source depth
  - policy/trust presence
  - content depth
- Platform readiness cards are derived from those signals.
- Prompt evidence rows are generated from store content and matched against platform readiness rules.

Important note:
- This is currently heuristic plus AI-assisted scoring, not real ranking data from ChatGPT/Gemini APIs.

## 3. Topics

Purpose:
- Generate blog topic ideas from synced store data and knowledge base context.

Main data sources:
- Store knowledge base
- Products
- Collections
- Existing topics
- Store analysis

Main code:
- Controller: `app/Http/Controllers/BlogTopicController.php`
- Service: `app/Services/BlogTopicService.php`
- Page: `resources/js/Pages/Topics/Index.vue`

What happens:
- Merchant selects scope or collection context.
- AI returns topic ideas with:
  - title
  - keyword
  - outline
  - opportunity score
  - estimated article size
- Topics are stored in `blog_topics`.

## 4. Blogs

Purpose:
- Turn approved topics into publish-ready blog content.

Main data sources:
- Blog topic
- Store knowledge base
- Synced products
- Synced collections
- Shopify pages

Main code:
- Controller: `app/Http/Controllers/BlogController.php`
- Body generation controller: `app/Http/Controllers/BlogBodyGenerationController.php`
- Publish controller: `app/Http/Controllers/BlogPublishController.php`
- Generation service: `app/Services/BlogGenerationService.php`
- Publish service: `app/Services/BlogPublishingService.php`
- Shopify service: `app/Services/Shopify/ShopifyService.php`
- Edit page: `resources/js/Pages/Blogs/Edit.vue`
- Index page: `resources/js/Pages/Blogs/Index.vue`

What happens:
- Topic approval creates or unlocks a draft flow.
- AI can generate:
  - metadata shell
  - full body
  - FAQ/internal links/product links
- Merchant edits in the blog editor.
- Publish sends the saved HTML article to Shopify.
- Revisions are stored in `blog_revisions`.
- Publish activity is stored in `publishing_logs`.

Recent behavior:
- Single blog publish now runs immediately instead of queue-only behavior.
- Blog page now has a Shopify sync action to reconcile portal blog status with Shopify article reality.
- Merchants can choose a target body word count up to 1500.

## 5. Products

Purpose:
- Generate product title/description SEO content and push it back to Shopify.

Main data sources:
- Synced Shopify products
- AI generation

Main code:
- Controller: `app/Http/Controllers/ProductController.php`
- Content controller: `app/Http/Controllers/ProductContentController.php`
- Service: `app/Services/ProductContentService.php`
- Page: `resources/js/Pages/Products/Index.vue`

What happens:
- Merchant selects a synced product.
- AI generates improved title/description/SEO fields.
- Merchant can save locally or push to Shopify.

## 6. Collections

Purpose:
- Generate collection descriptions and SEO content.

Main data sources:
- Synced Shopify collections
- Related synced products

Main code:
- Controller: `app/Http/Controllers/CollectionController.php`
- Content controller: `app/Http/Controllers/CollectionContentController.php`
- Service: `app/Services/CollectionContentService.php`
- Page: `resources/js/Pages/Collections/Index.vue`

What happens:
- AI generates collection copy and SEO fields.
- Merchant can push updates back to Shopify.

## 7. Keyword Tracking / Rank Tracking

Purpose:
- Track keyword performance using Google Search Console data.

Main data sources:
- Google Search Console OAuth
- Search Console properties
- Search Console query/page performance

Main code:
- Controller: `app/Http/Controllers/SearchConsoleController.php`
- Service: `app/Services/Google/SearchConsoleService.php`
- Page: `resources/js/Pages/RankTracking/Index.vue` or related Search Console screens

What happens:
- Merchant connects GSC.
- The app syncs properties and performance rows.
- Tracked keywords are stored locally for reporting over time.

Important note:
- This is currently Search Console based, not DataForSEO position tracking.

## 8. Store Sync / Shopify Connection

Purpose:
- Keep local portal data aligned with Shopify.

Main data sources:
- Shopify Admin GraphQL API

Main code:
- Controller: `app/Http/Controllers/ShopifyStoreController.php`
- Sync service: `app/Services/Shopify/ShopifySyncService.php`
- API wrapper: `app/Services/Shopify/ShopifyService.php`

What sync currently covers:
- products
- collections
- pages
- existing Shopify blogs/articles
- portal blog reconciliation against Shopify articles

Important tables:
- `shopify_stores`
- `shopify_credentials`
- `shopify_sync_logs`
- `products`
- `collections`
- `shopify_pages`
- `existing_shopify_blogs`

## 9. Billing / Plans

Purpose:
- Control feature access and credit limits by plan.

Main code:
- Controller: `app/Http/Controllers/BillingController.php`
- Feature gate: `app/Support/PlanFeatureGate.php`
- Admin plans: `app/Http/Controllers/Admin/AdminPlanController.php`

What happens:
- Plans define feature availability and allowances.
- Portal menus and actions are gated by plan features and account permissions.

## 10. Admin / Support Backoffice

Purpose:
- Give internal team visibility into customers, stores, usage, logs, and failures.

Main code:
- Dashboard: `app/Http/Controllers/Admin/AdminDashboardController.php`
- Customers: `app/Http/Controllers/Admin/AdminAccountController.php`
- Team users: `app/Http/Controllers/Admin/AdminUserController.php`
- Store support detail: `app/Http/Controllers/Admin/AdminStoreController.php`
- Activity: `app/Http/Controllers/Admin/AdminActivityController.php`
- Log pruning: `app/Console/Commands/PruneOperationalLogsCommand.php`

What it currently supports:
- customer directory
- internal team directory
- customer detail tabs
- per-store support page
- activity filtering
- AI cost summary
- recent failures

## 11. Core Supporting Tables

Useful tables to understand:
- `accounts`
- `account_users`
- `plans`
- `subscriptions`
- `shopify_stores`
- `shopify_credentials`
- `store_analyses`
- `store_knowledge_bases`
- `blog_topics`
- `blogs`
- `blog_revisions`
- `publishing_logs`
- `usage_logs`
- `ai_generations`
- `activity_logs`

## 12. Current Gaps Before Public Launch

Still important:
- deeper internal RBAC for platform team sections
- stronger Shopify embedded onboarding flow polish
- clearer sync progress UX in some merchant modules
- more defensive support logs around failed actions
- final pricing validation based on real usage and AI cost
