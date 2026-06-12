# Ranamatch / Velplay Backend and Admin Documentation

This document is for the next developer or operator working in:

- `D:\xampp\htdocs`

It covers:

- backend structure
- local environment setup
- routing and API behavior
- database setup
- admin panel usage
- non-developer operational tasks
- common troubleshooting
- OTP / SMS behavior
- branding, promotions, bonuses, games, sliders, tickets, deposits, and withdrawals

## 1. High-Level Overview

This codebase is a PHP + MySQL backend with:

- a public API router under `router/`
- request handlers under `route-paths/`
- admin panel under `admin/`
- security and configuration under `security/`
- media assets under `uploads/`
- game/player launch logic under `game/`
- payment-related integration under `payments/`

The frontend currently consumes this backend through router endpoints such as:

- `/router/?Route=route-login`
- `/router/?Route=route-send-sms`
- `/router/?Route=route-create-account`
- `/router/?Route=route-account-info`

## 2. Main Folder Guide

### Root

- `index.php`
  - default XAMPP root redirect
- `proxy.php`
  - proxy endpoint
- `broadcast_diag.php`
  - broadcast diagnostics
- `clear_cache.php`
  - cache-clearing helper

### `security/`

Core configuration and bootstrap files.

- `config.php`
  - database connection
  - timezone setup
  - required for almost everything
- `constants.php`
  - app constants
  - domain constants
  - payout config
  - branding fallback values
- `constant2.php`
  - alternate constants file still present
  - should be kept in sync if legacy code references it
- `headers-security.php`
  - CORS, request header validation, IP/browser handling
- `setup-database.php`
  - initial table creation and service seed data
- `license.php`
  - license validation wiring

### `router/`

API entry point.

- `index.php`
  - receives incoming API requests
  - loads config/constants
  - resolves route using `route-paths.php`
- `route-paths.php`
  - route map for all public API endpoints

Useful log files live here too:

- `play_debug.txt`
- `router_final.log`
- `debug_log.txt`

### `route-paths/`

Actual business logic handlers for API requests.

Examples:

- `request-login.php`
- `request-create-account.php`
- `request-reset-password.php`
- `request-account-info.php`
- `request-recharge.php`
- `request-withdrawl.php`
- `request-submit-ticket.php`
- `request-play-games.php`
- `request-active-promotions.php`
- `request-claim-bonus.php`
- `request-active-bonus-details.php`
- `load-big-wins.php`
- `load-trending-matches.php`

SMS route lives here:

- `route-paths/services/sms/send-sms.php`

### `admin/`

Admin panel and operations UI.

Important areas:

- `dashboard/`
- `users-data/`
- `recharge-records/`
- `withdraw-records/`
- `support-tickets/`
- `manage-settings/`
- `manage-games/`
- `manage-promotions/`
- `manage-bonus/`
- `manage-sliders/`
- `manage-admins/`
- `send-message/`
- `reports/`

### `uploads/`

Uploaded content used by admin-managed features.

Examples:

- promotion images
- bonus media
- general uploaded assets

## 3. Local Environment Setup

### Required stack

- XAMPP / Apache
- MySQL / MariaDB
- PHP from XAMPP

### Important local paths

- project root: `D:\xampp\htdocs`
- backend router entry: `D:\xampp\htdocs\router\index.php`
- database config: `D:\xampp\htdocs\security\config.php`

### Database config

Current config from `security/config.php`:

- host: `localhost`
- database: `ranamatch`
- user: `root`
- password: empty by default

### Initial database setup

Open this once in browser or run manually:

- `D:\xampp\htdocs\security\setup-database.php`

It creates core tables including:

- `tblusersdata`
- `tblrecentotp`
- `tblusersrecharge`
- `tbluserswithdraw`
- `tblusersactivity`
- `tblservices`
- `tbladmins`
- `tblsliders`

It also seeds default admin and service rows.

## 4. API Routing Model

### Entry pattern

Requests are routed like:

```text
http://localhost/router/?Route=route-login
http://localhost/router/?Route=route-send-sms
```

The `Route` value is matched in:

- `D:\xampp\htdocs\router\route-paths.php`

