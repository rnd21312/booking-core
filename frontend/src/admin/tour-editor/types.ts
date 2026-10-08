export type ItineraryItemType = 'transfer' | 'meal' | 'activity' | 'rest' | 'free' | 'stay';
export type DepartureStatus = 'draft' | 'open' | 'closed' | 'cancelled';
export type ExtraUnit = 'per_booking' | 'per_person';

/** Client-only stable key so rows keep identity while being edited/reordered. */
type Keyed = { _key: string };

export type GalleryImage = { id: number; url: string };

export type ItineraryItemRow = Keyed & { time: string; type: ItineraryItemType; title: string };
export type ItineraryDayRow = Keyed & {
  day: number;
  title: string;
  description: string;
  items: ItineraryItemRow[];
};

export type PlanRow = Keyed & {
  /** Empty for new plans — the server generates it on save. */
  id: string;
  label: string;
  pax: number;
  price: number;
  sale_price: number | null;
  extra_person_price: number | null;
  max_pax: number | null;
};

export type ExtraRow = Keyed & {
  id: string;
  label: string;
  price: number;
  unit: ExtraUnit;
  max_qty: number | null;
};

export type PriceOverride = { price?: number; sale_price?: number | null };

export type DepartureRow = Keyed & {
  id: number | null;
  start_date: string;
  end_date: string;
  capacity: number;
  status: DepartureStatus;
  price_overrides: Record<string, PriceOverride>;
  note: string;
  booked_pax: number;
  seats_left: number;
  bookings_count: number;
};

export type MeetingPoint = { name: string; address: string; lat: number | null; lng: number | null };

export type Draft = {
  details: {
    duration_days: number;
    highlights: string[];
    includes: string[];
    excludes: string[];
    meeting_point: MeetingPoint;
    itinerary: ItineraryDayRow[];
    gallery: GalleryImage[];
  };
  pricing: { currency: string; plans: PlanRow[] };
  extras: ExtraRow[];
  flags: { featured: boolean; special_offer: boolean; sort_order: number };
  departures: DepartureRow[];
};

type RawItem = Omit<ItineraryItemRow, '_key'>;
type RawDay = Omit<ItineraryDayRow, '_key' | 'items'> & { items: RawItem[] };

/** Shape exchanged with GET/PUT /admin/tours/{id}/details (no client keys). */
export type EditorPayload = {
  tour: { id: number; title: string; status: string; permalink: string };
  details: Omit<Draft['details'], 'itinerary'> & { itinerary: RawDay[] };
  pricing: { currency: string; plans: Omit<PlanRow, '_key'>[] };
  extras: Omit<ExtraRow, '_key'>[];
  flags: Draft['flags'];
  departures: Omit<DepartureRow, '_key'>[];
  warnings?: string[];
};

export type TabId = 'details' | 'itinerary' | 'pricing' | 'departures' | 'flags';
