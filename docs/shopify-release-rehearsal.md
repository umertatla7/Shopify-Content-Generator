# Shopify Release Rehearsal

Run this rehearsal on fresh development stores after deployment to staging and before App Store submission.

## Installation and identity

- Fresh install completes without a GrowShopHigh login screen.
- App opens embedded in Shopify with third-party cookies blocked.
- Invalid, expired, wrong-app, and wrong-store session tokens return `401`.
- Two stores open in the same browser always show their own account and catalog.
- An existing portal email cannot attach a new Shopify store without an explicit authenticated link.
- Uninstall removes credentials; reinstall creates a valid new installation.

## Billing

- Starter and Growth test subscriptions show the exact approved price and trial.
- Approval, decline, replay, upgrade, downgrade, cancellation, and reinstall are tested.
- Editing a callback query string cannot change entitlements.
- Credits and plan limits change once, and only after Shopify confirms the active subscription.
- Production billing remains disabled until public distribution and final owner approval.

## Core workflows

- Initial product, collection, page, and blog sync completes and progress is visible.
- Product and collection generation respects plan limits and credits.
- Topic, blog draft, body generation, approval, immediate publish, and scheduled publish complete.
- A Shopify API error is visible to the merchant and administrator.
- A provider timeout does not duplicate content or charge twice.
- Disabled plan features remain preview-only through both web and API routes.

## Compliance and support

- Valid compliance and uninstall webhooks return `2xx`; invalid HMAC returns `401`.
- Replayed webhook IDs are idempotent.
- Privacy pages describe actual retention and deletion behavior.
- Password reset and all support notifications reach real inboxes.

## Operations

- PHPUnit, frontend build, and dependency audits pass.
- Queue report is reviewed and failed jobs are zero.
- Worker and scheduler heartbeats are healthy.
- A simulated failed job sends an alert.
- A current encrypted database backup restores successfully to a separate database.
- Deployment rollback is rehearsed on staging.
- Shopify automated checks pass before submission.
