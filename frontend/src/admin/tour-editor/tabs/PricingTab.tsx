import { Plus, Trash2 } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Field } from '../../ui/Field';
import { Button, MoneyInput, NumberInput, Select, TextInput } from '../../ui/controls';
import { defaultPlans, newExtra, newPlan } from '../draft';
import type { ExtraRow, PlanRow } from '../types';
import type { TabProps } from './types';

export const PricingTab = ({ draft, update, errors, config }: TabProps) => {
  const { plans, currency } = draft.pricing;
  const { extras } = draft;
  const { minor_unit: minorUnit, symbol } = config.currency;

  const setPlans = (next: PlanRow[]) => update((d) => ({ ...d, pricing: { ...d.pricing, plans: next } }));
  const patchPlan = (key: string, patch: Partial<PlanRow>) =>
    setPlans(plans.map((plan) => (plan._key === key ? { ...plan, ...patch } : plan)));

  const setExtras = (next: ExtraRow[]) => update((d) => ({ ...d, extras: next }));
  const patchExtra = (key: string, patch: Partial<ExtraRow>) =>
    setExtras(extras.map((extra) => (extra._key === key ? { ...extra, ...patch } : extra)));

  const money = (value: number | null, onChange: (v: number | null) => void, placeholder?: string) => (
    <MoneyInput value={value} onChange={onChange} minorUnit={minorUnit} symbol={symbol} placeholder={placeholder} />
  );

  return (
    <div className="stz-stack">
      <div className="stz-grid stz-grid-3">
        <Field
          label={__('Currency code', 'suntourz')}
          hint={__('Shown with the prices of this tour (e.g. THB). Symbol and format: Suntourz → Settings.', 'suntourz')}
        >
          <TextInput
            value={currency}
            maxLength={8}
            onChange={(value) => update((d) => ({ ...d, pricing: { ...d.pricing, currency: value.toUpperCase() } }))}
          />
        </Field>
      </div>

      <section>
        <h3 className="stz-section-title">{__('Pricing plans', 'suntourz')}</h3>
        <p className="stz-hint" style={{ marginBottom: 8 }}>
          {__(
            'Each plan is the price for one group size. Give a plan an "extra person" price to let guests add travellers up to the maximum (e.g. Family+).',
            'suntourz',
          )}
        </p>
        <div className="stz-table-wrap">
          <table className="stz-table">
            <thead>
              <tr>
                <th>{__('Plan name', 'suntourz')}</th>
                <th>{__('Travellers', 'suntourz')}</th>
                <th>{__('Price', 'suntourz')}</th>
                <th>{__('Sale price', 'suntourz')}</th>
                <th>{__('Extra person', 'suntourz')}</th>
                <th>{__('Max travellers', 'suntourz')}</th>
                <th aria-label={__('Actions', 'suntourz')} />
              </tr>
            </thead>
            <tbody>
              {plans.map((plan, index) => {
                const key = `pricing.plans.${index}`;
                const fieldError = (field: string) => errors[`${key}.${field}`];
                return (
                  <tr key={plan._key}>
                    <td>
                      <TextInput
                        value={plan.label}
                        aria-label={__('Plan name', 'suntourz')}
                        aria-invalid={!!fieldError('label')}
                        onChange={(label) => patchPlan(plan._key, { label })}
                      />
                      {fieldError('label') && <p className="stz-error">{fieldError('label')}</p>}
                      {plan.id && <p className="stz-hint">ID: {plan.id}</p>}
                    </td>
                    <td style={{ width: 90 }}>
                      <NumberInput
                        min={1}
                        value={plan.pax}
                        aria-label={__('Travellers', 'suntourz')}
                        aria-invalid={!!fieldError('pax')}
                        onChange={(pax) => patchPlan(plan._key, { pax: pax ?? 1 })}
                      />
                      {fieldError('pax') && <p className="stz-error">{fieldError('pax')}</p>}
                    </td>
                    <td>
                      {money(plan.price, (price) => patchPlan(plan._key, { price: price ?? 0 }))}
                      {fieldError('price') && <p className="stz-error">{fieldError('price')}</p>}
                    </td>
                    <td>
                      {money(plan.sale_price, (sale_price) => patchPlan(plan._key, { sale_price }), '—')}
                      {fieldError('sale_price') && <p className="stz-error">{fieldError('sale_price')}</p>}
                    </td>
                    <td>
                      {money(
                        plan.extra_person_price,
                        (extra_person_price) =>
                          patchPlan(plan._key, {
                            extra_person_price,
                            max_pax: extra_person_price ? (plan.max_pax ?? plan.pax) : null,
                          }),
                        '—',
                      )}
                    </td>
                    <td style={{ width: 100 }}>
                      <NumberInput
                        min={plan.pax}
                        disabled={!plan.extra_person_price}
                        value={plan.max_pax}
                        aria-label={__('Max travellers', 'suntourz')}
                        aria-invalid={!!fieldError('max_pax')}
                        onChange={(max_pax) => patchPlan(plan._key, { max_pax })}
                      />
                      {fieldError('max_pax') && <p className="stz-error">{fieldError('max_pax')}</p>}
                    </td>
                    <td>
                      <Button
                        icon
                        variant="danger"
                        aria-label={__('Remove plan', 'suntourz')}
                        onClick={() => setPlans(plans.filter((p) => p._key !== plan._key))}
                      >
                        <Trash2 size={15} aria-hidden />
                      </Button>
                    </td>
                  </tr>
                );
              })}
              {plans.length === 0 && (
                <tr>
                  <td colSpan={7} className="stz-muted">
                    {__('No plans yet — guests cannot book this tour until you add one.', 'suntourz')}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="stz-row" style={{ marginTop: 10 }}>
          <Button variant="primary" onClick={() => setPlans([...plans, newPlan({ pax: 1 })])}>
            <Plus size={14} aria-hidden /> {__('Add plan', 'suntourz')}
          </Button>
          <Button onClick={() => setPlans([...plans, ...defaultPlans()])}>
            {__('Add default plans (Single, Couple, Family, Family+)', 'suntourz')}
          </Button>
        </div>
      </section>

      <section>
        <h3 className="stz-section-title">{__('Optional extras', 'suntourz')}</h3>
        <p className="stz-hint" style={{ marginBottom: 8 }}>
          {__('"Per person" extras can be taken by up to the number of travellers; "per booking" extras are charged per unit.', 'suntourz')}
        </p>
        <div className="stz-table-wrap">
          <table className="stz-table" style={{ minWidth: 600 }}>
            <thead>
              <tr>
                <th>{__('Extra', 'suntourz')}</th>
                <th>{__('Price', 'suntourz')}</th>
                <th>{__('Charged', 'suntourz')}</th>
                <th>{__('Max quantity', 'suntourz')}</th>
                <th aria-label={__('Actions', 'suntourz')} />
              </tr>
            </thead>
            <tbody>
              {extras.map((extra, index) => {
                const key = `extras.${index}`;
                return (
                  <tr key={extra._key}>
                    <td>
                      <TextInput
                        value={extra.label}
                        placeholder={__('e.g. Airport transfer', 'suntourz')}
                        aria-label={__('Extra', 'suntourz')}
                        aria-invalid={!!errors[`${key}.label`]}
                        onChange={(label) => patchExtra(extra._key, { label })}
                      />
                      {errors[`${key}.label`] && <p className="stz-error">{errors[`${key}.label`]}</p>}
                    </td>
                    <td>{money(extra.price, (price) => patchExtra(extra._key, { price: price ?? 0 }))}</td>
                    <td>
                      <Select
                        aria-label={__('Charged', 'suntourz')}
                        value={extra.unit}
                        options={[
                          { value: 'per_booking', label: __('Per booking', 'suntourz') },
                          { value: 'per_person', label: __('Per person', 'suntourz') },
                        ]}
                        onChange={(unit) => patchExtra(extra._key, { unit })}
                      />
                    </td>
                    <td style={{ width: 110 }}>
                      <NumberInput
                        min={1}
                        placeholder="—"
                        aria-label={__('Max quantity', 'suntourz')}
                        value={extra.max_qty}
                        onChange={(max_qty) => patchExtra(extra._key, { max_qty })}
                      />
                    </td>
                    <td>
                      <Button
                        icon
                        variant="danger"
                        aria-label={__('Remove extra', 'suntourz')}
                        onClick={() => setExtras(extras.filter((x) => x._key !== extra._key))}
                      >
                        <Trash2 size={15} aria-hidden />
                      </Button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <div style={{ marginTop: 10 }}>
          <Button onClick={() => setExtras([...extras, newExtra()])}>
            <Plus size={14} aria-hidden /> {__('Add extra', 'suntourz')}
          </Button>
        </div>
      </section>
    </div>
  );
};
