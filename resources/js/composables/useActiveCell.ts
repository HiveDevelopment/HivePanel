import { computed, ref } from 'vue'

export type CellStatus =
    | 'offline'
    | 'starting'
    | 'running'
    | 'stopping'

export type CellInstallStatus =
    | 'pending'
    | 'installing'
    | 'installed'
    | 'failed'
    | null

const activeCell = ref<any>(null)
const activeCellStats = ref<any>(null)
const activeCellStatus = ref<CellStatus>('offline')

let pollTimer: number | undefined
let pollingCellId: string | null = null
let loading = false

function normaliseStatus(status?: string): CellStatus {
    if (
        status === 'running' ||
        status === 'starting' ||
        status === 'stopping'
    ) {
        return status
    }

    return 'offline'
}

function normaliseInstallStatus(
    status?: string | null,
): CellInstallStatus {
    if (
        status === 'pending' ||
        status === 'installing' ||
        status === 'installed' ||
        status === 'failed'
    ) {
        return status
    }

    return null
}

function getInstallStatus(cell: any): CellInstallStatus {
    return normaliseInstallStatus(
        cell?.install_status ??
        cell?.installation_status ??
        null,
    )
}

/**
 * Runtime statistics only make sense once the Cell has
 * completed installation.
 *
 * Older Cells may not expose install_status, so a missing
 * status is allowed for backwards compatibility.
 */
function canPollStats(cell: any): boolean {
    if (!cell?.id) {
        return false
    }

    const installStatus = getInstallStatus(cell)

    if (
        installStatus === 'pending' ||
        installStatus === 'installing' ||
        installStatus === 'failed'
    ) {
        return false
    }

    return true
}

async function refreshActiveCell() {
    if (
        !activeCell.value?.id ||
        !canPollStats(activeCell.value) ||
        loading
    ) {
        return
    }

    loading = true

    try {
        const response = await fetch(
            `/cells/${activeCell.value.id}/stats-json`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        )

        if (!response.ok) {
            return
        }

        const stats = await response.json()

        activeCellStats.value = stats

        activeCellStatus.value = stats.running
            ? 'running'
            : normaliseStatus(
                activeCell.value.status,
            )
    } catch {
        // A temporary Worker/network failure should not
        // destroy the global active Cell state.
    } finally {
        loading = false
    }
}

function startActiveCellPolling() {
    if (!activeCell.value?.id) {
        stopActiveCellPolling()
        return
    }

    if (!canPollStats(activeCell.value)) {
        stopActiveCellPolling()

        activeCellStats.value = null
        activeCellStatus.value = normaliseStatus(
            activeCell.value.status,
        )

        return
    }

    const cellId = activeCell.value.id

    if (
        pollingCellId === cellId &&
        pollTimer
    ) {
        return
    }

    stopActiveCellPolling()

    pollingCellId = cellId

    void refreshActiveCell()

    pollTimer = window.setInterval(
        () => {
            void refreshActiveCell()
        },
        5000,
    )
}

function setActiveCell(
    cell: any,
    status?: CellStatus,
) {
    const previousCellId =
        activeCell.value?.id ?? null

    activeCell.value = cell

    activeCellStatus.value =
        status ??
        normaliseStatus(cell?.status)

    if (
        previousCellId !== cell?.id
    ) {
        activeCellStats.value = null
    }

    if (!cell?.id) {
        stopActiveCellPolling()
        return
    }

    startActiveCellPolling()
}

function stopActiveCellPolling() {
    if (pollTimer !== undefined) {
        window.clearInterval(pollTimer)
    }

    pollTimer = undefined
    pollingCellId = null
    loading = false
}

function updateActiveCellStatus(
    status: CellStatus,
) {
    activeCellStatus.value = status
}

/**
 * Allows installation state to be changed without replacing
 * the entire active Cell object.
 *
 * Useful after installation/retry transitions.
 */
function updateActiveCellInstallStatus(
    status: CellInstallStatus,
) {
    if (!activeCell.value) {
        return
    }

    activeCell.value = {
        ...activeCell.value,
        install_status: status,
    }

    if (canPollStats(activeCell.value)) {
        startActiveCellPolling()
    } else {
        stopActiveCellPolling()
        activeCellStats.value = null
    }
}

export function useActiveCell() {
    return {
        activeCell,
        activeCellStats,

        activeCellStatus: computed(
            () => activeCellStatus.value,
        ),

        setActiveCell,
        refreshActiveCell,
        startActiveCellPolling,
        stopActiveCellPolling,
        updateActiveCellStatus,
        updateActiveCellInstallStatus,
    }
}