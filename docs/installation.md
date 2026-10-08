# Installation

## Requirements

| | Minimum |
| --- | --- |
| WordPress | 6.5 |
| PHP | 8.1 |
| Database | MySQL 5.7+ / MariaDB 10.3+ (SQLite is fine for development) |
| Node.js (source builds only) | 20 |

## From the release zip

1. Download `booking-core.zip` from the [releases page](https://github.com/rnd21312/booking-core/releases).
2. **Plugins → Add New → Upload Plugin** → select the zip → **Install Now** → **Activate**.
3. Activation creates the database tables (`stz_departures`, `stz_bookings`, `stz_booking_log`), the *Booking manager* role and the capabilities for administrators.
4. Install the [Suntourz theme](https://github.com/rnd21312/react-theme) to get the public website.

## From source

```bash
git clone https://github.com/rnd21312/booking-core.git
cd booking-core/frontend
npm ci
npm run build          # writes ../dist
```

Copy the `booking-core` folder (with `dist/`) into `wp-content/plugins/` and activate it. Without `dist/`, admin screens show an *assets are missing* notice.

## Demo data

- Admin: **Suntourz → Settings → Demo data → Import demo tours**
- WP-CLI:

```bash
wp stz demo import
wp stz demo remove
```

The importer creates three tours with departures, sample bookings, pages (Home, Blog, About, Contact, Plan My Trip, Booking Success, Terms), menus, destinations, travel styles and guide articles. *Remove* deletes only the items it created.

## Upgrading

Replace the plugin with the new zip. Schema changes are versioned and run automatically (`dbDelta`, additive only — tables and columns are never renamed or dropped).

## Uninstalling

Deactivating keeps all data. Deleting the plugin keeps bookings so you can never lose customer records by accident; remove the `stz_*` tables and options manually if you really want a clean slate.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Admin screens say assets are missing | You installed a source checkout. Build `dist/` or use the release zip. |
| 404 on tour URLs | **Settings → Permalinks → Save**. |
| Where are the requests? | The plugin sends no e-mail. See **Suntourz → Bookings** and **Trip requests**; menu bubbles show new items. |
| Can't see the Suntourz menu | Your user needs `stz_manage_bookings` (Administrator or *Booking manager*). |
