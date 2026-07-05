# GrowthShopHigh Methodology, Accuracy, and Legal Notes

This document is for internal use.

Its purpose is to explain:
- what each major module is doing behind the scenes
- what data it uses
- how reliable the output is
- what we should and should not claim publicly

This is the document to use when:
- writing app store copy
- answering client questions
- training support/admin team
- reviewing legal risk in feature descriptions

---

## 1. Core Positioning We Can Honestly Claim

Safe claims:
- The app uses synced Shopify store data plus AI to generate SEO and AEO content recommendations.
- The app analyzes existing store content and surfaces missing or weak content signals.
- The app helps merchants create product content, collection content, blog topics, and blog articles that are more structured for search and answer-style discovery.
- The app provides AI-assisted audits and signal-based readiness scoring.

Claims we should avoid:
- “Guaranteed rankings”
- “Guaranteed indexing in ChatGPT/Gemini”
- “Guaranteed traffic increase”
- “Guaranteed sales increase”
- “Exact AI ranking tracker”
- “Direct official visibility score from ChatGPT, Gemini, Claude, or Perplexity”

Best public wording:
- “AI-assisted”
- “signal-based”
- “content-readiness scoring”
- “heuristic analysis”
- “based on your synced Shopify data”
- “designed to improve discoverability”

---

## 2. Accuracy Framework

Not every output in the app has the same reliability. Internally we should think in these levels:

### High confidence
Data pulled directly from a source system or simple deterministic logic.

Examples:
- synced product count
- synced collection count
- whether a page has a title/meta description
- whether a product has a description
- whether a blog exists in Shopify after sync
- PageSpeed values when Google PSI is available

### Medium confidence
Structured outputs derived from real source data plus rule-based interpretation.

Examples:
- store audit recommendations
- AI visibility readiness scores
- keyword/content gap suggestions
- blog topic prioritization

### Medium-to-low confidence
AI-generated content or inferred business interpretation.

Examples:
- inferred niche
- inferred target audience
- brand voice summary
- topic opportunity framing
- AI-written blog copy

Rule:
- factual source data can be treated as reliable if the sync is current
- AI interpretation should always be treated as assistance, not ground truth

---

## 3. Topic Generation

## What it does

The topic engine creates blog topic ideas that are meant to match the merchant’s store, products, collections, and store knowledge base.

## Main inputs

From `app/Services/BlogTopicService.php`:
- store details
- synced products
- synced collections
- store knowledge base
- latest completed store analysis
- selected collection filters
- existing topics already generated

## Approach

The system:
1. collects selected collection/product context
2. includes knowledge base and store analysis context in the AI prompt
3. tells AI not to create unrelated product-family topics
4. tells AI to avoid repeating old topic angles and keywords
5. stores structured topic outputs such as:
   - title
   - primary keyword
   - secondary keywords
   - outline
   - estimated article size
   - related products
   - related collections
   - opportunity score

It also does duplicate filtering after the AI response by fingerprinting titles/keywords and removing repeats.

## How it tries to improve visibility

The topic module improves visibility by:
- tying topics to real product/collection inventory
- focusing on buyer questions and collection relevance
- using knowledge base context to avoid generic blog ideas
- creating a path from topic -> draft -> publish -> internal links

## Accuracy view

Reliability: `medium`

Why:
- relevance is usually good when store sync and collection selection are good
- opportunity and intent framing are still AI-assisted, not search-engine verified truth

## Legal-safe wording

Safe:
- “topic ideas based on synced store data and knowledge base context”
- “AI-assisted content planning”

Avoid:
- “best possible keywords”
- “guaranteed winning topics”

---

## 4. Blog Writing

## What it does

The blog workflow creates:
- metadata shell
- full article body
- FAQ blocks
- internal link suggestions
- product link suggestions

## Main inputs

From `app/Services/BlogGenerationService.php`:
- approved topic
- store knowledge base
- synced products
- synced collections
- synced Shopify pages
- store tone / topic tone
- estimated article size or selected target word count

## Approach

There are two stages:

