import { Plus, Trash2, X } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Field } from '../../ui/Field';
import { Button, Select, Textarea, TextInput } from '../../ui/controls';
import { SortableList } from '../../ui/Sortable';
import { newItineraryDay, newItineraryItem } from '../draft';
import type { ItineraryDayRow, ItineraryItemRow, ItineraryItemType } from '../types';
import type { TabProps } from './types';

const itemTypes = (): { value: ItineraryItemType; label: string }[] => [
  { value: 'activity', label: __('Activity', 'suntourz') },
  { value: 'transfer', label: __('Transfer', 'suntourz') },
  { value: 'meal', label: __('Meal', 'suntourz') },
  { value: 'stay', label: __('Stay', 'suntourz') },
  { value: 'rest', label: __('Rest', 'suntourz') },
  { value: 'free', label: __('Free time', 'suntourz') },
];

export const ItineraryTab = ({ draft, update }: TabProps) => {
  const days = draft.details.itinerary;

  const setDays = (next: ItineraryDayRow[]) =>
    update((d) => ({
      ...d,
      details: { ...d.details, itinerary: next.map((day, index) => ({ ...day, day: index + 1 })) },
    }));

  const patchDay = (key: string, patch: Partial<ItineraryDayRow>) =>
    setDays(days.map((day) => (day._key === key ? { ...day, ...patch } : day)));

  const patchItem = (dayKey: string, itemKey: string, patch: Partial<ItineraryItemRow>) =>
    setDays(
      days.map((day) =>
        day._key === dayKey
          ? { ...day, items: day.items.map((item) => (item._key === itemKey ? { ...item, ...patch } : item)) }
          : day,
      ),
    );

  return (
    <div>
      {days.length === 0 && (
        <p className="stz-muted">{__('No days yet. Add the first day of the itinerary.', 'suntourz')}</p>
      )}

      <SortableList
        items={days}
        onChange={setDays}
        renderItem={(day, index, handle) => (
          <section className="stz-card stz-day" style={{ flex: 1 }}>
            <div className="stz-day-head">
              {handle}
              <span className="stz-day-badge">{__('Day', 'suntourz')} {index + 1}</span>
              <TextInput
                value={day.title}
                placeholder={__('Day title, e.g. Arrival in Bangkok', 'suntourz')}
                aria-label={__('Day title', 'suntourz')}
                onChange={(title) => patchDay(day._key, { title })}
              />
              <Button
                icon
                variant="danger"
                aria-label={__('Remove day', 'suntourz')}
                onClick={() => setDays(days.filter((d) => d._key !== day._key))}
              >
                <Trash2 size={15} aria-hidden />
              </Button>
            </div>

            <Field label={__('Description', 'suntourz')}>
              <Textarea
                rows={3}
                value={day.description}
                onChange={(description) => patchDay(day._key, { description })}
              />
            </Field>

            <p className="stz-label" style={{ marginTop: 12 }}>
              {__('Timeline', 'suntourz')}
            </p>
            <SortableList
              items={day.items}
              onChange={(items) => patchDay(day._key, { items })}
              renderItem={(item, _i, itemHandle) => (
                <>
                  {itemHandle}
                  <div className="stz-activity" style={{ marginBottom: 6 }}>
                    <TextInput
                      value={item.time}
                      placeholder="09:00"
                      aria-label={__('Time', 'suntourz')}
                      onChange={(time) => patchItem(day._key, item._key, { time })}
                    />
                    <Select
                      aria-label={__('Type', 'suntourz')}
                      value={item.type}
                      options={itemTypes()}
                      onChange={(type) => patchItem(day._key, item._key, { type })}
                    />
                    <TextInput
                      value={item.title}
                      placeholder={__('What happens', 'suntourz')}
                      aria-label={__('Activity', 'suntourz')}
                      onChange={(title) => patchItem(day._key, item._key, { title })}
                    />
                    <Button
                      icon
                      variant="ghost"
                      aria-label={__('Remove item', 'suntourz')}
                      onClick={() => patchDay(day._key, { items: day.items.filter((x) => x._key !== item._key) })}
                    >
                      <X size={16} aria-hidden />
                    </Button>
                  </div>
                </>
              )}
            />
            <Button onClick={() => patchDay(day._key, { items: [...day.items, newItineraryItem()] })}>
              <Plus size={14} aria-hidden /> {__('Add timeline item', 'suntourz')}
            </Button>
          </section>
        )}
      />

      <Button variant="primary" onClick={() => setDays([...days, newItineraryDay(days.length + 1)])}>
        <Plus size={14} aria-hidden /> {__('Add day', 'suntourz')}
      </Button>
    </div>
  );
};
