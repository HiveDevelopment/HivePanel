import { watch } from 'vue'

type CombForm = {
    external_id: string
    name: string
    category: string
    group: string
    game: string
    manifest: string
    console_commands_text: string
    stop_command: string
    stop_timeout: number
    stop_signal: string
}

export function useCombManifest(form: CombForm) {
    let synchronising = false
    const categories = ['game', 'web', 'database', 'application', 'bot', 'voice', 'runtime']

    function syncFieldsFromManifest() {
        if (synchronising) return
        try {
            const manifest = JSON.parse(form.manifest)
            if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) return
            synchronising = true
            form.external_id = typeof manifest.id === 'string' ? manifest.id : ''
            form.name = typeof manifest.name === 'string' ? manifest.name : ''
            form.category = categories.includes(manifest.category) ? manifest.category : 'application'
            form.group = typeof manifest.group === 'string' ? manifest.group : (typeof manifest.game === 'string' ? manifest.game : 'other')
            form.game = typeof manifest.game === 'string' ? manifest.game : ''
            form.console_commands_text = Array.isArray(manifest.console_commands) ? manifest.console_commands.filter((value: unknown) => typeof value === 'string').join('\n') : ''
            form.stop_command = typeof manifest.stop?.command === 'string' ? manifest.stop.command : 'stop'
            form.stop_timeout = Number.isInteger(manifest.stop?.timeout) ? manifest.stop.timeout : 30
            form.stop_signal = typeof manifest.stop?.signal === 'string' ? manifest.stop.signal : 'SIGTERM'
        } catch {
            // Preserve invalid JSON while the user is typing.
        } finally {
            synchronising = false
        }
    }

    function syncManifestFromFields() {
        if (synchronising) return
        try {
            const manifest = JSON.parse(form.manifest)
            if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) return
            manifest.id = form.external_id
            manifest.name = form.name
            manifest.category = form.category
            manifest.group = form.group
            if (form.game.trim()) manifest.game = form.game.trim()
            else delete manifest.game
            manifest.console_commands = form.console_commands_text.split(/\r?\n/).filter(value => value.trim().length > 0)
            manifest.stop = { command: form.stop_command, timeout: Number(form.stop_timeout), signal: form.stop_signal }
            synchronising = true
            form.manifest = JSON.stringify(manifest, null, 2)
        } catch {
            // Leave invalid manually edited JSON untouched for validation.
        } finally {
            synchronising = false
        }
    }

    watch(() => form.manifest, syncFieldsFromManifest, { flush: 'sync' })
    watch(() => [form.external_id, form.name, form.category, form.group, form.game, form.console_commands_text, form.stop_command, form.stop_timeout, form.stop_signal], syncManifestFromFields, { flush: 'sync' })
    syncFieldsFromManifest()
    return { syncManifestFromFields }
}
