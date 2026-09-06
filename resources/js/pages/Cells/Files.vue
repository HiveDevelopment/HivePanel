<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
    Archive,
    ArrowLeft,
    Download,
    File,
    Folder,
    FolderPlus,
    LinkIcon,
    Pencil,
    RefreshCw,
    RotateCcw,
    LogOut,
    Server,
    Trash,
    Upload,
    ChevronDown,
    Check,
    Copy,
    ExternalLink,
    Eye,
    EyeOff,
} from 'lucide-vue-next'

type SftpDetails = {
    enabled: boolean
    host: string
    port: number
    username: string
    has_password: boolean
    password?: string | null
    last_used_at?: string | null
}

type FileManagerMode = 'live' | 'backup'

type BackupMountDetails = {
    id: string
    status: string
    extracted_size?: number | null
    mounted_at?: string | null
    expires_at?: string | null
}

type BackupDetails = {
    id: string
    name: string
}

const props = withDefaults(defineProps<{
    cell: any
    sftp?: SftpDetails | null
    mode?: FileManagerMode
    mount?: BackupMountDetails | null
    backup?: BackupDetails | null
    initialPath?: string
}>(), {
    sftp: null,
    mode: 'live',
    mount: null,
    backup: null,
    initialPath: '',
})

const isBackupMode = computed(() => props.mode === 'backup')
const isReadOnly = computed(() => isBackupMode.value)

const sftpDetails = ref<SftpDetails>({
    enabled: props.sftp?.enabled ?? false,
    host: props.sftp?.host ?? '',
    port: props.sftp?.port ?? 22,
    username: props.sftp?.username ?? '',
    has_password: props.sftp?.has_password ?? false,
    password: props.sftp?.password ?? null,
    last_used_at: props.sftp?.last_used_at ?? null,
})

const generatedSftpPassword = ref<string | null>(
    sftpDetails.value.password ?? null,
)

const generatingSftpPassword = ref(false)
const revokingSftpAccess = ref(false)

type FileEntry = {
    name: string
    path: string
    type?: string
    kind?: string
    is_dir?: boolean
    isDirectory?: boolean
    directory?: boolean
    size?: number
    modified_at?: string
    virtual?: boolean
    virtual_type?: string
    mount_id?: string
    read_only?: boolean
}

type ConfirmAction = 'delete' | 'restore' | 'permanent-delete'
type CreateType = 'file' | 'folder'
type UploadType = 'files' | 'folder' | 'url'

const currentPath = ref(props.initialPath ?? '')
const entries = ref<FileEntry[]>([])
const loading = ref(false)
const error = ref('')
const actionLoading = ref('')
const unmountingBackup = ref(false)
const currentPage = ref(1)

const perPage = ref(250)

const pagination = ref({
    page: 1,
    per_page: 250,
    total: 0,
    total_pages: 1,
    from: 0,
    to: 0,
})

const totalPages = computed(() => pagination.value.total_pages)

const visiblePageNumbers = computed(() => {
    const total = pagination.value.total_pages
    const current = pagination.value.page

    const pages: number[] = []

    const start = Math.max(1, current - 2)
    const end = Math.min(total, current + 2)

    for (let page = start; page <= end; page++) {
        pages.push(page)
    }

    return pages
})

async function goToPage(page: number) {
    if (
        page < 1 ||
        page > pagination.value.total_pages ||
        page === currentPage.value
    ) {
        return
    }

    currentPage.value = page

    await loadFiles(
        currentPath.value,
        true,
    )
}

function previousPage() {
    goToPage(currentPage.value - 1)
}

function nextPage() {
    goToPage(currentPage.value + 1)
}

const confirmOpen = ref(false)
const confirmEntry = ref<FileEntry | null>(null)
const confirmAction = ref<ConfirmAction>('delete')

const createOpen = ref(false)
const createType = ref<CreateType>('file')
const createName = ref('')

const uploadOpen = ref(false)
const uploadType = ref<UploadType>('files')
const uploadUrl = ref('')
const uploadUrlName = ref('')
const draggingUpload = ref(false)

const selectedPaths = ref<string[]>([])
const contextMenuOpen = ref(false)
const contextMenuX = ref(0)
const contextMenuY = ref(0)
const contextEntry = ref<FileEntry | null>(null)

const renameOpen = ref(false)
const renameEntry = ref<FileEntry | null>(null)
const renameName = ref('')

const archiveOpen = ref(false)
const archiveMode = ref<'create' | 'extract'>('create')
const archiveSource = ref<FileEntry | null>(null)
const archiveDestination = ref('')
const archiveFormat = ref<'zip' | 'tar.gz'>('zip')
const archiveOverwrite = ref(false)

const bulkDeleteOpen = ref(false)

const sftpOpen = ref(false)

const fileInput = ref<HTMLInputElement | null>(null)
const folderInput = ref<HTMLInputElement | null>(null)

const toastMessage = ref('')
const toastType = ref<'success' | 'error'>('success')

const cellId = computed(() => props.cell?.id)

let dragDepth = 0

const createMenuOpen = ref(false)
const uploadMenuOpen = ref(false)

const isRecycleBin = computed(() => {
    return currentPath.value === '.recycle_bin' || currentPath.value.startsWith('.recycle_bin/')
})

const selectedEntries = computed(() =>
    entries.value.filter((entry) =>
        selectedPaths.value.includes(entry.path)
    )
)

const selectedCount = computed(() =>
    selectedEntries.value.length
)

const allVisibleSelected = computed(() => {
    const selectable = entries.value.filter(
        (entry) => !isMountedBackupItem(entry)
    )

    return selectable.length > 0
        && selectable.every((entry) =>
            selectedPaths.value.includes(entry.path)
        )
})

const breadcrumbs = computed(() => {
    if (!currentPath.value) return []

    let running = ''

    return currentPath.value.split('/').filter(Boolean).map((part) => {
        running = running ? `${running}/${part}` : part
        return { name: part, path: running }
    })
})

const mountExpiryLabel = computed(() => {
    if (!props.mount?.expires_at) return null

    const date = new Date(props.mount.expires_at)

    if (Number.isNaN(date.getTime())) {
        return null
    }

    return date.toLocaleString()
})

const confirmTitle = computed(() => {
    if (confirmAction.value === 'restore') {
        return isBackupMode.value
            ? 'Restore From Backup'
            : 'Restore File'
    }
    if (confirmAction.value === 'permanent-delete') return 'Delete Forever'
    return 'Move to Recycle Bin'
})

const confirmMessage = computed(() => {
    const name = confirmEntry.value?.name ?? 'this item'

    if (confirmAction.value === 'restore') {
        return isBackupMode.value
            ? `Restore "${name}" into the live server files? Existing files at the same path may be replaced.`
            : `Restore "${name}" back to its original location?`
    }
    if (confirmAction.value === 'permanent-delete') return `Permanently delete "${name}"? This cannot be undone.`

    return `Move "${name}" to the recycle bin?`
})

const confirmButtonText = computed(() => {
    if (confirmAction.value === 'restore') return 'Restore'
    if (confirmAction.value === 'permanent-delete') return 'Delete Forever'
    return 'Move to Recycle Bin'
})

const confirmButtonClass = computed(() => {
    if (confirmAction.value === 'restore') {
        return 'border-status-success bg-status-success text-white hover:brightness-110'
    }

    return 'border-status-danger bg-status-danger text-white hover:brightness-110'
})

const showSftpPassword = ref(false)
const copiedSftpField = ref<string | null>(null)

async function copySftpValue(field: string, value?: string | null) {
    if (!value) return

    await navigator.clipboard.writeText(value)

    copiedSftpField.value = field

    window.setTimeout(() => {
        if (copiedSftpField.value === field) {
            copiedSftpField.value = null
        }
    }, 1500)
}

function launchSftp() {
    const username = encodeURIComponent(
        sftpDetails.value.username ?? '',
    )

    const host = sftpDetails.value.host ?? ''
    const port = sftpDetails.value.port ?? 22

    if (!host) return

    const authentication = username ? `${username}@` : ''

    window.location.href =
        `sftp://${authentication}${host}:${port}`
}

