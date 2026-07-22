# Samina Rasul — WooCommerce Store Build Brief

Master prompt for building this from scratch — hand this whole file to Claude Cowork (or a fresh Claude Code session) as the starting context.

## ⚠️ For any AI session opening this file

Before building anything, actually open these skill files rather than relying on memory of what they say — they carry the full APIs, checklists, and do/don't lists that this brief only summarizes decisions from:

- `/mnt/skills/public/frontend-design/SKILL.md`
- `/mnt/skills/public/xlsx/SKILL.md` (for building the product import spreadsheet — Section 5)
- `/mnt/skills/user/ui-ux-pro-max/SKILL.md`
- `/mnt/skills/user/minimalist-ui/SKILL.md` or `/mnt/skills/user/premium-frontend-ui/SKILL.md` (pick whichever fits the final design direction — see Section 3)
- `/mnt/skills/user/design-system/SKILL.md`
- `/mnt/skills/plugins/marketing:seo-audit/SKILL.md`

If this is a WordPress/WooCommerce install rather than a coded static build, treat the frontend-design-adjacent skills as guidance for the child theme's CSS and any custom templates, not a from-scratch build system — WooCommerce's own template hierarchy and page builder (Elementor/Woodmart/Flatsome — decide in Section 8) still do the heavy lifting.

---

## 1. Project Overview

**Client:** Samina Rasul (brand handle: SaminaRasulOfficial)
**What we're building:** A WooCommerce store that is simultaneously a **functioning ecommerce shop** (Formals — browse, add to cart, checkout) and a **digital brochure for custom bridal orders** (Bridals — no price, no cart, WhatsApp inquiry only). This dual nature is the single most important thing to get right architecturally — it's not "an ecommerce site with a contact form," it's two purchase flows living on one product catalog.

**Base to build from:** An existing Lovable project — https://lovable.dev/projects/c7fd0e90-c967-4c17-b6f6-e1c4f513e5cf — use this as the starting visual/structural reference, not a locked design. Pull the export/code from Lovable directly (GitHub push or download) rather than trying to scrape the live editor, since Lovable blocks automated crawling.

**Delivery timelines are part of the brand story, not just logistics** — every product is made-to-order (7–9 weeks depending on collection), which should be stated plainly and confidently on product pages, not buried — this is a luxury-custom brand, not fast fashion, and the copy should read that way.

---

## 2. Reference Sites — what to borrow from each

Two references were reviewed in detail; neither should be copied wholesale, but specific structural patterns from each map directly onto this build.

**Kaida Official** (kaidaofficial.com, Shopify) — borrow:
- Nested category hierarchy (parent category → child "drop"/collection categories)
- Homepage pattern of stacked, horizontally-scrollable collection rows, each with its own hero banner + "Shop now" CTA
- Product card with sale price/regular price shown inline, quick "Add" without opening the product page, "Sold out" state

**Sania Maskatiya** (pk.saniamaskatiya.com, Shopify, a *bridal* brand — closer analog) — borrow:
- **The Bridal ≠ Formals split is real and confirmed on their live site**: every non-bridal product has Add to Cart; every Bridal product has **no visible price, no cart button — only an "Inquire on WhatsApp" button** with a pre-filled message (`https://wa.me/<number>?text=Hello, I am interested in <product-url>`). This is the exact pattern to replicate for Samina Rasul's Bridals category.
- Homepage: hero banner/video per drop → tabbed "New Arrivals" grid (tabs = category shortcuts) → visual category tiles → Bridal spotlight section (no prices) → "Shop by" style tiles (Kaftans/Saris/etc. equivalent) → editorial image grid → footer
- Full sitemap/footer structure (Section 6 below is built directly from this)
- Product card variation swatches shown as size chips inline on the card itself, before clicking in

---

## 3. Brand Identity

### Colors (confirmed)
| Role | Name | Hex | Usage |
|---|---|---|---|
| Background | White | `#FFFFFF` | Primary page background |
| Secondary surface | Cream Silk | `#F8F4ED` | Section backgrounds, cards, alternating-section tint |
| Primary | Deep Burgundy | `#4A1F24` | Headers, primary buttons, key accents, nav |