Example mapping:

- `/route-send-sms` => `route-paths/services/sms/send-sms.php`
- `/route-login` => `route-paths/request-login.php`

### Request headers commonly used

- `Content-Type: application/json`
- `Route: route-name`
- `AuthToken: random-or-auth-secret`

Some guest routes do not strictly require a real user auth secret, but user routes usually do.

## 5. Authentication Model

### User auth

User auth uses:

- `tblusersdata.tbl_auth_secret`

On login success:

- a new auth secret is generated
- stored in `tblusersdata`
- returned to frontend

### Admin auth

Admin login lives in:

- `D:\xampp\htdocs\admin\index.php`

Admin sessions use:

- `$_SESSION["admin_user_id"]`
- `$_SESSION["admin_secret_key"]`
- `$_SESSION["admin_access_list"]`

### Admin permissions

Permission validation lives in:

- `D:\xampp\htdocs\admin\access_validate.php`

Access list is stored as comma-separated text in:

- `tbladmins.tbl_user_access_list`

Known permission keys include:

- `access_match`
- `access_users_data`
- `access_recent_played`
- `access_recharge`
- `access_withdraw`
- `access_template`
- `access_help`
- `access_message`
- `access_gift`
- `access_settings`
- `access_pandl`
- `access_admins`

## 6. Core Service Settings in `tblservices`

This table drives a lot of app behavior.

Common keys:

- `APP_STATUS`
- `GAME_STATUS`
- `COMISSION_BONUS`
- `WITHDRAW_TAX`
- `MIN_WITHDRAW`
- `MIN_RECHARGE`
- `RECHARGE_OPTIONS`
- `DEPOSIT_BONUS`
- `DEPOSIT_BONUS_OPTIONS`
- `TELEGRAM_URL`
- `SMS_TOKEN`
- `OTP_ALLOWED`
- `SIGNUP_ALLOWED`
- `SIGNUP_BONUS`
- `IMP_MESSAGE`
- `IMP_ALERT`

Branding-related keys may also exist:

- `SITE_NAME`
- `SITE_LOGO_URL`
- `SITE_TAGLINE`
- `SITE_MARQUEE`
- `SITE_BRAND_COLOR`
- `SITE_BRAND_GRADIENT_END`
- `SITE_BG_COLOR`
- `SITE_TEXT_COLOR`
- `CONTACT_WHATSAPP`
- `CONTACT_SUPPORT_URL`

## 7. Admin Panel: Non-Developer Operations

This section is written for a site operator or business admin, not a programmer.

### 7.1 Login as Admin

Open:

- `http://localhost/admin/`

Use credentials from `tbladmins`.

Default seeded admin from setup is created in `setup-database.php`.
This should be changed immediately on real environments.

### 7.2 Dashboard

Main overview area:

- `admin/dashboard/`

Use this for quick summary checks, not detailed operations.

### 7.3 Change Site Branding

Use:

- `admin/manage-settings/site-branding.php`

Purpose:

- update site name
- update logo
- update support links
- update marquee text
- update theme colors
- update social links

Important behavior:

- branding values are loaded from `tblservices`
- `APP_NAME` and `APP_LOGO` fall back from `security/constants.php` if DB values are missing

Recommended branding tasks:

1. Set site name
2. Set logo URL
3. Set brand colors
4. Set support links
5. Set marquee or notice text

### 7.4 Update System Settings

Use:

- `admin/manage-settings/index.php`

Click any setting row to edit:

- `admin/manage-settings/manager.php?id=SETTING_NAME`

Common operational changes:

- turn signup on/off with `SIGNUP_ALLOWED`
- turn OTP on/off with `OTP_ALLOWED`
- set min recharge with `MIN_RECHARGE`
- set min withdraw with `MIN_WITHDRAW`
- update recharge button presets with `RECHARGE_OPTIONS`
- update SMS token with `SMS_TOKEN`
- set signup bonus with `SIGNUP_BONUS`

### 7.5 Add or Update Promotions

Use:

- list: `admin/manage-promotions/index.php`
- create: `admin/manage-promotions/create-promotion.php`
- edit: `admin/manage-promotions/edit-promotion.php`

