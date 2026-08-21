/* | KB @CerberRus00 - Nexus Invest Team */
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['IBM Plex Sans', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                ink: {
                    DEFAULT: '#0C0C0D',
                    soft: '#1A1A1C',
                    muted: '#6F6E69',
                    faint: '#9A9892',
                    line: '#E2E0D8',
                    paper: '#F3F2EE',
                },
            },
            maxWidth: {
                page: '80rem',
            },
        },
    },

    plugins: [forms],
};
