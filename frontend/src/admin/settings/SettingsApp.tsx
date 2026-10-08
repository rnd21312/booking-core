import { useEffect, useMemo, useState } from 'react';
import { ImagePlus, X } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { ApiError, createApi, type ShellConfig } from '../lib/api';
import { Button, Checkbox, NumberInput, Select, TextInput, Textarea } from '../ui/controls';
import { Field } from '../ui/Field';
import { Tabs, TabPanel, type TabDef } from '../ui/Tabs';
import { mergeContent, pickImage, read, write, type Json } from './content';

type Settings = {
  site_name: string;
  site_description: string;
  company_name: string;
  phone: string;
  whatsapp: string;
  line_id: string;
  telegram: string;
  support_email: string;
  instagram_url: string;
  facebook_url: string;
  currency_code: string;
  currency_symbol: string;
  currency_position: 'before' | 'after';
  currency_decimals: number;
  thousand_separator: string;
  decimal_separator: string;
  last_minute_days: number;
  few_seats_threshold: number;
  pending_holds_seats: boolean;
  reviews_auto_approve: boolean;
  response_time_text: string;
  booking_terms: string;
  turnstile_site_key: string;
  turnstile_secret_set: boolean;
  /** Brand, design and SEO groups live here (the rest of the page copy is edited in the visual editor). */
  home_content: Json;
};

type Payload = { settings: Settings; content_defaults: Json };
type TabId = 'general' | 'contact' | 'currency' | 'booking' | 'demo';

const ID = 'stz-settings';

