import { ILog } from '@/types/WpLog'
import React from 'react'
import ResetButton from '@/components/ResetButton'
import { __ } from '@wordpress/i18n'
import {
    ChevronLeftIcon,
    ChevronDoubleUpIcon,
    ChevronDoubleDownIcon,
} from '@heroicons/react/16/solid'
import { twJoin } from 'tailwind-merge'
import LogItem from '@/admin/logs-page/components/LogItem'
import { useDispatch } from 'react-redux'
import { Dispatch, store } from '@/admin/logs-page/store'
import { twm } from '@/utils/twm'

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

    return (
        <>
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
                            logs: activeTab.logs.map((_, index) => index),
                        })
                    }
                />
            </div>
            <div
                className={twJoin(
                    'bw-mt-4 bw-grid bw-grid-cols-1 bw-gap-2 bw-divide-y bw-divide-solid bw-divide-neutral-200',
                )}
            >
                {logs.map((log, index) => {
                    return (
                        <LogItem
                            log={log}
                            logIndex={index}
                            key={index + log.timestamp}
                        />
                    )
                })}
            </div>
        </>
    )
}

export default LogsList
