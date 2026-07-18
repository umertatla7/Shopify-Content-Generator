<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function privacy(): View
    {
        return $this->renderPage(
            'Privacy Policy',
            'How GrowShopHigh handles Shopify store data, content drafts, and support records.',
            [
                [
                    'title' => 'What we collect',
                    'points' => [
                        'Shop, account, and user identity details required to connect the app to Shopify.',
                        'Catalog and content records such as products, collections, pages, blogs, and sync metadata.',
                        'Operational records such as billing state, AI usage, activity logs, and support events.',
                    ],
                ],
                [
                    'title' => 'Why we use it',
                    'points' => [
                        'To sync Shopify catalog data into the workspace so content generation uses real store context.',
                        'To create AI-assisted product copy, collection copy, topic suggestions, blogs, audits, and visibility reports.',
                        'To troubleshoot failures, monitor usage, and support merchants during onboarding and ongoing use.',
                    ],
                ],
                [
                    'title' => 'Data sharing and retention',
                    'points' => [
                        'We use service providers such as Shopify and configured AI providers only to deliver app functionality.',
                        'We do not sell merchant data.',
                        'Operational logs and webhook delivery evidence are normally pruned after 90 days. Billing or support records may be retained longer where required for legal, accounting, fraud-prevention, or dispute purposes.',
                    ],
                ],
                [
                    'title' => 'Deletion and access requests',
                    'points' => [
                        'Uninstalling immediately revokes the locally stored Shopify access credential and disconnects the store.',
                        'When Shopify sends the required shop redaction webhook, the store workspace, synced catalog, generated content, and orphaned merchant identity are deleted.',
                        'GrowShopHigh does not request Shopify customer-data scopes or persist Shopify customer records. Required customer privacy webhooks are still accepted and recorded without retaining their payload.',
                        'For data access or deletion requests, contact the support address listed on the support page from the store owner email.',
                    ],
                ],
            ]
        );
    }

    public function terms(): View
    {
        return $this->renderPage(
            'Terms of Service',
            'The service rules for GrowShopHigh, including billing, AI-assisted content, and merchant responsibilities.',
            [
                [
                    'title' => 'Service scope',
                    'points' => [
                        'GrowShopHigh helps Shopify merchants generate and manage SEO, AEO, GEO, and content workflows.',
                        'The app is delivered as software and reports. It does not guarantee rankings, indexing, or sales outcomes.',
                    ],
                ],
                [
                    'title' => 'Merchant responsibilities',
                    'points' => [
                        'Review AI-generated content before publishing it to a live storefront.',
                        'Maintain accurate store, policy, and product information inside Shopify.',
                        'Use the app in compliance with Shopify policies and applicable law.',
                    ],
                ],
                [
                    'title' => 'Billing',
                    'points' => [
                        'Paid plans and trials are initiated and approved through Shopify billing.',
                        'Charges, renewals, upgrades, downgrades, and cancellations follow the subscription approved in Shopify admin.',
                        'Usage limits and included features depend on the active plan.',
                    ],
                ],
                [
                    'title' => 'Availability and support',
                    'points' => [
                        'We aim to keep the service available and to respond to support issues within a reasonable time.',
                        'We may update features, limits, and workflows as the product evolves.',
                    ],
                ],
            ]
        );
    }

    public function support(): View
    {
        return $this->renderPage(
            'Support',
            'How merchants and reviewers can get help with install, billing, sync, publishing, and account issues.',
            [
                [
                    'title' => 'Support channels',
                    'points' => [
                        'Email support is available for install, billing, sync, content generation, and publishing issues.',
                        'When reporting an issue, include the Shopify store domain, affected module, and the time the error happened.',
                    ],
                ],
                [
                    'title' => 'What we can help with',
                    'points' => [
                        'Shopify install and embedded app access.',
                        'Billing approvals, plan changes, and trial questions.',
                        'Catalog sync, store audit, AI visibility, topic generation, and blog publishing issues.',
                    ],
                ],
                [
                    'title' => 'Recommended first checks',
                    'points' => [
                        'Confirm the store is connected and the first catalog sync has completed.',
                        'Confirm the correct Shopify store is open when testing multiple stores in the same browser.',
                        'If billing looks stale, open the Billing page and use the sync action to refresh local state from Shopify.',
                    ],
                ],
            ]
        );
    }

    public function billingGuide(): View
    {
        return $this->renderPage(
            'Shopify Billing Guide',
            'The exact setup GrowShopHigh needs in Shopify Partner Dashboard before public billing can go live.',
            [
                [
                    'title' => '1. Turn on Shopify-managed billing',
                    'points' => [
                        'Use the app setup in Shopify Partner Dashboard so paid plans are approved through Shopify rather than inside the portal.',
                        'Keep the app embedded and use the live app URL and redirect URL that match this deployment.',
                    ],
                ],
                [
                    'title' => '2. Match plan handles',
                    'points' => [
                        'Each paid plan in the admin Plans screen needs its Shopify billing handle saved locally.',
                        'The local plan handle should stay stable so the app can map Shopify subscriptions back to the correct plan.',
                    ],
                ],
                [
                    'title' => '3. Test with development stores first',
                    'points' => [
                        'Use Shopify test mode while validating trials, approvals, upgrades, downgrades, and cancellations.',
                        'After billing is behaving correctly, switch to live mode before App Store review or merchant launch.',
                    ],
                ],
                [
                    'title' => '4. Verify the merchant flow',
                    'points' => [
                        'Install the app, complete onboarding, open Billing, start a trial, approve the subscription in Shopify, and confirm the plan updates locally.',
                        'Repeat the flow for upgrade, downgrade, cancellation, uninstall, and reinstall cases.',
                    ],
                ],
            ]
        );
    }

    public function reviewGuide(): View
    {
        return $this->renderPage(
            'Shopify Review Guide',
            'A concise walkthrough for reviewers and internal QA when validating the public app experience.',
            [
                [
                    'title' => 'Primary review flow',
                    'points' => [
                        'Install the app in a development store.',
                        'Complete embedded onboarding, connect the store context, and run the first catalog sync.',
                        'Open Billing, start a trial, and approve the subscription from Shopify billing.',
                        'Generate product or collection content, create topics, create a draft blog, and publish a reviewed blog to Shopify.',
                    ],
                ],
                [
                    'title' => 'What reviewers should see',
                    'points' => [
                        'Plan-locked modules stay preview-only until the active plan unlocks them.',
                        'Billing actions always return to Shopify-managed approval, never to a custom credit-card form.',
                        'Support, privacy, and terms pages are reachable without logging in.',
                    ],
                ],
                [
                    'title' => 'What we monitor internally',
                    'points' => [
                        'Per-store activity logs for sync, publish, analyze, and billing events.',
                        'Estimated AI cost, credit usage, and recent operational failures on the admin side.',
                    ],
                ],
            ]
        );
    }

    private function renderPage(string $title, string $intro, array $sections): View
    {
        return view('public.page', [
            'title' => $title,
            'intro' => $intro,
            'sections' => $sections,
            'companyName' => config('services.app_review.company_name', config('app.name')),
            'supportEmail' => config('services.app_review.support_email'),
            'legalEmail' => config('services.app_review.legal_email'),
            'websiteUrl' => config('services.app_review.website_url', config('app.url')),
        ]);
    }
}
