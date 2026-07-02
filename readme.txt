=== StackPress ===
Contributors: dicecodes
Tags: security, performance, seo, woocommerce, optimization
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One plugin, 170+ free tools: caching, security, SEO, WooCommerce, backups, object cache, image optimization and more. Turn on only what you need.

== Description ==

**StackPress** replaces a whole stack of separate (and often paywalled) plugins with one lightweight, modular toolkit. Switch tools on and off from a single dashboard — when a tool is off, none of its code runs, so your site stays fast.

Every feature is free. There is no locked "Pro" tier.

Built and maintained by **[Dice Codes](https://dicecodes.com)**.

= Why StackPress =

* **Modular & light** — disabled modules load zero code, scripts, or extra database reads.
* **See the cost of everything** — each module shows its memory, front-end JavaScript, and database-query impact so you decide what to keep.
* **No paywalls** — 2FA-grade login protection, schema, spam protection, caching, backups, and more, all included.
* **Self-hosted first** — runs on your own server. Modules that contact an external service ask for consent and link to where to get any keys.

= What's included (170+ modules across 10 categories) =

* **Security** — login protection, **change the wp-login URL (hide login)**, login & comment captcha, security hardening, security headers, force HTTPS, activity log, 404 monitor, bad-bot blocking, Cloudflare control (Under Attack mode, cache purge), and more.
* **Performance** — **page caching with gzip, mobile cache and preloading**, **Redis / Memcached object cache**, minify HTML, **minify CSS & JavaScript**, defer JavaScript, lazy loading, image optimization, database cleanup, hover prefetch, disable bloat, and more.
* **SEO & AI** — meta tags, schema/JSON-LD, XML-friendly robots, breadcrumbs, FAQ schema, site verification, **Analytics (GA4, Tag Manager, Meta Pixel)**, llms.txt for AI assistants, and a free SEO/AI visibility checker.
* **Forms** — contact form, spam shield, SMTP email, newsletter signup, and a universal submissions tracker that logs Contact Form 7 / WPForms / Gravity / Forminator / Elementor entries in one place.
* **WooCommerce** — checkout field editor, custom order statuses, wishlist, back-in-stock alerts, product badges, catalog mode, quick quantity buttons, and more.
* **Media** — image optimizer, WebP/AVIF uploads, media folders, broken-image finder.
* **Content & marketing** — popups, cookie consent, maintenance mode with shareable bypass link, reviews showcase (no API key), table of contents, related posts, and more.
* **Site management** — backup & restore (no upload limit), cloud backup (FTP/WebDAV/email/Google Drive), monthly client report, update notifications, search & replace, import/export config.
* **Admin & developer** — admin menu editor, hide notices, white label, master search, shortcode generator, and more.
* **Accessibility & legal** — accessibility toolbar, skip links, focus outlines, reduced-motion, cookie consent, terms consent.

== External services ==

StackPress itself contacts no external services by default. The following modules connect to a third party **only when you enable and configure them**, and each field links to where to obtain any required ID/token:

* **Analytics & tracking** — when you add a GA4, Google Tag Manager, or Meta Pixel ID, the corresponding script from Google/Meta is loaded on your front end and visitor analytics is sent to that provider. See Google's privacy policy (https://policies.google.com/privacy) and Meta's (https://www.facebook.com/privacy/policy/).
* **Cloudflare control** — sends your API token and Zone ID to the Cloudflare API (https://api.cloudflare.com) to change settings you request. Cloudflare privacy policy: https://www.cloudflare.com/privacypolicy/.
* **Cloud backup** — uploads your backup archive to the destination you configure (your own FTP/WebDAV server, an email address, or Google Drive). Google Drive uses a one-time connect via the Dice Codes connector (https://dicecodes.com/stackpress); only an access token is stored.
* **Reviews showcase (embed mode)** — if you paste a third-party reviews widget embed code, that provider's script loads on your front end. Manual mode sends nothing.

No data is sent to any of these unless you turn the relevant module on and enter your details.

== Installation ==

1. In wp-admin go to **Plugins → Add New → Upload Plugin** and choose `stackpress.zip` (or install from the directory).
2. Activate **StackPress**.
3. Open **StackPress** in the admin menu and toggle on the modules you want. Each card shows its performance cost.

== Frequently Asked Questions ==

= Does an inactive module slow my site down? =
No. When a module is off, its code is never loaded — no hooks, scripts, or database reads. The only baseline cost is one cached option read.

= Is it really free? =
Yes. Every module is free, with no locked features.

= Do I need API keys? =
Most modules need nothing. A few optional ones (Analytics, Cloudflare, Google Drive backup) need an ID or token — each field links to exactly where to get it.

= How do I request a feature? =
Use the "Request a feature" link in the StackPress dashboard, or email Contact@dicecodes.com.

= Who makes StackPress? =
StackPress is built and maintained by Dice Codes (https://dicecodes.com).

== Screenshots ==

1. The modular dashboard — every tool shows its memory, JavaScript, and database cost, and turns on or off with one click.
2. Smart dependencies — WooCommerce tools stay dormant and clearly show "WooCommerce is not installed" with a one-click install link.
3. The SEO category — Essential and Recommended tags, "replaces premium" notes, and a consent prompt for any tool that uses an external service.

== Changelog ==

= 1.5.4 =
* Improved: clearer messaging when a tool needs another plugin (for example WooCommerce), with a one-click install link.
* Improved: tools that have their own screen now show their settings right there.
* Housekeeping: leaner, security-focused build; every tool still loads only when it is enabled.
* Tested up to WordPress 7.0.

= 1.0.0 =
* First public release of StackPress.

== Upgrade Notice ==

= 1.5.4 =
Clearer dependency messaging and per-tool settings. Your enabled tools and settings are preserved across the update.
