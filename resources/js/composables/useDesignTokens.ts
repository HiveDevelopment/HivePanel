import { ref } from 'vue';
import hive from '../../themes/hive.json';
import ocean from '../../themes/ocean.json';
import forest from '../../themes/forest.json';

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
    return [1, 3, 5].map(index => parseInt(hex.slice(index, index + 2), 16)).join(' ');
}

export function applyTokens(input: unknown) {
    const tokens = validateTokens(input);
    designTokens.value = tokens;
    if (typeof document !== 'undefined') {
        for (const [key, value] of Object.entries(tokens)) document.documentElement.style.setProperty(`--hp-${key}`, typeof value === 'number' ? `${value}px` : rgb(value));
    }
    try { localStorage.setItem('hivepanel.designTokens', JSON.stringify(tokens)); } catch { /* In-memory theme remains usable. */ }
    if (typeof window !== 'undefined') window.dispatchEvent(new Event('hivepanel-ui-changed'));
}

export function initializeDesignTokens() {
    try { applyTokens(JSON.parse(localStorage.getItem('hivepanel.designTokens') ?? 'null') ?? hive); } catch { applyTokens(hive); }
}

export function extensionTheme(): Record<string, string> {
    return Object.fromEntries(Object.entries(designTokens.value).map(([key, value]) => [`--hp-${key}`, typeof value === 'number' ? `${value}px` : rgb(value)]));
}
