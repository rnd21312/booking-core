import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { readConfig } from '../lib/api';
import '../styles/admin.css';
import { TourEditor } from './TourEditor';

const root = document.getElementById('stz-tour-editor-root');

if (root) {
  createRoot(root).render(
    <StrictMode>
      <TourEditor config={readConfig('stz-tour-editor-config')} />
    </StrictMode>,
  );
}