Admin can:

- upload/crop promotion banner
- set title
- set subtitle/description
- choose category: `all`, `sports`, `casino`
- set end date
- activate/deactivate promotion

Stored in:

- `tbl_offer_promotions`

Uploaded images:

- `uploads/promotions/`

### 7.6 Manage Home Sliders / Hero Banners

Use:

- list: `admin/manage-sliders/index.php`
- create: `admin/manage-sliders/create-slider/`

Admin can:

- add slider image URL
- set optional action URL
- delete slider

Stored in:

- `tblsliders`

Important note:

- current slider system expects image URL input, not direct upload, in this create flow

### 7.7 Add / Sync Games

Use:

- `admin/manage-games/index.php`

Games are stored in:

- `tbl_games`

The admin games page supports:

- filtering by category
- filtering by provider
- filtering by active/inactive
- sort order review

There is also a sync/import script:

- `admin/manage-games/import_from_json.php`

That importer currently reads game definitions from an external frontend-style folder:

- `d:/winco-ishad/winco-frondend/src/components/jsondata/`

Files mapped include:

- `slotgames.js`
- `cusinolive.js`
- `turbogames.js`
- `fishgame.js`
- `indianpokergame.js`
- `topslot.js`
- `live.js`

Important for next developer:

- this is a hardcoded local path
- on another machine this will fail unless updated
- if game sync is broken, this file is one of the first places to inspect

### 7.8 Manage Bonuses

Entry:

- `admin/manage-bonus/index.php`

Redirects to:

- `admin/manage-bonus/bonus-list/`

Admin bonus responsibilities:

- create deposit/redeem bonuses
- create cashback rules
- review active and expired bonuses
- remove bad promotions/bonuses

Bonus-related areas:

- `create-bonus/`
- `create-cashback/`
- `bonus-list/`
- `cashback-list.php`
- `explore-bonus/`
- `explore-cashback/`

Important tables often involved:

- `tbl_bonuses`
- cashback-related tables depending on deployed schema

### 7.9 Manage Support Tickets

Use:

- `admin/support-tickets/index.php`

Admin can:

- filter by status
- filter by priority
- filter by username / userid
- search by date range
- open tickets and respond

Self-healing behavior:

- this page creates missing support tables automatically if needed

Important support tables:

- `tbl_support_tickets`
- `tbl_ticket_attachments`
- `tbl_ticket_replies`

### 7.10 Recharge / Deposit Review

Use:

- `admin/recharge-records/index.php`

Admin can:

- review recharge requests
- filter by username/date/status
- approve or reject deposits depending on the detailed page flow

Important table:

- `tblusersrecharge`

There is also auto-aging logic on this page:

- pending requests older than 12 hours are marked `rejected`

### 7.11 Withdraw Review

For actual withdraw requests, use:

- `admin/withdraw-records/`

There is also:

- `admin/manage-withdraw/`

Be careful:

- `manage-withdraw/index.php` currently appears to show records from `tblotherstransactions` with `Play Matched`
- this looks more like a transaction control page than the real withdraw approval panel

For real payout request handling, inspect and use:

- `withdraw-records/`
- `manual-withdraw-records/`
- `route-paths/request-withdrawl.php`

### 7.12 Manage Admin Accounts

Use:

- list: `admin/manage-admins/index.php`
- add: `admin/manage-admins/add-admin/`

When creating admin accounts:

- enter mobile number / admin ID
- enter password
- choose permission checkboxes

Stored in:

- `tbladmins`

Important limit:

- maximum count controlled by `ADMIN_ACCOUNTS_LIMIT` in `security/constants.php`

### 7.13 User Data / Player Management

Use:

- `admin/users-data/`

This area is generally where operators inspect:

- player balances
- account state
- joined under / referral
- profile details

If account support or manual balance work is needed, this is a key area to inspect.

## 8. OTP / SMS Flow

### Current behavior

OTP sending route:

- `route-send-sms`
- handler: `route-paths/services/sms/send-sms.php`

Signup route:

- `route-create-account`

Login route:

- `route-login`

### Required DB settings

These must be correct:

