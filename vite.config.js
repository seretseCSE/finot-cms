import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import serviceWorkerPlugin from './vite-plugins/service-worker-plugin.js';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        serviceWorkerPlugin(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Split admin tour logic into its own chunk
                    if (id.includes('pwa-tour')) {
                        return 'admin-tour';
                    }
                    // Split vendor libraries for better caching
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },
            },
        },
        // Ensure hashed filenames for long-term caching of assets
        chunkFileNames: 'js/[name]-[hash].js',
        entryFileNames: 'js/[name]-[hash].js',
        assetFileNames: ({ name }) => {
            if (name?.endsWith('.css')) return 'css/[name]-[hash][extname]';
            return 'assets/[name]-[hash][extname]';
        },
    },
});
