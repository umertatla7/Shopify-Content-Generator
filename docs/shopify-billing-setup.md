# Shopify Billing Setup

This is the exact setup GrowShopHigh needs before public billing is ready to go live.

## What is already built in code

- Shopify recurring subscription creation through `appSubscriptionCreate`
- Shopify confirmation redirect flow
- Trial-day support per local plan
- Subscription sync from Shopify back into local account state
- Cancellation from the portal back through Shopify
- Admin plan fields for:
  - monthly price
  - trial days
  - Shopify billing plan handle

## What still must be done in Shopify

1. In Shopify Partner Dashboard, keep the app as an embedded app using:
   - app URL: `https://app.growshophigh.com/shopify/app`
   - allowed redirection URL: `https://app.growshophigh.com/shopify/oauth/callback`

2. Make sure Shopify-managed billing is the merchant-facing billing path.
   - Merchants should approve charges inside Shopify.
   - The app should not collect card details directly.

3. Define the live plan structure you want to sell.
   - Example:
     - `growth`
     - `pro`
   - Keep the handle stable once merchants are using it.

4. In the app admin plans screen, make sure every paid plan has:
   - active status
   - monthly price
   - `shopify_billing_plan_handle`
   - trial days

5. Use test billing while validating the flow:
   - `.env`: `SHOPIFY_BILLING_TEST_MODE=true`
   - test install on development stores
   - verify:
     - install
     - onboarding
     - first sync
     - start trial
     - upgrade
     - downgrade
     - cancel
     - uninstall
     - reinstall

6. Before submission, switch production to live billing:
   - `.env`: `SHOPIFY_BILLING_TEST_MODE=false`

## Important local-to-Shopify mapping

- Local plan `shopify_billing_plan_handle` must match the plan handle you want to treat as that package.
- The app uses that handle to map a Shopify subscription back to the correct local plan.

## Public review assets you should have ready

- Privacy policy URL
- Terms of service URL
- Support URL
- Support email
- App website URL
- Reviewer walkthrough / test instructions

## Current public URLs in this app

- Privacy policy: `/privacy-policy`
- Terms of service: `/terms-of-service`
- Support: `/support`
- Billing guide: `/shopify/billing-guide`
- Review guide: `/shopify/review-guide`
