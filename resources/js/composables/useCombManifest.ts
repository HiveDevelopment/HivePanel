import { watch } from 'vue';

type CombForm = {
    external_id: string;
    name: string;
    category: string;
    group: string;
    game: string;
    manifest: string;
    console_commands_text: string;
    stop_command: string;
    stop_timeout: number;
    stop_signal: string;
};

export function useCombManifest(form: CombForm) {
    let synchronising = false;
    let previous: Record<string, unknown> = {};
    const snapshot = () =>
        Object.fromEntries(
            ['external_id', 'name', 'category', 'group', 'game', 'console_commands_text', 'stop_command', 'stop_timeout', 'stop_signal'].map(
                (key) => [key, form[key as keyof CombForm]],
            ),
        );
    const categories = ['game', 'web', 'database', 'application', 'bot', 'voice', 'runtime'];

    function syncFieldsFromManifest() {
        if (synchronising) return;
        try {
            const manifest = JSON.parse(form.manifest);
            if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) return;
            synchronising = true;
            form.external_id = typeof manifest.id === 'string' ? manifest.id : '';
            form.name = typeof manifest.name === 'string' ? manifest.name : '';
            form.category = categories.includes(manifest.category) ? manifest.category : 'application';
            form.group = typeof manifest.group === 'string' ? manifest.group : typeof manifest.game === 'string' ? manifest.game : 'other';
            form.game = typeof manifest.game === 'string' ? manifest.game : '';
            form.console_commands_text = Array.isArray(manifest.console_commands)
                ? manifest.console_commands.filter((value: unknown) => typeof value === 'string').join('\n')
                : '';
            form.stop_command = manifest.stop?.command === null ? '' : typeof manifest.stop?.command === 'string' ? manifest.stop.command : 'stop';
            form.stop_timeout = Number.isInteger(manifest.stop?.timeout) ? manifest.stop.timeout : 30;
            form.stop_signal = typeof manifest.stop?.signal === 'string' ? manifest.stop.signal : 'SIGTERM';
            previous = snapshot();
        } catch {
            // Preserve invalid JSON while the user is typing.
        } finally {
            synchronising = false;
        }
    }

    function syncManifestFromFields() {
        if (synchronising) return;
        try {
            const manifest = JSON.parse(form.manifest);
            if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) return;
            const changed = (key: keyof CombForm) => form[key] !== previous[key];
            for (const [field, key] of [
                ['external_id', 'id'],
                ['name', 'name'],
                ['category', 'category'],
                ['group', 'group'],
            ] as const) {
                if (changed(field)) manifest[key] = form[field];
            }
            if (changed('game')) {
                if (form.game.trim()) manifest.game = form.game.trim();
                else delete manifest.game;
            }
            if (changed('console_commands_text'))
                manifest.console_commands = form.console_commands_text.split(/\r?\n/).filter((value) => value.trim().length > 0);
            const stop = { command: 'stop', timeout: 30, signal: 'SIGTERM', ...(manifest.stop ?? {}) };
            if (changed('stop_command')) stop.command = form.stop_command;
            if (changed('stop_timeout')) stop.timeout = Number(form.stop_timeout);
            if (changed('stop_signal')) stop.signal = form.stop_signal;
            if (['stop_command', 'stop_timeout', 'stop_signal'].some((key) => changed(key as keyof CombForm))) manifest.stop = stop;
            previous = snapshot();
            synchronising = true;
            form.manifest = JSON.stringify(manifest, null, 2);
        } catch {
            // Leave invalid manually edited JSON untouched for validation.
        } finally {
            synchronising = false;
        }
    }

    watch(() => form.manifest, syncFieldsFromManifest, { flush: 'sync' });
    watch(
        () => [
            form.external_id,
            form.name,
            form.category,
            form.group,
            form.game,
            form.console_commands_text,
            form.stop_command,
            form.stop_timeout,
            form.stop_signal,
        ],
        syncManifestFromFields,
        { flush: 'sync' },
    );
    syncFieldsFromManifest();
    return { syncManifestFromFields };
}
