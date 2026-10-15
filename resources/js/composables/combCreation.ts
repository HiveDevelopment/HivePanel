export const defaultResources = {
    memory_mb: 2048,
    overhead_memory_mb: 0,
    swap_mb: 0,
    disk_mb: 10240,
    cpu_percent: 100,
    io_weight: 500,
};
export type ResourceKey = keyof typeof defaultResources;
export type ResourceValues = typeof defaultResources;
export type CreationComb = { game?: string; category?: string; group?: string; data?: Record<string, any> };
const validName = (value: unknown): string =>
    typeof value === 'string' && !['unknown', 'other', ''].includes(value.trim().toLowerCase()) ? value.trim() : '';
export function combGroupLabel(comb: CreationComb): string {
    const data = comb.data ?? {};
    const game = validName(data.game) || validName(comb.game);
    const category = String(data.category || comb.category || (game ? 'game' : 'application')).toLowerCase();
    const categories: Record<string, string> = {
        web: 'Web Hosting',
        runtime: 'Runtimes',
        bot: 'Bots',
        database: 'Databases',
        voice: 'Voice Servers',
        application: 'Applications',
    };
    if (category !== 'game') return categories[category] ?? title(category);
    return title(game || validName(data.group) || validName(comb.group) || 'Games');
}
function title(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
        .replace(/^Minecraft$/i, 'Minecraft');
}
export function resourceRecommendations(manifest: Record<string, any>): Partial<ResourceValues> {
    const result: Partial<ResourceValues> = {};
    const source = manifest.recommended_resources;
    if (!source || typeof source !== 'object' || Array.isArray(source)) return result;
    for (const key of Object.keys(defaultResources) as ResourceKey[]) {
        const value = source[key];
        const min = key === 'swap_mb' ? -1 : key === 'io_weight' ? 10 : 0;
        const max = key === 'cpu_percent' || key === 'io_weight' ? 1000 : Number.MAX_SAFE_INTEGER;
        if (typeof value === 'number' && Number.isSafeInteger(value) && value >= min && value <= max) result[key] = value;
    }
    return result;
}
export function nextResources(
    current: ResourceValues,
    previousAuto: ResourceValues,
    recommendations: Partial<ResourceValues>,
    force = false,
): { values: ResourceValues; automatic: ResourceValues } {
    const automatic = { ...defaultResources, ...recommendations };
    const values = { ...current };
    for (const key of Object.keys(defaultResources) as ResourceKey[]) {
        if (force || current[key] === previousAuto[key]) values[key] = automatic[key];
    }
    return { values, automatic };
}
