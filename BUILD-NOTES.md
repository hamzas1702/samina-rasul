# Samina Rasul — Build Notes

Local development build per `samina-rasul-store-brief.md`. Status as of 2026-07-18.

## Local environment (no Docker/Homebrew needed)

- Stack: static PHP 8.3 binary (`.tools/php`) + wp-cli (`.tools/wp`) + WordPress on **SQLite** (official `sqlite-database-integration` plugin) in `site/`.
- Start the server: it runs via the Claude Code browser preview (`.claude/launch.json`, config name `wordpress`), or manually:
  `./.tools/php -S localhost:8787 -t site site/router.php`
- Site: http://localhost:8787 — wp-admin login: `samina_admin` / `SR-local-dev-2026!`
- wp-cli from `site/`: `../.tools/wp <command>`
- SQLite is a dev convenience; production hosting will use MySQL (migrate via WP export/import or re-run the seed scripts). WooCommerce runs fine on it locally.

## What's built

**Core plugin** (`site/wp-content/mu-plugins/samina-core/` — theme-independent business logic):
- `taxonomy.php` — `sr_collection` taxonomy (Dhanak, Ujala) alongside product categories (Formals, Bridals)
- `product-fields.php` — per-product `_sr_delivery_time` field (product data panel) + frontend display
- `fabric-addons.php` — Layer-2 additive pricing: fabric upgrades (radio) + optional extras (checkbox), `Label | fee` lines in admin; fees applied on top of variation price in cart. Stands in for the paid WooCommerce Product Add-ons extension; data model migrates 1:1
- `bridal-flow.php` — Bridals: price hidden ("Price on inquiry"), not purchasable, sizes shown as reference, "Inquire on WhatsApp" CTA (pre-filled wa.me link), Offer schema stripped, sitewide floating WhatsApp button. WhatsApp number set under WooCommerce → Settings → General (currently a placeholder)
- `order-terms.php` — 50%-advance / no-return-on-custom terms under product CTAs and at checkout

**Theme** (`site/wp-content/themes/samina-rasul/`, child of Storefront):
- Palette: white / cream silk #F8F4ED / deep burgundy #4A1F24 (confirmed by client)
- Type: Bodoni Moda (display) + Archivo (body), self-hosted in `fonts/` — **proposal, awaiting client sign-off**
- `front-page.php` homepage: hero, Formals/Bridals tiles, collections row, bridal spotlight (no prices), craft story, newsletter band
- Size-chart `<dialog>` on product pages (measurements are placeholders)
- Footer columns per brief + brand credit; custom 404

**Catalog**: 5 sample SKUs proving each pricing pattern (DK-001 fixed, DK-002 variations+fabric fee, DK-008 bridal hidden price, UJ-003 per-combo absolute prices, UJ-009 base+extra). Seed scripts: `site/seed-1-taxonomies.php`, `seed-2-products.php`, `seed-3-pages.php` (re-runnable via `wp eval-file`).
`catalog/catalog-template.csv` is the template for the client's full ~25-SKU catalog.

**Pages**: About, FAQs, Contact, Shipping/Refund/ToS/Privacy — policies contain `[AWAITING CLIENT TEXT]` markers; the client's written policies must be pasted verbatim.

**Interaction layer** (added 2026-07-19, awwwards-style pass — `assets/js/sr-ui.js` + expanded `style.css`):
- Lenis smooth scroll + GSAP/ScrollTrigger (self-hosted in `assets/js/`), synced via `gsap.ticker`
- Hero: masked line-by-line headline reveal, eyebrow letter-spacing ease-in, staggered CTAs/divider dots; rotating zardozi-medallion SVG ornament (also in Craft section)
- Once-per-session preloader (brand letters), burgundy curtain page transitions (internal links only, cart/inquire links excluded)
- Scroll-velocity-aware marquee of craft terms; scroll-batch reveals on sections/cards; gentle parallax; footer outlined mega-wordmark with scrub sweep
- Custom cursor (dot + trailing ring; expands to "View"/"Inquire" badge over product cards), magnetic hero/newsletter buttons, nav underline sweeps, card image zoom + veil + button reveal, sticky blur header that hides on scroll-down
- Sections rhythm: cream hero → marquee → tiles → cream collections → **burgundy bridal spotlight** → craft → cream newsletter (floating-label form) → burgundy footer
- All motion gated behind `prefers-reduced-motion` (falls back to static, fully visible page); cursor/magnetic only on `pointer: fine`
- Dev server now runs with `PHP_CLI_SERVER_WORKERS=6` (launch.json uses zsh -c wrapper) — the single-worker server hung when a browser connection stalled

**Homepage narrative rebuild** (2026-07-19, refs: richardgeorge.uk story arc + apinistudio.com layouts):
Section order tells a story — split hero (content panel + visual) → marquee → scroll-revealed manifesto ("A dress can be made in a day. Ours are not.") → Formals split (visual + copy, parallax) → Bridals split (burgundy, reversed, explains the no-price/consultation model) → "→ New from the atelier" product row (Apini arrow-header) → values section (cream, big drifting words Heritage/Handwork/Patience on scroll-scrub, outlined text) → 3-step process (01 conversation / 02 making / 03 arrival — doubles as the dual-flow explainer + surfaces the 50% advance rule) → newsletter → footer.
All imagery slots are `.sr-ph` CSS placeholders (gradient + ornament + caption) — swap each for `<img>` when client photography arrives. New JS primitives: `[data-sr-lines]` scroll-triggered masked-line reveals, `[data-sr-drift]` scrub-driven drifting words.

**Process timeline** (2026-07-20): "How it works" is a vertical scroll-progress timeline — center hairline with a burgundy fill scrubbed to scroll (GSAP animates the `--sr-progress` CSS var), diamond markers that light as the fill passes (and un-light scrolling back), cards alternating left/right sliding in from their side. Mobile: line and markers move to the left edge, cards stack full-width. Reduced-motion: line full, markers lit, cards visible.

## Waiting on user/client

1. Full catalog data → drop into `catalog/` (template provided)
2. Lovable export → GitHub repo link (design fidelity pass pending)
3. Policy documents (written, not yet in this folder)
4. Typography sign-off (Bodoni Moda + Archivo proposed)
5. WhatsApp business number, Instagram handle
6. Open questions from brief §12: fabric add-on list shared vs per-product, DK-0013/14 missing prices, payment gateways (JazzCash/Easypaisa/bank transfer — deposit support matters for the 50% advance model), PKR-only vs multi-currency, wishlist scope, Track Order

## Not yet built (post-catalog scope)

Payment gateway integration, shipping zones, wishlist, reviews, GA4/Meta Pixel, newsletter ESP hookup, product photography, Lookbook page, production hosting migration.
