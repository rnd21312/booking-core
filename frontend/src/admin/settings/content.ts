import { __ } from '@wordpress/i18n';

export type Json = string | number | boolean | null | Json[] | { [key: string]: Json };

const isObject = (value: Json | undefined): value is { [key: string]: Json } =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

/** Lists replace, objects merge — mirrors the PHP SiteContent::merge(). */
export const mergeContent = (base: Json, override: Json | undefined): Json => {
  if (isObject(base) && isObject(override)) {
    const out: { [key: string]: Json } = { ...base };
    for (const [key, value] of Object.entries(override)) out[key] = mergeContent(base[key] ?? null, value);
    return out;
  }
  // PHP serializes an empty map as [] — never let that wipe an object of defaults.
  if (isObject(base) && Array.isArray(override)) return base;

  return override === undefined || override === null || override === '' ? base : override;
};

/** Reads `content[group][key]` as a string. */
export const read = (content: Json, group: string, key: string): string => {
  if (!isObject(content)) return '';
  const section = content[group];
  const value = isObject(section) ? section[key] : undefined;
  return typeof value === 'string' ? value : '';
};

/** Returns a copy of `content` with `content[group][key]` set. */
export const write = (content: Json, group: string, key: string, value: string): Json => {
  const root = isObject(content) ? content : {};
  const section = isObject(root[group]) ? (root[group] as { [key: string]: Json }) : {};
  return { ...root, [group]: { ...section, [key]: value } };
};

/** Opens the WordPress media library and resolves with the chosen image URL ('' when cancelled). */
export const pickImage = (): Promise<string> =>
  new Promise((resolve) => {
    const media = window.wp?.media;
    if (!media) return resolve('');
    const frame = media({
      title: __('Choose image', 'suntourz'),
      button: { text: __('Use image', 'suntourz') },
      library: { type: 'image' },
      multiple: false,
    });
    frame.on('select', () => resolve(frame.state().get('selection').toJSON()[0]?.url ?? ''));
    frame.open();
  });
