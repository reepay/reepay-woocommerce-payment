import React from 'react'
import clsx from 'clsx'

const iconBillwerk: React.FC = () => {
    return (
        <div
            className={clsx(
                'bw-h-[30px] bw-w-[30px] bw-flex-auto bw-flex-shrink-0 bw-flex-grow-0 bw-overflow-hidden bw-rounded',
            )}
        >
            <img
                className={clsx('bw-h-auto bw-w-full')}
                src={`${window.BILLWERK_SETTINGS.urlViteAssets}images/iconBillwerk.png`}
                alt="Billwerk field"
            />
        </div>
    )
}

export default iconBillwerk
