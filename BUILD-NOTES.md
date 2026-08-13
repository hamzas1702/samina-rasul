# Samina Rasul — Build Notes

Local development build per `samina-rasul-store-brief.md`. Status as of 2026-08-14.

## Audit remediation (2026-08-14)

A full senior-dev / QA / SEO pass. What changed, and what is still owed by a human.

**Pricing (the one that cost money).** `samina-core/fabric-addons.php` applied add-on
fees as `current price + fee` from `woocommerce_before_calculate_totals`. That hook
fires on every `calculate_totals()`, and WooCommerce calls it several times per
request — on add-to-cart, on a coupon, on a quantity change, and twice during
checkout — so the fee compounded and customers were overcharged, including on the
order actually written at checkout. The fee is now applied against a base re-read
from the product, so the result is identical however many times it runs.
`tools/qa/test-addon-pricing.php` proves it: run from `site/` with
`../.tools/wp eval-file ../tools/qa/test-addon-pricing.php`. It fails loudly against
the old implementation (73,000 instead of 56,500 after a single calculation).

**Repository.** The repo tracked all of WordPress core, all plugins, `wp-config.php`
(with live auth salts and the DB credential) and the SQLite database — 10,620 files
and a 446 MB history, none of which the deploy ships. Tracking is now the 58 files
this project actually authors. **A fresh clone no longer contains WordPress**: install
core and the plugins separately, then copy `wp-config.php` into `site/`. Nothing was
deleted from disk and no history was rewritten.

**Deploy.** The health check pinned `/product/dhanak-formal-dk-001-sample/`, so the day
the client deletes that sample every deploy would fail and auto-rollback would fire on
a good release — it is now optional, via the `HEALTH_CHECK_PRODUCT_PATH` repository
variable. `mu-plugins/samina-core.php` — the loader without which none of the store
logic loads at all — is now deployed rather than depending on a copy placed by hand.
A `php -l` gate runs before anything is synced. The OPcache secret moved out of the
query string (where access logs, proxies and Referer headers record it) into an
`X-SR-Deploy-Token` header on a POST; the endpoint also stopped running at file scope
on every request.

**Still owed by a human, in order:**

1. **Rotate the auth salts and the DB password in live `wp-config.php`.** They were
   committed and are in the git history; anyone with repo access can forge login
   cookies from the salts. Untracking the file does not undo that.
2. `SR_OPCACHE_SECRET` in live `wp-config.php`, matching the GitHub secret.
3. Optionally set the `HEALTH_CHECK_PRODUCT_PATH` repository variable to a real,
   permanent product path.
4. Consider `git filter-repo` to purge the old blobs if the repo is ever shared
   outside the team — rotation (1) is what actually closes the hole; this only
   shrinks the history.

**Not done, deliberately:** the CSS is still 169 KB unminified and there are still 69
`!important` declarations. Both want a build step and a visual-regression pass
respectively; neither is a change worth making blind.

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

**Catalog**: 5 sample SKUs proving each pricing pattern (DK-001 fixed, DK-002 variations+fabric fee, DK-008 bridal hidden price, UJ-003 per-combo absolute prices, UJ-009 base+extra). Seed scripts live in `tools/seed/` (outside the document root, since they are PHP importers that must never be web-reachable); run from `site/` with `../.tools/wp eval-file ../tools/seed/seed-1-taxonomies.php`.
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
Imagery slots fall back to `.sr-ph` CSS placeholders (gradient + ornament + caption) when no file is present. Superseded 2026-08-02 for hero/Formals/Bridals, which now take real images from the Customizer (see "Client-editable homepage content"); the Story portrait still resolves through `sr_image_url()` alone. New JS primitives: `[data-sr-lines]` scroll-triggered masked-line reveals, `[data-sr-drift]` scrub-driven drifting words.

**Client-editable homepage content** (2026-08-02, `inc/homepage-content.php`): 34 fields under Customizer → **Homepage Content**, in 5 sections — Hero (8), New from the atelier (2), Featured collections (15), Formals section (4), Bridals section (5). Every field defaults to the copy the theme shipped with, so an untouched install renders identically; clearing a field renders it empty (deliberate "hide this" — worth telling the client, since a blank hero line reads as a bug).
- Fields are declared once in `sr_home_fields()`; registration, sanitising and template output all read that registry, so a new field is one array entry.
- Accessors: `sr_home_text( $id )` returns the raw string (templates escape), `sr_home_image( $id, $attr )` returns escaped `<img>` markup, `sr_home_image_url( $id, $size )` returns a bare URL for meta tags.
- Images: a media-library pick renders via `wp_get_attachment_image()` (srcset/sizes — matters once the client uploads camera originals); with none set it falls back to the theme's shipped file through `sr_image_url()`. Hero is `loading="eager"` + `fetchpriority="high"` as the LCP element; `og:image` follows the Customizer hero.
- Sanitising: text → `sanitize_text_field`, body → `sanitize_textarea_field`, display headings → `sr_sanitize_inline_html` (`<em> <strong> <br> <span class>` only — `wp_kses_post` would allow links and block elements that break these layouts), images → `sr_sanitize_attachment_id` (rejects non-existent IDs, non-attachment posts, and non-image attachments).
- **Deliberately not exposed**: the Story section, manifesto, values, process steps and newsletter block; SVG motifs; CTA destinations (auto-resolve by category slug, so they survive renames); auto-numbered indexes. Collection card images are already editable at Products → Categories → term image.

