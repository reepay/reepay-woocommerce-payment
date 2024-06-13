import { ILogFile } from '@/types/WpLog'
import React from 'react'
import moment from 'moment'
import { filesize } from 'filesize'
import ResetButton from '@/components/ResetButton'
import { twJoin } from 'tailwind-merge'
import { isEven } from '@/utils/number'
import { twm } from '@/utils/twm'

type FileProps = {
    file: ILogFile
    fileIndex: number
    onOpen: () => void
}

const File: React.FC<FileProps> = ({ file, fileIndex, onOpen }) => {
    return (
        <>
            <tr
                className={twm(
                    twJoin(
                        'bw-dark:bg-gray-800 bw-dark:border-gray-700 bw-hover:bg-gray-50 bw-dark:hover:bg-gray-600 bw-border-b bw-bg-gray-100',
                        isEven(fileIndex) && 'bw-bg-white',
                    ),
                )}
            >
                <th
                    scope="row"
                    className={twJoin(
                        'bw-dark:text-white bw-whitespace-nowrap bw-px-6 bw-py-2 bw-font-medium bw-text-gray-900',
                    )}
                >
                    <ResetButton
                        className={twJoin(
                            'bw-cursor-pointer bw-font-bold bw-text-blue-600',
                        )}
                        onClick={() => onOpen()}
                    >
                        {file.name}
                    </ResetButton>
                </th>
                <td className={twJoin('bw-px-6 bw-py-2')}>
                    {moment(file.created, moment.ISO_8601).format('YYYY-MM-DD')}
                </td>
                <td className={twJoin('bw-px-6 bw-py-2')}>
                    {moment(file.modified, moment.ISO_8601).format(
                        'YYYY-MM-DD HH:mm:ss',
                    )}
                </td>
                <td className={twJoin('bw-px-6 bw-py-2')}>
                    {filesize(file.size, { round: 0 })}
                </td>
            </tr>
        </>
    )
}

export default File
