import { describe, expect, it } from 'vitest';
import { buildSeries, defaultPlans, duplicateDeparture, newDeparture, toDraft, toPayload } from './draft';
import type { EditorPayload } from './types';

const payload: EditorPayload = {
  tour: { id: 1, title: 'T', status: 'draft', permalink: '' },
  details: {
    duration_days: 4,
    highlights: ['a'],
    includes: [],
    excludes: [],
    meeting_point: { name: '', address: '', lat: null, lng: null },
    itinerary: [
      { day: 1, title: 'Arrive', description: '', items: [{ time: '10:00', type: 'transfer', title: 'Pickup' }] },
    ],
    gallery: [],
  },
  pricing: { currency: 'THB', plans: [{ id: 'single', label: 'Single', pax: 1, price: 100, sale_price: null, extra_person_price: null, max_pax: null }] },
  extras: [],
  flags: { featured: false, special_offer: false, sort_order: 0 },
  departures: [
    { id: 5, start_date: '2030-01-10', end_date: '2030-01-13', capacity: 10, status: 'open', price_overrides: { single: { price: 90 } }, note: '', booked_pax: 3, seats_left: 7, bookings_count: 1 },
  ],
};

describe('draft conversion', () => {
  it('round-trips without client keys or read-only counters', () => {
    const body = toPayload(toDraft(payload));
    const departure = body.departures[0] as Record<string, unknown>;

    expect(JSON.stringify(body)).not.toContain('_key');
    expect(departure).not.toHaveProperty('booked_pax');
    expect(departure).not.toHaveProperty('seats_left');
    expect(departure.id).toBe(5);
    expect(body.details.itinerary[0]?.items[0]?.title).toBe('Pickup');
  });

  it('renumbers itinerary days by position', () => {
    const draft = toDraft(payload);
    draft.details.itinerary.push({ ...draft.details.itinerary[0]!, _key: 'x', day: 9 });

    expect(toPayload(draft).details.itinerary.map((d) => d.day)).toEqual([1, 2]);
  });
});

describe('departure helpers', () => {
  it('builds a weekly series', () => {
    const rows = buildSeries({
      firstStart: '2030-01-04',
      durationDays: 4,
      everyDays: 7,
      count: 3,
      capacity: 12,
      status: 'open',
    });

    expect(rows.map((r) => [r.start_date, r.end_date])).toEqual([
      ['2030-01-04', '2030-01-07'],
      ['2030-01-11', '2030-01-14'],
      ['2030-01-18', '2030-01-21'],
    ]);
    expect(rows.every((r) => r.id === null && r.capacity === 12 && r.status === 'open')).toBe(true);
    expect(new Set(rows.map((r) => r._key)).size).toBe(3);
  });

  it('caps and sanitizes series input', () => {
    expect(buildSeries({ firstStart: '2030-01-01', durationDays: 0, everyDays: 0, count: 1000, capacity: 1, status: 'draft' })).toHaveLength(104);
    expect(buildSeries({ firstStart: '2030-01-01', durationDays: 3, everyDays: 7, count: -2, capacity: 1, status: 'draft' })).toHaveLength(0);
  });

  it('duplicates a departure a week later as a draft without bookings', () => {
    const source = newDeparture({ id: 7, start_date: '2030-01-10', end_date: '2030-01-13', status: 'open', booked_pax: 4, bookings_count: 2, price_overrides: { single: { price: 1 } } });
    const copy = duplicateDeparture(source);

    expect(copy).toMatchObject({ id: null, start_date: '2030-01-17', end_date: '2030-01-20', status: 'draft', booked_pax: 0, bookings_count: 0 });
    expect(copy.price_overrides).toEqual({ single: { price: 1 } });
    expect(copy.price_overrides).not.toBe(source.price_overrides);
  });

  it('offers the four default plans', () => {
    const plans = defaultPlans();

    expect(plans.map((p) => p.label)).toEqual(['Single', 'Couple', 'Family', 'Family+']);
    expect(plans[3]).toMatchObject({ pax: 5, max_pax: 8 });
  });
});
