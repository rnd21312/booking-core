import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { readConfig, type ShellConfig } from '../lib/api';
import '../styles/admin.css';
import { SettingsApp } from './SettingsApp';

const root = document.getElementById('stz-settings-root');

if (root) {
  createRoot(root).render(
    <StrictMode>
      <SettingsApp config={readConfig<ShellConfig>('stz-settings-config')} />
    </StrictMode>,
  );
}
