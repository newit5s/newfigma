# Security and Performance Review

## 1. AJAX and REST endpoints
- The public booking widget validates nonces via `check_ajax_referer` before handling requests and sanitizes most visitor input, reducing CSRF and injection risk.【F:public/class-modern-booking-widget.php†L245-L280】【F:public/class-modern-booking-widget.php†L354-L358】
- Dashboard and back-office AJAX controllers enforce both nonce checks and capability validation prior to touching privileged data.【F:public/class-modern-dashboard.php†L276-L358】【F:public/class-modern-dashboard.php†L963-L968】【F:public/class-modern-booking-manager.php†L225-L275】【F:public/class-modern-booking-manager.php†L750-L771】
- Authentication endpoints rely on the `rb_portal_auth` nonce and sanitize credentials before attempting login flows.【F:public/class-modern-portal-auth.php†L186-L235】【F:public/class-modern-portal-auth.php†L300-L309】
- The calendar REST route registers sanitizers for each argument and normalizes request data before querying availability, though it is publicly readable via `__return_true` permissions.【F:includes/services/class-calendar-service.php†L98-L170】
- Booking queries rely on `$wpdb->prepare()` and escaped search fragments to prevent SQL injection when filtering records.【F:includes/models/class-booking.php†L1010-L1069】
- **Hygiene gap:** `ajax_get_locations()` forwards raw location names/labels from the database without sanitization, allowing a stored XSS vector if malicious data is saved in `RB_Location` records. Escaping with `sanitize_text_field()` or `wp_strip_all_tags()` before returning JSON would mitigate this.【F:public/class-modern-booking-widget.php†L200-L236】
- **Hygiene gap:** `normalize_table_record()` and related helpers expose table/customer strings exactly as stored. Sanitizing the human-facing fields before returning them to the browser would harden against injected markup from compromised data sources.【F:public/class-modern-table-manager.php†L358-L438】【F:public/class-modern-table-manager.php†L582-L632】

## 2. Service worker and caching
- The service worker keeps a single versioned cache and serves a static offline HTML shell, but it caches every successful same-origin GET request opportunistically without an eviction strategy. Over time this "cache everything" approach can bloat storage and risks serving stale API responses when backend state changes. Consider restricting the cache to static assets or adding cache busting/TTL logic while still returning the offline fallback for navigations.【F:sw.js†L1-L45】

## 3. Script and stylesheet enqueue strategy
- Front-end contexts enqueue multiple unminified CSS and JS bundles (design system, components, animations, booking modal) on every page that renders the shortcode. Leveraging `.min` builds or a build step would trim payload size, and selectively loading only the assets needed for the active view could further reduce cost.【F:public/class-modern-booking-widget.php†L120-L186】
- The shared asset loader centralizes registrations but similarly references non-minified files and does not mark scripts with `defer` or `async`, so they still block parsing until footer execution. Introducing minified variants and optionally deferring non-critical scripts would improve load performance, especially for the dashboard bundle.【F:includes/traits/trait-rb-asset-loader.php†L32-L173】
