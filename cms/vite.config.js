import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const HOT_FILE = path.join(__dirname, '.vite-hot');

function phpHotFile() {
  let cleaned = false;
  const cleanup = () => {
    if (cleaned) return;
    cleaned = true;
    try { fs.unlinkSync(HOT_FILE); } catch {}
  };
  return {
    name: 'md-php-hot',
    configureServer(server) {
      server.httpServer?.once('listening', () => {
        const addr = server.httpServer.address();
        const port = typeof addr === 'object' && addr ? addr.port : 5173;
        // Always use 'localhost' — server.host is pinned to 'localhost', and
        // raw IPv6 addresses like "::1" are not valid in browser script src URLs.
        fs.writeFileSync(HOT_FILE, `http://localhost:${port}`);
      });
      server.httpServer?.on('close', cleanup);
      process.on('exit', cleanup);
      for (const sig of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
        process.on(sig, () => { cleanup(); process.exit(0); });
      }
      process.on('uncaughtException', () => { cleanup(); process.exit(1); });
    },
  };
}

export default defineConfig(({ command }) => ({
  plugins: [react(), tailwindcss(), phpHotFile()],
  base: command === 'build' ? '/cms/dist/' : '/',
  build: {
    outDir: path.resolve(__dirname, '../public/cms/dist'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'admin/main.jsx'),
    },
  },
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5173',
  },
}));
