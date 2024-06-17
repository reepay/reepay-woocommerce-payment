import React, { HTMLProps, PropsWithChildren } from 'react'
import ResetButton from '@/components/ResetButton'
import { clsx } from 'clsx'

const ButtonLink: React.FC<PropsWithChildren<HTMLProps<HTMLButtonElement>>> = ({
    children,
    className,
    ...props
}) => {
    return (
        <ResetButton
            {...props}
            className={clsx(
                'bw-cursor-pointer bw-p-0 bw-text-blue-600 bw-underline',
                className,
            )}
        >
            {children}
        </ResetButton>
    )
}

export default ButtonLink