This is a tight, restrained luxury palette — resist the urge to add more colors. Any additional accent (e.g. a gold/metallic for embellishment-heavy product photography context) should be proposed, not assumed — flag it as an open question if the design needs one (see Section 10).

### Typography — not yet decided (open question)
No fonts have been specified. Per `frontend-design` guidance: avoid the generic AI-default faces (Inter, Roboto, Open Sans, Arial, system-ui stacks, Space Grotesk) — for a bridal/luxury Pakistani fashion brand, lean toward a refined serif or high-contrast didone for headlines (evokes couture/editorial) paired with a clean, restrained grotesk for body/UI. Confirm with the client before locking in — this is a real gap, not a detail to silently decide.

### Tone
Luxury, custom-order, heritage craftsmanship (hand embellishment, zardozi, mukesh, resham work are constantly referenced in the product copy provided) — the site's voice should sound like it respects that craft, not like a generic fast-fashion storefront. Avoid generic ecommerce phrasing ("Shop Now Sale Ends Soon!") in favor of language that matches the existing product-description voice (e.g. "each piece is made to order," "customization to your preference").

---

## 4. Site Architecture / Sitemap

```
/ (Home)
├── /shop or /collections (all products, filterable)
│   ├── /product-category/formals
│   │   ├── collection filter: Dhanak
│   │   └── collection filter: Ujala
│   └── /product-category/bridals
│       ├── collection filter: Dhanak
│       └── collection filter: Ujala
├── /product/[slug] — two templates (see Section 7)
├── /cart
├── /checkout
├── /my-account
├── /wishlist (if included — see Section 10)
├── /search
├── /about-us
├── /contact
├── /faqs
├── /size-chart (or a modal, not necessarily a standalone page)
├── /lookbook (optional — present on Sania Maskatiya, worth including for a bridal brand)
├── /privacy-policy (content provided — Section 9)
├── /shipping-policy (content provided — Section 9)
├── /refund-policy (content provided — Section 9)
├── /terms-of-service (content provided — Section 9)
└── 404 (custom, on-brand, not the default WP error page)
```

### Header nav
`Home | Formals | Bridals | Dhanak | Ujala | Contact` — plus search, account, cart icons. Keep it to one row; Sania Maskatiya's is already fairly dense (7 items) and that's near the ceiling before it needs a "More" overflow.

### Footer (mirrors Sania Maskatiya's structure, adapted)
- **About the Brand:** About Us, Lookbook, FAQs
- **Customer Service:** Contact Us, Track Order (if a courier tracking integration exists — otherwise omit), Payments/Shipping info
- **Information:** My Account, Shipping Policy, Refund Policy, Terms of Service, Privacy Policy
- Newsletter signup
- Social icons (Instagram is near-mandatory for this category of brand — confirm handle)
- WhatsApp floating contact button, site-wide (visible on every page, not just product pages)

---

## 5. Product Catalog Architecture

This is the technical core of the build — get this right before any theming work starts.

### Taxonomies (two independent axes, confirmed by the client)
1. **Product Category** (native WooCommerce category): `Formals`, `Bridals` — this determines which purchase flow a product uses (Section 7).
2. **Collection** (custom taxonomy or Product Tag): `Dhanak`, `Ujala` — cuts across categories; a product is simultaneously in one Category and one Collection.

### Variation structure — two layers, confirmed
**Layer 1 — Piece count / item selection (native WooCommerce Variations):**
Products vary by how many pieces are included (e.g. Shirt only / Shirt + Izaar / full 3pc set) and by Size (XS, S, M, ML, L, XL, Customized). Each Item × Size combination is a distinct variation with its own base price — build this as two WooCommerce product Attributes (`Item`, `Size`) used for Variations, native functionality, no plugin required.

**Layer 2 — Fabric upgrade (Add-ons, additive fee on top of the variation price):**
Fabric options (Raw Silk 80gm, Sheesha Silk, etc.) add a fee on top of whichever variation price Layer 1 landed on — e.g. base 39,500 + Raw Silk 80gm (+16,500) = 55,500 at checkout. This needs **WooCommerce Product Add-ons** (official extension) — a native variation can't do additive pricing on top of itself.

