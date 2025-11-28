import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    yellow: '#facc15', // 50%
                    green:  '#22c55e', // 20%
                    purple: '#8b5cf6', // 20%
                    brown:  '#8b5e3c', // 10%
                    ink:    '#0f172a'
                }
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
