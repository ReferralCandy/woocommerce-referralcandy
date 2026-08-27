# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WordPress plugin (`woocommerce-referralcandy`) that connects a WooCommerce store to ReferralCandy via its Advanced Integration API. Published to the WordPress.org plugin directory under slug `referralcandy-for-woocommerce`. PHP runtime (no Composer) plus one React entry (`src/index.js`) built with `@wordpress/scripts` for the wc-admin settings page. No test suite, no linter configured. Requires WooCommerce 9.0.1+, WP 6.4+, PHP 7.4+.

## Commands

Local dev uses `@wordpress/env` (Docker) with pnpm:

```
pnpm i
pnpm run build          # src/index.js -> build/index.js + build/index.asset.php (required before the settings page works)
pnpm run watch          # rebuild on change
pnpm run zip            # build + dist/woocommerce-referralcandy-<ver>.zip for manual upload
pnpm run zip:staging    # build + dist/woocommerce-referralcandy-<ver>-staging.zip (staging flavor, see below)
pnpm run start          # wp-env up at http://localhost:8888 (WP 7.0, WC 10.9.1, PHP 8.2 per .wp-env.json)
pnpm run start:xdebug   # same, with Xdebug
pnpm run dev            # start + `wp rewrite flush --hard` + cloudflared HTTPS tunnel (foreground, Ctrl-C stops tunnel only)
pnpm run tunnel         # tunnel only, when wp-env already running
pnpm run stop
pnpm run destroy
```

Run WP-CLI inside the container: `wp-env run cli wp <command>`. No local PHP; lint with `wp-env run cli php -l wp-content/plugins/woocommerce-referralcandy/<file>`.

pnpm 11 via corepack (`packageManager` pinned). Postinstall scripts are disabled in `pnpm-workspace.yaml` (`allowBuilds`); none are needed for the build.

HTTPS tunnel (`scripts/tunnel.mjs`) requires `cloudflared` on PATH. Quick tunnel by default (random `*.trycloudflare.com`); named tunnel via `WP_TUNNEL_HOST=... WP_TUNNEL_NAME=... pnpm run tunnel`. Needed for anything that requires SSL: WC REST key auth, ReferralCandy webhooks, payment gateways.

Testing is manual: activate plugin, fill API keys in the ReferralCandy admin app (top-level menu, `admin.php?page=referralcandy`, Settings > API Connection), place order, move it to configured status, check order note "Order sent to ReferralCandy".

## Release

- Bump `Version:` in `woocommerce-referralcandy.php` header, add changelog entry in `readme.txt`, keep `Tested up to` in both files in sync.
- Pushing a git tag triggers `.github/workflows/deploy.yml`: pnpm install + `pnpm run build`, then 10up action deploys the working tree to WordPress.org SVN. `build/` is gitignored but ships because 10up filters by `.distignore` only. `.distignore` excludes `src/`, `dev/`, `scripts/`, `assets/`, `.wp-env.json`, etc. Anything dev-only must be listed there.

## Flavors (production / staging)

One codebase, two builds. `woocommerce-referralcandy.php` starts with the flavor defines — `WC_REFERRALCANDY_SUFFIX` (`''` / `'_staging'`), `WC_REFERRALCANDY_LABEL`, and three hosts `WC_REFERRALCANDY_API_BASE` (external API: purchase/verify), `WC_REFERRALCANDY_MAIN_API_BASE` (onboarding: wc-auth signup start, store URL check), `WC_REFERRALCANDY_APP_BASE` (merchant-facing links) — and everything flavor-specific derives from them at runtime: integration id + option key (`woocommerce_referralcandy[_staging]_settings`), REST namespace (`referralcandy[-staging]/v1`), wc-admin path, checkout field ids/names, labels, order note. Order meta (`rc_aic`, `rc_loc`, `rc_accepts_marketing`) and the `rc_referrer_id` cookie are deliberately shared so both flavors see the same attribution.

`scripts/package.mjs staging` rewrites those defines (every `*_BASE` staging value comes from `WC_REFERRALCANDY_STAGING_<X>_BASE` in a gitignored `.env` or the shell — never commit them, the repo is public; see `.env.example`), the `Plugin Name`, and suffixes every PHP class/function/constant (`WC_Referralcandy_Staging`, `RC_Order_Staging`, `wc_referralcandy_staging_*`, `WC_REFERRALCANDY_STAGING_*`) so the staging plugin (folder `woocommerce-referralcandy-staging`) can be active next to production on one store. Add new flavor-dependent values by deriving from the defines, not by adding replacements; add new global PHP symbols using the existing `WC_Referralcandy` / `RC_` / `wc_referralcandy_` / `WC_REFERRALCANDY_` prefixes so the rename map keeps catching them.

The React bundle is identical for both flavors: `RC_Admin::enqueue` runs only on the flavor's own screen and injects `window.wcReferralCandyAdmin = { rootId, id, title, version, restPath, adminUrl, links }`; the app reads everything from there.

## Architecture

Five PHP files plus one JS entry carry the whole plugin:

