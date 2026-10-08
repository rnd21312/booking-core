# Architecture

Core follows a thin-controller / service / repository layout with a tiny dependency container and **zero Composer dependencies** (a PSR-4 autoloader ships in the main plugin file), so the zip works on any host.

```
suntourz-core.php          plugin header, constants, autoloader
src/
├── Plugin.php             wires services, hooks and REST controllers
├── Container.php          minimal service container
├── Install/               Activator, Roles, Schema (dbDelta migrations)
├── PostTypes/             stz_tour, stz_trip_request, taxonomies, meta
├── Domain/                BookingStatus, DepartureStatus, Pricing, Seats, Badges, BookingCode
├── Repository/            DepartureRepository, BookingRepository …
├── Services/              TourCatalog, QuoteService, BookingService, BookingAdminService,
│                          ReviewService, TripRequestService, TourEditorService, DemoImporter …
├── Rest/                  one controller per resource
├── Settings/              Settings (option stz_settings, sanitising)
├── Support/               RateLimiter, Turnstile
├── Admin/                 Menu, TourEditorScreen, ViteAssets
└── Cli/                   DemoCommand (wp stz demo …)
frontend/src/admin/        React admin apps: tour-editor, bookings, settings
```

## Database

Tours, trip requests, destinations, travel styles and articles use native WordPress content types. High-write, relational data lives in three custom tables:

| Table | Purpose |
| --- | --- |
| `{prefix}stz_departures` | Dated departures per tour: capacity, status, price overrides |
| `{prefix}stz_bookings` | Booking requests: code, tour, departure, plan, pax, total, guest contact, status, payment, notes |
| `{prefix}stz_booking_log` | Immutable status history (who, when, from → to, note) |

Migrations are versioned in `Schema::install()` and idempotent. Existing tables and columns are never renamed.

## Domain rules

- **Pricing** (`Domain/Pricing.php`): plan base price × pax rules, extras, per-departure overrides and discounts. Always computed on the server; the client total is ignored.
- **Seats** (`Domain/Seats.php`): capacity minus seats held by bookings that hold seats. Booking creation runs inside a transaction that locks the departure row, so two simultaneous requests cannot oversell the last seat.
- **Status machine** (`Domain/BookingStatus.php`): explicit transition table; invalid jumps are rejected.
- **Badges** (`Domain/Badges.php`): *last minute*, *few seats*, *on sale* derived from data and settings.

## Admin front end

Three React 19 apps built by Vite (multi-entry, manifest). `Admin/ViteAssets.php` reads `dist/.vite/manifest.json` and enqueues only the entry needed by the current screen. They talk to the `/admin/*` REST routes with the WordPress REST nonce.

## Security model

- Admin routes: capability checks on every route (`manage_options`, `stz_manage_bookings`, per-post `edit_post`).
- Public writes: server-side validation, honeypot, per-visitor rate limit (`RateLimiter`, IP is stored only as a salted hash), optional Cloudflare Turnstile.
- Output: all admin data is escaped by React; PHP output is escaped late.
- Privacy: no outbound e-mail or third-party calls from the plugin (Turnstile only when you enable it).

## Testing

`tests/Unit` holds PHPUnit tests for the pure domain classes (`Pricing`, `Seats`, `Badges`, `BookingStatus`, `BookingCode`). The front end has Vitest suites for formatting, dates and the API client.
