import React, { useEffect } from 'react'
import { useDispatch } from 'react-redux'
import { Dispatch, store } from '@/admin/logs-page/store'
import FilesList from '@/admin/logs-page/components/FilesList'
import LogsList from '@/admin/logs-page/components/LogsList'
import { ILogTab } from '@/types/WpLog'

type TabContentProps = {
    tab: ILogTab
}

const TabContent: React.FC<TabContentProps> = ({ tab }) => {
    const dispatch = useDispatch<Dispatch>()
    const activeTab = store.select.logs.getActiveTab(store.getState())
    const activeFile = tab.activeFile

    useEffect(() => {
        if (activeFile) {
            dispatch.logs.fetchLogs({ tabId: tab.id, activeFile })
        }
    }, [dispatch, tab.id, activeFile])

    if (activeTab.id !== tab.id) return

    return (
        <>
            {!activeFile && <FilesList tab={tab} />}

            {activeFile && (
                <LogsList
                    logs={tab.logs}
                    onClose={() => {
                        dispatch.logs.setActiveFile({
                            tab,
                            file: undefined,
                        })
                    }}
                />
            )}
        </>
    )
}

export default TabContent
