# REST API

Base URL: `https://your-site.com/wp-json/stz/v1`

Public routes need no authentication. Admin routes (`/admin/*`) require a logged-in user with the right capability and the standard WordPress REST nonce (`X-WP-Nonce`), or Application Passwords.

Errors use the WordPress `WP_Error` shape: `{ "code": "...", "message": "...", "data": { "status": 422, "errors": { "field": "message" } } }`.

## Public endpoints

| Method | Route | Description |
| --- | --- | --- |
| GET | `/health` | Plugin version and status |
| GET | `/tours` | Search tours (filters below). Headers: `X-WP-Total`, `X-WP-TotalPages` |
| GET | `/tours/{id}` | One tour by id or slug: details, itinerary, plans, extras, departures, badges, rating |
| POST | `/quote` | Live price for a departure + plan |
| POST | `/bookings` | Create a booking request |
| GET | `/bookings/{code}` | Booking summary by code (used by the success page) |
| POST | `/trip-requests` | "Plan My Trip" request |
| GET | `/reviews` | Latest reviews |
| GET | `/tours/{id}/reviews` | Reviews of a tour (`page`, `per_page`) |
| POST | `/tours/{id}/reviews` | Submit a review (held for moderation) |
| GET | `/destinations` | Destinations |
| GET | `/styles` | Travel styles |
| GET | `/articles`, `/articles/{id}`, `/articles/taxonomy` | Guide articles |

### `GET /tours`

| Parameter | Type | Notes |
| --- | --- | --- |
| `destination`, `style` | string | Term slugs |
| `month` | string | `YYYY-MM` — tours with an open departure that month |
| `duration_min`, `duration_max` | int | Days |
| `price_min`, `price_max` | int | Smallest currency unit |
| `pax` | int | Only tours with enough seats |
| `discount`, `last_minute`, `special_offer`, `featured` | bool | Badge filters |
| `search` | string | Free text |
| `include` | int[] | Restrict to these ids |
| `sort` | enum | `recommended`, `price_asc`, `price_desc`, `date_asc`, `duration_asc`, `newest` |
| `page`, `per_page` | int | Pagination |

```bash
curl "https://example.com/wp-json/stz/v1/tours?destination=phuket&sort=price_asc&per_page=6"
```

### `POST /quote`

```json
{ "departure_id": 12, "plan_id": "couple", "pax": 2, "extras": [{ "id": "airport-pickup", "qty": 1 }] }
```

`departure_id` and `plan_id` are required; `pax` (1–50) defaults to the plan's size. The price is always recomputed on the server.

### `POST /bookings`

```json
{
  "departure_id": 12,
  "plan_id": "couple",
  "pax": 2,
  "extras": [],
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+66 81 234 5678",
  "contact_channel": "whatsapp",
  "contact_handle": "+66 81 234 5678",
  "message": "Vegetarian meals please",
  "terms": true,
  "turnstile_token": ""
}
```

`contact_channel` is one of `phone`, `whatsapp`, `line`, `email`. Response: `{ "code": "STZ-XXXXXX", "total": 36000, "pax": 2 }`.

| Status | Meaning |
| --- | --- |
| 422 | Validation errors (`data.errors`) |
| 403 | Turnstile check failed |
| 409 | Not enough seats on that departure |
| 429 | Rate limit (5 requests / 10 min per visitor) |

A hidden honeypot field (`website`) silently drops bot submissions.

## Admin endpoints

| Method | Route | Capability | Description |
| --- | --- | --- | --- |
| GET | `/admin/bookings` | `stz_manage_bookings` | List with filters (status, tour, dates, search) |
| GET | `/admin/bookings/export` | `stz_manage_bookings` | CSV export of the filtered list |
| GET / PATCH | `/admin/bookings/{id}` | `stz_manage_bookings` | Read, change status / note / payment |
| GET / PUT | `/admin/tours/{id}/details` | edit the tour | Tour editor payload (details, itinerary, plans, departures, flags) |
| GET / PUT | `/admin/settings` | `manage_options` | Plugin settings |
| POST | `/admin/demo` | `manage_options` | Import or remove demo data |

Status changes follow the allowed transitions; an invalid jump returns a 4xx error:

```
new ─► contacted ─► confirmed ─► paid ─► completed
 │          │           │         │
 └──────────┴─► cancelled ◄───────┘   confirmed/paid ─► no_show
```

## Using the API from another front end

The API is deliberately client-agnostic: build a headless site, a mobile app or a booking widget on the same endpoints. Remember to:

1. Always recompute prices with `/quote` before showing a total.
2. Handle `429` and `409` gracefully.
3. Never trust client-side totals — Core ignores them.
