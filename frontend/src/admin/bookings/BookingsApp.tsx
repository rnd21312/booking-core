import { useCallback, useEffect, useMemo, useState } from 'react';
import { Download, Mail, MessageSquare, Phone, RefreshCw, X } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';
import { formatDate, formatMoney } from '@/lib/format';
import { toQueryString } from '@/lib/publicApi';
import { ApiError, createApi, type ShellConfig } from '../lib/api';
import { Button, MoneyInput, Select, TextInput, Textarea } from '../ui/controls';
import { Field } from '../ui/Field';
import { STATUSES, type Booking, type BookingFilters, type BookingList, type BookingStatus } from './types';

const statusLabel = (status: string): string =>
  ({
    new: __('New', 'suntourz'),
    contacted: __('Contacted', 'suntourz'),
    confirmed: __('Confirmed', 'suntourz'),
    paid: __('Paid', 'suntourz'),
    completed: __('Completed', 'suntourz'),
    cancelled: __('Cancelled', 'suntourz'),
    no_show: __('No-show', 'suntourz'),
  })[status] ?? status;

const methodLabel = (method: string): string =>
  ({
    '': __('— not set —', 'suntourz'),
    cash: __('Cash', 'suntourz'),
    bank_transfer: __('Bank transfer', 'suntourz'),
    card: __('Card', 'suntourz'),
    promptpay: __('PromptPay', 'suntourz'),
    other: __('Other', 'suntourz'),
  })[method] ?? method;

const Chip = ({ status }: { status: string }) => <span className={`stz-chip stz-chip-status-${status}`}>{statusLabel(status)}</span>;

/** Contact shortcuts: call, WhatsApp, LINE, e-mail. */
const quickLinks = (b: Booking) => {
  const digits = (value: string) => value.replace(/\D+/g, '');
  const wa = digits(b.contact.channel === 'whatsapp' && b.contact.handle ? b.contact.handle : b.customer.phone);

  return [
    { key: 'call', label: __('Call', 'suntourz'), href: `tel:${b.customer.phone.replace(/[^\d+]/g, '')}`, icon: Phone },
    { key: 'wa', label: 'WhatsApp', href: wa ? `https://wa.me/${wa}` : '', icon: MessageSquare },
    ...(b.contact.channel === 'line' && b.contact.handle
      ? [{ key: 'line', label: 'LINE', href: `https://line.me/ti/p/~${encodeURIComponent(b.contact.handle)}`, icon: MessageSquare }]
      : []),
    { key: 'mail', label: __('Email', 'suntourz'), href: `mailto:${b.customer.email}`, icon: Mail },
  ].filter((link) => link.href);
};

type PanelProps = {
  id: number;
  config: ShellConfig;
  api: ReturnType<typeof createApi>;
  onClose: () => void;
  onChanged: () => void;
};

