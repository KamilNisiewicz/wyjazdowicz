import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, daisyui],

    daisyui: {
        themes: [
            {
                wyjazdowicz: {
                    primary: '#2563EB',
                    'primary-content': '#FFFFFF',
                    secondary: '#1E3A8A',
                    'secondary-content': '#FFFFFF',
                    accent: '#DC2626',
                    'accent-content': '#FFFFFF',
                    neutral: '#1F2937',
                    'neutral-content': '#F9FAFB',
                    'base-100': '#FFFFFF',
                    'base-200': '#F3F4F6',
                    'base-300': '#E5E7EB',
                    'base-content': '#111827',
                    info: '#3B82F6',
                    success: '#16A34A',
                    warning: '#D97706',
                    error: '#DC2626',
                },
            },
        ],
    },
};
