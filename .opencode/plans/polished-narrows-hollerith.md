# MGS — Pre-dashboard redesign + Pashto/Dari text fixes

## Context

- MGS is a Laravel 13 app packaged as an Android app via NativePHP (`nativephp/android`, WebView). All UI is Blade + Tailwind v4.
- The app has a coherent, already-refined design system: emerald brand (`--color-brand #10ae64`), warm `ink` neutrals, Plus Jakarta Sans + Vazirmatn (full Arabic-script glyph coverage for Pashto/Dari), dark mode via `.dark`, RTL via `dir` attr + `app-rtl.css` + `postcss-rtlcss` + manual `[dir="rtl"]` overrides.
- **User goals:** (1) redesign weak pages — login, register, OpenWA gateway + full pre-dashboard flow; (2) fix text that doesn't show in Pashto/Dari.
- **User decisions:** Full pre-dashboard flow scope · Refine existing identity (no new palette/fonts) · Verify with `npm run build` + review.

## Root cause: "text not showing" in Pashto/Dari

**~28 translation keys are referenced in views but missing from ALL three lang files** (`resources/lang/{en,fa,ps}/messages.php`). Laravel `__()` returns the raw key (e.g. `messages.login_hint`) — so the text renders as a key in *every* language. Verified missing via grep:

```
login_hint, register_hint, show_password, password_help, phone_help, or,
forgot_password, forgot_password_title, forgot_password_hint, remember_password,
reset_password, reset_password_title, reset_password_hint, new_password, resend_otp,
invalid_phone, network_error,
openwa_restart, openwa_restarting, openwa_restarted, openwa_restart_failed,
cashbook_net, select_person, select_product, summary, returned, confirm, remove
```

Secondary causes:
- `resources/views/onboarding/index.blade.php` — no `dir="rtl"` (line 2), no `app-rtl.css` bundle, no language switcher → stuck LTR English-feel for ps/fa users.
- `resources/views/settings/openwa.blade.php` — hardcoded English: `'Unreachable'`, `ucfirst($rawState)`, and pervasive `??` English fallbacks in Blade + inline JS.
- `resources/views/whatsapp/index.blade.php:28` — dynamic key `__("messages.wa_{$chat['last_status']}")` prints raw key for any status outside {sent, failed, pending}.

## Work plan

### Step 1 — Localization fixes (do first; design uses these strings)

1. Re-run a referenced-vs-defined key audit over `resources/views/**` + `resources/js/**` to get the definitive missing-key list (expected: the 28 above; possibly a few more like `owner_capital`, `have_account` — verify each with grep, not regex alone).
2. Add every missing key to all three files with proper translations:
   - `en`: plain English.
   - `ps`: Pashto (drafted by agent; user reviews — flag for native-speaker check).
   - `fa`: Dari (same).
   - Match each file's existing tone/style (e.g. existing ps entries use «کړئ» imperative style, formal register).
3. `settings/openwa.blade.php`: replace hardcoded `'Unreachable'`/`ucfirst(status)` with translated status labels (new keys: `openwa_status_connected`, `openwa_status_qr_ready`, `openwa_status_unreachable`, etc.) in both the PHP badge block and the `setBadge()` JS; remove the `??` English fallbacks once keys exist (server-rendered strings only — keep JS structure intact).
4. `whatsapp/index.blade.php`: guard the dynamic key (`@php` map with fallback to `wa_pending`).
5. `onboarding/index.blade.php`: add `dir` per locale, load `app-rtl.css` for ps/fa, add the language switcher (same pattern as `layouts/auth.blade.php`), add `dark:` support (page currently hardcodes light classes only).

### Step 2 — Redesign auth shell + auth pages

Skill applied: `redesign-existing-projects` (scan → diagnose → fix; work with existing stack; never break form contracts).

Targets (keep all routes, input names, validation, `data-password-toggle` contract, `@error` blocks):

- `resources/views/layouts/auth.blade.php` — the shared shell:
  - Use real brand mark (`<x-icon name="app-icon"/>`) instead of letter "M" square.
  - Add restrained ambient background depth (app.css already ships `grain-overlay` / `hero-aurora` utilities) — subtle, no AI-gradient clichés.
  - Stronger hierarchy: display-size title with tight tracking inside the card; calmer page-level brand block (brand + one-line tagline, no duplicated footer tagline).
  - Vary corner-radius hierarchy (large container radius, tighter inner elements).
  - Language switcher: keep position/behavior, refine as a segmented pill (ps / fa / en) instead of a bare `<select>` if it fits, else keep styled select.
