# Configuration

All settings live under **Suntourz → Settings** and are stored in one option (`stz_settings`). The same values are available through `GET/PUT /wp-json/stz/v1/admin/settings` (administrators only).

## General

Site name and description, logo (+ version for dark backgrounds), favicon, logo badge text and subtitle, **primary / accent / background colours**, footer description, and the home page SEO title, description and default share image.

## Company & contact

| Setting | Key | Default |
| --- | --- | --- |
| Company name | `company_name` | Suntourz |
| Phone | `phone` | placeholder — **replace** |
| WhatsApp number | `whatsapp` | placeholder — **replace** |
| LINE ID | `line_id` | empty |
| Telegram | `telegram` | empty |
| Public support e-mail | `support_email` | `support@example.com` |
| Instagram / Facebook URLs | `instagram_url`, `facebook_url` | empty |
| "We reply…" text | `response_time_text` | `within 24 hours` |

## Currency

| Setting | Key | Default |
| --- | --- | --- |
| Code | `currency_code` | `THB` |
| Symbol | `currency_symbol` | `฿` |
| Position | `currency_position` | `before` (or `after`) |
| Decimals | `currency_decimals` | `0` (0–2) |
| Thousand / decimal separator | `thousand_separator`, `decimal_separator` | `,` and `.` |

## Booking rules

| Setting | Key | Default |
| --- | --- | --- |
| "Last minute" window (days) | `last_minute_days` | 14 |
| "Only X left" threshold | `few_seats_threshold` | 4 |
| Pending bookings hold seats | `pending_holds_seats` | on |
| Auto-approve reviews | `reviews_auto_approve` | off |
| Booking terms text | `booking_terms` | empty (guests must tick the terms box) |
| Cloudflare Turnstile | `turnstile_site_key`, `turnstile_secret` | off |

### Turnstile

Create a Turnstile widget in Cloudflare, paste the **site key** and **secret** here, and the booking form adds the challenge automatically. Leave both empty to disable it.

## Demo data

*Import demo tours* / *Remove demo data* (see [Installation](installation.md)).

## Hooks

Core is built to be extended. Useful integration points:

- REST API — everything the theme uses is public and documented in [rest-api.md](rest-api.md).
- WordPress capabilities — `stz_manage_bookings`, and the `stz_trip_request` capability set.
- Options — `stz_settings` (settings) and the custom tables `{prefix}stz_departures`, `{prefix}stz_bookings`, `{prefix}stz_booking_log`.
