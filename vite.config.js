import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';

export default defineConfig({
    input: {
        app: './assets/scripts/app.js',
        'password-repeated-form-type': './assets/scripts/password-repeated-form-type.js',
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        Symfony(),
    ],
    resolve: {
        alias: {
            '@': '/assets',
        },
    },
});
