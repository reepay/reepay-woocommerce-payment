import { useDispatch, useSelector } from 'react-redux'
import { Dispatch, RootState } from '@/admin/logs-page/store'
import React from 'react'
import Tab from '@/admin/logs-page/components/Tab'
import TabContent from '@/admin/logs-page/components/TabContent'
import { ILogTab } from '@/types/WpLog'
import moment from 'moment'
import { twJoin } from 'tailwind-merge'

const Logs: React.FC = () => {
    const logsState = useSelector((state: RootState) => state.logs)
    const dispatch = useDispatch<Dispatch>()

    const getName = (tab: ILogTab, index: number): string => {
        const fileName = tab.activeFile?.name
        const fileCreated = tab.activeFile?.created
        if (fileName && fileCreated) {
            return `${fileName} (${moment(fileCreated, moment.ISO_8601).format('DD.MM.YYYY')})`
        }
        return fileName ?? (index + 1).toString()
    }

    return (
        <>
            <div className={twJoin('bw-flex bw-gap-2')}>
                {logsState?.tabs.map((tab, index) => (
                    <Tab
                        key={tab.id}
                        active={
                            logsState?.activeTabId ?
                                tab.id === logsState?.activeTabId
                            :   index === 0
                        }
                        name={getName(tab, index)}
                        onClick={() => dispatch.logs.setActiveTabId(tab.id)}
                        onDelete={() => {
                            dispatch.logs.deleteTab(tab.id)
                        }}
                    ></Tab>
                ))}
                <Tab
                    name={'+'}
                    active={false}
                    onClick={() => dispatch.logs.addTab()}
                ></Tab>
            </div>

            <div className={twJoin('bw-mt-4')}>
                {logsState?.tabs.map((tab, index) => (
                    <TabContent
                        tab={tab}
                        key={index}
                    />
                ))}
            </div>
        </>
    )
}

export default Logs
