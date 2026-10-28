import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
  plugins: [react()],
  publicDir: false,
  build: {
    outDir: path.resolve(__dirname, 'public/app'),
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'frontend/src/main.tsx'),
      output: {
        entryFileNames: 'app.js',
        assetFileNames: (assetInfo) => {
          const names = assetInfo.names ?? [];
          return names.some((name) => name.endsWith('.css')) ? 'app.css' : 'assets/[name][extname]';
        },
      },
    },
  },
});
