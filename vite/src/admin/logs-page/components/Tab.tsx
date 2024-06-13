import React, { ButtonHTMLAttributes } from 'react'
import { XCircleIcon } from '@heroicons/react/16/solid'
import { twJoin } from 'tailwind-merge'

type TabProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    active: boolean
    name: string
    onDelete?: () => void
}

const Tab: React.FC<TabProps> = ({ name, active, onDelete, ...props }) => {
    return (
        <>
            <button
                className={twJoin(
                    'bw-flex bw-cursor-pointer bw-items-center bw-gap-5 bw-rounded bw-border bw-border-solid bw-border-transparent bw-p-2',
                    active && 'bw-bg-blue-500 bw-text-gray-100',
                    !active && 'bw-bg-gray-200 bw-text-gray-600',
                    !onDelete && 'bw-min-w-10 bw-justify-center',
                    onDelete && 'bw-min-w-16 bw-justify-between',
                )}
                {...props}
            >
                {name}

                {onDelete && (
                    <XCircleIcon
                        className={twJoin(
                            'bw-size-4',
                            active && 'bw-text-gray-100',
                            !active && 'bw-text-red-500',
                        )}
                        onClick={(e) => {
                            e.stopPropagation()
                            onDelete()
                        }}
                    />
                )}
            </button>
        </>
    )
}

export default Tab