async function generateSftpPassword() {
    if (!cellId.value || generatingSftpPassword.value) {
        return
    }

    generatingSftpPassword.value = true
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/sftp/reset`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            showError(
                await responseError(
                    response,
                    'Failed to generate SFTP password.',
                ),
            )

            return
        }

        const data = await response.json()

        generatedSftpPassword.value = data.password
            ?? data.sftp_password
            ?? null

        sftpDetails.value.username = data.username
            ?? data.sftp_username
            ?? sftpDetails.value.username

        sftpDetails.value.has_password = true
        showSftpPassword.value = true

        showToast('SFTP password generated.')
    } finally {
        generatingSftpPassword.value = false
    }
}

async function revokeSftpAccess() {
    if (!cellId.value || revokingSftpAccess.value) {
        return
    }

    revokingSftpAccess.value = true
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/sftp/revoke`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            showError(
                await responseError(
                    response,
                    'Failed to revoke SFTP access.',
                ),
            )

            return
        }

        sftpDetails.value.has_password = false
        generatedSftpPassword.value = null
        showSftpPassword.value = false

        showToast('SFTP access revoked.')
    } finally {
        revokingSftpAccess.value = false
    }
}

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

async function responseError(response: Response, fallback = 'Something went wrong.') {
    try {
        const data = await response.json()
        return data.message || data.error || fallback
    } catch {
        const text = await response.text()
        return text || fallback
    }
}

function showToast(message: string, type: 'success' | 'error' = 'success') {
    toastMessage.value = message
    toastType.value = type

    setTimeout(() => {
        toastMessage.value = ''
    }, 4500)
}

function showError(message: string) {
    error.value = message
    showToast(message, 'error')
}

function closeConfirm(force = false) {
    if (actionLoading.value && !force) return
    confirmOpen.value = false
    confirmEntry.value = null
}

function isFolder(entry: FileEntry) {
    const type = String(entry.type ?? entry.kind ?? '').toLowerCase()

    return (
        type === 'folder' ||
        type === 'directory' ||
        type === 'dir' ||
        entry.is_dir === true ||
        entry.isDirectory === true ||
        entry.directory === true
    )
}

function fullPath(name: string) {
    return currentPath.value ? `${currentPath.value}/${name}` : name
}

function parentPath(path: string) {
    const parts = path.split('/').filter(Boolean)
    parts.pop()

    return parts.join('/')
}

function fileName(path: string) {
    return path.split('/').filter(Boolean).pop() ?? path
}

function archiveBaseName(name: string) {
    return name
        .replace(/\.tar\.gz$/i, '')
        .replace(/\.tgz$/i, '')
        .replace(/\.zip$/i, '')
}

function isArchive(entry: FileEntry) {
    if (isFolder(entry)) return false

    const name = entry.name.toLowerCase()

    return (
        name.endsWith('.zip')
        || name.endsWith('.tar.gz')
        || name.endsWith('.tgz')
    )
}

function isSelected(entry: FileEntry) {
    return selectedPaths.value.includes(entry.path)
}

function toggleSelection(entry: FileEntry) {
    if (
        isReadOnly.value
        || isRecycleBin.value
        || isMountedBackupItem(entry)
    ) {
        return
    }

    if (isSelected(entry)) {
        selectedPaths.value = selectedPaths.value.filter(
            (path) => path !== entry.path
        )

        return
    }

    selectedPaths.value = [
        ...selectedPaths.value,
        entry.path,
    ]
}

function toggleSelectAll() {
    if (
        isReadOnly.value
        || isRecycleBin.value
    ) {
        return
    }

    const selectable = entries.value.filter(
        (entry) => !isMountedBackupItem(entry)
    )

    if (allVisibleSelected.value) {
        selectedPaths.value = []
        return
    }

    selectedPaths.value = selectable.map(
        (entry) => entry.path
    )
}

function clearSelection() {
    selectedPaths.value = []
}

function closeContextMenu() {
    contextMenuOpen.value = false
    contextEntry.value = null
}

function positionContextMenu(event: MouseEvent) {
    const menuWidth = 220
    const menuHeight = 380
    const padding = 12

    contextMenuX.value = Math.max(
        padding,
        Math.min(
            event.clientX,
            window.innerWidth - menuWidth - padding,
        ),
    )

    contextMenuY.value = Math.max(
        padding,
        Math.min(
            event.clientY,
            window.innerHeight - menuHeight - padding,
        ),
    )
}

function openEntryContextMenu(
    event: MouseEvent,
    entry: FileEntry,
) {
    if (
        !isSelected(entry)
        && !isReadOnly.value
        && !isRecycleBin.value
        && !isMountedBackupItem(entry)
    ) {
        selectedPaths.value = [entry.path]
    }

    if (
        isRecycleBin.value
        || isMountedBackupItem(entry)
    ) {
        selectedPaths.value = []
    }

    contextEntry.value = entry
    positionContextMenu(event)
    contextMenuOpen.value = true
}

function openBackgroundContextMenu(
    event: MouseEvent,
) {
    if (isBackupMode.value) return

    contextEntry.value = null
    positionContextMenu(event)
    contextMenuOpen.value = true
}

function openRename(entry: FileEntry) {
    if (
        isReadOnly.value
        || isRecycleBin.value
        || isMountedBackupItem(entry)
    ) {
        return
    }

    closeContextMenu()
    renameEntry.value = entry
    renameName.value = displayName(entry)
    renameOpen.value = true
}

function closeRename() {
    if (actionLoading.value === 'rename') return

    renameOpen.value = false
    renameEntry.value = null
    renameName.value = ''
}

