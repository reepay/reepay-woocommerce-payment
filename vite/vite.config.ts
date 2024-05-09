import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react-swc'
import wpResolve from './plugins/rollup-plugin-wp-resolve'
import { join } from 'path'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [react(), wpResolve()],
    server: {
        fs: {
            // cachedChecks: false,
        },
    },
    build: {
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: ['src/admin/meta-fields/main.tsx'],
            output: {
                dir: '../assets/dist/vite',
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
