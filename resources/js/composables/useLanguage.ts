import { ref } from 'vue';
import de from '../../lang/de.json';
import en from '../../lang/en.json';

const catalogs: Record<string, Record<string, string>> = { en, de };
export const availableLanguages = [
    { id: 'en', name: 'English' },
    { id: 'de', name: 'Deutsch (partial)' },
];
export const locale = ref('en');
const sourceKeys = new Map<string, string>();
for (const [key, value] of Object.entries(en)) if (!sourceKeys.has(value)) sourceKeys.set(value, key);

export function t(key: string, params: Record<string, string | number> = {}): string {
    let value =
        catalogs[locale.value]?.[key] ??
        (sourceKeys.has(key) ? catalogs[locale.value]?.[sourceKeys.get(key)!] : undefined) ??
        catalogs.en[key] ??
        key;
    for (const [name, replacement] of Object.entries(params)) value = value.replaceAll(`{${name}}`, String(replacement));
    return value;
}

export function setLanguage(value: string) {
    if (!catalogs[value]) return;
    locale.value = value;
    if (typeof document !== 'undefined') {
        document.documentElement.lang = value;
        document.cookie = `hivepanel_locale=${value}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
    }
    try {
        localStorage.setItem('hivepanel.locale', value);
    } catch {
        /* Browser storage can be unavailable. */
    }
    if (typeof window !== 'undefined') window.dispatchEvent(new Event('hivepanel-ui-changed'));
}

export function initializeLanguage() {
    try {
        setLanguage(localStorage.getItem('hivepanel.locale') ?? 'en');
    } catch {
        setLanguage('en');
    }
}

export function extensionMessages(): Record<string, string> {
    return Object.fromEntries(
        Object.keys(en)
            .filter((key) => key.startsWith('votifier.'))
            .map((key) => [key, t(key)]),
    );
}