### Stage 1: draft metadata generation
The system creates:
- title
- meta title
- meta description
- slug
- excerpt
- FAQ/link placeholders

### Stage 2: full body generation
The system writes the article body using:
- topic
- selected tone
- store context
- knowledge base summary and notes
- product and collection context
- available store pages
- target article size

The editor then lets the merchant review and publish manually.

## How it tries to improve visibility

The blog workflow is designed to improve discoverability by:
- targeting collection-aware buying questions
- producing longer-form answer content
- adding FAQ and internal/product links
- publishing directly into Shopify so the content lives on the merchant domain

## Important limitation

This is AI-generated content. Even when it is grounded in store data, it still requires merchant review.

The app can improve structure and coverage, but it cannot guarantee:
- factual perfection
- policy compliance for every niche
- ranking outcome
- AI engine citation

## Accuracy view

Reliability: `medium`

Why:
- structure and formatting are usually strong
- factual safety depends on the quality of synced store data and merchant review
- tone and helpfulness are good targets for AI, but exact business facts still need validation

## Legal-safe wording

Safe:
- “AI-assisted blog drafting”
- “grounded in synced store data and knowledge base context”
- “review before publishing”

Avoid:
- “fully accurate without review”
- “guaranteed to rank in LLMs”

---

## 5. Product Content

## What it does

The product module generates:
- improved product title
- product description HTML
- SEO title
- SEO description

## Main inputs

From `app/Services/ProductContentService.php`:
- customer-provided base title/description
- existing synced Shopify product data
- product type
- vendor
- product tags
- collection context
- store knowledge base
- store tone/context

## Approach

The prompt explicitly tells the AI:
- do not invent materials, gemstones, sizes, warranties, or shipping promises unless provided
- use merchant/customer details as source of truth
- improve clarity, SEO, and buying confidence

That makes this module safer than a generic content writer because it is instructed to stay grounded.

## How it tries to improve visibility

It improves product discoverability by:
- filling weak or thin descriptions
- improving search snippet fields
- adding clearer buyer-intent language
- making product pages more complete for search and answer-style systems

## Accuracy view

Reliability: `medium to medium-high`

Why:
- the prompt is grounded and restrictive
- output quality is strong when merchant source input is complete
- risk increases if the source product data is incomplete or vague

## Legal-safe wording

Safe:
- “AI-assisted product SEO content”
- “uses existing product and store context”

Avoid:
- “fact-checked automatically”
- “guaranteed compliant for every product niche”

---

## 6. Store Audit

## What it does

The store audit combines:
- deterministic checks
- homepage crawl signals
- optional PageSpeed/PSI values
- AI enrichment for recommendations and summaries

## Main inputs

From `app/Services/StoreAnalysisService.php`:
- synced products
- synced collections
- synced pages
- synced Shopify blogs
- portal blogs
- homepage crawl data
- optional PageSpeed insights data

## How it works

The service first creates a `base audit` using rule-based logic.

Examples of base audit checks:
- missing homepage title
- missing homepage meta description
- H1 count
- missing viewport meta
- missing canonical
- homepage image alt issues
- high script count
- high response time
- products missing descriptions
- products missing SEO fields
- collections missing descriptions
- thin blog library

Then AI is asked to enrich that audit with:
- niche
- target audience
- brand voice summary
- SEO opportunities
- content gaps
- suggested keywords
- suggested blog categories
- regional opportunities

If AI fails, the system still keeps the rule-based audit and marks the AI enrichment as failed.

## How accurate is it?

### High-confidence parts
- raw crawl findings
- content counts
- missing-field detection
- PSI/performance values when available

### Medium-confidence parts
- prioritization
- content strategy suggestions
- keyword suggestions
- business interpretation

Overall reliability: `medium-high for site/content issues`, `medium for strategic recommendations`

## Legal-safe wording

Safe:
- “AI-assisted audit based on synced store content and crawl signals”
- “performance and content-readiness checks”

Avoid:
- “complete technical SEO audit”
- “100% accurate diagnosis of all SEO issues”

---

## 7. AI Visibility / AI Audit

## What it does

