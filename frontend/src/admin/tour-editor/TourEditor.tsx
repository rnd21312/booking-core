import { useEffect, useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { createApi, type AdminConfig } from '../lib/api';
import { Button } from '../ui/controls';
import { TabPanel, Tabs, type TabDef } from '../ui/Tabs';
import { DeparturesTab } from './tabs/DeparturesTab';
import { DetailsTab } from './tabs/DetailsTab';
import { FlagsTab } from './tabs/FlagsTab';
import { ItineraryTab } from './tabs/ItineraryTab';
import { PricingTab } from './tabs/PricingTab';
import type { TabId } from './types';
import { tabForErrorPath, useTourEditor } from './useTourEditor';

const ID_PREFIX = 'stz-tour';

type TourEditorProps = { config: AdminConfig };

export const TourEditor = ({ config }: TourEditorProps) => {
  const api = useMemo(() => createApi(config), [config]);
  const editor = useTourEditor(api, config.tourId);
  const [tab, setTab] = useState<TabId>('details');
  const rootRef = useRef<HTMLDivElement>(null);

  // Refs let the long-lived listeners below always see the latest state.
  const dirtyRef = useRef(false);
  const saveRef = useRef(editor.save);
  dirtyRef.current = editor.dirty;
  saveRef.current = editor.save;

  // Warn before leaving with unsaved changes.
  useEffect(() => {
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      if (!dirtyRef.current) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, []);

  // WordPress's own Update/Publish button: save our panel first, then let the post form submit.
  useEffect(() => {
    const form = document.getElementById('post');
    if (!(form instanceof HTMLFormElement)) return;

    let bypass = false;
    const onSubmit = (event: SubmitEvent) => {
      if (bypass || !dirtyRef.current) return;

      event.preventDefault();
      const submitter = event.submitter instanceof HTMLElement ? event.submitter : undefined;

      void saveRef.current().then((ok) => {
        if (!ok) {
          rootRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
        }
        dirtyRef.current = false; // so the form navigation does not trigger the "unsaved" prompt
        bypass = true;
        form.requestSubmit(submitter);
      });
    };

    form.addEventListener('submit', onSubmit);
    return () => form.removeEventListener('submit', onSubmit);
  }, []);

  if (editor.state.status === 'loading') {
    return <p>{__('Loading tour editor…', 'suntourz')}</p>;
  }
  if (editor.state.status === 'error' || !editor.draft) {
    return (
      <div className="stz-notice stz-notice-error">
        {editor.state.status === 'error' ? editor.state.message : __('Could not load the tour.', 'suntourz')}
      </div>
    );
  }

  const { draft, errors, notice } = editor;
  const errorCount = (id: TabId) => Object.keys(errors).filter((path) => tabForErrorPath(path) === id).length;

  const tabs: TabDef<TabId>[] = [
    { id: 'details', label: __('Details', 'suntourz'), badge: errorCount('details') },
    { id: 'itinerary', label: __('Itinerary', 'suntourz'), badge: errorCount('itinerary') },
    { id: 'pricing', label: __('Pricing', 'suntourz'), badge: errorCount('pricing') },
    { id: 'departures', label: __('Departures', 'suntourz'), badge: errorCount('departures') },
    { id: 'flags', label: __('Flags', 'suntourz'), badge: errorCount('flags') },
  ];

  const tabProps = { draft, update: editor.update, errors, config };

  return (
    <div ref={rootRef}>
      {notice && (
        <div
          className={`stz-notice stz-notice-${notice.kind}`}
          role={notice.kind === 'error' ? 'alert' : 'status'}
        >
          <strong>{notice.message}</strong>
          {notice.items && (
            <ul>
              {notice.items.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          )}
        </div>
      )}

      <Tabs idPrefix={ID_PREFIX} tabs={tabs} active={tab} onChange={setTab} />

      <TabPanel idPrefix={ID_PREFIX} id="details" active={tab === 'details'}>
        <DetailsTab {...tabProps} />
      </TabPanel>
      <TabPanel idPrefix={ID_PREFIX} id="itinerary" active={tab === 'itinerary'}>
        <ItineraryTab {...tabProps} />
      </TabPanel>
      <TabPanel idPrefix={ID_PREFIX} id="pricing" active={tab === 'pricing'}>
        <PricingTab {...tabProps} />
      </TabPanel>
      <TabPanel idPrefix={ID_PREFIX} id="departures" active={tab === 'departures'}>
        <DeparturesTab {...tabProps} />
      </TabPanel>
      <TabPanel idPrefix={ID_PREFIX} id="flags" active={tab === 'flags'}>
        <FlagsTab {...tabProps} />
      </TabPanel>

      <div className="stz-savebar">
        <Button variant="primary" disabled={editor.saving || !editor.dirty} onClick={() => void editor.save()}>
          {editor.saving ? __('Saving…', 'suntourz') : __('Save tour details', 'suntourz')}
        </Button>
        {editor.dirty && <span className="stz-dirty">{__('Unsaved changes', 'suntourz')}</span>}
        <span className="stz-muted">
          {__('Title, description, excerpt and featured image are saved with the WordPress Update button.', 'suntourz')}
        </span>
      </div>
    </div>
  );
};
