import { ImagePlus, Trash2 } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Field } from '../../ui/Field';
import { Button, NumberInput, TextInput } from '../../ui/controls';
import { SortableList } from '../../ui/Sortable';
import { StringList } from '../../ui/StringList';
import type { GalleryImage } from '../types';
import type { TabProps } from './types';

/** Opens the WordPress media library and resolves with the chosen images. */
const pickImages = (): Promise<GalleryImage[]> =>
  new Promise((resolve) => {
    const media = window.wp?.media;
    if (!media) return resolve([]);

    const frame = media({
      title: __('Choose gallery images', 'suntourz'),
      button: { text: __('Add to gallery', 'suntourz') },
      library: { type: 'image' },
      multiple: true,
    });

    frame.on('select', () => {
      const selected = frame.state().get('selection').toJSON();
      resolve(selected.map((item) => ({ id: item.id, url: item.sizes?.medium?.url ?? item.url })));
    });
    frame.open();
  });

export const DetailsTab = ({ draft, update, errors }: TabProps) => {
  const { details } = draft;

  const set = (patch: Partial<typeof details>) => update((d) => ({ ...d, details: { ...d.details, ...patch } }));
  const setPoint = (patch: Partial<typeof details.meeting_point>) =>
    set({ meeting_point: { ...details.meeting_point, ...patch } });

  const addImages = async () => {
    const picked = await pickImages();
    if (picked.length === 0) return;
    const known = new Set(details.gallery.map((image) => image.id));
    set({ gallery: [...details.gallery, ...picked.filter((image) => !known.has(image.id))] });
  };

  return (
    <div className="stz-stack">
      <div className="stz-grid stz-grid-3">
        <Field label={__('Duration (days)', 'suntourz')} error={errors['details.duration_days']}>
          <NumberInput
            min={1}
            max={365}
            value={details.duration_days}
            onChange={(value) => set({ duration_days: value ?? 1 })}
          />
        </Field>
      </div>

      <div className="stz-grid stz-grid-2">
        <StringList
          label={__('Highlights', 'suntourz')}
          items={details.highlights}
          onChange={(highlights) => set({ highlights })}
          placeholder={__('e.g. Sunrise at the Grand Palace', 'suntourz')}
          addLabel={__('Add highlight', 'suntourz')}
        />
      </div>

      <div className="stz-grid stz-grid-2">
        <StringList
          label={__('Included', 'suntourz')}
          items={details.includes}
          onChange={(includes) => set({ includes })}
          placeholder={__('e.g. Hotel pickup', 'suntourz')}
          addLabel={__('Add included item', 'suntourz')}
        />
        <StringList
          label={__('Not included', 'suntourz')}
          items={details.excludes}
          onChange={(excludes) => set({ excludes })}
          placeholder={__('e.g. International flights', 'suntourz')}
          addLabel={__('Add excluded item', 'suntourz')}
        />
      </div>

      <section className="stz-card">
        <h3 className="stz-section-title">{__('Meeting point', 'suntourz')}</h3>
        <div className="stz-grid stz-grid-2">
          <Field label={__('Name', 'suntourz')}>
            <TextInput value={details.meeting_point.name} onChange={(name) => setPoint({ name })} />
          </Field>
          <Field label={__('Address', 'suntourz')}>
            <TextInput value={details.meeting_point.address} onChange={(address) => setPoint({ address })} />
          </Field>
          <Field label={__('Latitude (optional)', 'suntourz')}>
            <NumberInput
              step="any"
              min={-90}
              max={90}
              value={details.meeting_point.lat}
              onChange={(lat) => setPoint({ lat })}
            />
          </Field>
          <Field label={__('Longitude (optional)', 'suntourz')}>
            <NumberInput
              step="any"
              min={-180}
              max={180}
              value={details.meeting_point.lng}
              onChange={(lng) => setPoint({ lng })}
            />
          </Field>
        </div>
      </section>

      <section>
        <h3 className="stz-section-title">{__('Gallery', 'suntourz')}</h3>
        <p className="stz-hint" style={{ marginBottom: 8 }}>
          {__('The featured image (right sidebar) is the main photo. Drag to reorder the gallery.', 'suntourz')}
        </p>
        <div className="stz-gallery">
          <SortableList
            layout="grid"
            items={details.gallery.map((image) => ({ ...image, _key: String(image.id) }))}
            onChange={(items) => set({ gallery: items.map(({ id, url }) => ({ id, url })) })}
            renderItem={(image, _index, handle) => (
              <div className="stz-thumb" style={{ width: '100%' }}>
                <img src={image.url} alt="" loading="lazy" />
                <span className="stz-thumb-drag">{handle}</span>
                <span className="stz-thumb-actions">
                  <Button
                    icon
                    variant="danger"
                    aria-label={__('Remove image', 'suntourz')}
                    onClick={() => set({ gallery: details.gallery.filter((item) => item.id !== image.id) })}
                  >
                    <Trash2 size={14} aria-hidden />
                  </Button>
                </span>
              </div>
            )}
          />
        </div>
        <div style={{ marginTop: 10 }}>
          <Button onClick={addImages}>
            <ImagePlus size={14} aria-hidden /> {__('Add images', 'suntourz')}
          </Button>
        </div>
      </section>
    </div>
  );
};
