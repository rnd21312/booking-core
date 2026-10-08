import path from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// wp-admin apps → ../dist. One entry per admin screen.
export default defineConfig({
  base: './',
  plugins: [react(), tailwindcss()],
  resolve: { alias: { '@': path.resolve(__dirname, 'src') } },
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        'tour-editor': path.resolve(__dirname, 'src/admin/tour-editor/main.tsx'),
        bookings: path.resolve(__dirname, 'src/admin/bookings/main.tsx'),
        settings: path.resolve(__dirname, 'src/admin/settings/main.tsx'),
      },
    },
  },
  server: {
    host: 'localhost',
    port: 5174,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5174',
  },
});