async function renameItem() {
    if (
        !cellId.value
        || !renameEntry.value
        || !renameName.value.trim()
        || actionLoading.value
    ) {
        return
    }

    const oldPath = renameEntry.value.path
    const parent = parentPath(oldPath)
    const newPath = parent
        ? `${parent}/${renameName.value.trim()}`
        : renameName.value.trim()

    if (oldPath === newPath) {
        closeRename()
        return
    }

    actionLoading.value = 'rename'
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/files/rename`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    old_path: oldPath,
                    new_path: newPath,
                }),
            },
        )

        if (!response.ok) {
            showError(
                await responseError(
                    response,
                    'Failed to rename item.',
                ),
            )
            return
        }

        clearSelection()
        await loadFiles(currentPath.value, true)
        closeRename()
        showToast('Item renamed.')
    } finally {
        actionLoading.value = ''
    }
}

function defaultArchiveDestination(
    paths: string[],
    format: 'zip' | 'tar.gz',
) {
    let name = 'archive'

    if (paths.length === 1) {
        name = archiveBaseName(
            fileName(paths[0])
        )
    }

    const extension = format === 'zip'
        ? '.zip'
        : '.tar.gz'

    return fullPath(`${name}${extension}`)
}

function openCreateArchive(
    entriesToArchive: FileEntry[],
) {
    if (
        isReadOnly.value
        || entriesToArchive.length === 0
    ) {
        return
    }

    closeContextMenu()

    selectedPaths.value = entriesToArchive.map(
        (entry) => entry.path
    )

    archiveMode.value = 'create'
    archiveSource.value = null
    archiveFormat.value = 'zip'
    archiveDestination.value = defaultArchiveDestination(
        selectedPaths.value,
        archiveFormat.value,
    )
    archiveOverwrite.value = false
    archiveOpen.value = true
}

function openExtractArchive(entry: FileEntry) {
    if (
        isReadOnly.value
        || !isArchive(entry)
        || isRecycleBin.value
        || isMountedBackupItem(entry)
    ) {
        return
    }

    closeContextMenu()

    archiveMode.value = 'extract'
    archiveSource.value = entry
    archiveDestination.value = [
        parentPath(entry.path),
        archiveBaseName(entry.name),
    ].filter(Boolean).join('/')
    archiveOverwrite.value = false
    archiveOpen.value = true
}

function closeArchive() {
    if (
        actionLoading.value === 'archive'
        || actionLoading.value === 'extract'
    ) {
        return
    }

    archiveOpen.value = false
    archiveSource.value = null
    archiveDestination.value = ''
    archiveOverwrite.value = false
}

function archiveFormatChanged() {
    if (
        archiveMode.value !== 'create'
        || selectedPaths.value.length === 0
    ) {
        return
    }

    archiveDestination.value = defaultArchiveDestination(
        selectedPaths.value,
        archiveFormat.value,
    )
}

async function submitArchive() {
    if (
        !cellId.value
        || !archiveDestination.value.trim()
        || actionLoading.value
    ) {
        return
    }

    error.value = ''

    if (archiveMode.value === 'create') {
        if (selectedPaths.value.length === 0) return

        actionLoading.value = 'archive'

        try {
            const response = await fetch(
                `/cells/${cellId.value}/files/archive`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        paths: selectedPaths.value,
                        destination:
                            archiveDestination.value.trim(),
                        format: archiveFormat.value,
                    }),
                },
            )

            if (!response.ok) {
                showError(
                    await responseError(
                        response,
                        'Failed to create archive.',
                    ),
                )
                return
            }

            clearSelection()
            await loadFiles(currentPath.value, true)
            closeArchive()
            showToast('Archive created.')
        } finally {
            actionLoading.value = ''
        }

        return
    }

    if (!archiveSource.value) return

    actionLoading.value = 'extract'

    try {
        const response = await fetch(
            `/cells/${cellId.value}/files/extract`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    path: archiveSource.value.path,
                    destination:
                        archiveDestination.value.trim(),
                    overwrite: archiveOverwrite.value,
                }),
            },
        )

        if (!response.ok) {
            showError(
                await responseError(
                    response,
                    'Failed to extract archive.',
                ),
            )
            return
        }

        clearSelection()
        await loadFiles(currentPath.value, true)
        closeArchive()
        showToast('Archive extracted.')
    } finally {
        actionLoading.value = ''
    }
}

function openBulkDelete() {
    if (
        isReadOnly.value
        || selectedEntries.value.length === 0
    ) {
        return
    }

    closeContextMenu()
    bulkDeleteOpen.value = true
}

function closeBulkDelete() {
    if (actionLoading.value === 'bulk-delete') return
    bulkDeleteOpen.value = false
}

async function deleteSelected() {
    if (
        !cellId.value
        || selectedEntries.value.length === 0
        || actionLoading.value
    ) {
        return
    }

    const targets = [...selectedEntries.value]

    actionLoading.value = 'bulk-delete'
    error.value = ''

    try {
        for (const entry of targets) {
            const response = await fetch(
                `/cells/${cellId.value}/files/delete?path=${encodeURIComponent(entry.path)}`,
                {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                },
            )

            if (!response.ok) {
                showError(
                    await responseError(
                        response,
                        `Failed to move ${displayName(entry)} to the recycle bin.`,
                    ),
                )
                return
            }
        }

        clearSelection()
        bulkDeleteOpen.value = false
        await loadFiles(currentPath.value, true)
        showToast(
            targets.length === 1
                ? 'Moved item to recycle bin.'
                : `Moved ${targets.length} items to recycle bin.`,
        )
    } finally {
        actionLoading.value = ''
    }
}

function contextOpenEntry() {
    if (!contextEntry.value) return
    const entry = contextEntry.value

    closeContextMenu()
    openEntry(entry)
}

function contextDownload() {
    if (!contextEntry.value) return
    const entry = contextEntry.value

    closeContextMenu()
    downloadFile(entry)
}

function onGlobalPointerDown(event: MouseEvent) {
    const target = event.target as HTMLElement | null

    if (
        target?.closest?.('[data-file-context-menu]')
    ) {
        return
    }

    closeContextMenu()
}

function onGlobalKeydown(event: KeyboardEvent) {
    if (event.key !== 'Escape') return

    closeContextMenu()
    closeRename()
    closeArchive()

    if (!actionLoading.value) {
        bulkDeleteOpen.value = false
    }
}

function mountedBackupBaseUrl() {
    if (!cellId.value || !props.mount?.id) {
        return ''
    }

    return `/cells/${cellId.value}/backup-mounts/${encodeURIComponent(props.mount.id)}`
}

function filesJsonUrl(params: URLSearchParams) {
    if (isBackupMode.value) {
        return `${mountedBackupBaseUrl()}/files-json?${params.toString()}`
    }

    return `/cells/${cellId.value}/files-json?${params.toString()}`
}

function fileDownloadUrl(entry: FileEntry) {
    const encodedPath = encodeURIComponent(entry.path)

    return `/cells/${cellId.value}/files/download?path=${encodedPath}`
}

function formatBytes(bytes?: number) {
    const value = bytes ?? 0

    if (value >= 1024 * 1024 * 1024) return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
    if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(2)} MB`
    if (value >= 1024) return `${(value / 1024).toFixed(2)} KB`

    return `${value} B`
}

function isRecycleBinEntry(entry: FileEntry) {
    return entry.path === '.recycle_bin' || entry.name === '.recycle_bin'
}

function isMountedBackupItem(entry: FileEntry) {
    return (
        entry.virtual === true
        || entry.read_only === true
        || String(entry.virtual_type ?? '').startsWith('backup_mount')
        || entry.path.startsWith('__backup_mount__/')
    )
}

function displayName(entry: FileEntry) {
    return isRecycleBinEntry(entry) ? 'Recycle Bin' : entry.name
}

function canDeleteEntry(entry: FileEntry) {
    return !isRecycleBinEntry(entry)
}

function hasFiles(event: DragEvent) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

function onWindowDragEnter(event: DragEvent) {
    if (isReadOnly.value || !hasFiles(event)) return

    event.preventDefault()
    dragDepth++
    draggingUpload.value = true
}

function onWindowDragOver(event: DragEvent) {
    if (isReadOnly.value || !hasFiles(event)) return

    event.preventDefault()
    draggingUpload.value = true
}

function onWindowDragLeave(event: DragEvent) {
    if (isReadOnly.value || !hasFiles(event)) return

    event.preventDefault()
    dragDepth = Math.max(0, dragDepth - 1)

    if (dragDepth === 0) {
        draggingUpload.value = false
    }
}

function onWindowDrop(event: DragEvent) {
    if (isReadOnly.value || !hasFiles(event)) return

    event.preventDefault()

    dragDepth = 0
    draggingUpload.value = false

    const files = event.dataTransfer?.files ?? null
    uploadFiles(files, false)
}

function onUploadDrop(event: DragEvent) {
    if (isReadOnly.value) return

    event.preventDefault()

    dragDepth = 0
    draggingUpload.value = false

    const files = event.dataTransfer?.files ?? null
    uploadFiles(files, uploadType.value === 'folder')
}

async function loadFiles(
    path = currentPath.value,
    preservePage = false,
) {
    if (!cellId.value) return

    loading.value = true
    error.value = ''

    const requestedPage = preservePage
        ? currentPage.value
        : 1

    try {
        const params = new URLSearchParams({
            path,
            page: requestedPage.toString(),
            per_page: perPage.value.toString(),
        })

        const response = await fetch(
            filesJsonUrl(params),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        )

        if (!response.ok) {
            error.value = await responseError(
                response,
                'Failed to load files.',
            )

            return
        }

        const data = await response.json()

        currentPath.value = data.path ?? path
        selectedPaths.value = []
        closeContextMenu()
        entries.value = data.files ?? []

        pagination.value = {
            page: data.pagination?.page ?? requestedPage,
            per_page: data.pagination?.per_page ?? perPage.value,
            total: data.pagination?.total ?? entries.value.length,
            total_pages: data.pagination?.total_pages ?? 1,
            from: data.pagination?.from ?? 0,
            to: data.pagination?.to ?? entries.value.length,
        }

        currentPage.value = pagination.value.page
        perPage.value = pagination.value.per_page
    } catch {
        error.value = 'Failed to connect to the worker.'
    } finally {
        loading.value = false
    }
}

function openEntry(entry: FileEntry) {
    if (isFolder(entry)) {
        loadFiles(entry.path)
        return
    }

    if (isBackupMode.value) {
        return
    }

    router.visit(`/cells/${cellId.value}/files/edit?path=${encodeURIComponent(entry.path)}`)
}