**Open question for the client before build starts:** is the fabric add-on list a shared pool across most products, or unique per product? If shared, build one global add-on group in the plugin and attach it everywhere, rather than rebuilding it per product — confirm this before data entry begins, since it changes the whole data-entry workflow.

### Product data sample (from real catalog data provided by client)
| SKU | Collection | Category | Price structure | Delivery |
|---|---|---|---|---|
| DK-001 | Dhanak | Formals | Fixed single price (87,500) | 7–8 weeks |
| DK-002 | Dhanak | Formals | Base (39,500) + fabric addon (+16,500) | 7–8 weeks |
| DK-008 | Dhanak | Bridals | Fixed (120,000) — but no price shown on frontend per bridal flow | 7–8 weeks |
| UJ-003 | Ujala | Formals | 4 distinct fabric/embellishment combos, each its own absolute price (110,500 / 69,500 / 94,000 / 54,000) | 8–9 weeks |
| UJ-009 | Ujala | Formals | Base price + optional standalone add-on item (matching shawl) | 8–9 weeks |

DK-0013 and DK-0014 have no listed price — both are Bridals, which is fine under the inquiry-only flow, but confirm this is intentional and not a data gap.

**Delivery time varies by collection** (Dhanak ≈ 7–8 weeks, Ujala ≈ 8–9 weeks) — store this as a per-product custom field so it displays on the product page and can be updated per-product later, rather than hardcoding it into every description.

### Recommended workflow
Build the full product list in a spreadsheet first (SKU, name, category, collection, item variations + prices, size range, fabric addons + fees, base description, delivery time) before touching wp-admin — then bulk-import via WooCommerce's CSV importer or a Claude Code script. Manually re-entering ~25+ products with nested variations in the admin UI is slow and error-prone; get the data model right in a sheet first.

---

## 6. Page-by-Page Breakdown

