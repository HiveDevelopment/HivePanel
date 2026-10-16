import { ref } from 'vue';
import forest from '../../themes/forest.json';
import hive from '../../themes/hive.json';
import ocean from '../../themes/ocean.json';

export type DesignTokens = typeof hive;
export const themePresets = { hive, ocean, forest };
export const designTokens = ref<DesignTokens>({ ...hive });

export function validateTokens(input: unknown): DesignTokens {
    if (!input || typeof input !== 'object' || Array.isArray(input)) throw new Error('Invalid theme');
    const tokens = { ...hive };
    for (const [key, value] of Object.entries(input)) {
        if (!Object.hasOwn(hive, key)) throw new Error('Unsupported token');
        if (key === 'panelRadius' || key === 'buttonRadius') {
            if (typeof value !== 'number' || !Number.isFinite(value) || value < 0 || value > 24) throw new Error('Invalid radius');
        } else if (typeof value !== 'string' || !/^#[0-9a-f]{6}$/i.test(value)) throw new Error('Invalid colour');
        (tokens as Record<string, string | number>)[key] = value as string | number;
    }
    return tokens;
}

function rgb(hex: string) {
    return [1, 3, 5].map((index) => parseInt(hex.slice(index, index + 2), 16)).join(' ');
}

// shadcn controls and the native panel share the same administrator theme.
export function hsl(hex: string): string {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
    const max = Math.max(r, g, b),
        min = Math.min(r, g, b),
        delta = max - min,
        light = (max + min) / 2;
    let hue = 0,
        saturation = 0;
    if (delta) {
        saturation = delta / (1 - Math.abs(2 * light - 1));
        hue = max === r ? ((g - b) / delta) % 6 : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4;
        hue = (hue * 60 + 360) % 360;
    }
    return `${hue.toFixed(2)} ${(saturation * 100).toFixed(2)}% ${(light * 100).toFixed(2)}%`;
}
export function semanticTokens(tokens: DesignTokens): Record<string, string> {
    const mapping: Record<string, keyof DesignTokens> = {
        background: 'surfaceDark',
        foreground: 'foreground',
        card: 'surface',
        'card-foreground': 'foreground',
        popover: 'surfaceElevated',
        'popover-foreground': 'foreground',
        primary: 'accent',
        secondary: 'surfaceLight',
        'secondary-foreground': 'foreground',
        muted: 'surfaceLight',
        'muted-foreground': 'muted',
        accent: 'surfaceHover',
        'accent-foreground': 'foreground',
        destructive: 'danger',
        border: 'border',
        input: 'border',
        ring: 'accent',
        'sidebar-background': 'surface',
        'sidebar-foreground': 'foreground',
        'sidebar-primary': 'accent',
        'sidebar-accent': 'surfaceHover',
        'sidebar-accent-foreground': 'foreground',
        'sidebar-border': 'border',
        'sidebar-ring': 'accent',
    };
    const result = Object.fromEntries(Object.entries(mapping).map(([name, key]) => [`--${name}`, hsl(tokens[key] as string)]));
    // Contrast on filled controls remains readable even with a pale accent.
    for (const [name, key] of [
        ['primary-foreground', 'accent'],
        ['sidebar-primary-foreground', 'accent'],
        ['destructive-foreground', 'danger'],
    ] as const) {
        const color = tokens[key];
        const channels = [1, 3, 5]
            .map((i) => parseInt(color.slice(i, i + 2), 16) / 255)
            .map((v) => (v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
        const luminance = channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
        result[`--${name}`] = luminance > 0.179 ? '0 0% 0%' : '0 0% 100%';
    }
    result['--radius'] = `${tokens.panelRadius}px`;
    return result;
}

export function applyTokens(input: unknown) {
    const tokens = validateTokens(input);
    designTokens.value = tokens;
    if (typeof document !== 'undefined') {
        for (const [key, value] of Object.entries(semanticTokens(tokens))) document.documentElement.style.setProperty(key, value);
        for (const [key, value] of Object.entries(tokens))
            document.documentElement.style.setProperty(`--hp-${key}`, typeof value === 'number' ? `${value}px` : rgb(value));
    }

    if (typeof window !== 'undefined') window.dispatchEvent(new Event('hivepanel-ui-changed'));
}

export function initializeDesignTokens(props: any = {}) {
    const catalog = props.themes;
    const offered = Array.isArray(catalog?.themes) ? catalog.themes : [];
    const selected = offered.find((theme: any) => theme.id === props.themePreference) ?? offered.find((theme: any) => theme.id === catalog?.default);
    try {
        applyTokens(selected?.tokens ?? hive);
    } catch {
        applyTokens(hive);
    }
    try {
        localStorage.removeItem('hivepanel.designTokens');
    } catch {
        /* Old local custom themes are no longer used. */
    }
}

export function extensionTheme(): Record<string, string> {
    return Object.fromEntries(
        Object.entries(designTokens.value).map(([key, value]) => [`--hp-${key}`, typeof value === 'number' ? `${value}px` : rgb(value)]),
    );
}