The AI visibility module estimates whether a store is ready for answer-engine and LLM-style discovery.

## Important truth

This module is **not** reading direct ranking APIs from ChatGPT, Gemini, Claude, or Perplexity.

It is a **signal-based readiness model** built from the merchant’s own content and structure.

That means it is best understood as:
- a preparedness score
- a coverage score
- a visibility-likelihood helper

It is **not** a guaranteed live ranking measurement.

## Main inputs

From `app/Services/AeoGeoVisibilityService.php`:
- products and whether they have descriptions/SEO
- collections and description depth
- pages and answer-page coverage
- Shopify blogs
- portal blogs
- published portal blogs
- FAQ presence
- internal/product link presence
- knowledge base depth
- brand profile / audience profile presence
- latest analysis output

## How the scoring works

The module builds a source snapshot, then creates prompt checks and score blocks.

Examples of signals used:
- number of products with meaningful descriptions
- number of collections with usable descriptions
- whether trust / FAQ / policy / about pages exist
- whether published blogs contain enough body depth
- whether blogs include FAQs
- whether blogs include internal or product links
- whether the knowledge base has a strong summary and owner notes
- whether the brand profile / audience profile exists

### Prompt evidence

Prompt evidence rows are generated from common question types such as:
- what should I know before buying X
- how do I choose the right X
- can this blog help me decide what to buy

The system scores each prompt by checking whether current store content provides enough supporting evidence.

### Platform readiness

Platform readiness cards are derived from grouped signals.

Examples:
- ChatGPT readiness looks at answer-page coverage, brand clarity, and prompt coverage
- Gemini readiness looks at technical readiness, collection source depth, and content depth
- Perplexity readiness looks at citable content, policy/trust pages, and prompt coverage
- Claude readiness looks at brand trust signals, product detail coverage, and source depth

## How accurate is it?

For content-readiness diagnosis: `medium`

For predicting real-world AI visibility outcome: `medium-low to medium`

Why:
- it is strong at identifying missing content signals
- it is weaker as a direct predictor of how any external LLM will behave on a given day
- external AI systems change often and do not expose stable ranking data for this use case

## Best way to describe its rating

Good wording:
- “signal-based AI visibility score”
- “content-readiness score for AI discovery”
- “heuristic estimate of answer-engine readiness”

Bad wording:
- “exact ChatGPT ranking”
- “official Gemini score”
- “guaranteed citation probability”

---

## 8. What The Ratings Are Based On

## Store Audit ratings / recommendations

These are based on:
- synced content completeness
- homepage crawl findings
- script/image/meta/H1/canonical issues
- product/collection description coverage
- blog volume and publishing state
- AI strategic interpretation layered on top

## AI Visibility ratings

These are based on:
- content depth
- FAQ coverage
- internal/product link coverage
- answer-page presence
- published content count
- knowledge base maturity
- brand and audience context
- prompt-level evidence checks

## Product / Blog / Topic quality

These are based on:
- grounded store inputs
- AI generation quality
- merchant edits
- SEO scoring logic

---

## 9. Suggested Public Disclaimers

These are good patterns for app copy, support, and legal safety:

- “Outputs are AI-assisted and should be reviewed before publishing.”
- “Visibility scores are based on content-readiness signals and do not guarantee rankings or traffic.”
- “Recommendations depend on the quality and freshness of synced Shopify data.”
- “External AI and search platform behavior can change over time.”

---

## 10. Suggested Internal Improvement Feedback Areas

Good feedback to collect from merchants:
- Did the topics feel store-specific?
- Did the blog body sound accurate for the product/category?
- Did the product content avoid invented details?
- Did the store audit highlight real problems or mostly obvious ones?
- Did the AI visibility score match the merchant’s intuition?
- Which recommendations felt actionable and which felt vague?

This kind of feedback is more useful than just asking whether they “liked” the app.

---

## 11. Recommended Next Documentation Layer

After this document, the next useful docs are:
- a merchant-facing disclaimer page
- an internal support SOP for each module
- a pricing/cost model document by module and feature
- a Shopify App Store claims checklist

