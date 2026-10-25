import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: 'drafting.test',
        hmr: {
            host: 'drafting.test',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
