import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Pages are served from the central domain and any {tenant}
        // subdomain, so the dev server's default single allowed origin
        // (its own URL) rejects them with CORS errors — reflect whatever
        // origin the browser sends instead.
        cors: true,
        // The node container listens on all interfaces so Docker's port
        // mapping can reach it, but Vite then reports its own address as
        // the container's internal bind address (e.g. http://[::]:5173),
        // which the browser on the host can't resolve. Pin the URLs Vite
        // writes into public/hot (and uses for HMR) to the mapped host port.
        origin: 'http://localhost:5173',
        hmr: {
            host: 'localhost',
        },
        // Bind mounts from a Windows/macOS host don't propagate filesystem
        // events into the container, so Vite never sees edits (stale
        // modules, no HMR). Poll instead.
        watch: {
            usePolling: true,
            interval: 300,
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
