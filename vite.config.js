import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                // Headlines, notes and quotes (italic for emphasis)
                bunny('Newsreader', {
                    weights: [400, 500],
                    styles: ['normal', 'italic'],
                }),
                // The UI
                bunny('Geist', {
                    weights: [400, 500, 600],
                }),
                // Dates, labels and counts
                bunny('Geist Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
