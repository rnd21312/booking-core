# User guide

## Create a tour

**Suntourz → All tours → Add New.** Title, description, excerpt and featured image are normal WordPress fields. Below them the **tour editor** has five tabs:

1. **Details** — duration, highlights, included / not included, meeting point, gallery.
2. **Itinerary** — days and timeline items (drag to reorder).
3. **Pricing** — plans (Single, Couple, Family, Family+) and optional extras. Tip: use *Add default plans*.
4. **Departures** — dates, capacity and status. Only **Open** departures can be booked. Use *Create recurring series* for weekly trips, and **Prices** to override a plan price for one date.
5. **Flags** — featured, special offer, sort order.

Press **Publish / Update** — it saves the tour editor too (or use *Save tour details* inside the editor).

### Departure statuses

| Status | Bookable | Notes |
| --- | --- | --- |
| `draft` | no | Hidden from the site |
| `open` | yes | Counts seats |
| `closed` | no | Visible, shown as closed / sold out |
| `cancelled` | no | Existing bookings stay in the list |

### Badges

Computed automatically from your data and settings: **On sale** (discounted plan), **Last minute** (departs within *N* days), **Only X left** (few seats), plus the manual **Special offer** flag.

## Handle a booking

1. A guest sends a request. **Suntourz → Bookings** shows a bubble with new requests.
2. Open it: all details, one-click **Call / WhatsApp / LINE / Email**, status history.
3. Contact the guest, then move the status: **New → Contacted → Confirmed → Paid → Completed** (or **Cancelled / No-show**). Add a note on every change.
4. Record the **amount paid**, **payment method** and an internal note.
5. **Export CSV** downloads the filtered list for Excel.

Pending bookings (New / Contacted) hold seats by default — change it under *Booking rules → Pending bookings hold seats*. Cancelled and No-show bookings release their seats.

## Trip requests

**Suntourz → Trip requests** lists *Plan My Trip* submissions with the trip details and the guest's contact information. Use the status field to track follow-up.

## Reviews

Guest reviews appear under **Comments**. Approve to publish; the tour rating and review count update automatically. Enable *Reviews auto-approve* to skip moderation.

## Permissions

| Role | Access |
| --- | --- |
| Administrator | Everything, including settings and demo data |
| Booking manager | Bookings and trip requests only (`stz_manage_bookings` + trip-request caps) |

Assign *Booking manager* to sales staff who should not touch the rest of WordPress.

## FAQ

**Does the site send e-mails?** No. All requests stay in WordPress so there is no spam or deliverability work. Check the admin bubbles.

**Can I change the currency?** Yes — **Settings → Currency**. Amounts are stored in the smallest unit, so switching display format is safe.

**Can I use a different front end?** Yes. See the [REST API](rest-api.md).
