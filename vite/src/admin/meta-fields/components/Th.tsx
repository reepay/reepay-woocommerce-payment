import React, { PropsWithChildren } from 'react'
import clsx from 'clsx'

const Th: React.FC<PropsWithChildren> = ({ children }) => {
    return (
        <th
            className={clsx(
                'bw-border-b bw-border-l-0 bw-border-r-0 bw-border-t-0 bw-border-solid bw-border-gray-300 bw-p-2 bw-text-sm bw-font-medium bw-text-gray-600',
            )}
        >
            {children}
        </th>
    )
}

export default Th
