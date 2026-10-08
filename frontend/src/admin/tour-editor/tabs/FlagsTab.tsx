import { __ } from '@wordpress/i18n';
import { Field } from '../../ui/Field';
import { Checkbox, NumberInput } from '../../ui/controls';
import type { TabProps } from './types';

export const FlagsTab = ({ draft, update }: TabProps) => {
  const { flags } = draft;
  const set = (patch: Partial<typeof flags>) => update((d) => ({ ...d, flags: { ...d.flags, ...patch } }));

  return (
    <div className="stz-stack" style={{ maxWidth: 520 }}>
      <Checkbox
        checked={flags.featured}
        onChange={(featured) => set({ featured })}
        label={__('Featured', 'suntourz')}
        hint={__('Shown first in "Recommended" order and in the featured tours of the home page.', 'suntourz')}
      />
      <Checkbox
        checked={flags.special_offer}
        onChange={(special_offer) => set({ special_offer })}
        label={__('Special offer', 'suntourz')}
        hint={__('Adds a "Special offer" badge to the tour.', 'suntourz')}
      />
      <Field
        label={__('Sort order', 'suntourz')}
        hint={__('Lower numbers come first within "Recommended" order.', 'suntourz')}
      >
        <NumberInput value={flags.sort_order} onChange={(sort_order) => set({ sort_order: sort_order ?? 0 })} />
      </Field>
    </div>
  );
};