function goHome() {
    loadFiles('')
}

function goUp() {
    if (!currentPath.value) return

    const parts = currentPath.value.split('/').filter(Boolean)
    parts.pop()

    loadFiles(parts.join('/'))
}

function downloadFile(entry: FileEntry) {
    if (isBackupMode.value) return

    window.location.href = fileDownloadUrl(entry)
}

async function unmountBackup() {
    if (
        !isBackupMode.value ||
        !cellId.value ||
        !props.mount?.id ||
        unmountingBackup.value
    ) {
        return
    }

    unmountingBackup.value = true
    error.value = ''

    try {
        const response = await fetch(
            mountedBackupBaseUrl(),
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            showError(
                await responseError(
                    response,
                    'Failed to unmount backup.',
                ),
            )

            return
        }

        router.visit(`/cells/${cellId.value}/backups`)
    } catch {
        showError('Failed to connect to the server.')
    } finally {
        unmountingBackup.value = false
    }
}

function openConfirm(action: ConfirmAction, entry: FileEntry) {
    confirmAction.value = action
    confirmEntry.value = entry
    confirmOpen.value = true
}

function openCreate(type: CreateType) {
    createType.value = type
    createName.value = ''
    createOpen.value = true
}

function closeCreate() {
    if (actionLoading.value) return
    createOpen.value = false
    createName.value = ''
}

function openUpload(type: UploadType) {
    uploadType.value = type
    uploadUrl.value = ''
    uploadUrlName.value = ''
    draggingUpload.value = false
    uploadOpen.value = true
}

function closeUpload() {
    if (actionLoading.value) return
    uploadOpen.value = false
    uploadUrl.value = ''
    uploadUrlName.value = ''
    draggingUpload.value = false
}

async function createItem() {
    if (isReadOnly.value || !cellId.value || !createName.value.trim()) return

    const path = fullPath(createName.value.trim())
    const endpoint = createType.value === 'file' ? 'file' : 'folder'

    actionLoading.value = path
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/files/${endpoint}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ path }),
        })

        if (!response.ok) {
            showError(await responseError(response, `Failed to create ${createType.value}.`))
            return
        }

        await loadFiles(currentPath.value, true)
        closeCreate()
        showToast(createType.value === 'file' ? 'File created.' : 'Folder created.')
    } finally {
        actionLoading.value = ''
    }
}

async function uploadFiles(files: FileList | null, folderUpload = false) {
    if (isReadOnly.value || !cellId.value || !files || files.length === 0) return

    actionLoading.value = 'upload'
    error.value = ''

    try {
        for (const file of Array.from(files)) {
            const formData = new FormData()
            formData.append('file', file)

            if (folderUpload) {
                formData.append('relative_path', (file as any).webkitRelativePath || file.name)
            }

            const response = await fetch(`/cells/${cellId.value}/files/upload?path=${encodeURIComponent(currentPath.value)}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            })

            if (!response.ok) {
                showError(await responseError(response, 'Upload failed.'))
                return
            }
        }

        await loadFiles(currentPath.value, true)
        closeUpload()
        showToast(folderUpload ? 'Folder uploaded.' : 'Files uploaded.')
    } finally {
        actionLoading.value = ''
        if (fileInput.value) fileInput.value.value = ''
        if (folderInput.value) folderInput.value.value = ''
    }
}

async function uploadFromUrl() {
    if (isReadOnly.value || !cellId.value || !uploadUrl.value.trim() || !uploadUrlName.value.trim()) return

    const path = fullPath(uploadUrlName.value.trim())

    actionLoading.value = 'upload-url'
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/files/upload-url`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                path,
                url: uploadUrl.value.trim(),
            }),
        })

        if (!response.ok) {
            showError(await responseError(response, 'URL upload failed.'))
            return
        }

        await loadFiles(currentPath.value, true)
        closeUpload()
        showToast('File uploaded from URL.')
    } finally {
        actionLoading.value = ''
    }
}

async function confirmSelectedAction() {
    if (!confirmEntry.value) return

    if (confirmAction.value === 'restore') {
        restoreFile(confirmEntry.value)
        return
    }

    if (confirmAction.value === 'permanent-delete') {
        permanentDeleteFile(confirmEntry.value)
        return
    }

    deleteFile(confirmEntry.value)
}

async function deleteFile(entry: FileEntry) {
    if (isReadOnly.value || !cellId.value) return

    actionLoading.value = entry.path
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/files/delete?path=${encodeURIComponent(entry.path)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            showError(await responseError(response, 'Failed to move item to recycle bin.'))
            return
        }

        await loadFiles(currentPath.value, true)
        closeConfirm(true)
        showToast('Moved to recycle bin.')
    } finally {
        actionLoading.value = ''
    }
}

async function restoreFile(entry: FileEntry) {
    if (!cellId.value) return

    if (isBackupMode.value && !props.mount?.id) {
        showError('This backup mount is unavailable.')
        return
    }

    actionLoading.value = entry.path
    error.value = ''

    try {
        const endpoint = isBackupMode.value
            ? `${mountedBackupBaseUrl()}/restore`
            : `/cells/${cellId.value}/files/restore`

        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ path: entry.path }),
        })

        if (!response.ok) {
            showError(await responseError(
                response,
                isBackupMode.value
                    ? 'Failed to restore item from backup.'
                    : 'Failed to restore item.',
            ))
            return
        }

        if (!isBackupMode.value) {
            await loadFiles(currentPath.value, true)
        }

        closeConfirm(true)
        showToast(
            isBackupMode.value
                ? 'Item restored from backup.'
                : 'Item restored.',
        )
    } finally {
        actionLoading.value = ''
    }
}

async function permanentDeleteFile(entry: FileEntry) {
    if (isReadOnly.value || !cellId.value) return

    actionLoading.value = entry.path
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/files/permanent?path=${encodeURIComponent(entry.path)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            showError(await responseError(response, 'Failed to permanently delete item.'))
            return
        }

        await loadFiles(currentPath.value, true)
        closeConfirm(true)
        showToast('Item permanently deleted.')
    } finally {
        actionLoading.value = ''
    }
}

onMounted(() => {
    loadFiles(props.initialPath ?? '')

    document.addEventListener('mousedown', onGlobalPointerDown)
    window.addEventListener('keydown', onGlobalKeydown)
    window.addEventListener('blur', closeContextMenu)

    if (!isReadOnly.value) {
        window.addEventListener('dragenter', onWindowDragEnter)
        window.addEventListener('dragover', onWindowDragOver)
        window.addEventListener('dragleave', onWindowDragLeave)
        window.addEventListener('drop', onWindowDrop)
    }
})