export const SettingsApp = ({ config }: { config: ShellConfig }) => {
  const api = useMemo(() => createApi(config), [config]);
  const [tab, setTab] = useState<TabId>('general');
  const [settings, setSettings] = useState<Settings | null>(null);
  const [content, setContent] = useState<Json>({});
  const [saved, setSaved] = useState('');
  const [secret, setSecret] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [hasDemo, setHasDemo] = useState(config.demo.hasDemo);

  const adopt = (payload: Payload) => {
    const merged = mergeContent(payload.content_defaults, payload.settings.home_content);
    setSettings(payload.settings);
    setContent(merged);
    setSecret('');
    setSaved(JSON.stringify([payload.settings, merged]));
  };

  useEffect(() => {
    api.get<Payload>('admin/settings').then(adopt).catch((e: unknown) => setNotice({ kind: 'error', text: String(e) }));
  }, [api]);

  const dirty = settings !== null && (JSON.stringify([settings, content]) !== saved || secret !== '');

  useEffect(() => {
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      if (dirty) {
        event.preventDefault();
        event.returnValue = '';
      }
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [dirty]);

  const save = async () => {
    if (!settings) return;
    setBusy(true);
    setNotice(null);
    setErrors({});
    try {
      const { turnstile_secret_set: _set, ...rest } = settings;
      void _set;
      const payload = await api.put<Payload>('admin/settings', { ...rest, home_content: content, turnstile_secret: secret });
      adopt(payload);
      setNotice({ kind: 'ok', text: __('Settings saved.', 'suntourz') });
    } catch (error) {
      if (error instanceof ApiError && Object.keys(error.fieldErrors).length > 0) {
        setErrors(error.fieldErrors);
        setNotice({ kind: 'error', text: Object.values(error.fieldErrors).join(' ') });
      } else {
        setNotice({ kind: 'error', text: error instanceof Error ? error.message : String(error) });
      }
    } finally {
      setBusy(false);
    }
  };

  const demo = async (action: 'import' | 'remove') => {
    if (action === 'remove' && !window.confirm(__('Remove all demo tours, bookings, pages and menus created by the importer?', 'suntourz'))) return;
    setBusy(true);
    setNotice(null);
    try {
      const result = await api.post<{ hasDemo: boolean }>('admin/demo', { action });
      setHasDemo(result.hasDemo);
      setNotice({ kind: 'ok', text: action === 'import' ? __('Demo data imported. Visit the site to see it.', 'suntourz') : __('Demo data removed.', 'suntourz') });
    } catch (error) {
      setNotice({ kind: 'error', text: error instanceof Error ? error.message : String(error) });
    } finally {
      setBusy(false);
    }
  };

  if (!settings) return <p>{notice?.text ?? __('Loading settings…', 'suntourz')}</p>;

  const set = <K extends keyof Settings>(key: K, value: Settings[K]) => setSettings({ ...settings, [key]: value });
  const text = (key: keyof Settings, label: string, hint?: string) => (
    <Field label={label} hint={hint} error={errors[key]}>
      <TextInput value={String(settings[key] ?? '')} onChange={(v) => set(key, v as never)} />
    </Field>
  );

  /* Fields stored in the site content tree (brand / design / seo). */
  const field = (group: string, key: string, label: string, hint?: string, long = false) => (
    <Field label={label} hint={hint}>
      {long ? (
        <Textarea rows={3} value={read(content, group, key)} onChange={(v) => setContent(write(content, group, key, v))} />
      ) : (
        <TextInput value={read(content, group, key)} onChange={(v) => setContent(write(content, group, key, v))} />
      )}
    </Field>
  );

  const image = (group: string, key: string, label: string, hint?: string) => {
    const value = read(content, group, key);
    return (
      <Field label={label} hint={hint}>
        <span className="stz-row" style={{ alignItems: 'center' }}>
          {value && <img src={value} alt="" style={{ height: 38, maxWidth: 120, objectFit: 'contain', background: '#e8e2d9', borderRadius: 6, padding: 3 }} />}
          <TextInput value={value} onChange={(v) => setContent(write(content, group, key, v))} placeholder="https://" />
          <Button
            aria-label={__('Choose image', 'suntourz')}
            onClick={async () => {
              const url = await pickImage();
              if (url) setContent(write(content, group, key, url));
            }}
          >
            <ImagePlus size={14} aria-hidden />
          </Button>
          {value && (
            <Button icon variant="ghost" aria-label={__('Remove image', 'suntourz')} onClick={() => setContent(write(content, group, key, ''))}>
              <X size={14} aria-hidden />
            </Button>
          )}
        </span>
      </Field>
    );
  };

  const colour = (key: string, label: string) => {
    const value = read(content, 'design', key);
    return (
      <Field label={label}>
        <span className="stz-row">
          <input
            type="color"
            value={/^#[0-9a-f]{6}$/i.test(value) ? value : '#000000'}
            onChange={(event) => setContent(write(content, 'design', key, event.target.value))}
            aria-label={label}
            style={{ width: 44, height: 34, padding: 0 }}
          />
          <TextInput value={value} onChange={(v) => setContent(write(content, 'design', key, v))} />
        </span>
      </Field>
    );
  };

  const tabs: TabDef<TabId>[] = [
    { id: 'general', label: __('General', 'suntourz') },
    { id: 'contact', label: __('Company & contact', 'suntourz') },
    { id: 'currency', label: __('Currency', 'suntourz') },
    { id: 'booking', label: __('Booking rules', 'suntourz') },
    { id: 'demo', label: __('Demo data', 'suntourz') },
  ];

  return (
    <div className="stz-settings">
      <h1>{__('Suntourz settings', 'suntourz')}</h1>
      {notice && <div className={`stz-notice stz-notice-${notice.kind}`} role={notice.kind === 'error' ? 'alert' : 'status'}>{notice.text}</div>}

      <Tabs idPrefix={ID} tabs={tabs} active={tab} onChange={setTab} />

      <TabPanel idPrefix={ID} id="general" active={tab === 'general'}>
        <div className="stz-stack" style={{ maxWidth: 820 }}>
          <p className="stz-hint">
            {__('The basics of the site. To change the page design, texts and sections, use Suntourz → Visual editor.', 'suntourz')}
          </p>

          <h2 className="stz-subtitle">{__('Site identity', 'suntourz')}</h2>
          <div className="stz-grid stz-grid-2">
            {text('site_name', __('Site name', 'suntourz'), __('Shown in the browser tab, search results and the header when there is no logo.', 'suntourz'))}
            {text('site_description', __('Site description', 'suntourz'), __('A short line about the site (the tagline).', 'suntourz'))}
          </div>
          <div className="stz-grid stz-grid-2">
            {image('brand', 'logo_image', __('Logo', 'suntourz'), __('Header and loading screen.', 'suntourz'))}
            {image('brand', 'logo_image_white', __('Logo for dark backgrounds', 'suntourz'), __('Footer and the transparent header. Optional.', 'suntourz'))}
            {image('brand', 'favicon_image', __('Favicon', 'suntourz'), __('The small icon in the browser tab (square, at least 64×64).', 'suntourz'))}
          </div>
          <div className="stz-grid stz-grid-2">
            {field('brand', 'badge', __('Logo badge text', 'suntourz'), __('The small label next to the name, e.g. Thailand.', 'suntourz'))}
            {field('brand', 'tagline', __('Logo subtitle', 'suntourz'), __('Under the name, e.g. Boutique Travel Operator.', 'suntourz'))}
          </div>

          <h2 className="stz-subtitle">{__('Colours', 'suntourz')}</h2>
          <div className="stz-grid stz-grid-3">
            {colour('color_primary', __('Primary colour', 'suntourz'))}
            {colour('color_accent', __('Accent colour', 'suntourz'))}
            {colour('color_background', __('Page background', 'suntourz'))}
          </div>

          <h2 className="stz-subtitle">{__('Footer', 'suntourz')}</h2>
          {field('brand', 'footer_pitch', __('Short description', 'suntourz'), undefined, true)}
          <div className="stz-grid stz-grid-2">
            {field('brand', 'copyright', __('Copyright line', 'suntourz'), __('Leave empty for the automatic “© year name”.', 'suntourz'))}
            {field('brand', 'license_note', __('Licence / registration note', 'suntourz'))}
          </div>

          <h2 className="stz-subtitle">{__('Search engines (home page defaults)', 'suntourz')}</h2>
          <div className="stz-grid stz-grid-2">
            {field('seo', 'home_title', __('Home page title', 'suntourz'), __('About 50–60 characters. Empty = automatic. Other pages are edited in the visual editor’s SEO tab.', 'suntourz'))}
            {image('seo', 'default_image', __('Default share image', 'suntourz'), __('Used when a page has no picture of its own.', 'suntourz'))}
          </div>
          {field('seo', 'home_description', __('Home page description', 'suntourz'), __('About 140–160 characters. Empty = automatic.', 'suntourz'), true)}
        </div>
      </TabPanel>

      <TabPanel idPrefix={ID} id="contact" active={tab === 'contact'}>
        <div className="stz-grid stz-grid-2">
          {text('company_name', __('Company name', 'suntourz'))}
          {text('phone', __('Phone', 'suntourz'), __('Shown in the header, footer and contact popup.', 'suntourz'))}
          {text('whatsapp', __('WhatsApp number', 'suntourz'), __('Digits with country code, e.g. 66812345678.', 'suntourz'))}
          {text('line_id', __('LINE ID', 'suntourz'))}
          {text('telegram', __('Telegram username', 'suntourz'))}
          {text('support_email', __('Public support email', 'suntourz'))}
          {text('instagram_url', __('Instagram URL', 'suntourz'))}
          {text('facebook_url', __('Facebook URL', 'suntourz'))}
          {text('response_time_text', __('Response time text', 'suntourz'), __('"We will contact you …", e.g. within 24 hours.', 'suntourz'))}
        </div>
      </TabPanel>

      <TabPanel idPrefix={ID} id="currency" active={tab === 'currency'}>
        <div className="stz-grid stz-grid-3">
          {text('currency_code', __('Currency code', 'suntourz'), 'THB')}
          {text('currency_symbol', __('Symbol', 'suntourz'), '฿')}
          <Field label={__('Symbol position', 'suntourz')}>
            <Select value={settings.currency_position} onChange={(v) => set('currency_position', v)} options={[{ value: 'before', label: __('Before the amount (฿1,000)', 'suntourz') }, { value: 'after', label: __('After the amount (1,000 ฿)', 'suntourz') }]} />
          </Field>
          <Field label={__('Decimals shown', 'suntourz')}>
            <NumberInput min={0} max={2} value={settings.currency_decimals} onChange={(v) => set('currency_decimals', v ?? 0)} />
          </Field>
          {text('thousand_separator', __('Thousands separator', 'suntourz'))}
          {text('decimal_separator', __('Decimal separator', 'suntourz'))}
        </div>
        <p className="stz-hint">{__('Prices are stored in the smallest unit, so changing the format never changes any amount.', 'suntourz')}</p>
      </TabPanel>

      <TabPanel idPrefix={ID} id="booking" active={tab === 'booking'}>
        <div className="stz-stack">
          <div className="stz-grid stz-grid-3">
            <Field label={__('"Last minute" window (days)', 'suntourz')} hint={__('Tours starting within this many days get the badge.', 'suntourz')}>
              <NumberInput min={1} value={settings.last_minute_days} onChange={(v) => set('last_minute_days', v ?? 14)} />
            </Field>
            <Field label={__('"Only X left" threshold', 'suntourz')} hint={__('Show the badge when this many seats or fewer remain.', 'suntourz')}>
              <NumberInput min={1} value={settings.few_seats_threshold} onChange={(v) => set('few_seats_threshold', v ?? 4)} />
            </Field>
          </div>
          <Checkbox checked={settings.pending_holds_seats} onChange={(v) => set('pending_holds_seats', v)} label={__('Pending bookings hold seats', 'suntourz')} hint={__('New and Contacted bookings reserve seats until you confirm or cancel them.', 'suntourz')} />
          <Checkbox checked={settings.reviews_auto_approve} onChange={(v) => set('reviews_auto_approve', v)} label={__('Publish reviews immediately', 'suntourz')} hint={__('When off, new reviews wait for approval in Comments.', 'suntourz')} />
          <Field label={__('Booking terms & cancellation wording', 'suntourz')} hint={__('Shown inside the booking form.', 'suntourz')}>
            <Textarea rows={6} value={settings.booking_terms} onChange={(v) => set('booking_terms', v)} />
          </Field>
          <div className="stz-grid stz-grid-2">
            {text('turnstile_site_key', __('Cloudflare Turnstile site key (optional)', 'suntourz'))}
            <Field label={__('Turnstile secret key', 'suntourz')} hint={settings.turnstile_secret_set ? __('A secret is saved. Type a new one to replace it.', 'suntourz') : __('Leave empty to disable the spam check.', 'suntourz')}>
              <TextInput type="password" autoComplete="off" value={secret} onChange={setSecret} />
            </Field>
          </div>
        </div>
      </TabPanel>

      <TabPanel idPrefix={ID} id="demo" active={tab === 'demo'}>
        <div className="stz-stack" style={{ maxWidth: 640 }}>
          <p>{__('Import three complete tours (with images, itineraries, plans, extras and departures starting from today), six sample bookings in every status, destinations, travel styles, guide articles, pages and menus.', 'suntourz')}</p>
          <div className="stz-row">
            <Button variant="primary" disabled={busy || hasDemo} onClick={() => void demo('import')}>{__('Import demo tours', 'suntourz')}</Button>
            <Button variant="danger" disabled={busy || !hasDemo} onClick={() => void demo('remove')}>{__('Remove demo data', 'suntourz')}</Button>
          </div>
          <p className="stz-hint">{__('Removing only deletes items created by the importer — your own tours and bookings are never touched. WP-CLI: wp stz demo import | remove.', 'suntourz')}</p>
        </div>
      </TabPanel>

      <div className="stz-savebar">
        <Button variant="primary" disabled={busy || !dirty} onClick={() => void save()}>{busy ? __('Saving…', 'suntourz') : __('Save settings', 'suntourz')}</Button>
        {dirty && <span className="stz-dirty">{__('Unsaved changes', 'suntourz')}</span>}
      </div>
    </div>
  );
};
