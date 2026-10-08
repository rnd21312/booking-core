import { useState } from 'react';
import { CalendarPlus, ChevronDown, ChevronUp, Copy, Plus, Trash2 } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';
import { formatMoney } from '@/lib/format';
import { addDays, daysBetween } from '@/lib/dates';
import { Field } from '../../ui/Field';
import { Button, MoneyInput, NumberInput, Select, TextInput } from '../../ui/controls';
import { buildSeries, duplicateDeparture, newDeparture } from '../draft';
import type { DepartureRow, DepartureStatus, PriceOverride } from '../types';
import type { TabProps } from './types';

const statusOptions = (): { value: DepartureStatus; label: string }[] => [
  { value: 'draft', label: __('Draft (hidden)', 'suntourz') },
  { value: 'open', label: __('Open for booking', 'suntourz') },
  { value: 'closed', label: __('Closed', 'suntourz') },
  { value: 'cancelled', label: __('Cancelled', 'suntourz') },
];

export const DeparturesTab = ({ draft, update, errors, config }: TabProps) => {
  const { departures } = draft;
  const plans = draft.pricing.plans.filter((plan) => plan.id !== '');
  const { minor_unit: minorUnit, symbol } = config.currency;

  const [expanded, setExpanded] = useState<Set<string>>(new Set());
  const [seriesOpen, setSeriesOpen] = useState(false);
  const [series, setSeries] = useState({
    firstStart: addDays(config.today, 14),
    durationDays: draft.details.duration_days,
    everyDays: 7,
    count: 8,
    capacity: 12,
    status: 'open' as DepartureStatus,
  });

  const setDepartures = (next: DepartureRow[]) => update((d) => ({ ...d, departures: next }));
  const patch = (key: string, change: Partial<DepartureRow>) =>
    setDepartures(departures.map((row) => (row._key === key ? { ...row, ...change } : row)));

  const patchOverride = (row: DepartureRow, planId: string, change: PriceOverride) => {
    const merged: PriceOverride = { ...row.price_overrides[planId], ...change };
    if (merged.price == null) delete merged.price;
    if (merged.sale_price == null) delete merged.sale_price;

    const next = { ...row.price_overrides };
    if (Object.keys(merged).length === 0) delete next[planId];
    else next[planId] = merged;
    patch(row._key, { price_overrides: next });
  };

  const toggle = (key: string) =>
    setExpanded((current) => {
      const next = new Set(current);
      if (!next.delete(key)) next.add(key);
      return next;
    });

  const sortByDate = () =>
    setDepartures([...departures].sort((a, b) => a.start_date.localeCompare(b.start_date)));

  const overrideCount = (row: DepartureRow) => Object.keys(row.price_overrides).length;

  return (
    <div className="stz-stack">
      <p className="stz-hint">
        {__('Only departures set to "Open for booking" can be booked. Dates are in the site timezone.', 'suntourz')}
      </p>

      <div className="stz-table-wrap">
        <table className="stz-table">
          <thead>
            <tr>
              <th>{__('Start', 'suntourz')}</th>
              <th>{__('End', 'suntourz')}</th>
              <th>{__('Capacity', 'suntourz')}</th>
              <th>{__('Status', 'suntourz')}</th>
              <th>{__('Booked / left', 'suntourz')}</th>
              <th>{__('Note', 'suntourz')}</th>
              <th aria-label={__('Actions', 'suntourz')} />
            </tr>
          </thead>
          <tbody>
            {departures.length === 0 && (
              <tr>
                <td colSpan={7} className="stz-muted">
                  {__('No departures yet. Add one, or create a recurring series.', 'suntourz')}
                </td>
              </tr>
            )}
            {departures.flatMap((row, index) => {
              const key = `departures.${index}`;
              const isOpen = expanded.has(row._key);
              const past = row.start_date !== '' && row.start_date <= config.today;
              const rows = [
                <tr key={row._key}>
                  <td style={{ width: 150 }}>
                    <TextInput
                      type="date"
                      value={row.start_date}
                      aria-label={__('Start date', 'suntourz')}
                      aria-invalid={!!errors[`${key}.start_date`]}
                      onChange={(start_date) =>
                        patch(row._key, {
                          start_date,
                          // Keep the trip length when the start moves and the end was in sync.
                          end_date:
                            row.end_date && row.start_date
                              ? addDays(start_date, daysOf(row.start_date, row.end_date))
                              : row.end_date,
                        })
                      }
                    />
                    {errors[`${key}.start_date`] && <p className="stz-error">{errors[`${key}.start_date`]}</p>}
                  </td>
                  <td style={{ width: 150 }}>
                    <TextInput
                      type="date"
                      value={row.end_date}
                      min={row.start_date || undefined}
                      aria-label={__('End date', 'suntourz')}
                      aria-invalid={!!errors[`${key}.end_date`]}
                      onChange={(end_date) => patch(row._key, { end_date })}
                    />
                    {errors[`${key}.end_date`] && <p className="stz-error">{errors[`${key}.end_date`]}</p>}
                  </td>
                  <td style={{ width: 100 }}>
                    <NumberInput
                      min={row.booked_pax}
                      max={1000}
                      value={row.capacity}
                      aria-label={__('Capacity', 'suntourz')}
                      aria-invalid={!!errors[`${key}.capacity`]}
                      onChange={(capacity) => patch(row._key, { capacity: capacity ?? 0 })}
                    />
                    {errors[`${key}.capacity`] && <p className="stz-error">{errors[`${key}.capacity`]}</p>}
                  </td>
                  <td style={{ width: 170 }}>
                    <Select
                      aria-label={__('Status', 'suntourz')}
                      value={row.status}
                      options={statusOptions()}
                      onChange={(status) => patch(row._key, { status })}
                    />
                    {past && <span className="stz-chip" style={{ marginTop: 4 }}>{__('Started', 'suntourz')}</span>}
                  </td>
                  <td style={{ whiteSpace: 'nowrap' }}>
                    {row.id === null ? (
                      <span className="stz-muted">—</span>
                    ) : (
                      <>
                        <strong>{row.booked_pax}</strong> / {row.capacity}
                        <span className="stz-muted">
                          {' '}
                          · {sprintf(__('%d left', 'suntourz'), Math.max(0, row.capacity - row.booked_pax))}
                        </span>
                      </>
                    )}
                  </td>
                  <td>
                    <TextInput
                      value={row.note}
                      aria-label={__('Note', 'suntourz')}
                      placeholder={__('Internal note', 'suntourz')}
                      onChange={(note) => patch(row._key, { note })}
                    />
                  </td>
                  <td>
                    <div className="stz-row" style={{ justifyContent: 'flex-end' }}>
                      <Button
                        aria-expanded={isOpen}
                        disabled={plans.length === 0}
                        title={plans.length === 0 ? __('Save the tour with pricing plans first', 'suntourz') : undefined}
                        onClick={() => toggle(row._key)}
                      >
                        {__('Prices', 'suntourz')}
                        {overrideCount(row) > 0 ? ` (${overrideCount(row)})` : ''}
                        {isOpen ? <ChevronUp size={14} aria-hidden /> : <ChevronDown size={14} aria-hidden />}
                      </Button>
                      <Button
                        icon
                        aria-label={__('Duplicate (one week later)', 'suntourz')}
                        title={__('Duplicate (one week later)', 'suntourz')}
                        onClick={() => setDepartures([...departures, duplicateDeparture(row)])}
                      >
                        <Copy size={15} aria-hidden />
                      </Button>
                      <Button
                        icon
                        variant="danger"
                        aria-label={__('Delete departure', 'suntourz')}
                        disabled={row.bookings_count > 0}
                        title={
                          row.bookings_count > 0
                            ? __('This departure has bookings — set it to Cancelled instead.', 'suntourz')
                            : undefined
                        }
                        onClick={() => setDepartures(departures.filter((d) => d._key !== row._key))}
                      >
                        <Trash2 size={15} aria-hidden />
                      </Button>
                    </div>
                  </td>
                </tr>,
              ];

              if (isOpen) {
                rows.push(
                  <tr key={`${row._key}-prices`} className="is-sub">
                    <td colSpan={7}>
                      <p className="stz-hint" style={{ marginBottom: 8 }}>
                        {__('Override plan prices for this departure only. Leave empty to use the tour price.', 'suntourz')}
                      </p>
                      <div className="stz-grid stz-grid-3">
                        {plans.map((plan) => {
                          const override = row.price_overrides[plan.id] ?? {};
                          return (
                            <div key={plan.id} className="stz-card">
                              <p className="stz-label">
                                {plan.label}{' '}
                                <span className="stz-muted">
                                  ({formatMoney(plan.sale_price ?? plan.price, config.currency)})
                                </span>
                              </p>
                              <Field label={__('Price', 'suntourz')}>
                                <MoneyInput
                                  value={override.price ?? null}
                                  minorUnit={minorUnit}
                                  symbol={symbol}
                                  placeholder={String(plan.price / minorUnit)}
                                  onChange={(price) => patchOverride(row, plan.id, { price: price ?? undefined })}
                                />
                              </Field>
                              <Field label={__('Sale price', 'suntourz')}>
                                <MoneyInput
                                  value={override.sale_price ?? null}
                                  minorUnit={minorUnit}
                                  symbol={symbol}
                                  placeholder="—"
                                  onChange={(sale) => patchOverride(row, plan.id, { sale_price: sale ?? undefined })}
                                />
                              </Field>
                            </div>
                          );
                        })}
                      </div>
                    </td>
                  </tr>,
                );
              }

              return rows;
            })}
          </tbody>
        </table>
      </div>

      <div className="stz-row">
        <Button
          variant="primary"
          onClick={() => setDepartures([...departures, newDeparture({ start_date: addDays(config.today, 14), end_date: addDays(config.today, 14 + draft.details.duration_days - 1) })])}
        >
          <Plus size={14} aria-hidden /> {__('Add departure', 'suntourz')}
        </Button>
        <Button aria-expanded={seriesOpen} onClick={() => setSeriesOpen(!seriesOpen)}>
          <CalendarPlus size={14} aria-hidden /> {__('Create recurring series', 'suntourz')}
        </Button>
        <span className="stz-spacer" />
        <Button variant="ghost" onClick={sortByDate}>
          {__('Sort by date', 'suntourz')}
        </Button>
      </div>

      {seriesOpen && (
        <section className="stz-card">
          <h3 className="stz-section-title">{__('Recurring series', 'suntourz')}</h3>
          <div className="stz-grid stz-grid-3">
            <Field label={__('First start date', 'suntourz')}>
              <TextInput type="date" value={series.firstStart} onChange={(firstStart) => setSeries({ ...series, firstStart })} />
            </Field>
            <Field label={__('Trip length (days)', 'suntourz')}>
              <NumberInput min={1} value={series.durationDays} onChange={(v) => setSeries({ ...series, durationDays: v ?? 1 })} />
            </Field>
            <Field label={__('Repeat every (days)', 'suntourz')} hint={__('7 = weekly', 'suntourz')}>
              <NumberInput min={1} value={series.everyDays} onChange={(v) => setSeries({ ...series, everyDays: v ?? 7 })} />
            </Field>
            <Field label={__('Number of departures', 'suntourz')}>
              <NumberInput min={1} max={104} value={series.count} onChange={(v) => setSeries({ ...series, count: v ?? 1 })} />
            </Field>
            <Field label={__('Capacity each', 'suntourz')}>
              <NumberInput min={0} value={series.capacity} onChange={(v) => setSeries({ ...series, capacity: v ?? 0 })} />
            </Field>
            <Field label={__('Status', 'suntourz')}>
              <Select value={series.status} options={statusOptions()} onChange={(status) => setSeries({ ...series, status })} />
            </Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Button
              variant="primary"
              disabled={!series.firstStart}
              onClick={() => {
                setDepartures([...departures, ...buildSeries(series)]);
                setSeriesOpen(false);
              }}
            >
              {sprintf(__('Add %d departures', 'suntourz'), series.count)}
            </Button>
          </div>
        </section>
      )}
    </div>
  );
};

/** Trip length in days between two Y-m-d strings (0 when invalid). */
const daysOf = (start: string, end: string): number => {
  const days = daysBetween(start, end);
  return Number.isNaN(days) ? 0 : days;
};
