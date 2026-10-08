import { addDays } from '@/lib/dates';
import type {
  DepartureRow,
  DepartureStatus,
  Draft,
  EditorPayload,
  ExtraRow,
  ItineraryDayRow,
  ItineraryItemRow,
  PlanRow,
} from './types';

let counter = 0;
/** Unique, stable key for list rows (client side only). */
export const newKey = (): string => `k${Date.now().toString(36)}${(counter++).toString(36)}`;

/** Server payload → editable draft (adds client keys). */
export const toDraft = (payload: EditorPayload): Draft => ({
  details: {
    ...payload.details,
    itinerary: payload.details.itinerary.map((day) => ({
      ...day,
      _key: newKey(),
      items: day.items.map((item) => ({ ...item, _key: newKey() })),
    })),
  },
  pricing: {
    currency: payload.pricing.currency,
    plans: payload.pricing.plans.map((plan) => ({
      ...plan,
      sale_price: plan.sale_price ?? null,
      extra_person_price: plan.extra_person_price ?? null,
      max_pax: plan.max_pax ?? null,
      _key: newKey(),
    })),
  },
  extras: payload.extras.map((extra) => ({ ...extra, max_qty: extra.max_qty ?? null, _key: newKey() })),
  flags: payload.flags,
  departures: payload.departures.map((departure) => ({
    ...departure,
    price_overrides: { ...departure.price_overrides },
    _key: newKey(),
  })),
});

const stripKey = <T extends { _key: string }>(row: T): Omit<T, '_key'> => {
  const { _key, ...rest } = row;
  void _key;
  return rest;
};

/** Draft → request body (client keys and read-only counters are dropped). */
export const toPayload = (draft: Draft) => ({
  details: {
    ...draft.details,
    itinerary: draft.details.itinerary.map((day, index) => ({
      ...stripKey(day),
      day: index + 1,
      items: day.items.map(stripKey),
    })),
  },
  pricing: {
    currency: draft.pricing.currency,
    plans: draft.pricing.plans.map(stripKey),
  },
  extras: draft.extras.map(stripKey),
  flags: draft.flags,
  departures: draft.departures.map((row) => {
    const departure = stripKey(row);
    return {
      id: departure.id,
      start_date: departure.start_date,
      end_date: departure.end_date,
      capacity: departure.capacity,
      status: departure.status,
      price_overrides: departure.price_overrides,
      note: departure.note,
    };
  }),
});

/* ---- row factories ---- */

export const newItineraryItem = (): ItineraryItemRow => ({
  _key: newKey(),
  time: '',
  type: 'activity',
  title: '',
});

export const newItineraryDay = (day: number): ItineraryDayRow => ({
  _key: newKey(),
  day,
  title: '',
  description: '',
  items: [],
});

export const newPlan = (partial: Partial<PlanRow> = {}): PlanRow => ({
  _key: newKey(),
  id: '',
  label: '',
  pax: 1,
  price: 0,
  sale_price: null,
  extra_person_price: null,
  max_pax: null,
  ...partial,
});

/** The four plans from the brief (Single, Couple, Family, Family+) with empty prices. */
export const defaultPlans = (): PlanRow[] => [
  newPlan({ label: 'Single', pax: 1 }),
  newPlan({ label: 'Couple', pax: 2 }),
  newPlan({ label: 'Family', pax: 4 }),
  newPlan({ label: 'Family+', pax: 5, extra_person_price: 0, max_pax: 8 }),
];

export const newExtra = (): ExtraRow => ({
  _key: newKey(),
  id: '',
  label: '',
  price: 0,
  unit: 'per_booking',
  max_qty: null,
});

export const newDeparture = (partial: Partial<DepartureRow> = {}): DepartureRow => ({
  _key: newKey(),
  id: null,
  start_date: '',
  end_date: '',
  capacity: 10,
  status: 'draft',
  price_overrides: {},
  note: '',
  booked_pax: 0,
  seats_left: 0,
  bookings_count: 0,
  ...partial,
});

export type SeriesOptions = {
  firstStart: string;
  /** Trip length in days (end = start + days − 1). */
  durationDays: number;
  /** Repeat interval in days (7 = weekly). */
  everyDays: number;
  count: number;
  capacity: number;
  status: DepartureStatus;
};

/** Bulk-creates a recurring series of departures (e.g. every week for 8 weeks). */
export const buildSeries = (options: SeriesOptions): DepartureRow[] => {
  const count = Math.max(0, Math.min(104, Math.floor(options.count)));
  const every = Math.max(1, Math.floor(options.everyDays));
  const length = Math.max(1, Math.floor(options.durationDays));

  return Array.from({ length: count }, (_, index) => {
    const start = addDays(options.firstStart, index * every);
    return newDeparture({
      start_date: start,
      end_date: addDays(start, length - 1),
      capacity: options.capacity,
      status: options.status,
    });
  });
};

/** Duplicates a departure one week later as a draft (keeps capacity, note and price overrides). */
export const duplicateDeparture = (source: DepartureRow): DepartureRow =>
  newDeparture({
    start_date: addDays(source.start_date, 7),
    end_date: addDays(source.end_date, 7),
    capacity: source.capacity,
    status: 'draft',
    price_overrides: structuredClone(source.price_overrides),
    note: source.note,
  });