### Home
- Hero: rotating banner per active collection/drop (Dhanak / Ujala), each "Shop Now" CTA
- Category tiles: Formals, Bridals as large visual cards
- Featured products row per collection (tabbed, like Sania Maskatiya's New Arrivals tabs)
- Bridal spotlight section — **no prices shown**, WhatsApp inquire CTAs, framed editorially ("Tradition reimagined for today...") rather than as a product grid
- Trust/craft section: a short block on the hand-embellishment/made-to-order story — this is a genuine differentiator for SEO and conversion, don't skip it
- Newsletter signup

### Shop / Category archive
- Filter by Category, Collection, Size, Price range, Fabric
- Product cards show: image, name, price (Formals) or "Inquire" (Bridals), available sizes as chips, Sale badge if applicable

### Product page — two templates (critical distinction)
**Formals template:**
- Gallery, name, price (updates live with variation + addon selection), SKU, delivery estimate, Item/Size selectors, Fabric addon selector, Size Chart link (modal), Quantity, Add to Cart
- Long description (fabric details, design & embellishment, customization note) — this content already exists verbatim in the client's product data, reuse it directly

**Bridals template:**
- Gallery, name, **no price**, delivery estimate, available sizes shown as reference only (not selectable/add-to-cart), "Inquire on WhatsApp" as the sole CTA (pre-filled message with product name/URL)
- Same long-description content block
- Optional: a short custom-order inquiry form as a secondary path alongside WhatsApp, for customers who don't use WhatsApp

### Custom size capture
Two places this can live, pick based on client preference:
1. Inline: selecting "Customized" as the Size option reveals a measurement form before Add to Cart (Formals)
2. Standalone: a general "Request Custom Fit" form, separate from the buy flow (fits the Bridals inquiry model better)

### Size Chart
A modal/lightbox (image or table) linked from every product page — no dedicated page needed unless the client wants one indexed for SEO.

### About Us / Contact / FAQs / Lookbook
Standard content pages — About Us should tell the actual brand story (heritage, craftsmanship focus); FAQs should cover delivery time, customization, payment terms (50% advance, 100% for international — see Section 9), and returns (near-zero per the policy).

---

## 7. Bridal vs Formals Purchase Flow (the core architectural decision)

| | Formals | Bridals |
|---|---|---|
| Price shown | Yes | **No** |
| Add to Cart | Yes | **No — WhatsApp Inquire only** |
| Checkout | Standard WooCommerce | N/A — handled off-platform via WhatsApp/manual order |
| Template | Standard product template | Custom template (price/cart hidden) |

This needs either two distinct WooCommerce product templates (conditional on category) or one template with conditional logic — a template override in the child theme is the cleaner approach so it's not fragile logic buried in a page builder.

---

## 8. Tech Stack Recommendation

- **Core:** WordPress + WooCommerce
- **Theme/builder:** decide between a page-builder theme (Flatsome, Woodmart) for speed of iteration, or a custom child theme for tighter design control matching the Lovable base exactly — given the Lovable design is meant to be the visual base, a custom theme/child-theme approach likely preserves fidelity better than forcing it into a pre-built theme's constraints
- **WooCommerce Product Add-ons** (official extension) — fabric upgrade fees, optional shawl-style add-ons
- **ACF (Advanced Custom Fields)** — delivery-time-per-product field, any bridal-specific custom fields
- **WhatsApp Chat / Click-to-Chat plugin** — site-wide floating button + per-product prefilled inquiry links
- **No multi-currency plugin needed** unless international sales in multiple currencies are wanted — the provided Shipping/Refund policy already implies PKR-primary with a 100% advance rule for international orders, so a simple PKR-only setup may be sufficient; confirm with client
- **Payment gateways for Pakistan:** confirm with client whether JazzCash, Easypaisa, bank transfer, and/or COD are needed alongside any card gateway — this isn't in the data provided yet and needs to be asked

---

## 9. Foundational Setup Steps (Hosting → Initial Setup Wizard)

Before any theming, catalog, or content work — the base platform sequence. Standard for any WooCommerce build, with notes specific to this one where relevant.

### 9.1 Hosting
Use hosting built for WooCommerce specifically, not generic shared hosting — variations, cart sessions, and dynamic pricing (Section 5's two-layer pricing model especially) are resource-heavier than a brochure site.
- **Managed WooCommerce hosting**: Cloudways, SiteGround GrowBig+, Kinsta, or WP Engine — best balance of performance and control for a freelance/agency build
- Minimum spec: PHP 8.1+, 1GB+ RAM (2GB+ preferred once the full catalog and Product Add-ons plugin are active), SSD storage, staging environment included, daily automated backups
- Since this store serves both Pakistan-based and (per the Shipping Policy) international customers, confirm the host's CDN/edge coverage (Cloudflare integration) is solid for both — local checkout speed matters as much as international load times

### 9.2 Domain + SSL
- Point the domain to the host's nameservers/DNS
- SSL is mandatory — WooCommerce checkout will not process payments over HTTP. Most managed hosts include free SSL (Let's Encrypt) by default; confirm it's active before going further

### 9.3 Install WordPress
One-click installer on most managed hosts. Use a non-default admin username (never "admin"), a strong generated password, and change the default `wp-` table prefix if the host doesn't already randomize it — basic hardening step, costs nothing at install time.

### 9.4 Install & activate WooCommerce
- Install the WooCommerce plugin, run its Setup Wizard: store address (for tax/shipping calculations), currency (PKR primary per Section 8), industry, and product types
- Set Permalinks to the "Post name" structure before adding any products/pages — changing this later breaks URLs and SEO after the fact

### 9.5 Core plugin stack

**Payments:**
- Confirm actual gateway availability for Pakistan before picking one — Stripe has limited direct Pakistan support, so this needs local verification rather than defaulting to it (this is one of the open items in Section 12 — resolve before this step)
- Realistic options to evaluate: **JazzCash** and **Easypaisa** WooCommerce gateway plugins (mobile wallet, dominant in Pakistan), a local bank's payment gateway plugin, or a manual **Bank Transfer** + **Cash on Delivery** setup as a fallback while a proper gateway is being approved
- Given the 50%-advance/custom-order business model (Section 10), a gateway that supports **partial/deposit payments** cleanly is worth prioritizing over one that only does full-amount checkout — otherwise the 50% advance has to be handled as a manual invoice process outside WooCommerce

**Shipping:**
- WooCommerce's native Shipping Zones (Pakistan domestic vs International, per the Shipping Policy's two-tier advance-payment rule) — set up as two zones with different rates/rules
- A **flat-rate or free-shipping** setup is usually sufficient for made-to-order fashion (not weight/dimension-based), unless the client wants live courier rate calculation — if so, a Pakistan courier plugin (e.g. TCS/Leopards/M&P integration) would be the next step, but that's likely post-launch scope

**Security:**
- **Wordfence** or **Sucuri** — firewall + malware scanning, standard on any WooCommerce store handling payment data
- Enforce SSL sitewide (not just checkout)
- Limit login attempts (built into most security plugins) + two-factor auth on admin accounts
- Automated backups at the host level (9.1) plus a plugin-level backup (UpdraftPlus) as a second layer, stored off-server

**This build's specific additions (from Sections 5 & 8):**
- **WooCommerce Product Add-ons** — fabric upgrade fees (Layer 2 pricing)
- **ACF (Advanced Custom Fields)** — per-product delivery time field
- **WhatsApp Chat / Click-to-Chat** — sitewide floating button + per-product prefilled Bridals inquiry links
- **WooCommerce CSV Import Suite** (or the built-in importer) — for bulk-loading the product catalog spreadsheet once Section 5's data model is finalized

Once 9.1–9.5 are done, the store has a working (empty) foundation — Section 5's catalog/taxonomy setup is the next real work, per Section 14's build order.

---

The client has already written full Privacy Policy, Shipping Policy, Refund Policy, and Terms of Service content. This does **not** need to be drafted — just formatted into WordPress pages using the theme's page template. Key business rules embedded in these policies that should also surface elsewhere on the site (not just buried in legal text):
- Orders require minimum 50% advance payment; international orders require 100% advance
- No refund/exchange for change-of-mind or dislike; no exchange on customized orders; refunds only apply if the product arrives destroyed/burnt
- All products are made-to-order (ties directly into the delivery-time messaging in Section 1/6)

These payment/refund terms should probably also appear as a short, friendly summary near checkout and on product pages (not just in the legal footer page) — customers should know the 50% advance rule *before* they commit to ordering a custom bridal piece, not discover it buried in a policy page after inquiring.

---

## 11. Conversion / High-Converting Store Checklist

Standard ecommerce CRO fundamentals that apply regardless of brand, worth building in from day one rather than retrofitting:

- **Trust signals near price/CTA:** delivery time, made-to-order note, secure payment badges
- **Mobile-first:** most fashion ecommerce traffic in Pakistan skews heavily mobile — every flow (variation selection, WhatsApp inquire, checkout) needs to work cleanly on a phone, not just responsively shrink
- **Fast checkout:** guest checkout enabled (don't force account creation)
- **Clear CTAs:** one obvious primary action per page — "Add to Cart" or "Inquire on WhatsApp," never both competing for attention on the same product
- **Search + filters:** by category, collection, size, price, fabric — with product volume in the dozens this matters less at launch but should be planned for as the catalog grows
- **Related/complementary products:** on product pages (e.g. show other Dhanak pieces, or "complete the look" cross-sell)
- **Wishlist:** worth including given the bridal-shopping behavior of saving favorites before committing to inquire
- **Reviews/testimonials:** if the client has any existing customer photos/testimonials, a review section on product pages builds trust for a brand this custom-order-dependent
- **Analytics from day one:** GA4 + Meta Pixel installed at launch, not added later — needed for any future paid social (this category of brand typically runs heavily on Instagram/Facebook ads)
- **Structured data / schema:** `Product` schema on Formals product pages (skip price/offer schema on Bridals since there's no listed price), `Organization` schema sitewide, `FAQPage` schema if an FAQ page is built
- **Image quality/speed:** product photography is doing most of the selling for a brand like this — ensure images are properly compressed/served in modern formats (WebP) without visibly degrading the embellishment detail that's the whole selling point
- **Breadcrumbs:** Home > Formals > Dhanak > Product Name — helps both UX and SEO given the two-taxonomy structure

---

## 12. Open Questions — resolve before or during build (don't guess silently)

1. Typography — no fonts specified (Section 3)
2. Fabric add-on list — shared pool vs per-product (Section 5)
3. DK-0013 / DK-0014 missing prices — confirm intentional (Section 5)
4. Payment gateways available in Pakistan to integrate (Section 8)
5. Multi-currency / international pricing needed, or PKR-only (Section 8)
6. Wishlist — in scope for launch or later? (Section 10)
7. Instagram/social handles to link in footer
8. Any existing product photography, or is that still being produced?
9. WhatsApp business number(s) for the Inquire CTA — one number sitewide, or different per category?
10. Whether "Track Order" needs a real courier integration or can be omitted at launch

---

## 13. Content Generation Playbook (for product/category copy at scale)

With ~25+ SKUs across two collections, copy needs to be generated consistently rather than one-off — these are the same prompts to plug into Cowork/Claude for that pass, pre-filled with this brand's actual context so the output doesn't come back generic. Don't run these blind — the client's existing product descriptions (Section 5 sample data) already have a strong, specific voice (fabric names, embellishment technique, customization note, delivery time) — these prompts should extend that voice, not replace it with generic ecommerce copy.

### A. Product description pass
```
Act as an expert e-commerce copywriter for Samina Rasul, a luxury Pakistani
custom-order fashion house specializing in hand-embellished formals and
bridal wear (zardozi, mukesh, resham, gota work on silk/organza/net).

Write a product page description for [Product Name / SKU], using this
existing raw data as the source of truth — do not invent fabric or
embellishment details not present in it:
[paste the product's fabric/design/embellishment/customization/delivery
fields from the catalog]

Structure:
1. Hook (8-12 words, benefit-driven, matches the brand's understated-luxury
   tone — no exclamation points, no "must-have" style hype language)
2. 3 short bullet points: fabric, embellishment technique, styling/occasion
3. One line reinforcing the made-to-order craft (delivery time framed as
   quality signal, not a delay — e.g. "each piece is hand-finished to
   order, ready in 7-8 weeks")
4. SEO Meta Title (<60 chars) and Meta Description (120-150 chars)
   including [Collection Name — Dhanak/Ujala] and [Category — Formals/Bridals]
```

### B. Collection/category page copy
```
Write introductory copy for the [Dhanak / Ujala] collection page on the
Samina Rasul WooCommerce store.

Include:
- A compelling H1 (collection name + one evocative descriptor, not generic
  "Shop Our Collection")
- 150 words of intro copy naming the collection's actual defining fabrics/
  techniques (pull from the real SKUs in that collection, e.g. Dhanak
  leans heavily on mukesh-embellished organza and zari net — reflect that,
  don't write generic "beautiful craftsmanship" filler)
- A short "What to Look For" buying guide (3-4 bullets) — for a
  custom-order brand this should cover fabric choice, delivery lead time,
  and sizing/customization, since those are real decision points a
  bridal-wear shopper faces
- 2-sentence wrap-up encouraging browsing
- Tone: quiet luxury, heritage-craft, understated — NOT energetic or
  hype-driven; avoid urgency language ("Sale ends soon!") which clashes
  with a made-to-order/custom brand
```

### C. UX/friction review pass (run this once checkout is built, not before)
```
Act as an expert WooCommerce UX consultant reviewing the Samina Rasul
checkout and product-page flows. This store has an unusual constraint:
Formals products use standard Add to Cart/Checkout, but Bridals products
have NO price and NO cart — only a WhatsApp inquiry button. Review both
flows separately and give a 5-point bulleted list per flow on reducing
friction, with special attention to:
- Whether the 50% advance payment / no-refund-on-customized-orders terms
  (see policy content) are surfaced clearly enough BEFORE a customer
  commits to an inquiry or order, not just buried in the legal footer
- Button placement and field count on the Formals checkout
- Whether the dual Formals/Bridals model is intuitive on first visit, or
  needs an explainer
```

Run (A) per-product once the catalog spreadsheet (Section 5) is finalized — batching all SKUs from one collection together in one Cowork session keeps voice consistent across that collection, rather than generating one product at a time across separate sessions.

---

## 14. Next Step

Once Cowork/Claude Code has this brief: complete the foundational setup (Section 9 — hosting through plugin install) first, then start with the product catalog data model (Section 5) and taxonomy setup — that's the foundation everything else (templates, filters, cart logic) depends on. Design/theming (Section 3, Section 6) can happen in parallel once the Lovable export is available, but shouldn't block catalog setup.
