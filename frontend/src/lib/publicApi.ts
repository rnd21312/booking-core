import type {
  ArticlesQuery,
  ArticlesResponse,
  BookingInput,
  BookingResponse,
  BookingSummary,
  QuoteRequest,
  QuoteResponse,
  ReviewInput,
  ReviewsResponse,
  ToursQuery,
  ToursResponse,
  TripRequestInput,
  TripRequestResponse,
} from './types';

let restBase = '/wp-json/';

/** Called once at start-up with the REST root printed by PHP. */
export const configureApi = (restUrl: string): void => {
  restBase = restUrl.endsWith('/') ? restUrl : `${restUrl}/`;
};

/** Error with the per-field messages WordPress sends back for validation failures (HTTP 422). */
export class PublicApiError extends Error {
  readonly status: number;
  readonly fieldErrors: Record<string, string>;

  constructor(message: string, status: number, fieldErrors: Record<string, string> = {}) {
    super(message);
    this.name = 'PublicApiError';
    this.status = status;
    this.fieldErrors = fieldErrors;
  }
}

/** Serializes filters the way the REST API expects (arrays comma-joined, true → 1). */
export const toQueryString = (params: object): string => {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || value === false) continue;
    if (Array.isArray(value)) {
      if (value.length > 0) search.set(key, value.join(','));
    } else {
      search.set(key, value === true ? '1' : String(value));
    }
  }

  return search.toString();
};

type ErrorBody = { message?: string; data?: { errors?: Record<string, string> } };

const request = async <T>(path: string, init: RequestInit = {}): Promise<T> => {
  const response = await fetch(restBase + path.replace(/^\//, ''), {
    ...init,
    headers: { 'Content-Type': 'application/json', ...init.headers },
  });
  const body: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const error = (body ?? {}) as ErrorBody;
    throw new PublicApiError(
      error.message ?? `Request failed (${response.status})`,
      response.status,
      error.data?.errors ?? {},
    );
  }

  return body as T;
};

const post = <T>(path: string, data: unknown) =>
  request<T>(path, { method: 'POST', body: JSON.stringify(data) });

export const api = {
  quote: (input: QuoteRequest, signal?: AbortSignal) =>
    request<QuoteResponse>('stz/v1/quote', { method: 'POST', body: JSON.stringify(input), signal }),
  postBooking: (input: BookingInput) => post<BookingResponse>('stz/v1/bookings', input),
  bookingSummary: (code: string, email: string) =>
    request<BookingSummary>(`stz/v1/bookings/${encodeURIComponent(code)}?email=${encodeURIComponent(email)}`),
  tours: (query: ToursQuery, signal?: AbortSignal) => {
    const qs = toQueryString(query);
    return request<ToursResponse>(`stz/v1/tours${qs ? `?${qs}` : ''}`, { signal });
  },
  reviews: (tourId: number, page = 1) =>
    request<ReviewsResponse>(`stz/v1/tours/${tourId}/reviews?page=${page}`),
  postReview: (tourId: number, input: ReviewInput) =>
    post<{ ok: boolean; status: 'pending' | 'approved'; message: string }>(
      `stz/v1/tours/${tourId}/reviews`,
      input,
    ),
  articles: (query: ArticlesQuery = {}, perPage = 9, signal?: AbortSignal) => {
    const qs = toQueryString({ ...query, per_page: perPage });
    return request<ArticlesResponse>(`stz/v1/articles?${qs}`, { signal });
  },
  postTripRequest: (input: TripRequestInput) =>
    post<TripRequestResponse>('stz/v1/trip-requests', input),
};
