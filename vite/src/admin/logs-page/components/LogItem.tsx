import React from 'react'
import { ILog, SelectLogFieldEnum } from '@/types/WpLog'
import { twJoin } from 'tailwind-merge'
import Level from '@/admin/logs-page/components/Level'
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/16/solid'
import ResetButton from '@/components/ResetButton'
import { useDispatch } from 'react-redux'
import { Dispatch, store } from '@/admin/logs-page/store'
import moment from 'moment/moment'
import ReactJson from 'react-json-view'

type LogItemProps = {
    log: ILog
}

const OpenIcon: React.FC<{
    Icon: React.ComponentType<React.SVGProps<SVGSVGElement>>
}> = ({ Icon }) => {
    return (
        <Icon
            className={twJoin(
                'bw-size-4 bw-flex-0-0-auto bw-pr-2 bw-text-blue-500',
            )}
        />
    )
}

const LogItem: React.FC<LogItemProps> = ({ log }) => {
    const dispatch = useDispatch<Dispatch>()
    const activeTabId = store.select.logs.getActiveTabId(store.getState())
    const openLogs = store.select.logs.getActiveOpenLogs(store.getState())

    return (
        <>
            <div
                className={twJoin(
                    'bw-flex bw-flex-col bw-border-l-0 bw-border-r-0 bw-pt-2',
                )}
            >
                <ResetButton
                    className={twJoin(
                        'bw-flex bw-cursor-pointer bw-items-center bw-gap-2',
                    )}
                    onClick={() => {
                        dispatch.logs.toggleOpenLog({
                            tabId: activeTabId,
                            logId: log.id,
                        })
                    }}
                >
                    <Level level={log.level} />
                    <div className="">{log.message}</div>
                    <div className={twJoin('bw-ml-auto bw-flex')}>
                        <div className={twJoin('bw-mr-2 bw-text-gray-400')}>
                            {moment(log.timestamp, moment.ISO_8601).format(
                                'DD.MM.YYYY HH:mm:ss',
                            )}
                        </div>
                        <OpenIcon
                            Icon={
                                !openLogs.includes(log.id) ?
                                    ChevronDownIcon
                                :   ChevronUpIcon
                            }
                        />
                    </div>
                </ResetButton>
                {openLogs.includes(log.id) && (
                    <div
                        className={twJoin(
                            'bw-break-words bw-border-l-0 bw-border-r-0 bw-pt-2',
                        )}
                    >
                        <div className={twJoin('bw-mb-2 bw-flex bw-gap-2')}>
                            {Object.values(SelectLogFieldEnum).map((field) => (
                                <ResetButton
                                    key={field}
                                    className={twJoin(
                                        'bw-cursor-pointer bw-rounded bw-p-2 bw-px-2 bw-py-1 bw-text-center bw-text-xs bw-text-gray-500',
                                        (log.select ?? 'context') === field ?
                                            'bw-bg-gray-200'
                                        :   'bw-border bw-border-solid bw-border-gray-200',
                                    )}
                                    onClick={() => {
                                        dispatch.logs.setSelectInLog({
                                            tabId: activeTabId,
                                            logId: log.id,
                                            field,
                                        })
                                    }}
                                >
                                    {field}
                                </ResetButton>
                            ))}
                        </div>

                        <ReactJson
                            src={log[log.select ?? 'context']}
                            name={log.select ?? 'context'}
                            displayDataTypes={false}
                            displayObjectSize={false}
                            enableClipboard={false}
                            quotesOnKeys={false}
                            style={{
                                wordBreak: 'break-all',
                            }}
                        />
                    </div>
                )}
            </div>
        </>
    )
}

export default LogItem