- `OTP_ALLOWED = true`
- `SMS_TOKEN = your real SMS token`

### Live failure statuses now supported

- `success`
- `otp_limit_error`
- `sms_token_missing`
- `otp_service_disabled`
- `sms_gateway_error`
- `sms_gateway_invalid_response`
- `sms_send_failed`
- `invalid_params`

### Recent security fix

The hardcoded global OTP bypass was removed.

That means:

- only real OTPs saved in `tblrecentotp` should now work
- signup/login should no longer accept arbitrary OTP values

### How to test OTP with Postman

POST to:

- `https://api.velplay365.com/router/?Route=route-send-sms`

Headers:

```text
Content-Type: application/json
Route: route-send-sms
AuthToken: test123456789
```

Body:

```json
{
  "SMS_MOBILE": "9876543210"
}
```

If response is:

```json
{"status_code":"otp_service_disabled"}
```

Then live DB has OTP disabled.

## 9. Important Tables and Their Purpose

### `tblusersdata`

Main player account table.

Contains:

- user ID
- mobile
- username/full name
- password hash
- balances
- account status
- auth secret

### `tblrecentotp`

Recent OTP storage.

Used for:

- signup OTP verification
- login OTP verification
- password reset OTP verification

### `tblservices`

Service and feature flags.

Acts like global app config.

### `tbladmins`

Admin accounts and access lists.

### `tblusersrecharge`

Deposit/recharge requests.

### `tbluserswithdraw`

Withdraw requests.

### `tblusersactivity`

Recent login/device tracking.

### `tblsliders`

Home page banners / sliders.

### `tbl_offer_promotions`

Promotional offer cards/banners.

### `tblotherstransactions`

Generic transaction records such as bonuses or other app-created entries.

### `tbl_support_tickets`

Customer support tickets.

## 10. Operational Workflows for Admins

### Daily startup checklist

1. Verify site branding is correct
2. Check `APP_STATUS` and `GAME_STATUS`
3. Review recharge records
4. Review withdraw records
5. Review support tickets
6. Review active promotions and bonus campaigns
7. Verify OTP service is enabled if onboarding is active

### Before launching a new campaign

1. Create promotion banner
2. Upload or set slider if campaign is homepage-visible
3. Create corresponding bonus or cashback rule
4. Verify category placement
5. Confirm end date
6. Test campaign from frontend

### Before enabling signup

1. Confirm `SIGNUP_ALLOWED = true`
2. Confirm `OTP_ALLOWED = true`
3. Confirm `SMS_TOKEN` is set
4. Confirm `SIGNUP_BONUS` if needed
5. Test signup end to end

## 11. Known Technical Cautions

### Hardcoded external paths

`admin/manage-games/import_from_json.php` uses a hardcoded path:

- `d:/winco-ishad/winco-frondend/src/components/jsondata/`

This is machine-specific and will break on another system.

### Mixed naming and legacy structure

There are some inconsistencies such as:

- `request-withdrawl.php` spelling
- duplicate constants files
- old fallback values in constants
- mixed use of direct SQL and prepared statements

### Self-healing migrations inside pages

Some admin pages create tables or columns on page load.

Examples:

- promotions
- support tickets

This is convenient but risky for production predictability.

### Logs can become very large

Router log files in `router/` can grow significantly:

- `router_hit.log`
- `router_debug.log`
- `router_final.log`
- `play_debug.txt`

Periodic cleanup is recommended.

## 12. Recommended Improvements for Next Developer

1. Move all environment-specific values to `.env`
2. Remove hardcoded filesystem paths
3. Centralize DB migrations instead of page-level self-healing
4. Standardize all API JSON response formats
5. Add admin audit logging for critical changes
6. Add explicit withdraw approval workflow documentation in code
7. Normalize naming:
   - withdrawl -> withdrawal
8. Add table schema reference file
9. Add seed script for branding-related `tblservices` keys
10. Add health-check page for:
   - DB
   - SMS token
   - OTP enabled
   - payout config

## 13. Quick Reference

### Developer entry points

