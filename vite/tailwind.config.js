/** @type {import('tailwindcss').Config} */
export default {
    prefix: 'bw-',
    important: true,
    content: ['./src/**/*.{js,ts,jsx,tsx}'],
    theme: {
        extend: {
            boxShadow: {
                'input-error': '0 0 0 1px #ef4444',
            },
        },
    },
    plugins: [],
    corePlugins: {
        preflight: false,
    },
}
