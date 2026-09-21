import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';

const token = (name, fallback) => `rgb(var(--wn-${name}, ${fallback}) / <alpha-value>)`;
const dataColors = Object.fromEntries(
    ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'].map(hue => [hue,
        Object.fromEntries([100, 200, 300, 400, 500, 600, 700].map(shade => [shade,
            token(`data-${hue}-${shade}`, colors[hue][shade].match(/[a-f\d]{2}/gi).map(v => parseInt(v, 16)).join(' ')),
        ])),
    ]),
);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                ui: {
                    'body': token('body', '229 231 235'),
                    'faint': token('faint', '75 85 99'),
                    'disabled': token('disabled', '75 85 99'),
                    'inactive': token('inactive', '107 114 128'),
                    'divider': token('divider', '55 65 81'),
                    'slate-deep': token('slate-deep', '15 23 42'),
                    'slate-soft': token('slate-soft', '51 65 85'),
                    'slate-border': token('slate-border', '100 116 139'),
                    'action': token('action', '59 130 246'),
                    'action-deep': token('action-deep', '29 78 216'),
                    'timestamp-raised': 'rgb(var(--wn-timestamp-raised, 0 0 0 / 0.3))',
                    timestamp: 'rgb(var(--wn-timestamp, 0 0 0 / 0.2))',
                    fg: token('fg', '255 255 255'), secondary: token('secondary', '209 213 219'),
                    muted: token('muted', '156 163 175'), subtle: token('subtle', '148 163 184'),
                    overlay: token('overlay', '255 255 255'), line: token('line', '255 255 255'),
                    border: token('border', '75 85 99'), raised: token('raised', '31 41 55'),
                    deep: token('deep', '17 24 39'), soft: token('soft', '55 65 81'),
                    accent: token('accent', '59 130 246'), 'accent-strong': token('accent-strong', '37 99 235'),
                    link: token('link', '96 165 250'), 'accent-end': token('accent-end', '34 211 238'),
                },
                data: dataColors,
                weather: {
                    dark: token('bg', '15 20 25'),
                    card: token('card', '26 35 50'),
                    accent: token('accent', '59 130 246'),
                    warm: '#f59e0b',
                    cold: '#06b6d4',
                    rain: '#6366f1',
                }
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['JetBrains Mono', 'monospace'],
            },
        },
    },

    plugins: [forms],
};