**Homepage imagery ships with the theme** (2026-08-02): `hero-section.jpg`, `DSC08661/08885/08885-1.jpg` moved into `themes/samina-rasul/assets/images/`. `wp-content/uploads/` is gitignored and outside the deploy's rsync scope, so anything left there never reaches live — the theme directory is the only path that deploys. `sr_image_url()` matches theme files by **basename**, so that directory is flat and filenames must stay unique.

**mu-plugin loader is now glob-based** (2026-08-02): `mu-plugins/samina-core.php` loads every `samina-core/*.php` alphabetically instead of a hardcoded `require` list. The loader sits in the mu-plugins root, which the deploy does **not** sync (only `samina-core/` itself is rsynced), so a hardcoded list silently went stale on live whenever a module was added. Modules must therefore be order-independent: register hooks, run nothing at load time.
- Side effect: `opcache-reset.php` was in the directory but never in the require list, so the endpoint did not exist on live — the deploy's OPcache check curled the homepage, got a 200, and passed while OPcache was never flushed. It now loads for real, which means **`SR_OPCACHE_SECRET` must be defined in live's `wp-config.php`** and match the GitHub secret, or the deploy step returns 403 and auto-rollback fires.
- Hardened while enabling it: `$_GET['token']` is string-checked before `hash_equals()`, which throws a `TypeError` on an array — `?sr_opcache_reset=1&token[]=x` was a fatal 500 any visitor could trigger. Note the token still travels in the query string, so it lands in access logs; a header or POST would be better.

**Action Scheduler on SQLite** (2026-08-02, `mu-plugins/sr-sqlite-action-scheduler.php` + `sr-sqlite-as/store.php` — **local dev only, inert on MySQL, not deployed**): `ActionScheduler_DBStore::claim_actions()` issues a MySQL-only `UPDATE … JOIN ( SELECT … FOR UPDATE SKIP LOCKED )`, which the SQLite driver cannot translate (`General error: 1 near "t1": syntax error`). No batch was ever claimed, so no action ever ran and past-due actions piled up behind the "23 past-due actions" dashboard warning. The replacement store subclasses `ActionScheduler_DBStore` and claims via SELECT-then-UPDATE with identical WHERE/claim-filter/order-by semantics; row locking is dropped because SQLite serialises writers anyway. Registering a custom store also makes Action Scheduler skip the wpPost → custom-table migration, clearing the "migration in progress" notice. Drain manually with `../.tools/wp action-scheduler run`.

**Process timeline** (2026-07-20): "How it works" is a vertical scroll-progress timeline — center hairline with a burgundy fill scrubbed to scroll (GSAP animates the `--sr-progress` CSS var), diamond markers that light as the fill passes (and un-light scrolling back), cards alternating left/right sliding in from their side. Mobile: line and markers move to the left edge, cards stack full-width. Reduced-motion: line full, markers lit, cards visible.

## Waiting on user/client

1. Full catalog data → drop into `catalog/` (template provided)
2. Lovable export → GitHub repo link (design fidelity pass pending)
3. Policy documents (written, not yet in this folder)
4. Typography sign-off (Bodoni Moda + Archivo proposed)
5. WhatsApp business number, Instagram handle
6. Open questions from brief §12: fabric add-on list shared vs per-product, DK-0013/14 missing prices, payment gateways (JazzCash/Easypaisa/bank transfer — deposit support matters for the 50% advance model), PKR-only vs multi-currency, wishlist scope, Track Order
7. **`SR_OPCACHE_SECRET` in live's `wp-config.php`**, matching the GitHub secret of the same name — required before the next deploy, see "mu-plugin loader is now glob-based" above
8. Product photography uploaded to the **live** media library — attachments are database rows and the deploy syncs no database, so local uploads never reach live

## Not yet built (post-catalog scope)

Payment gateway integration, shipping zones, wishlist, reviews, GA4/Meta Pixel, newsletter ESP hookup, product photography, Lookbook page, production hosting migration.