- `woocommerce-referralcandy.php` — bootstrap + flavor defines. On `plugins_loaded` gates on WooCommerce ≥ `WC_REFERRALCANDY_MIN_WC` (admin notice + bail otherwise), `require_once`s every `.php` in `includes/` (scandir, so dropping a new file in `includes/` auto-loads it), instantiates `WC_Referralcandy_Integration` directly on `before_woocommerce_init` (stored in `WC_Referralcandy::$integration`) and declares HPOS compatibility. It is deliberately **not** registered through `woocommerce_integrations`, so no section appears under WooCommerce > Settings > Integration; the old URL redirects to the wc-admin page.
- `includes/class-wc-referralcandy-integration.php` — `WC_Referralcandy_Integration extends WC_Integration`. `init_form_fields()` is the settings schema (served to React via REST), `validate_settings()` is the only write path, and all storefront/checkout hooks live here. Settings are read via `$this->get_option()` from option `woocommerce_referralcandy_settings` (same key as 2.x); `order_status` is stored with `wc-` prefix and stripped into `$this->status_to`.
- `includes/class-rc-admin.php` — `RC_Admin`. Own top-level menu (`add_menu_page`, slug = `WC_REFERRALCANDY_SLUG`, submenus deep-link via `#/settings`, `#/help`). On that screen only it adds a body class and inline CSS that hide the wp-admin chrome (admin bar, menu, footer, notices) and make `#wc-referralcandy-admin-root` `position:fixed; inset:0`, so the React app fills the viewport — the same pattern Milo Subscriptions uses. Enqueues `build/index.js` + `build/style-index.css` with `build/index.asset.php` deps, injects `window.wcReferralCandyAdmin`, and exposes REST `referralcandy/v1/settings` (GET/POST, `manage_woocommerce`; responses include `status` = `get_requirement_checks()`, which includes a live `api_verified` check once keys exist) plus onboarding routes: `GET /onboarding` (store URL, https, capability, `storeExists`, `signupStartedAt`), `POST /onboarding/start` (calls rc-main `wc-auth/signup/start` with `home_url()` + `returnTo`, refuses a `redirectUrl` whose host is not this store, records `wc_referralcandy_signup_started`), `POST /onboarding/verify`. Settings POST accepts partial objects: defaults ← stored ← submitted, then `validate_settings()`.
- `includes/class-rc-api.php` — `RC_Api`. All outbound HTTP. `signed_request()` = external API with `accessID` + `timestamp` + `md5(secret . concatenated sorted "key=value" pairs)` (hand-concatenated, not `http_build_query`, because the server signs the raw string); `main_api()` = JSON to the main API; `store_exists()` (`GET /signup/storeurl`, 10 min transient) and `verify()` (`verify.json`, 1 h transient, cleared on every settings save).
- `includes/class-rc-order.php` — `RC_Order`. Builds the purchase payload from a `WC_Order` and sends it through `RC_Api::signed_request('purchase.json')`. Adds order note on success.
- `src/` — React app mounted by `index.js` into the root div. `app.js` = hash router (`#/`, `#/setup`, `#/setup/keys`, `#/settings/<group>`, `#/help`) + settings load/save state + the setup gate (no API keys saved → everything routes to `#/setup` until keys are saved or the merchant skips); `shell.js` = dark 300px sidebar (Back to WP Admin, nav, settings sub-nav, footer) + white content card with header/Save; `groups.js` = which PHP field keys form which settings sub-page (unknown keys fall into "Other"); `fields.js` = shared settings row/control; `pages/` = setup (create account via wc-auth / account already exists / enter keys + Save & verify), overview (hero driven by key + verification state, status checks), settings, help. `style.scss` → `build/style-index.css`. Imports `@wordpress/*` externals plus `@wordpress/icons` (bundled, tree-shaken); default `wp-scripts` config, no `webpack.config.js`.

Dev-only: `dev/mu-plugins/00-tunnel-ssl.php` is mapped into the container by `.wp-env.json`, rewrites URLs/`is_ssl()` when served through the tunnel. Not shipped. `dev/mu-plugins/.tunnel-host` is written by `scripts/tunnel.mjs` and gitignored.

### Data flow

1. Visitor lands with `?aic=<referrer_id>` → `rc_set_referrer_cookie` (on `init`) sets `rc_referrer_id` cookie for 28 days.
2. Checkout writes order meta `rc_aic` (referrer), `rc_loc` (locale), `rc_accepts_marketing`. Two paths exist and must stay in parity:
   - Classic/shortcode checkout: `woocommerce_checkout_create_order` → `add_order_meta_classic`; checkbox injected via JS before `#place_order` (`enqueue_classic_accepts_marketing_script`) because template hooks break across checkout plugins.
   - Block checkout: field registered with `woocommerce_register_additional_checkout_field` (id `referralcandy/accepts-marketing`), meta saved in `woocommerce_store_api_checkout_update_order_meta` → `update_order_meta`.
3. Order reaches configured status → `woocommerce_order_status_{status}` → `rc_submit_purchase` → `RC_Order::submit_purchase()`.
4. Thank-you page: `render_tracking_code` injects `go.referralcandy.com/purchase/{app_id}.js` (also on optional custom `tracking_page`); `render_post_purchase_popup` optionally renders popup using `popup_campaign_key`.

Order meta is read/written through `WC_Order` methods (`get_meta`/`update_meta_data`) so HPOS and legacy post storage both work; HPOS compatibility is declared in the bootstrap. New code must use the `WC_Order` API.
