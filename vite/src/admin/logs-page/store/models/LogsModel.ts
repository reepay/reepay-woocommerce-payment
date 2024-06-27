import { createModel } from '@rematch/core'
import { RootModel } from '.'
import {
    IDefaultLog,
    ILog,
    ILogFile,
    ILogTab,
    SelectLogFieldEnum,
} from '@/types/WpLog'
import { v4 as uuidv4 } from 'uuid'
import { LogApi } from '@/api/wp/log'
import { reverse } from 'lodash'

type LogsState = {
    activeTabId: string | null
    tabs: ILogTab[]
}

export const logsModel = createModel<RootModel>()({
    state: {
        activeTabId: null,
        tabs: [
            {
                id: uuidv4(),
                filters: [],
                logs: [],
                files: [],
                openLogs: [],
            },
        ],
    } as LogsState,
    reducers: {
        addTab(state) {
            state.tabs = [
                ...state.tabs,
                {
                    id: uuidv4(),
                    filters: [],
                    logs: [],
                    files: [],
                    openLogs: [],
                },
            ]
        },
        setActiveTabId(state, id: string) {
            state.activeTabId = id
        },
        setFiles(
            state,
            { tabId, files }: { tabId: string; files: ILogFile[] },
        ) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.files = files
            }
        },
        setLogs(state, { tabId, logs }: { tabId: string; logs: ILog[] }) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.logs = logs
            }
        },
        setReverseLogs(
            state,
            { tabId, logs }: { tabId: string; logs: IDefaultLog[] },
        ) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (!targetTab) return

            reverse(logs)
            const currentLogsCount = targetTab.logs.length

            const newLogs = logs.map((log) => ({ id: uuidv4(), ...log }))
            if (currentLogsCount > 0) {
                targetTab.logs = [
                    ...newLogs.slice(0, logs.length - currentLogsCount),
                    ...targetTab.logs,
                ]
            } else {
                targetTab.logs = newLogs
            }
        },
        setActiveFile(state, { tab, file }: { tab: ILogTab; file?: ILogFile }) {
            const targetTab = state.tabs.find((item) => item.id === tab.id)
            if (targetTab) {
                targetTab.activeFile = file
                targetTab.openLogs = []
            }
        },
        deleteTab(state, id: string) {
            if (state.tabs.length === 1) return

            state.tabs = state.tabs.filter((tab) => tab.id !== id)
            if (state.activeTabId === id) {
                state.activeTabId = state.tabs[0].id
            }
        },
        setSelectInLog(
            state,
            {
                tabId,
                logId,
                field,
            }: { tabId: string; logId: string; field: SelectLogFieldEnum },
        ) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                const targetLog = targetTab.logs.find(
                    (item) => item.id === logId,
                )
                if (targetLog) {
                    targetLog.select = field
                }
            }
        },
        setOpenLogs(state, { tabId, logs }: { tabId: string; logs: string[] }) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.openLogs = logs
            }
        },
        toggleOpenLog(
            state,
            { tabId, logId }: { tabId: string; logId: string },
        ) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.openLogs =
                    targetTab.openLogs.includes(logId) ?
                        targetTab.openLogs.filter((id) => id !== logId)
                    :   [...targetTab.openLogs, logId]
            }
        },
    },
    selectors: (slice, createSelector) => ({
        getActiveTabId() {
            return createSelector(
                slice,
                (state) => state.activeTabId || state.tabs[0].id,
            )
        },
        getActiveTab() {
            return createSelector(
                slice,
                (state) =>
                    state.tabs.find((tab) => tab.id === state.activeTabId) ||
                    state.tabs[0],
            )
        },
        getActiveOpenLogs: () =>
            createSelector(slice, (state) => {
                const activeTab =
                    state.tabs.find((tab) => tab.id === state.activeTabId) ||
                    state.tabs[0]
                return activeTab.openLogs
            }),
    }),
    effects: (dispatch) => ({
        async fetchFiles({ tabId }: { tabId: string | null }) {
            if (!tabId) return
            const files = await LogApi.files()
            dispatch.logs.setFiles({ tabId, files })
        },
        async fetchLogs({
            tabId,
            activeFile,
        }: {
            tabId?: string
            activeFile?: ILogFile
        }) {
            if (!activeFile || !tabId) return
            const logs = await LogApi.logs(activeFile.url)
            dispatch.logs.setReverseLogs({ tabId, logs })
        },
        async cleanLogs({
            tabId,
            activeFile,
        }: {
            tabId?: string
            activeFile?: ILogFile
        }) {
            if (!activeFile || !tabId) return
            await LogApi.clean(activeFile.path)
            dispatch.logs.setLogs({ tabId, logs: [] })
        },
    }),
})
