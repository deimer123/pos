import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pos-catalogo-offline.js',
                'resources/js/pos-offline-queue.js',
                'resources/js/pos-offline-auth.js',
                'resources/js/pos-clientes-offline.js',
            ],
            refresh: true,
        }),
    ],
});
