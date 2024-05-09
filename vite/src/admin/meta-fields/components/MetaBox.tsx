import React, { PropsWithChildren } from 'react'
import clsx from 'clsx'

type Props = {
    title: string
}

const MetaBox: React.FC<PropsWithChildren<Props>> = ({ children, title }) => {
    return (
        <>
            <div className={clsx('postbox bw-my-12 bw-max-w-[1070px]')}>
                <div className={clsx('postbox-header')}>
                    <h2 className={clsx('bw-m-0 bw-p-2 bw-text-sm')}>
                        {title}
                    </h2>
                </div>
                <div className={clsx('inside')}>{children}</div>
            </div>
        </>
    )
}

export default MetaBox
