import { createModel } from '@rematch/core'
import { RootModel } from '.'
import { ILog, ILogFile, ILogTab, SelectLogFieldEnum } from '@/types/WpLog'
import { v4 as uuidv4 } from 'uuid'
import { LogApi } from '@/api/wp/log'

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
                targetTab.logs = logs.map((log) => ({ id: uuidv4(), ...log }))
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
                logIndex,
                field,
            }: { tabId: string; logIndex: number; field: SelectLogFieldEnum },
        ) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.logs[logIndex].select = field
            }
        },
        setOpenLogs(state, { tabId, logs }: { tabId: string; logs: number[] }) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.openLogs = logs
            }
        },
        toggleOpenLog(
            state,
            { tabId, logIndex }: { tabId: string; logIndex: number },
        ) {
            const targetTab = state.tabs.find((item) => item.id === tabId)
            if (targetTab) {
                targetTab.openLogs =
                    targetTab.openLogs.includes(logIndex) ?
                        targetTab.openLogs.filter((index) => index !== logIndex)
                    :   [...targetTab.openLogs, logIndex]
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
            tabId: string | null
            activeFile?: ILogFile
        }) {
            if (!activeFile || !tabId) return
            const logs = await LogApi.logs(activeFile.url)
            dispatch.logs.setLogs({ tabId, logs })
        },
    }),
})
