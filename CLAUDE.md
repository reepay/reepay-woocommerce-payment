# CLAUDE.md — reepay-checkout-gateway (Frisbii Pay)

Guidance for Claude when working in this repository. This file lives inside the plugin's
own git repo (`origin` = `github.com/reepay/reepay-woocommerce-payment`), which is checked
out as a plugin *inside* a larger WordPress install (`/home/radaradmin/billwerk_web/wp`).
The WordPress root itself is **not** a git repo — only this plugin directory is. Do not
assume `git` commands run from the WP root will do anything useful; `cd` into this plugin
directory (or rely on the working directory already being here).

## What this project is

A WooCommerce payment gateway plugin (product name "Frisbii Pay", formerly "Reepay
Checkout") that integrates WooCommerce with the Frisbii/Reepay payment API. Supports
regular checkout, WooCommerce Blocks checkout, subscriptions (via WooCommerce
Subscriptions and its own `reepay-subscriptions-for-woocommerce` companion plugin), saved
payment methods, refunds/captures, and various local payment methods (MobilePay, Vipps,
Klarna, Swish, Anyday, ApplePay/GooglePay, etc).

Two Frisbii/Reepay API hosts are used — don't mix them up:
- `checkout-api.reepay.com` — session/charge, session/recurring, configuration list
- `api.reepay.com` — account, charge, refund, settle, agreement, direct customer/card ops

## Directory map

- `includes/` — all plugin source (PSR-4 autoloaded as `Reepay\Checkout\`). This is the
  only place production code should be edited.
- `includes/Gateways/` — `ReepayGateway` (abstract base, extends `WC_Payment_Gateway`) and
  concrete gateways like `ReepayCheckout` (the main/default gateway — also doubles as the
  plugin's central settings store), plus per-method gateways (Vipps, ApplePay, etc).
- `includes/Api.php` — the single HTTP client class for all Frisbii/Reepay API calls.
- `reepay-woocommerce-payment.php` — plugin bootstrap file; defines the global `reepay()`
  accessor (`WC_ReepayCheckout::get_instance()`).
- `tests/` — PHPUnit test suite (see Testing section below).
- `languages/` — `.po`/`.mo` translation files (Danish `da_DK`, English `en_US`). Edit the
  `.po` and recompile with `msgfmt file.po -o file.mo` — WordPress only reads the `.mo`.
- `build/` — a **generated build output copy** of the plugin (checked into git by CI /
  release tooling). **Never edit anything under `build/`** — always edit the real source
  under `includes/` etc.; changes to `build/` are silently discarded on the next build.
- `vendor/`, `node_modules/` — dependencies, don't edit.

## Architecture essentials

- **Global settings registry vs `get_option()`**: `WC_Payment_Gateway::get_option($key)`
  (via `$this->settings[$key]`) only works reliably *inside* a gateway instance that was
  just constructed with fresh settings. Code outside `ReepayCheckout` (e.g. `Api.php`, or
  any gateway other than the one that owns a setting) must instead read through
  `reepay()->get_setting('key')`, which is a hand-maintained whitelist array built in
  `WC_ReepayCheckout::get_setting()` (`reepay-woocommerce-payment.php`). **Any new
  `ReepayCheckout` setting that needs to be read from `Api.php` or `ReepayGateway.php`
  must be added to that whitelist array**, or `reepay()->get_setting('your_key')` will
  silently return `null`.
- **DI container**: `reepay()->di()->set(ClassName::class, $instance)` /
  `reepay()->di()->get(ClassName::class)`. `reepay()->api($source)` resolves an `Api`
  instance from the container and calls `set_logging_source($source)` on it — always
  fetch the API client this way in production code, never `new Api()` directly (tests are
  the one exception, see below).
- **Classic checkout and WooCommerce Blocks checkout share one code path**:
  `ReepayGateway::process_payment($order_id)` is called for *both*; Blocks is detected
  inside that single method via `$_SERVER['CONTENT_TYPE'] === 'application/json'`. A
  payload change for `session/charge` only needs to be made once in `process_payment()`
  to affect both integration paths — there is no separate Blocks-specific payload
  builder.
- **`is_gateway_settings_page()`** (`ReepayGateway.php`) checks `$_GET['tab']`/
  `$_GET['section']` and is the standard guard to stop an expensive/optional API call
  from firing on every checkout/cart/admin page load — only call the API when this
  returns `true` (see `get_account_info()`, `check_is_active()`,
  `ReepayCheckout::get_payment_window_configuration_options()` for the pattern). Pair
  this with a short-lived transient (5s TTL is the existing convention) to dedupe calls
  within one request lifecycle.
- **Logging**: every relevant class uses `Utils\LoggingTrait::log($arrayOrString)`, which
  writes to `wc_get_logger()` tagged with `$this->logging_source` (visible under
  WooCommerce → Status → Logs when the plugin's own "Debug" setting is enabled). Always
  pass an associative array with a `'source'` key describing the log point (e.g.
  `'source' => 'process_session_charge_start'`) — this is the existing convention for
  making logs greppable per order/flow, not just per class.

## Coding conventions

- WordPress Coding Standards (tabs for indentation, Yoda-ish conditionals in places,
  PHPDoc on every method) — see `ruleset.xml` for the exact phpcs config. Run
  `bin/phpcs.sh` / `bin/phpcs.tests.sh` if unsure.
- No inline comments explaining *what* the code does — only *why*, when non-obvious
  (matches this repo's existing style).
- Don't add settings-page UI without also adding the value to the global setting
  whitelist (see above) if anything outside `ReepayCheckout` needs to read it.

## Testing

PHPUnit 8 against a **real** WordPress + WooCommerce test install (`WP_UnitTestCase`), not
a fully-mocked environment. Config: `phpunit.xml` (bootstrap `tests/unit/bootstrap.php`).

Run tests:
```bash
php ./vendor/bin/phpunit                              # whole suite, current plugin combo
php ./vendor/bin/phpunit --testsuite "Gateways tested" # one suite
bin/phpunit-test-with-plugins.sh                       # full matrix: with/without WooCommerce
                                                        # Subscriptions, Reepay Subscriptions,
                                                        # and HPOS on/off (5 combinations)
```
Relevant env vars the bootstrap reacts to: `PLUGINS_STATE` via `PHPUNIT_PLUGINS`
(`woo`, `woo,woo_subs`, `woo,rp_subs`, `woo,woo_subs,rp_subs`) and `HPOS_ENABLED`
(`yes`/`no`). Some tests use `PLUGINS_STATE::maybe_skip_test_by_product_type(...)` to
self-skip when a required companion plugin isn't in the active combo — don't be alarmed
by "Skipped" results for those, only investigate unexpected failures.

Key test infrastructure (`tests/helpers/`):
- `Reepay_UnitTestCase` (+ `Reepay_UnitTestCase_Trait`) — base test case. Its `set_up_data()`
  **mocks the entire `Api` class** via
  `$this->api_mock = $this->getMockBuilder(Api::class)->getMock(); reepay()->di()->set(Api::class, $this->api_mock);`.
  Every test extending it gets `$this->api_mock`, `$this->order_generator`,
  `$this->cart_generator`, and static `self::$options` (an `OptionsController`) for free.
- `OptionsController::set_option($key, $value)` / `set_options([...])` — writes through
  the real `ReepayCheckout` singleton (`reepay()->gateways()->checkout()`) and calls
  `reepay()->reset_settings()`. **Gotcha**: if a test also holds its own separately
  `new`'d gateway instance (e.g. `self::$gateway = new ReepayCheckout()`), that instance's
  own `WC_Settings_API::$settings` cache does *not* auto-refresh — call
  `self::$gateway->init_settings();` after changing an option if that instance's
  `get_option()` needs to see the new value.
- **`$this->api_mock` mocks away the *entire* `Api` class.** This means any logic that
  lives *inside* `Api.php` itself (as opposed to in a `ReepayGateway` subclass that merely
  *calls* `Api::request()`/`Api::recurring()`) cannot be exercised through `api_mock` —
  the mock replaces the method body wholesale. To test logic inside `Api.php` directly,
  instantiate a real `new Api()` (don't extend the DI substitution for that test) and
  intercept the HTTP layer with WordPress's `pre_http_request` filter instead — see
  `tests/unit/api/ApiTest.php` for the pattern (`mock_http_request()` helper there
  captures the outgoing method/url/body and returns a canned JSON response).
- `Api::request()` requires a non-empty `private_key`/`private_key_test` setting
  (depending on `test_mode`) or it short-circuits with a `WP_Error` before ever calling
  `wp_remote_request()` — tests that exercise the real `Api` class must set a dummy key
  via `OptionsController` first.
- WP_UnitTestCase wraps each test in a DB transaction that's rolled back afterwards, and
  flushes the object cache between tests — so transients and options set mid-test don't
  leak into the next test. Superglobals like `$_GET` are **not** auto-reset — clean up
  anything you set on it (e.g. `is_gateway_settings_page()` fixtures) in `tear_down()`.

## Known pre-existing issues (found, not necessarily fixed)

- `ReepayGateway::add_payment_method()`: none of its error-handling branches `return`
  after calling `wc_add_notice()` — execution always falls through to
  `wp_redirect($result['url']); exit();` at the end of the method, even when `$result` is
  a `WP_Error` (which has no `['url']` array access → fatal `Error`). This also means the
  method cannot currently be unit-tested via a full invocation (the success path calls a
  real `exit()`, killing the PHPUnit process; the error path throws before reaching
  `exit()`). Out of scope to fix opportunistically — flag it if you touch this method.
- The working tree may show a large number of pre-existing unstaged modifications
  unrelated to whatever you're working on (commonly line-ending/whitespace drift across
  `assets/`, `.github/workflows/`, etc). Run `git status` before assuming a diff is
  something you introduced; don't `git checkout --`/`git clean` files you didn't touch
  without checking with the user first.

## Secrets — never commit

This repo's remote is a public plugin repository. **Never write real API keys, WordPress
admin credentials, or other secrets into any file in this repo** (including this one) —
not even temporarily "for reference." Frisbii/Reepay API auth is Basic
`base64(private_key + ':')`; keys live only in WooCommerce settings (DB option
`woocommerce_reepay_checkout_settings`) or are supplied ad hoc by the user for a specific
manual test.

## Manual/browser testing

A Playwright MCP server is configured one level up, in `.mcp.json` at the WordPress root
(`/home/radaradmin/billwerk_web/wp/.mcp.json`), for driving a real browser against the
local site's `wp-admin`/checkout to verify UI-facing changes (e.g. gateway settings
fields). It's configured with `--browser=chromium`; Chromium + a system `google-chrome`
binary are installed locally under `~/.cache/ms-playwright`. Ask the user for wp-admin
credentials each time rather than assuming/storing them.

The local site runs via Docker (`docker-compose.yml` at
`/home/radaradmin/billwerk_web/`, mounting `./wp` straight to `/var/www/html` — so editing
files here **is** editing the live site, no separate deploy step). **PHP OPcache is
enabled on that container and does not reliably pick up source edits** — after changing
any PHP file, hit `http://localhost/clear-opcache.php` once (e.g. via
`browser_navigate`) before doing any manual/browser verification, or you will silently
test stale bytecode (confirmed to happen — a newly-added `log()` call and a param
injection were both missing from the live request/log until the cache was cleared, even
though the file on disk was already correct). There is no WP-CLI access to this site from
the plain host shell (`mariadb` isn't resolvable outside the Docker network) — use the
browser (real requests) or `wp-admin`/REST API calls via `browser_evaluate` with a nonce
scraped from an admin page instead.

There is no WooCommerce Blocks checkout page on this site by default (only classic
`[woocommerce_checkout]` shortcode pages exist). To test a Blocks-specific path, create
one via the REST API (`POST /wp-json/wp/v2/pages` with a `<!-- wp:woocommerce/checkout -->`
block in `content`, using a nonce read from `window.wpApiSettings.nonce` on any open
block-editor admin page) rather than fighting the Gutenberg UI through Playwright.

**Known Blocks-checkout bug (found while testing BWPM-279, unrelated to it, not fixed)**:
if `ReepayGateway::process_payment()` returns `false` (e.g. a saved-card charge fails with
"Payment method not found") during a WooCommerce Blocks checkout request, WooCommerce
core's `Automattic\WooCommerce\StoreApi\Legacy::process_legacy_payment()` does
`array_merge($default, $result)` without checking `$result` is an array first, throwing an
uncaught `TypeError` ("Argument #2 must be of type array, false given") and producing a
hard "critical error" page instead of a normal checkout error notice. Reproducible by
selecting an invalid/stale saved card on a Blocks checkout page. Worth a real bug report
if a customer hits this in production — a failed saved-token charge on Blocks checkout
currently white-screens instead of showing a payment-declined message.
