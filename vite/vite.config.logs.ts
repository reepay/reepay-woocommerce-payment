import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react-swc'
import { join } from 'path'
import copy from 'rollup-plugin-copy'
import wpDependencyExtraction from 'rollup-plugin-wordpress-dependency-extraction'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        react(),
        wpDependencyExtraction(),
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
                'meta-fields': 'src/admin/logs-page/main.tsx',
            },
            output: {
                dir: '../assets/dist/vite/logs-page',
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
