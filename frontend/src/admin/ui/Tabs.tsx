import type { KeyboardEvent, ReactNode } from 'react';

export type TabDef<T extends string> = { id: T; label: string; badge?: number };

type TabsProps<T extends string> = {
  tabs: TabDef<T>[];
  active: T;
  onChange: (id: T) => void;
  idPrefix: string;
};

/** WAI-ARIA tabs with arrow-key navigation. Panels are rendered by the caller via TabPanel. */
export const Tabs = <T extends string>({ tabs, active, onChange, idPrefix }: TabsProps<T>) => {
  const onKeyDown = (event: KeyboardEvent, index: number) => {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (!step) return;
    event.preventDefault();
    const next = tabs[(index + step + tabs.length) % tabs.length];
    if (!next) return;
    onChange(next.id);
    document.getElementById(`${idPrefix}-tab-${next.id}`)?.focus();
  };

  return (
    <div className="stz-tabs" role="tablist">
      {tabs.map((tab, index) => (
        <button
          key={tab.id}
          type="button"
          role="tab"
          id={`${idPrefix}-tab-${tab.id}`}
          aria-selected={tab.id === active}
          aria-controls={`${idPrefix}-panel-${tab.id}`}
          tabIndex={tab.id === active ? 0 : -1}
          className="stz-tab"
          onClick={() => onChange(tab.id)}
          onKeyDown={(event) => onKeyDown(event, index)}
        >
          {tab.label}
          {tab.badge ? <span className="stz-tab-badge">{tab.badge}</span> : null}
        </button>
      ))}
    </div>
  );
};

type TabPanelProps = { idPrefix: string; id: string; active: boolean; children: ReactNode };

/** Inactive panels stay mounted (hidden) so in-progress input is never lost when switching tabs. */
export const TabPanel = ({ idPrefix, id, active, children }: TabPanelProps) => (
  <div role="tabpanel" id={`${idPrefix}-panel-${id}`} aria-labelledby={`${idPrefix}-tab-${id}`} hidden={!active}>
    {children}
  </div>
);
