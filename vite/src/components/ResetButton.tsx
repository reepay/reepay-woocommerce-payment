import React, { HTMLProps, PropsWithChildren } from 'react'
import { twm } from '@/utils/twm'

const ResetButton: React.FC<
    PropsWithChildren<HTMLProps<HTMLButtonElement>>
> = ({ children, className, ...props }) => {
    return (
        <button
            {...props}
            type={'button'}
            className={twm(
                'bw-border-none bw-bg-transparent bw-p-0 bw-text-left',
                className,
            )}
        >
            {children}
        </button>
    )
}

export default ResetButton
