import React from 'react'
import { LogLevel } from '@/types/WpLog'
import { twm } from '@/utils/twm'

type LevelProps = {
    level: LogLevel
}

const levelClasses = {
    [LogLevel.ERROR]: 'bw-bg-red-500',
    [LogLevel.INFO]: 'bw-bg-blue-500',
    [LogLevel.WARNING]: 'bw-bg-yellow-500',
    [LogLevel.NOTICE]: 'bw-bg-green-500',
    [LogLevel.DEBUG]: 'bw-bg-gray-500',
}

const Level: React.FC<LevelProps> = ({ level }) => {
    return (
        <>
            <div
                className={twm(
                    'bw-w-[48px] bw-overflow-hidden bw-text-ellipsis bw-text-nowrap bw-rounded bw-px-2 bw-py-1 bw-text-center bw-text-xs bw-text-white',
                    levelClasses[level],
                )}
            >
                {level}
            </div>
        </>
    )
}

export default Level
