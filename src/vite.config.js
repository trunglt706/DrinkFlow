import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/public.css',
                'resources/css/global.css',
                'resources/css/room.css',
                'resources/css/admin.css',
                'resources/css/superadmin.css',
                'resources/js/app.js',
                'resources/js/public.js',
                'resources/js/global.js',
                'resources/js/room.js',
                'resources/js/admin.js',
                'resources/js/game-play.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            onwarn(warning, warn) {
                // "use client" của react-toastify chỉ có nghĩa với React Server Components; app render React phía client nên bỏ qua an toàn.
                if (warning.code === 'MODULE_LEVEL_DIRECTIVE' && warning.message.includes('"use client"')) {
                    return;
                }
                warn(warning);
            },
        },
    },
    server: {
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
