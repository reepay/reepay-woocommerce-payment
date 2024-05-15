import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react-swc'
import wpResolve from './plugins/rollup-plugin-wp-resolve'
import { join } from 'path'
import copy from 'rollup-plugin-copy'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        react(),
        wpResolve(),
        copy({
            targets: [{ src: 'public/*', dest: '../assets/dist/vite' }],
        }),
    ],
    build: {
        manifest: true,
        emptyOutDir: true,
        copyPublicDir: false,
        rollupOptions: {
            input: {
                'meta-fields': 'src/admin/meta-fields/main.tsx',
            },
            output: {
                dir: '../assets/dist/vite/meta-fields',
                format: 'iife',
            },
        },
    },
    esbuild: {
        minifyIdentifiers: false,
    },
    resolve: {
        alias: {
            '@': join(__dirname, 'src'),
        },
    },
})
