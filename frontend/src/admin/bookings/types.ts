export type BookingStatus = 'new' | 'contacted' | 'confirmed' | 'paid' | 'completed' | 'cancelled' | 'no_show';

export const STATUSES: BookingStatus[] = ['new', 'contacted', 'confirmed', 'paid', 'completed', 'cancelled', 'no_show'];

export type BookingExtra = { id: string; label: string; qty: number; unit_amount: number; amount: number };

export type HistoryEntry = { from: string | null; to: string; by: string; note: string; at: string };

export type Booking = {
  id: number;
  code: string;
  status: BookingStatus;
  allowed: BookingStatus[];
  tour: { id: number; title: string; edit: string };
  departure: { id: number; start_date: string; end_date: string };
  plan_id: string;
  plan_label: string;
  pax: number;
  extras: BookingExtra[];
  total_amount: number;
  amount_paid: number;
  currency: string;
  payment_method: string;
  customer: { name: string; email: string; phone: string };
  contact: { channel: 'phone' | 'whatsapp' | 'line' | 'email'; handle: string };
  message: string;
  admin_note: string;
  locale: string;
  created_at: string;
  updated_at: string;
  history?: HistoryEntry[];
};

export type BookingList = {
  items: Booking[];
  total: number;
  total_pages: number;
  page: number;
  per_page: number;
  counts: Partial<Record<BookingStatus, number>>;
};

export type BookingFilters = {
  status: string;
  search: string;
  tour_id: string;
  date_from: string;
  date_to: string;
  page: number;
};
