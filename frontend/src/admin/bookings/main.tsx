import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { readConfig, type ShellConfig } from '../lib/api';
import '../styles/admin.css';
import { BookingsApp } from './BookingsApp';

const root = document.getElementById('stz-bookings-root');

if (root) {
  createRoot(root).render(
    <StrictMode>
      <BookingsApp config={readConfig<ShellConfig>('stz-bookings-config')} />
    </StrictMode>,
  );
}
