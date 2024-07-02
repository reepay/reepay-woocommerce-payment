import { ILog } from '@/types/WpLog'
import React from 'react'
import ResetButton from '@/components/ResetButton'
import { __ } from '@wordpress/i18n'
import {
    ChevronLeftIcon,
    ChevronDoubleUpIcon,
    ChevronDoubleDownIcon,
    ArrowPathIcon,
    TrashIcon,
} from '@heroicons/react/16/solid'
import { twJoin } from 'tailwind-merge'
import LogItem from '@/admin/logs-page/components/LogItem'
import { useDispatch, useSelector } from 'react-redux'
import { Dispatch, RootState, store } from '@/admin/logs-page/store'
import { twm } from '@/utils/twm'
import { getErrorMessage } from '@/utils/error'

interface LogButtonProps {
    className?: string
    onClick?: () => void
    Icon: React.ComponentType<React.SVGProps<SVGSVGElement>>
    classNameIcon?: string
    label: string
}

const LogButton: React.FC<LogButtonProps> = ({
    className,
    onClick,
    Icon,
    classNameIcon,
    label,
}) => (
    <ResetButton
        className={twJoin(
            'bw-flex bw-cursor-pointer bw-items-center bw-gap-1 bw-rounded bw-bg-gray-200 bw-p-2 bw-text-gray-500',
            className,
        )}
        onClick={onClick}
    >
        <Icon
            className={twm(twJoin('bw-size-3 bw-text-gray-500', classNameIcon))}
        />
        {label}
    </ResetButton>
)

type LogsListProps = {
    logs: ILog[]
    onClose: () => void
}

const LogsList: React.FC<LogsListProps> = ({ logs, onClose }) => {
    const dispatch = useDispatch<Dispatch>()
    const activeTab = store.select.logs.getActiveTab(store.getState())

    const { loading, error } = useSelector(
        (rootState: RootState) => rootState.loading.effects.logs.fetchLogs,
    )

    return (
        <>
            <div className={twJoin('bw-flex bw-gap-2')}>
                <div className={twJoin('bw-flex bw-gap-2')}>
                    <LogButton
                        className={'bw-bg-blue-200 bw-text-blue-500'}
                        classNameIcon={'bw-text-blue-500'}
                        Icon={ChevronLeftIcon}
                        label={__('Close', 'reepay-checkout-gateway')}
                        onClick={() => onClose()}
                    />
                    <LogButton
                        Icon={ChevronDoubleUpIcon}
                        label={__('Collapse all', 'reepay-checkout-gateway')}
                        onClick={() =>
                            dispatch.logs.setOpenLogs({
                                tabId: activeTab.id,
                                logs: [],
                            })
                        }
                    />
                    <LogButton
                        Icon={ChevronDoubleDownIcon}
                        label={__('Expand all', 'reepay-checkout-gateway')}
                        onClick={() =>
                            dispatch.logs.setOpenLogs({
                                tabId: activeTab.id,
                                logs: activeTab.logs.map((log) => log.id),
                            })
                        }
                    />
                    <LogButton
                        Icon={ArrowPathIcon}
                        label={__('Reload', 'reepay-checkout-gateway')}
                        className={'bw-bg-emerald-200 bw-text-emerald-600'}
                        classNameIcon={'bw-text-emerald-600'}
                        onClick={() =>
                            dispatch.logs.fetchLogs({
                                tabId: activeTab.id,
                                activeFile: activeTab.activeFile,
                            })
                        }
                    />
                </div>
                <div
                    className={twJoin(
                        'bw-ml-auto bw-flex bw-items-center bw-gap-3',
                    )}
                >
                    <div className="">{logs.length} entries</div>
                    <LogButton
                        Icon={TrashIcon}
                        label={__('Clean', 'reepay-checkout-gateway')}
                        className={'bw-bg-red-200 bw-text-red-600'}
                        classNameIcon={'bw-text-red-600'}
                        onClick={() =>
                            dispatch.logs.cleanLogs({
                                tabId: activeTab.id,
                                activeFile: activeTab.activeFile,
                            })
                        }
                    />
                </div>
            </div>
            <div
                className={twJoin(
                    'bw-mt-4 bw-grid bw-grid-cols-1 bw-gap-2 bw-divide-y bw-divide-solid bw-divide-neutral-200',
                )}
            >
                {loading && (
                    <div>{__('Loading...', 'reepay-checkout-gateway')}</div>
                )}
                {(error as Error) && <div>{getErrorMessage(error)}</div>}
                {!loading &&
                    !error &&
                    (logs.length > 0 ?
                        logs.map((log, index) => (
                            <LogItem
                                log={log}
                                key={index + log.timestamp}
                            />
                        ))
                    :   <div>{__('Empty', 'reepay-checkout-gateway')}</div>)}
            </div>
        </>
    )
}

export default LogsList
