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
                'resources/js/financial-charts.js',
                'resources/js/tours/filament-init.js',
                'resources/css/sunday-school.css',
                'resources/js/sunday-school.js',
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
                    if (id.includes('node_modules')) {
                        if (id.includes('driver.js')) {
                            return 'tour-vendor';
                        }
                        if (id.includes('chart.js')) {
                            return 'chart-vendor';
                        }
                        return 'vendor';
                    }
                    if (id.includes('/tours/')) {
                        if (id.includes('/roles/')) return 'tour-roles';
                        if (id.includes('/pages/')) return 'tour-pages';
                        if (id.includes('/core/')) return 'tour-core';
                        if (id.includes('/components/')) return 'tour-components';
                        return 'tour-main';
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