- router: `D:\xampp\htdocs\router\index.php`
- routes map: `D:\xampp\htdocs\router\route-paths.php`
- DB config: `D:\xampp\htdocs\security\config.php`
- constants: `D:\xampp\htdocs\security\constants.php`
- setup: `D:\xampp\htdocs\security\setup-database.php`

### Admin entry points

- login: `http://localhost/admin/`
- settings: `http://localhost/admin/manage-settings/`
- branding: `http://localhost/admin/manage-settings/site-branding.php`
- games: `http://localhost/admin/manage-games/`
- promotions: `http://localhost/admin/manage-promotions/`
- sliders: `http://localhost/admin/manage-sliders/`
- bonuses: `http://localhost/admin/manage-bonus/`
- recharge records: `http://localhost/admin/recharge-records/`
- withdraw records: `http://localhost/admin/withdraw-records/`
- support tickets: `http://localhost/admin/support-tickets/`
- admin accounts: `http://localhost/admin/manage-admins/`

## 14. Final Advice for the Next Person

If something looks wrong in frontend behavior, check these in order:

1. frontend API base URL
2. router logs in `router/`
3. `tblservices` flags
4. DB connectivity from `security/config.php`
5. request handler in `route-paths/`
6. admin-managed content tables

If something looks wrong in site operations, check these in order:

1. branding settings
2. service flags
3. game status
4. promotions/sliders
5. bonus state
6. support / deposit / withdraw queues

This project is workable, but it relies heavily on convention and DB-driven behavior. The fastest way to debug is usually:

- identify the route
- inspect the matching file in `route-paths.php`
- inspect related `tblservices` values
- inspect the relevant table rows

## 15. Domain Change Checklist

When the project moves from one domain to another, do not change only one file. This stack has domain references in:

- `.htaccess`
- backend constants
- frontend constants
- branding data in `tblservices`
- payment endpoints
- any third-party callback configuration

This section is the minimum checklist for a safe domain migration.

### 15.1 Main places to update

#### Backend root rewrite rules

File:

- `D:\xampp\htdocs\.htaccess`

This file currently contains host-based rewrite rules for:

- `api.ranamatch.com`
- `ranamatch.com`

If domain changes, update:

- `api.ranamatch.com`
- `ranamatch.com`

Example:

```apache
RewriteCond %{HTTP_HOST} ^api\.ranamatch\.com$ [NC]
```

must become something like:

```apache
RewriteCond %{HTTP_HOST} ^api\.newdomain\.com$ [NC]
```

and any main-domain condition must also be updated:

```apache
RewriteCond %{HTTP_HOST} !^api\.newdomain\.com$ [NC]
```

Also review comments in that file so they match reality and do not confuse the next operator.

#### API folder rewrite rules

Reference files:

- `D:\xampp\htdocs\api_folder_htaccess.txt`
- `D:\xampp\htdocs\assets_htaccess_example.txt`
- `D:\xampp\htdocs\security\.htaccess`

These may be used during deployment if the live server structure changes.

If backend is exposed under `/api/` or another subfolder, verify:

- `RewriteBase`
- router target
- admin/uploads/static folder passthrough

Example:

```apache
RewriteBase /api/
RewriteRule ^ router/index.php [QSA,L]
```

If deployment path changes from `/api/` to something else, this must match.

### 15.2 Backend domain constants

File:

- `D:\xampp\htdocs\security\constants.php`

Important values:

- `$MAIN_DOMAIN_URL`
- `$API_TARGET_URL`
- `$API_ACCESS_URL`
- `$PAY_TARGET_URL`
- `$APP_DOWNLOAD_URL`

Current pattern:

```php
$MAIN_DOMAIN_URL = "velplay365.com";
$API_TARGET_URL = "https://api." . $MAIN_DOMAIN_URL . "/";
$API_ACCESS_URL = "https://" . $MAIN_DOMAIN_URL;
$PAY_TARGET_URL = "https://pay." . $MAIN_DOMAIN_URL;
```

When changing domain:

1. update `$MAIN_DOMAIN_URL`
2. verify all derived URLs are correct
3. verify the target subdomains actually exist:
   - `api.newdomain.com`
   - `pay.newdomain.com`

Also check:

- `D:\xampp\htdocs\security\constant2.php`