const BookingPanel = ({ id, config, api, onClose, onChanged }: PanelProps) => {
  const [booking, setBooking] = useState<Booking | null>(null);
  const [note, setNote] = useState('');
  const [adminNote, setAdminNote] = useState('');
  const [paid, setPaid] = useState<number | null>(0);
  const [method, setMethod] = useState('');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null);

  const adopt = useCallback((next: Booking) => {
    setBooking(next);
    setAdminNote(next.admin_note);
    setPaid(next.amount_paid);
    setMethod(next.payment_method);
  }, []);

  useEffect(() => {
    setBooking(null);
    setMessage(null);
    setNote('');
    api.get<Booking>(`admin/bookings/${id}`).then(adopt).catch((e: unknown) => setMessage({ kind: 'error', text: String(e) }));
  }, [id, api, adopt]);

  const send = async (patch: Record<string, unknown>) => {
    setBusy(true);
    setMessage(null);
    try {
      const next = await api.patch<Booking>(`admin/bookings/${id}`, patch);
      adopt(next);
      setNote('');
      setMessage({ kind: 'ok', text: __('Saved.', 'suntourz') });
      onChanged();
    } catch (error) {
      const fields = error instanceof ApiError ? Object.values(error.fieldErrors).join(' ') : '';
      setMessage({ kind: 'error', text: fields || (error instanceof Error ? error.message : String(error)) });
    } finally {
      setBusy(false);
    }
  };

  if (!booking) {
    return (
      <aside className="stz-panel" aria-live="polite">
        <Button variant="ghost" onClick={onClose}>{__('Close', 'suntourz')}</Button>
        <p>{message?.text ?? __('Loading booking…', 'suntourz')}</p>
      </aside>
    );
  }

  const money = (minor: number) => formatMoney(minor, config.currency);
  const detailsDirty = adminNote !== booking.admin_note || paid !== booking.amount_paid || method !== booking.payment_method;
  const row = (label: string, value: string) => (
    <div className="stz-kv"><dt>{label}</dt><dd>{value || '—'}</dd></div>
  );

  return (
    <aside className="stz-panel" aria-label={sprintf(__('Booking %s', 'suntourz'), booking.code)}>
      <div className="stz-row">
        <h2 className="stz-panel-title">{booking.code}</h2>
        <Chip status={booking.status} />
        <span className="stz-spacer" />
        <Button icon variant="ghost" aria-label={__('Close', 'suntourz')} onClick={onClose}><X size={16} aria-hidden /></Button>
      </div>

      {message && <div className={`stz-notice stz-notice-${message.kind}`} role={message.kind === 'error' ? 'alert' : 'status'}>{message.text}</div>}

      <div className="stz-row" style={{ flexWrap: 'wrap' }}>
        {quickLinks(booking).map(({ key, label, href, icon: Icon }) => (
          <a key={key} className="stz-btn" href={href} target={href.startsWith('http') ? '_blank' : undefined} rel="noopener noreferrer">
            <Icon size={14} aria-hidden /> {label}
          </a>
        ))}
      </div>

      <section className="stz-card">
        <h3 className="stz-section-title">{__('Status', 'suntourz')}</h3>
        {booking.allowed.length === 0 ? (
          <p className="stz-muted">{__('This booking is in a final state.', 'suntourz')}</p>
        ) : (
          <>
            <Field label={__('Note for the history (optional)', 'suntourz')}>
              <TextInput value={note} onChange={setNote} placeholder={__('e.g. Called, customer will pay on Friday', 'suntourz')} />
            </Field>
            <div className="stz-row" style={{ flexWrap: 'wrap', marginTop: 10 }}>
              {booking.allowed.map((to) => (
                <Button
                  key={to}
                  variant={to === 'cancelled' || to === 'no_show' ? 'danger' : 'primary'}
                  disabled={busy}
                  onClick={() => {
                    if ((to === 'cancelled' || to === 'no_show') && !window.confirm(sprintf(__('Mark this booking as "%s"?', 'suntourz'), statusLabel(to)))) return;
                    void send({ status: to, note, ...(to === 'paid' && (paid ?? 0) === 0 ? { amount_paid: booking.total_amount } : {}) });
                  }}
                >
                  {sprintf(__('Mark as %s', 'suntourz'), statusLabel(to))}
                </Button>
              ))}
            </div>
          </>
        )}
      </section>

      <section className="stz-card">
        <h3 className="stz-section-title">{__('Trip', 'suntourz')}</h3>
        <dl className="stz-kvs">
          <div className="stz-kv"><dt>{__('Tour', 'suntourz')}</dt><dd><a href={booking.tour.edit}>{booking.tour.title}</a></dd></div>
          {row(__('Departure', 'suntourz'), `${formatDate(booking.departure.start_date, config.locale)} – ${formatDate(booking.departure.end_date, config.locale)}`)}
          {row(__('Plan', 'suntourz'), booking.plan_label)}
          {row(__('Travellers', 'suntourz'), String(booking.pax))}
          {booking.extras.map((extra) => row(extra.label, `× ${extra.qty} — ${money(extra.amount)}`))}
          {row(__('Total', 'suntourz'), money(booking.total_amount))}
          {row(__('Received', 'suntourz'), `${formatDate(booking.created_at.slice(0, 10), config.locale)} ${booking.created_at.slice(11, 16)} UTC`)}
        </dl>
      </section>

      <section className="stz-card">
        <h3 className="stz-section-title">{__('Guest', 'suntourz')}</h3>
        <dl className="stz-kvs">
          {row(__('Name', 'suntourz'), booking.customer.name)}
          {row(__('Email', 'suntourz'), booking.customer.email)}
          {row(__('Phone', 'suntourz'), booking.customer.phone)}
          {row(__('Prefers', 'suntourz'), `${booking.contact.channel}${booking.contact.handle ? ` — ${booking.contact.handle}` : ''}`)}
          {row(__('Language', 'suntourz'), booking.locale)}
          {row(__('Message', 'suntourz'), booking.message)}
        </dl>
      </section>

      <section className="stz-card">
        <h3 className="stz-section-title">{__('Payment & notes', 'suntourz')}</h3>
        <div className="stz-grid stz-grid-2">
          <Field label={sprintf(__('Amount paid (%s)', 'suntourz'), booking.currency)} hint={sprintf(__('Total: %s', 'suntourz'), money(booking.total_amount))}>
            <MoneyInput value={paid} onChange={setPaid} minorUnit={config.currency.minor_unit} symbol={config.currency.symbol} />
          </Field>
          <Field label={__('Payment method', 'suntourz')}>
            <Select
              value={method}
              onChange={setMethod}
              options={['', 'cash', 'bank_transfer', 'card', 'promptpay', 'other'].map((m) => ({ value: m, label: methodLabel(m) }))}
            />
          </Field>
        </div>
        <div style={{ marginTop: 12 }}>
          <Field label={__('Internal note (never shown to the guest)', 'suntourz')}>
            <Textarea value={adminNote} onChange={setAdminNote} rows={3} />
          </Field>
        </div>
        <div className="stz-row" style={{ marginTop: 12 }}>
          <Button variant="primary" disabled={busy || !detailsDirty} onClick={() => void send({ admin_note: adminNote, amount_paid: paid ?? 0, payment_method: method })}>
            {busy ? __('Saving…', 'suntourz') : __('Save payment & note', 'suntourz')}
          </Button>
          <Button disabled={busy} onClick={() => setPaid(booking.total_amount)}>{__('Fill full amount', 'suntourz')}</Button>
        </div>
      </section>

      <section className="stz-card">
        <h3 className="stz-section-title">{__('History', 'suntourz')}</h3>
        <ol className="stz-history">
          {(booking.history ?? []).map((entry, index) => (
            <li key={index}>
              <strong>{entry.from && entry.from !== entry.to ? `${statusLabel(entry.from)} → ${statusLabel(entry.to)}` : statusLabel(entry.to)}</strong>
              <span className="stz-muted"> · {entry.by} · {entry.at.slice(0, 16)} UTC</span>
              {entry.note && <div>{entry.note}</div>}
            </li>
          ))}
        </ol>
      </section>
    </aside>
  );
};

