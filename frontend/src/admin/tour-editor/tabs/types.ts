import type { AdminConfig } from '../../lib/api';
import type { Draft } from '../types';

/** Props shared by every editor tab. */
export type TabProps = {
  draft: Draft;
  update: (change: (draft: Draft) => Draft) => void;
  /** Server validation errors keyed by field path, e.g. `departures.2.capacity`. */
  errors: Record<string, string>;
  config: AdminConfig;
};