This file is legacy/alternate, but if it is still loaded anywhere it must stay aligned with `constants.php`.

### 15.3 Frontend API / site constants

Frontend file:

- `D:\winco-ishad\ranamatch\src\utils\constants.js`

This is one of the most important migration files.

Common values there:

- `URL`
- `API_URL`
- `PAYMENT_URL`

Example:

```js
export const URL = "https://api.velplay365.com/";
export const API_URL = "https://api.velplay365.com/router/";
export const PAYMENT_URL = "https://pay.winco.cc/gateapi/payments/gateways1/initialisation/casypay.php";
```

Update these carefully:

1. `URL`
2. `API_URL`
3. `PAYMENT_URL`

If the payment domain is different from the main site domain, do not assume it follows the same naming.

### 15.4 Branding values stored in DB

Some branding is not controlled only by files. Check `tblservices` for:

- `SITE_NAME`
- `SITE_LOGO_URL`
- `SITE_TAGLINE`
- `SITE_MARQUEE`
- `SITE_SOCIAL_LINKS`
- `CONTACT_SUPPORT_URL`
- `CONTACT_WHATSAPP`

If these contain old domain references, update them in admin:

- `http://localhost/admin/manage-settings/site-branding.php`

or directly in database if needed.

### 15.5 Payment / payout / callback configuration

Check these files:

- `D:\xampp\htdocs\security\constants.php`
- `D:\xampp\htdocs\payments\config.json`
- `D:\xampp\htdocs\security\india_lotto_config.php`

Look for:

- webhook URLs
- return URLs
- callback domains
- payout endpoints
- subdomain-specific configs

Even if the site domain changes, third-party providers may still be pointed to old callback URLs.

### 15.6 Game and provider callback paths

Check:

- `D:\xampp\htdocs\game\`
- `D:\xampp\htdocs\router\route-paths.php`
- `D:\xampp\htdocs\route-paths\callbacks\`

Also review external provider settings in:

- `security/constants.php`
- `security/india_lotto_config.php`

If a provider dashboard is configured with an old callback domain, gameplay may fail even if the main site loads correctly.

### 15.7 CORS and request security

File:

- `D:\xampp\htdocs\security\headers-security.php`

If this file validates origin, host, or request headers in a domain-aware way, update it too when domain changes.

This is easy to miss during migration.

### 15.8 Search for old domain strings before going live

Recommended search from project root:

```powershell
rg -n "olddomain\.com|api\.olddomain\.com|pay\.olddomain\.com|velplay365\.com|ranamatch\.com" "D:\xampp\htdocs"
```

And for frontend:

```powershell
rg -n "olddomain\.com|api\.olddomain\.com|pay\.olddomain\.com|velplay365\.com|ranamatch\.com" "D:\winco-ishad\ranamatch\src" "D:\winco-ishad\ranamatch\public"
```

Do this before final deployment.

### 15.9 Post-domain-change smoke test

After updating the domain, test all of these:

1. main homepage loads
2. SPA routes load after refresh
3. `api.newdomain.com` resolves
4. login works
5. OTP send works
6. signup works
7. deposit page loads
8. withdraw page loads
9. promotions load
10. game launch works
11. admin panel login works
12. admin branding page saves correctly
13. uploaded assets still render
14. payment return/callback flow works

### 15.10 Typical migration mistakes

Common mistakes:

1. updating frontend API URL but forgetting `.htaccess`
2. updating `.htaccess` but not `constants.php`
3. changing main domain but forgetting `pay.` domain
4. moving domain but leaving old logo/image URLs in DB
5. forgetting callback URLs in third-party dashboards
6. forgetting CORS/host validation in `headers-security.php`
7. not checking hardcoded links inside promotions or sliders

### 15.11 Recommended migration order

Use this order:

1. backup DB and current files
2. update DNS / hosting
3. update `.htaccess`
4. update `security/constants.php`
5. update `security/constant2.php`
6. update frontend `src/utils/constants.js`
7. update branding values in `tblservices`
8. update payment / callback configuration
9. search codebase for old domain strings
10. smoke test all critical flows

This order reduces confusing half-migrated behavior.
