import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ApiError, type Api } from '../lib/api';
import { toDraft, toPayload } from './draft';
import type { Draft, EditorPayload, TabId } from './types';

export type Notice = { kind: 'ok' | 'error' | 'warn'; message: string; items?: string[] };

type LoadState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; draft: Draft; savedJson: string; tour: EditorPayload['tour'] };

/** Which tab a server error path belongs to (for the red badges on the tab bar). */
export const tabForErrorPath = (path: string): TabId => {
  if (path.startsWith('pricing.') || path.startsWith('extras.')) return 'pricing';
  if (path.startsWith('departures.')) return 'departures';
  if (path.startsWith('flags.')) return 'flags';
  return 'details';
};

export const useTourEditor = (api: Api, tourId: number) => {
  const [state, setState] = useState<LoadState>({ status: 'loading' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<Notice | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let cancelled = false;

    api
      .get<EditorPayload>(`admin/tours/${tourId}/details`)
      .then((payload) => {
        if (cancelled) return;
        const draft = toDraft(payload);
        setState({ status: 'ready', draft, savedJson: JSON.stringify(draft), tour: payload.tour });
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setState({ status: 'error', message: error instanceof Error ? error.message : String(error) });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [api, tourId]);

  const draft = state.status === 'ready' ? state.draft : null;
  const dirty = useMemo(
    () => state.status === 'ready' && JSON.stringify(state.draft) !== state.savedJson,
    [state],
  );

  const update = useCallback((change: (draft: Draft) => Draft) => {
    setState((current) => (current.status === 'ready' ? { ...current, draft: change(current.draft) } : current));
  }, []);

  // Latest draft for save(), which is called from event handlers outside React's render cycle.
  const draftRef = useRef<Draft | null>(null);
  draftRef.current = draft;

  const save = useCallback(async (): Promise<boolean> => {
    const current = draftRef.current;
    if (!current) return false;

    setSaving(true);
    setNotice(null);
    try {
      const payload = await api.put<EditorPayload>(`admin/tours/${tourId}/details`, toPayload(current));
      const fresh = toDraft(payload);
      setState({ status: 'ready', draft: fresh, savedJson: JSON.stringify(fresh), tour: payload.tour });
      setErrors({});
      setNotice(
        payload.warnings?.length
          ? { kind: 'warn', message: __('Saved, with notes:', 'suntourz'), items: payload.warnings }
          : { kind: 'ok', message: __('Tour details saved.', 'suntourz') },
      );
      return true;
    } catch (error) {
      if (error instanceof ApiError && Object.keys(error.fieldErrors).length > 0) {
        setErrors(error.fieldErrors);
        setNotice({
          kind: 'error',
          message: __('Nothing was saved. Please fix the highlighted fields:', 'suntourz'),
          items: Object.values(error.fieldErrors),
        });
      } else {
        setNotice({ kind: 'error', message: error instanceof Error ? error.message : String(error) });
      }
      return false;
    } finally {
      setSaving(false);
    }
  }, [api, tourId]);

  return { state, draft, dirty, errors, notice, saving, update, save, dismissNotice: () => setNotice(null) };
};