- `resources/views/auth/login.blade.php` — bigger heading presence, icon-labeled inputs with `start`-anchored icons inside fields (RTL-safe logical utilities), prominent primary CTA, calm secondary (phone login), clear register cross-link.
- `resources/views/auth/register.blade.php` — same language; group fields with breathing room; password confirm parity.
- `resources/views/auth/otp-login.blade.php` — phone step → OTP step: segmented 6-digit OTP cells (like the lock screen PIN inputs) instead of one tracking-widest input; keep JS functions `sendOtp/verifyOtp/resetForm` and element IDs.
- `resources/views/auth/forgot.blade.php`, `reset.blade.php` — same visual language (these are the pages whose titles/hints were raw keys — now real text in all 3 languages).

RTL/dark: use only logical Tailwind utilities (`ps-*`, `pe-*`, `start-*`, `end-*`, `text-start`) and existing `[dir="rtl"]` overrides; test dark styling stays intact.

### Step 3 — Redesign OpenWA gateway page (`settings/openwa.blade.php`)

Keep EVERY JS contract (element IDs: `qrImage`, `qrPlaceholder`, `sessionBadge`, `disconnectNote`, `refreshBtn`, `restartBtn`, `testSendBtn`, `testSendResult`, `pairingPhone`, `pairingBtn`, `pairingResult`, `pairingCode`, `pairingExpiry`, `openwaDiagnostics`, `openwaLastError`; routes: `settings.openwa.qr/.pairing/.pairing-status/.restart/.test`; `showToast` usage; polling logic).

Restructure layout only:
- Header row: page title + back link (chevron flips via existing RTL rule); status as a hero strip — session badge + phone number + gateway badge.
- Action row: Test send / Refresh / Restart with consistent `btn-*` sizes, mobile-friendly wrap.
- Connection area: QR card and pairing-code card as two stacked full-width cards on mobile (current `md:grid-cols-2` never triggers on phones) — QR centered with scan hint; pairing card with number input + big code display + honest countdown (all already functional).
- Diagnostics block: collapsible/indented, mono, keep contents.
- Localize all JS string literals (status labels, toasts) using the new keys from Step 1.

### Step 4 — Remaining pre-dashboard pages

- `resources/views/welcome.blade.php` — polish splash: brand mark icon, single column, login/register CTAs, version footer. Keep `@auth` branch.
- `resources/views/onboarding/index.blade.php` — apply Step 1.5 fixes + align step design with auth language (icon tiles, step-dot progress already present), fix the `usd_with_paren_2`/`USD)` option rendering, add `dark:` classes (page currently light-only).
- `resources/views/app-lock/lock.blade.php` — polish PIN UI: keep 4-box inputs + JS, add error shake, pressed feedback, localized labels (already keyed); verify RTL centering.

### Step 5 — CSS support (`resources/css/app.css`)

Only additive utilities if needed (e.g. `.auth-backdrop` ambient layer, `.otp-cell` shared between otp-login and lock screen). No palette/font changes.

## Files

```
resources/lang/en/messages.php            +~28 keys
resources/lang/fa/messages.php            +~28 keys (Dari)
resources/lang/ps/messages.php            +~28 keys (Pashto)
resources/views/layouts/auth.blade.php    redesign shell
resources/views/auth/login.blade.php      redesign
resources/views/auth/register.blade.php   redesign
resources/views/auth/otp-login.blade.php  redesign (segmented OTP)
resources/views/auth/forgot.blade.php     redesign
resources/views/auth/reset.blade.php      redesign
resources/views/settings/openwa.blade.php restructure + localize
resources/views/onboarding/index.blade.php RTL + lang + dark + polish
resources/views/welcome.blade.php         polish
resources/views/app-lock/lock.blade.php   polish
resources/views/whatsapp/index.blade.php  wa_ key guard
resources/css/app.css                     additive utilities only
```

## Verification

1. `npm run build` — must compile (Vite + Tailwind v4); catch CSS/JS errors.
2. Re-run the translation audit — zero referenced-but-undefined keys.
3. Grep views for `__('messages.` keys not defined; grep for hardcoded English strings in redesigned files.
4. Manual review pass of every redesigned template: dark-mode classes, logical RTL utilities, preserved form/JS contracts.
5. (User, optional later) visual check on device/emulator; APK rebuild via `build_now.bat` if desired — NOT part of this task.

## Constraints / risks

- **No PHP CLI on this machine** — cannot run `php artisan` / tests. Blade correctness verified via build + careful review.
- **Pashto/Dari translations drafted by agent** — user should native-review the ~28 new strings (esp. ps).
- OpenWA page JS is intricate (polling, guards, auto-pairing) — edit Blade markup + localized literals only; never alter fetch logic or IDs.
- `app-rtl.css` currently only `@import`s app.css; RTL correctness relies on logical utilities + `[dir="rtl"]` blocks — during implementation, verify built output actually differs (postcss-rtlcss); if not, rely on the existing manual RTL overrides (already comprehensive).