onUnmounted(() => {
    document.removeEventListener('mousedown', onGlobalPointerDown)
    window.removeEventListener('keydown', onGlobalKeydown)
    window.removeEventListener('blur', closeContextMenu)

    if (!isReadOnly.value) {
        window.removeEventListener('dragenter', onWindowDragEnter)
        window.removeEventListener('dragover', onWindowDragOver)
        window.removeEventListener('dragleave', onWindowDragLeave)
        window.removeEventListener('drop', onWindowDrop)
    }
})
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head
            :title="isBackupMode
                ? `${cell.name} Backup Files`
                : `${cell.name} Files`"
        />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-2xl font-black sm:text-3xl">
                                    {{ isBackupMode ? 'Backup File Browser' : 'File Manager' }}
                                </h1>

                                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-400">
                                    <button class="text-hive hover:text-hive-light" @click="goHome">
                                        Home
                                    </button>

                                    <template v-for="crumb in breadcrumbs" :key="crumb.path">
                                        <span class="text-zinc-600">/</span>

                                        <button class="hover:text-hive" @click="loadFiles(crumb.path)">
                                            {{ crumb.name }}
                                        </button>
                                    </template>

                                    <span
                                        v-if="isRecycleBin"
                                        class="rounded-full border border-status-danger/30 bg-status-danger/10 px-2 py-0.5 text-xs font-bold text-status-danger"
                                    >
                                        Recycle Bin
                                    </span>

                                    <span
                                        v-if="isBackupMode"
                                        class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                    >
                                        {{ backup?.name ?? 'Mounted Backup' }}
                                    </span>

                                    <span
                                        v-if="isBackupMode"
                                        class="rounded-full border border-zinc-700 bg-surface-light px-2 py-0.5 text-xs font-bold text-zinc-400"
                                    >
                                        Read Only
                                    </span>

                                    <span
                                        v-if="isBackupMode && mountExpiryLabel"
                                        class="text-xs font-bold text-zinc-500"
                                    >
                                        Expires {{ mountExpiryLabel }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive" @click="goUp">
                                    <ArrowLeft class="size-4" />
                                    Up
                                </button>

                                <button class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive" @click="loadFiles(currentPath, true)">
                                    <RefreshCw class="size-4" />
                                    Refresh
                                </button>

                                <button
                                    v-if="isBackupMode"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-4 py-2 text-sm font-black text-status-danger transition hover:border-status-danger hover:bg-status-danger/20 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="unmountingBackup"
                                    @click="unmountBackup"
                                >
                                    <LogOut class="size-4" />
                                    {{ unmountingBackup ? 'Unmounting...' : 'Unmount Backup' }}
                                </button>

                                <button
                                    v-if="!isBackupMode"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                    @click="sftpOpen = true"
                                >
                                    <Server class="size-4" />
                                    SFTP
                                </button>

                                <div v-if="!isBackupMode" class="relative">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="createMenuOpen = !createMenuOpen; uploadMenuOpen = false"
                                    >
                                        <FolderPlus class="size-4" />
                                        New
                                        <ChevronDown
                                            class="ml-1 size-4 transition-transform duration-200"
                                            :class="{ 'rotate-180': createMenuOpen }"
                                        />
                                    </button>

                                    <div
                                        v-if="createMenuOpen"
                                        class="absolute right-0 z-30 mt-2 w-44 overflow-hidden rounded-button border border-zinc-800 bg-surface shadow-[0_20px_60px_rgba(0,0,0,0.45)]"
                                    >
                                        <button
                                            class="block w-full px-4 py-3 text-left text-sm font-bold text-zinc-300 hover:bg-hive/10 hover:text-hive"
                                            @click="createMenuOpen = false; openCreate('file')"
                                        >
                                            New File
                                        </button>

                                        <button
                                            class="block w-full px-4 py-3 text-left text-sm font-bold text-zinc-300 hover:bg-hive/10 hover:text-hive"
                                            @click="createMenuOpen = false; openCreate('folder')"
                                        >
                                            New Folder
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!isBackupMode" class="relative">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light"
                                        @click="uploadMenuOpen = !uploadMenuOpen; createMenuOpen = false"
                                    >
                                        <Upload class="size-4" />
                                        Upload
                                        <ChevronDown
                                            class="ml-1 size-4 transition-transform duration-200"
                                            :class="{ 'rotate-180': uploadMenuOpen }"
                                        />
                                    </button>

                                    <div
                                        v-if="uploadMenuOpen"
                                        class="absolute right-0 z-30 mt-2 w-48 overflow-hidden rounded-button border border-zinc-800 bg-surface shadow-[0_20px_60px_rgba(0,0,0,0.45)]"
                                    >
                                        <button
                                            class="block w-full px-4 py-3 text-left text-sm font-bold text-zinc-300 hover:bg-hive/10 hover:text-hive"
                                            @click="uploadMenuOpen = false; openUpload('files')"
                                        >
                                            Upload Files
                                        </button>

                                        <button
                                            class="block w-full px-4 py-3 text-left text-sm font-bold text-zinc-300 hover:bg-hive/10 hover:text-hive"
                                            @click="uploadMenuOpen = false; openUpload('folder')"
                                        >
                                            Upload Folder
                                        </button>

                                        <button
                                            class="block w-full px-4 py-3 text-left text-sm font-bold text-zinc-300 hover:bg-hive/10 hover:text-hive"
                                            @click="uploadMenuOpen = false; openUpload('url')"
                                        >
                                            Upload From URL
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                        @contextmenu.prevent="openBackgroundContextMenu"
                    >
                        <div
                            v-if="!isBackupMode && selectedCount > 0"
                            class="flex flex-col gap-3 border-b border-hive/20 bg-hive/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                        >
                            <div class="text-sm font-black text-hive">
                                {{ selectedCount }} selected
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                    @click="openCreateArchive(selectedEntries)"
                                >
                                    <Archive class="size-4" />
                                    Compress
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-black text-status-danger transition hover:bg-status-danger/20"
                                    @click="openBulkDelete"
                                >
                                    <Trash class="size-4" />
                                    Delete
                                </button>

                                <button
                                    type="button"
                                    class="rounded-button border border-zinc-800 bg-surface px-3 py-2 text-xs font-black text-zinc-400 transition hover:text-white"
                                    @click="clearSelection"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div class="hidden grid-cols-[36px_1fr_120px_170px_130px] border-b border-zinc-800 bg-surface-light px-5 py-3 text-xs font-black uppercase tracking-wide text-zinc-500 sm:grid">
                            <div>
                                <input
                                    v-if="!isBackupMode && !isRecycleBin"
                                    type="checkbox"
                                    class="size-4 accent-hive"
                                    :checked="allVisibleSelected"
                                    @change="toggleSelectAll"
                                />
                            </div>
                            <div>Name</div>
                            <div>Size</div>
                            <div>Modified</div>
                            <div class="text-right">Actions</div>
                        </div>

                        <div v-if="loading" class="p-6 text-zinc-500">
                            Loading files...
                        </div>

                        <div v-else-if="error" class="p-6 font-bold text-status-danger">
                            {{ error }}
                        </div>

                        <div v-else-if="pagination.total === 0" class="p-6 text-zinc-500">
                            This folder is empty.
                        </div>

                        <div
                            v-for="entry in entries"
                            v-else
                            :key="entry.path"
                            class="grid cursor-pointer grid-cols-[1fr_auto] gap-3 border-b border-zinc-900 px-4 py-4 text-sm transition last:border-b-0 hover:bg-surface-hover sm:grid-cols-[36px_1fr_120px_170px_130px] sm:items-center sm:px-5 sm:py-3"
                            :class="{ 'bg-hive/5': isSelected(entry) }"
                            @dblclick="openEntry(entry)"
                            @contextmenu.prevent.stop="openEntryContextMenu($event, entry)"
                        >
                            <div class="hidden sm:block">
                                <input
                                    v-if="!isBackupMode && !isRecycleBin && !isMountedBackupItem(entry)"
                                    type="checkbox"
                                    class="size-4 accent-hive"
                                    :checked="isSelected(entry)"
                                    @click.stop
                                    @change="toggleSelection(entry)"
                                />
                            </div>

                            <div class="flex min-w-0 items-center gap-3">
                                <input
                                    v-if="!isBackupMode && !isRecycleBin && !isMountedBackupItem(entry)"
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-hive sm:hidden"
                                    :checked="isSelected(entry)"
                                    @click.stop
                                    @change="toggleSelection(entry)"
                                />
                                <Trash
                                    v-if="isRecycleBinEntry(entry)"
                                    class="size-5 shrink-0 text-status-danger"
                                />
                                <Folder
                                    v-else-if="isFolder(entry)"
                                    class="size-5 shrink-0 text-hive"
                                />
                                <File
                                    v-else
                                    class="size-5 shrink-0 text-zinc-400"
                                />

                                <button class="truncate text-left font-bold text-zinc-200 hover:text-hive" @click="openEntry(entry)">
                                    {{ displayName(entry) }}
                                </button>
                            </div>

                            <div class="hidden text-zinc-500 sm:block">
                                {{ isFolder(entry) ? '—' : formatBytes(entry.size) }}
                            </div>

                            <div class="hidden text-zinc-500 sm:block">
                                {{ entry.modified_at ?? '—' }}
                            </div>

                            <div class="flex justify-end gap-2">
                                <button
                                    v-if="!isBackupMode && !isFolder(entry)"
                                    class="text-zinc-500 transition hover:text-hive"
                                    title="Download"
                                    @click.stop="downloadFile(entry)"
                                >
                                    <Download class="size-4" />
                                </button>

                                <template v-if="isBackupMode">
                                    <button
                                        class="text-zinc-500 transition hover:text-status-success disabled:opacity-50"
                                        :disabled="actionLoading === entry.path"
                                        title="Restore to live files"
                                        @click.stop="openConfirm('restore', entry)"
                                    >
                                        <RotateCcw class="size-4" />
                                    </button>
                                </template>

                                <template v-else-if="isRecycleBin">
                                    <button class="text-zinc-500 transition hover:text-status-success disabled:opacity-50" :disabled="actionLoading === entry.path" title="Restore" @click.stop="openConfirm('restore', entry)">
                                        <RotateCcw class="size-4" />
                                    </button>

                                    <button class="text-zinc-500 transition hover:text-status-danger disabled:opacity-50" :disabled="actionLoading === entry.path" title="Delete forever" @click.stop="openConfirm('permanent-delete', entry)">
                                        <Trash class="size-4" />
                                    </button>
                                </template>

                                <button
                                    v-else-if="canDeleteEntry(entry)"
                                    class="text-zinc-500 transition hover:text-status-danger disabled:opacity-50"
                                    :disabled="actionLoading === entry.path"
                                    title="Move to recycle bin"
                                    @click.stop="openConfirm('delete', entry)"
                                >
                                    <Trash class="size-4" />
                                </button>
                            </div>

                            <div class="col-span-2 flex flex-wrap gap-3 text-xs text-zinc-500 sm:hidden">
                                <span>{{ isFolder(entry) ? 'Folder' : formatBytes(entry.size) }}</span>
                                <span v-if="entry.modified_at">{{ entry.modified_at }}</span>
                            </div>
                        </div>
                        <div
                            v-if="!loading && !error && pagination.total_pages > 1"
                            class="flex flex-col gap-4 border-t border-zinc-800 bg-surface-light px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="text-sm font-bold text-zinc-500">
                                Showing
                                <span class="text-zinc-300">
                                    {{ pagination.from }}–{{ pagination.to }}
                                </span>
                                of
                                <span class="text-zinc-300">
                                    {{ pagination.total }}
                                </span>
                                items
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-button border border-zinc-800 bg-surface px-3 py-2 text-sm font-bold text-zinc-400 transition hover:border-hive/50 hover:text-hive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="currentPage === 1"
                                    @click="previousPage"
                                >
                                    Previous
                                </button>

                                <button
                                    v-if="visiblePageNumbers[0] > 1"
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-button border border-zinc-800 bg-surface text-sm font-black text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                    @click="goToPage(1)"
                                >
                                    1
                                </button>

                                <span
                                    v-if="visiblePageNumbers[0] > 2"
                                    class="px-1 text-zinc-600"
                                >
                                    …
                                </span>

                                <button
                                    v-for="page in visiblePageNumbers"
                                    :key="page"
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-button border text-sm font-black transition"
                                    :class="page === currentPage
                                        ? 'border-hive bg-hive text-black'
                                        : 'border-zinc-800 bg-surface text-zinc-400 hover:border-hive/50 hover:text-hive'"
                                    @click="goToPage(page)"
                                >
                                    {{ page }}
                                </button>

                                <span
                                    v-if="visiblePageNumbers[visiblePageNumbers.length - 1] < totalPages - 1"
                                    class="px-1 text-zinc-600"
                                >
                                    …
                                </span>

                                <button
                                    v-if="visiblePageNumbers[visiblePageNumbers.length - 1] < totalPages"
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-button border border-zinc-800 bg-surface text-sm font-black text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                    @click="goToPage(totalPages)"
                                >
                                    {{ totalPages }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-button border border-zinc-800 bg-surface px-3 py-2 text-sm font-bold text-zinc-400 transition hover:border-hive/50 hover:text-hive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="currentPage === totalPages"
                                    @click="nextPage"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="contextMenuOpen && !isBackupMode"
            data-file-context-menu
            class="fixed z-[90] w-56 overflow-hidden rounded-button border border-zinc-800 bg-surface shadow-[0_22px_70px_rgba(0,0,0,0.65)]"
            :style="{
                left: `${contextMenuX}px`,
                top: `${contextMenuY}px`,
            }"
            @contextmenu.prevent
        >
            <template v-if="contextEntry">
                <template v-if="isRecycleBin">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-status-success transition hover:bg-status-success/10"
                        @click="closeContextMenu(); openConfirm('restore', contextEntry)"
                    >
                        <RotateCcw class="size-4" />
                        Restore
                    </button>

                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-status-danger transition hover:bg-status-danger/10"
                        @click="closeContextMenu(); openConfirm('permanent-delete', contextEntry)"
                    >
                        <Trash class="size-4" />
                        Delete Forever
                    </button>
                </template>

                <template v-else-if="isMountedBackupItem(contextEntry)">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                        @click="contextOpenEntry"
                    >
                        <Folder v-if="isFolder(contextEntry)" class="size-4" />
                        <File v-else class="size-4" />
                        {{ isFolder(contextEntry) ? 'Open' : 'View' }}
                    </button>
                </template>

                <template v-else>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                        @click="contextOpenEntry"
                    >
                        <Folder v-if="isFolder(contextEntry)" class="size-4" />
                        <File v-else class="size-4" />
                        {{ isFolder(contextEntry) ? 'Open' : 'Edit' }}
                    </button>

                    <button
                        v-if="!isFolder(contextEntry)"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                        @click="contextDownload"
                    >
                        <Download class="size-4" />
                        Download
                    </button>

                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                        @click="openRename(contextEntry)"
                    >
                        <Pencil class="size-4" />
                        Rename
                    </button>

                    <button
                        v-if="isArchive(contextEntry)"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                        @click="openExtractArchive(contextEntry)"
                    >
                        <Archive class="size-4" />
                        Extract
                    </button>

                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                        @click="openCreateArchive(selectedCount > 0 ? selectedEntries : [contextEntry])"
                    >
                        <Archive class="size-4" />
                        {{ selectedCount > 1 ? `Compress ${selectedCount} Items` : 'Compress' }}
                    </button>

                    <div class="my-1 border-t border-zinc-800"></div>

                    <button
                        v-if="canDeleteEntry(contextEntry)"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-status-danger transition hover:bg-status-danger/10"
                        @click="selectedCount > 1 ? openBulkDelete() : (closeContextMenu(), openConfirm('delete', contextEntry))"
                    >
                        <Trash class="size-4" />
                        {{ selectedCount > 1 ? `Delete ${selectedCount} Items` : 'Delete' }}
                    </button>
                </template>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                    @click="closeContextMenu(); openCreate('file')"
                >
                    <File class="size-4" />
                    New File
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                    @click="closeContextMenu(); openCreate('folder')"
                >
                    <FolderPlus class="size-4" />
                    New Folder
                </button>

                <div class="my-1 border-t border-zinc-800"></div>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                    @click="closeContextMenu(); openUpload('files')"
                >
                    <Upload class="size-4" />
                    Upload Files
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                    @click="closeContextMenu(); openUpload('folder')"
                >
                    <Upload class="size-4" />
                    Upload Folder
                </button>

                <div class="my-1 border-t border-zinc-800"></div>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-bold text-zinc-300 transition hover:bg-hive/10 hover:text-hive"
                    @click="closeContextMenu(); loadFiles(currentPath, true)"
                >
                    <RefreshCw class="size-4" />
                    Refresh
                </button>
            </template>
        </div>

        <input v-if="!isBackupMode" ref="fileInput" type="file" class="hidden" multiple @change="uploadFiles(($event.target as HTMLInputElement).files, false)" />
        <input v-if="!isBackupMode" ref="folderInput" type="file" class="hidden" multiple webkitdirectory @change="uploadFiles(($event.target as HTMLInputElement).files, true)" />

        <div
            v-if="renameOpen && renameEntry && !isBackupMode"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="closeRename"
        >
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface p-6 shadow-2xl">
                <h2 class="text-xl font-black text-white">
                    Rename {{ isFolder(renameEntry) ? 'Folder' : 'File' }}
                </h2>

                <p class="mt-2 truncate font-mono text-xs text-zinc-500">
                    {{ renameEntry.path }}
                </p>

                <input
                    v-model="renameName"
                    class="mt-5 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none transition focus:border-hive"
                    @keydown.enter.prevent="renameItem"
                />

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300"
                        :disabled="actionLoading === 'rename'"
                        @click="closeRename"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white disabled:opacity-50"
                        :disabled="actionLoading === 'rename' || !renameName.trim()"
                        @click="renameItem"
                    >
                        {{ actionLoading === 'rename' ? 'Renaming...' : 'Rename' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="archiveOpen && !isBackupMode"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="closeArchive"
        >
            <div class="w-full max-w-lg rounded-panel border border-zinc-800 bg-surface p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-button bg-hive/10 text-hive">
                        <Archive class="size-5" />
                    </div>

                    <div>
                        <h2 class="text-xl font-black text-white">
                            {{ archiveMode === 'create' ? 'Compress Files' : 'Extract Archive' }}
                        </h2>

                        <p class="mt-1 text-sm leading-6 text-zinc-400">
                            <template v-if="archiveMode === 'create'">
                                Create an archive from {{ selectedCount }} selected item{{ selectedCount === 1 ? '' : 's' }}.
                            </template>

                            <template v-else>
                                Extract <span class="font-mono text-zinc-300">{{ archiveSource?.name }}</span> inside this Cell.
                            </template>
                        </p>
                    </div>
                </div>

                <div
                    v-if="archiveMode === 'create'"
                    class="mt-5"
                >
                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                        Format
                    </label>

                    <select
                        v-model="archiveFormat"
                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                        @change="archiveFormatChanged"
                    >
                        <option value="zip">
                            ZIP (.zip)
                        </option>

                        <option value="tar.gz">
                            Tar + Gzip (.tar.gz)
                        </option>
                    </select>
                </div>

                <div class="mt-5">
                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                        Destination
                    </label>

                    <input
                        v-model="archiveDestination"
                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none transition focus:border-hive"
                        :placeholder="archiveMode === 'create' ? 'archive.zip' : 'plugins/example'"
                    />

                    <p class="mt-2 text-xs leading-5 text-zinc-600">
                        Path is relative to the Cell root.
                    </p>
                </div>

                <label
                    v-if="archiveMode === 'extract'"
                    class="mt-5 flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
                >
                    <input
                        v-model="archiveOverwrite"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-hive"
                    />

                    <div>
                        <div class="text-sm font-black text-white">
                            Overwrite existing files
                        </div>

                        <p class="mt-1 text-xs leading-5 text-zinc-500">
                            Disabled by default. Existing files will stop extraction instead of being silently replaced.
                        </p>
                    </div>
                </label>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300"
                        :disabled="actionLoading === 'archive' || actionLoading === 'extract'"
                        @click="closeArchive"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white disabled:opacity-50"
                        :disabled="!!actionLoading || !archiveDestination.trim()"
                        @click="submitArchive"
                    >
                        {{
                            actionLoading === 'archive'
                                ? 'Compressing...'
                                : actionLoading === 'extract'
                                    ? 'Extracting...'
                                    : archiveMode === 'create'
                                        ? 'Compress'
                                        : 'Extract'
                        }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="bulkDeleteOpen && !isBackupMode"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
                <div class="border-b border-zinc-800 px-6 py-5">
                    <h2 class="text-lg font-black text-white">
                        Move {{ selectedCount }} Items to Recycle Bin?
                    </h2>
                </div>

                <div class="px-6 py-5">
                    <p class="text-sm leading-6 text-zinc-400">
                        The selected files and folders will be moved to the Cell recycle bin and can be restored later.
                    </p>
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-800 px-6 py-4">
                    <button
                        type="button"
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 disabled:opacity-50"
                        :disabled="actionLoading === 'bulk-delete'"
                        @click="closeBulkDelete"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-button border border-status-danger bg-status-danger/80 px-4 py-2 text-sm font-black text-white transition hover:bg-status-danger disabled:opacity-50"
                        :disabled="actionLoading === 'bulk-delete'"
                        @click="deleteSelected"
                    >
                        {{ actionLoading === 'bulk-delete' ? 'Moving...' : 'Move to Recycle Bin' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="uploadOpen && !isBackupMode" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface p-6">
                <h2 class="text-xl font-black text-white">
                    {{ uploadType === 'url' ? 'Upload From URL' : uploadType === 'folder' ? 'Upload Folder' : 'Upload Files' }}
                </h2>

                <p class="mt-2 text-sm text-zinc-400">
                    Uploading to: <span class="font-mono text-hive">{{ currentPath || '/' }}</span>
                </p>

                <div v-if="uploadType === 'url'" class="mt-5 space-y-3">
                    <input
                        v-model="uploadUrl"
                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none transition focus:border-hive"
                        placeholder="https://example.com/plugin.jar"
                    />

                    <input
                        v-model="uploadUrlName"
                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none transition focus:border-hive"
                        placeholder="plugins/plugin.jar"
                    />
                </div>

                <div
                    v-else
                    class="mt-5 cursor-pointer rounded-button border border-dashed p-6 text-center transition"
                    :class="draggingUpload
                        ? 'border-hive bg-hive/10'
                        : 'border-zinc-700 bg-surface-light'"
                    @dragover.prevent="draggingUpload = true"
                    @dragleave.prevent="draggingUpload = false"
                    @drop.prevent="onUploadDrop"
                    @click="uploadType === 'folder' ? folderInput?.click() : fileInput?.click()"
                >
                    <Upload class="mx-auto size-8 text-hive" />

                    <p class="mt-3 text-sm text-zinc-400">
                        Drag and drop {{ uploadType === 'folder' ? 'a folder' : 'files' }} here, or click to choose.
                    </p>

                    <p v-if="actionLoading === 'upload'" class="mt-3 text-xs font-bold text-hive">
                        Uploading...
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300" @click="closeUpload">
                        Cancel
                    </button>

                    <button
                        v-if="uploadType === 'files'"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white"
                        @click="fileInput?.click()"
                    >
                        Choose Files
                    </button>

                    <button
                        v-else-if="uploadType === 'folder'"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white"
                        @click="folderInput?.click()"
                    >
                        Choose Folder
                    </button>

                    <button
                        v-else
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white disabled:opacity-50"
                        :disabled="!!actionLoading || !uploadUrl.trim() || !uploadUrlName.trim()"
                        @click="uploadFromUrl"
                    >
                        {{ actionLoading ? 'Uploading...' : 'Upload' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="createOpen && !isBackupMode" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface p-6">
                <h2 class="text-xl font-black text-white">
                    Create {{ createType === 'file' ? 'File' : 'Folder' }}
                </h2>

                <p class="mt-2 text-sm text-zinc-400">
                    Creating in: <span class="font-mono text-hive">{{ currentPath || '/' }}</span>
                </p>

                <input
                    v-model="createName"
                    class="mt-5 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none transition focus:border-hive"
                    :placeholder="createType === 'file' ? 'server.properties' : 'plugins'"
                    @keydown.enter.prevent="createItem"
                />

                <div class="mt-6 flex justify-end gap-3">
                    <button class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300" @click="closeCreate">
                        Cancel
                    </button>

                    <button class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="!!actionLoading || !createName.trim()" @click="createItem">
                        {{ actionLoading ? 'Creating...' : 'Create' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="sftpOpen && !isBackupMode"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="sftpOpen = false"
        >
            <div class="w-full max-w-2xl rounded-panel border border-zinc-800 bg-surface p-6 shadow-2xl">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-button bg-hive/10 text-hive">
                        <Server class="size-6" />
                    </div>

                    <div class="min-w-0">
                        <h2 class="text-xl font-black text-white">
                            SFTP Details
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-zinc-400">
                            Connect to this cell using an SFTP client such as FileZilla,
                            WinSCP, or Cyberduck.
                        </p>
                    </div>
                </div>

                <div
                    v-if="!sftpDetails.enabled"
                    class="mt-6 rounded-button border border-status-warning/30 bg-status-warning/10 p-4"
                >
                    <p class="text-sm font-bold text-status-warning">
                        SFTP is currently disabled on this node.
                    </p>
                </div>

                <div
                    v-else
                    class="mt-6 space-y-3"
                >
                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                            Address
                        </div>

                        <div class="mt-2 flex items-center gap-3">
                            <code class="min-w-0 flex-1 break-all text-sm font-bold text-white">
                                {{ sftpDetails.host }}:{{ sftpDetails.port }}
                            </code>

                            <button
                                type="button"
                                class="flex size-9 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-surface-light text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                title="Copy address"
                                @click="copySftpValue('address', `${sftpDetails.host}:${sftpDetails.port}`)"
                            >
                                <Check
                                    v-if="copiedSftpField === 'address'"
                                    class="size-4 text-status-success"
                                />
                                <Copy
                                    v-else
                                    class="size-4"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                            Username
                        </div>

                        <div class="mt-2 flex items-center gap-3">
                            <code class="min-w-0 flex-1 break-all text-sm font-bold text-white">
                                {{ sftpDetails.username }}
                            </code>

                            <button
                                type="button"
                                class="flex size-9 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-surface-light text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                title="Copy username"
                                @click="copySftpValue('username', sftpDetails.username)"
                            >
                                <Check
                                    v-if="copiedSftpField === 'username'"
                                    class="size-4 text-status-success"
                                />
                                <Copy
                                    v-else
                                    class="size-4"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                            Password
                        </div>

                        <div
                            v-if="generatedSftpPassword"
                            class="mt-2"
                        >
                            <div class="flex items-center gap-3">
                                <code class="min-w-0 flex-1 break-all text-sm font-bold text-white">
                                    {{
                                        showSftpPassword
                                            ? generatedSftpPassword
                                            : '••••••••••••••••••••••••'
                                    }}
                                </code>

                                <button
                                    type="button"
                                    class="flex size-9 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-surface-light text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                    :title="showSftpPassword ? 'Hide password' : 'Show password'"
                                    @click="showSftpPassword = !showSftpPassword"
                                >
                                    <EyeOff
                                        v-if="showSftpPassword"
                                        class="size-4"
                                    />
                                    <Eye
                                        v-else
                                        class="size-4"
                                    />
                                </button>

                                <button
                                    type="button"
                                    class="flex size-9 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-surface-light text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                    title="Copy password"
                                    @click="copySftpValue(
                                        'password',
                                        generatedSftpPassword,
                                    )"
                                >
                                    <Check
                                        v-if="copiedSftpField === 'password'"
                                        class="size-4 text-status-success"
                                    />
                                    <Copy
                                        v-else
                                        class="size-4"
                                    />
                                </button>
                            </div>

                            <p class="mt-3 text-xs font-bold text-status-warning">
                                Copy this password now. It will not be shown again after
                                leaving or refreshing this page.
                            </p>
                        </div>

                        <div
                            v-else-if="sftpDetails.has_password"
                            class="mt-2"
                        >
                            <div class="text-sm font-bold text-white">
                                Password already configured
                            </div>

                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                The existing password cannot be displayed. Generate a new
                                password if you no longer have it.
                            </p>

                            <button
                                type="button"
                                class="mt-4 inline-flex items-center gap-2 rounded-button border border-hive/40 bg-hive/10 px-3 py-2 text-xs font-black text-hive transition hover:bg-hive/20 disabled:opacity-50"
                                :disabled="generatingSftpPassword"
                                @click="generateSftpPassword"
                            >
                                <RefreshCw
                                    class="size-4"
                                    :class="{ 'animate-spin': generatingSftpPassword }"
                                />

                                {{
                                    generatingSftpPassword
                                        ? 'Generating...'
                                        : 'Reset Password'
                                }}
                            </button>
                        </div>

                        <div
                            v-else
                            class="mt-2"
                        >
                            <div class="text-sm font-bold text-status-warning">
                                No SFTP password configured
                            </div>

                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                Generate a persistent password to connect through FileZilla,
                                WinSCP, or Cyberduck.
                            </p>

                            <button
                                type="button"
                                class="mt-4 inline-flex items-center gap-2 rounded-button bg-hive px-3 py-2 text-xs font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                                :disabled="generatingSftpPassword"
                                @click="generateSftpPassword"
                            >
                                <RefreshCw
                                    class="size-4"
                                    :class="{ 'animate-spin': generatingSftpPassword }"
                                />

                                {{
                                    generatingSftpPassword
                                        ? 'Generating...'
                                        : 'Generate Password'
                                }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                            Connection URL
                        </div>

                        <div class="mt-2 flex items-center gap-3">
                            <code class="min-w-0 flex-1 break-all text-sm font-bold text-white">
                                sftp://{{ sftpDetails.username }}@{{ sftpDetails.host }}:{{ sftpDetails.port }}
                            </code>

                            <button
                                type="button"
                                class="flex size-9 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-surface-light text-zinc-400 transition hover:border-hive/50 hover:text-hive"
                                title="Copy connection URL"
                                @click="copySftpValue(
                                    'url',
                                    `sftp://${sftpDetails.username}@${sftpDetails.host}:${sftpDetails.port}`,
                                )"
                            >
                                <Check
                                    v-if="copiedSftpField === 'url'"
                                    class="size-4 text-status-success"
                                />
                                <Copy
                                    v-else
                                    class="size-4"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <button
                        v-if="sftpDetails.has_password"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-4 py-2 text-sm font-black text-status-danger transition hover:bg-status-danger/20 disabled:opacity-50"
                        :disabled="revokingSftpAccess"
                        @click="revokeSftpAccess"
                    >
                        <Trash class="size-4" />
                        {{ revokingSftpAccess ? 'Revoking...' : 'Revoke Access' }}
                    </button>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-zinc-700 hover:text-white"
                            @click="sftpOpen = false"
                        >
                            Close
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                !sftpDetails.enabled ||
                                !sftpDetails.has_password
                            "
                            @click="launchSftp"
                        >
                            <ExternalLink class="size-4" />
                            Launch SFTP
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface p-6 shadow-[0_20px_80px_rgba(0,0,0,0.55)]">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-button" :class="confirmAction === 'restore' ? 'bg-status-success/10 text-status-success' : 'bg-status-danger/10 text-status-danger'">
                        <RotateCcw v-if="confirmAction === 'restore'" class="size-6" />
                        <Trash v-else class="size-6" />
                    </div>

                    <div class="min-w-0">
                        <h2 class="text-xl font-black text-white">
                            {{ confirmTitle }}
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-zinc-400">
                            {{ confirmMessage }}
                        </p>

                        <p v-if="confirmEntry" class="mt-3 break-all rounded-button border border-zinc-800 bg-surface-light px-3 py-2 font-mono text-xs text-zinc-500">
                            {{ confirmEntry.path }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-zinc-600 hover:text-white disabled:opacity-50" :disabled="!!actionLoading" @click="closeConfirm">
                        Cancel
                    </button>

                    <button class="rounded-button border px-4 py-2 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-60" :class="confirmButtonClass" :disabled="!!actionLoading" @click="confirmSelectedAction">
                        {{ actionLoading ? 'Working...' : confirmButtonText }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="draggingUpload && !isBackupMode"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
        >
            <div class="pointer-events-none w-full max-w-md rounded-panel border border-hive bg-surface p-8 text-center shadow-hive">
                <Upload class="mx-auto size-12 text-hive" />

                <h2 class="mt-4 text-xl font-black text-white">
                    Drop files to upload
                </h2>

                <p class="mt-2 text-sm text-zinc-400">
                    Uploading to <span class="font-mono text-hive">{{ currentPath || '/' }}</span>
                </p>
            </div>
        </div>
        
        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] max-w-md rounded-button border px-5 py-3 text-sm font-bold shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
            :class="toastType === 'success'
                ? 'border-status-success/40 bg-status-success/15 text-status-success'
                : 'border-status-danger/40 bg-status-danger/15 text-status-danger'"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>