export const BookingsApp = ({ config }: { config: ShellConfig }) => {
  const api = useMemo(() => createApi(config), [config]);
  const [filters, setFilters] = useState<BookingFilters>({ status: '', search: '', tour_id: '', date_from: '', date_to: '', page: 1 });
  const [list, setList] = useState<BookingList | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [openId, setOpenId] = useState<number | null>(config.openId || null);
  const [tours, setTours] = useState<{ id: number; title: string }[]>([]);
  const [search, setSearch] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    setError('');
    api
      .get<BookingList>(`admin/bookings?${toQueryString({ ...filters, per_page: 20 })}`)
      .then(setList)
      .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
      .finally(() => setLoading(false));
  }, [api, filters]);

  useEffect(load, [load]);

  useEffect(() => {
    fetch(`${config.restUrl}stz/v1/tours?per_page=50`)
      .then((r) => r.json() as Promise<{ items: { id: number; title: string }[] }>)
      .then((r) => setTours(r.items.map(({ id, title }) => ({ id, title }))))
      .catch(() => undefined);
  }, [config.restUrl]);

  // Debounced search box.
  useEffect(() => {
    const timer = window.setTimeout(() => setFilters((f) => (f.search === search ? f : { ...f, search, page: 1 })), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  const set = (patch: Partial<BookingFilters>) => setFilters((f) => ({ ...f, page: 1, ...patch }));
  const total = list ? Object.values(list.counts).reduce((sum, n) => sum + (n ?? 0), 0) : 0;
  const exportHref = `${config.exportUrl}?${toQueryString({ ...filters, page: undefined, _wpnonce: config.nonce })}`;
  const money = (minor: number) => formatMoney(minor, config.currency);

  return (
    <div className={`stz-bookings${openId ? ' has-panel' : ''}`}>
      <div className="stz-bookings-main">
        <div className="stz-row" style={{ marginBottom: 12 }}>
          <h1 style={{ margin: 0 }}>{__('Bookings', 'suntourz')}</h1>
          <span className="stz-spacer" />
          <Button onClick={load} disabled={loading}><RefreshCw size={14} aria-hidden /> {__('Refresh', 'suntourz')}</Button>
          <a className="stz-btn" href={exportHref}><Download size={14} aria-hidden /> {__('Export CSV', 'suntourz')}</a>
        </div>

        <div className="stz-tabs" role="tablist" aria-label={__('Filter by status', 'suntourz')}>
          {[{ id: '', n: total }, ...STATUSES.map((s) => ({ id: s, n: list?.counts[s] ?? 0 }))].map(({ id, n }) => (
            <button key={id || 'all'} type="button" role="tab" aria-selected={filters.status === id} className="stz-tab" onClick={() => set({ status: id })}>
              {id ? statusLabel(id) : __('All', 'suntourz')} <span className="stz-muted">({n})</span>
            </button>
          ))}
        </div>

        <div className="stz-grid stz-grid-3" style={{ marginBottom: 12 }}>
          <Field label={__('Search', 'suntourz')}>
            <TextInput type="search" value={search} onChange={setSearch} placeholder={__('Code, name, phone or email', 'suntourz')} />
          </Field>
          <Field label={__('Tour', 'suntourz')}>
            <Select value={filters.tour_id} onChange={(tour_id) => set({ tour_id })} options={[{ value: '', label: __('All tours', 'suntourz') }, ...tours.map((t) => ({ value: String(t.id), label: t.title }))]} />
          </Field>
          <Field label={__('From', 'suntourz')}><TextInput type="date" value={filters.date_from} onChange={(date_from) => set({ date_from })} /></Field>
          <Field label={__('To', 'suntourz')}><TextInput type="date" value={filters.date_to} onChange={(date_to) => set({ date_to })} /></Field>
        </div>

        {error && <div className="stz-notice stz-notice-error" role="alert">{error}</div>}

        <div className="stz-table-wrap">
          <table className="stz-table stz-table-click">
            <thead>
              <tr>
                <th>{__('Code', 'suntourz')}</th>
                <th>{__('Guest', 'suntourz')}</th>
                <th>{__('Tour & departure', 'suntourz')}</th>
                <th>{__('Pax', 'suntourz')}</th>
                <th>{__('Total / paid', 'suntourz')}</th>
                <th>{__('Status', 'suntourz')}</th>
                <th>{__('Received', 'suntourz')}</th>
              </tr>
            </thead>
            <tbody>
              {list?.items.map((b) => (
                <tr key={b.id} className={b.id === openId ? 'is-selected' : ''} onClick={() => setOpenId(b.id)}>
                  <td>
                    <button type="button" className="stz-link" onClick={() => setOpenId(b.id)}><strong>{b.code}</strong></button>
                  </td>
                  <td>{b.customer.name}<br /><span className="stz-muted">{b.customer.phone}</span></td>
                  <td>{b.tour.title}<br /><span className="stz-muted">{formatDate(b.departure.start_date, config.locale)} · {b.plan_label}</span></td>
                  <td>{b.pax}</td>
                  <td>{money(b.total_amount)}<br /><span className="stz-muted">{money(b.amount_paid)}</span></td>
                  <td><Chip status={b.status} /></td>
                  <td className="stz-muted">{b.created_at.slice(0, 10)}</td>
                </tr>
              ))}
              {list && list.items.length === 0 && (
                <tr><td colSpan={7} className="stz-muted">{__('No bookings match these filters.', 'suntourz')}</td></tr>
              )}
              {!list && <tr><td colSpan={7} className="stz-muted">{__('Loading…', 'suntourz')}</td></tr>}
            </tbody>
          </table>
        </div>

        {list && list.total_pages > 1 && (
          <div className="stz-row" style={{ marginTop: 12, justifyContent: 'center' }}>
            <Button disabled={filters.page <= 1} onClick={() => setFilters((f) => ({ ...f, page: f.page - 1 }))}>{__('Previous', 'suntourz')}</Button>
            <span>{sprintf(__('Page %1$d of %2$d (%3$d bookings)', 'suntourz'), list.page, list.total_pages, list.total)}</span>
            <Button disabled={filters.page >= list.total_pages} onClick={() => setFilters((f) => ({ ...f, page: f.page + 1 }))}>{__('Next', 'suntourz')}</Button>
          </div>
        )}
      </div>

      {openId && <BookingPanel id={openId} config={config} api={api} onClose={() => setOpenId(null)} onChanged={load} />}
    </div>
  );
};

export type { BookingStatus };
