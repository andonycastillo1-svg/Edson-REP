import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const token = (name) => `rgb(var(--ui-${name}-rgb) / <alpha-value>)`;
const corporateBlue = {
    50: token('info-bg'),
    100: token('tint'),
    200: token('info-border'),
    300: token('ring'),
    400: token('border-strong'),
    500: token('interaction'),
    600: token('interaction'),
    700: token('primary'),
    800: token('primary-dark'),
    900: token('rail'),
    950: token('rail'),
};
const corporateNeutral = {
    50: token('subtle'),
    100: token('bg'),
    200: token('border'),
    300: token('border'),
    400: token('border-strong'),
    500: token('muted'),
    600: token('muted'),
    700: token('text'),
    800: token('text'),
    900: token('text'),
    950: token('rail'),
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                blue: corporateBlue,
                sky: corporateBlue,
                indigo: corporateBlue,
                slate: corporateNeutral,
                gray: corporateNeutral,
                ink: token('text'),
                muted: token('muted'),
                canvas: token('bg'),
                surface: token('surface'),
                subtle: token('subtle'),
                line: token('border'),
                brand: token('primary'),
                interaction: token('interaction'),
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
