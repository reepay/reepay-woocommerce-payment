import React, {
    ButtonHTMLAttributes,
    PropsWithChildren,
    useEffect,
    useState,
} from 'react'
import { __ } from '@wordpress/i18n'
import File from '@/admin/logs-page/components/File'
import {
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/react/16/solid'
import { ILogFile, ILogTab } from '@/types/WpLog'
import moment from 'moment/moment'
import { useDispatch } from 'react-redux'
import { Dispatch } from '@/admin/logs-page/store'
import { twJoin } from 'tailwind-merge'

const ITEMS_PER_PAGE = 10

type FilesListProps = {
    tab: ILogTab
}

const Th: React.FC<PropsWithChildren> = ({ children }) => {
    return (
        <th
            scope="col"
            className={twJoin('bw-px-6 bw-py-3')}
        >
            {children}
        </th>
    )
}

const ButtonNavigation: React.FC<
    ButtonHTMLAttributes<HTMLButtonElement> & {
        Icon: React.ComponentType<React.SVGProps<SVGSVGElement>>
    }
> = ({ Icon, disabled, ...props }) => {
    return (
        <button
            {...props}
            disabled={disabled}
            className={twJoin(
                'bw-flex bw-h-8 bw-w-8 bw-items-center bw-justify-center bw-rounded bw-border bw-border-solid',
                !disabled && 'bw-cursor-pointer bw-border-blue-500 bw-bg-white',
                disabled && 'bw-border-gray-500 bw-bg-gray-100 bw-opacity-30',
            )}
        >
            <Icon
                className={twJoin(
                    'bw-size-3',
                    !disabled && 'bw-text-blue-500',
                    disabled && 'bw-text-gray-500',
                )}
            />
        </button>
    )
}

const FilesList: React.FC<FilesListProps> = ({ tab }) => {
    const dispatch = useDispatch<Dispatch>()
    const [currentPage, setCurrentPage] = useState(1)

    useEffect(() => {
        dispatch.logs.fetchFiles({ tabId: tab.id })
    }, [dispatch, tab.id])

    const sortedFiles = [...tab.files].sort(
        (a, b) =>
            moment(b.created, moment.ISO_8601).valueOf() -
            moment(a.created, moment.ISO_8601).valueOf(),
    )

    const indexOfLastFile = currentPage * ITEMS_PER_PAGE
    const indexOfFirstFile = indexOfLastFile - ITEMS_PER_PAGE
    const currentFiles = sortedFiles.slice(indexOfFirstFile, indexOfLastFile)

    const totalPages = Math.ceil(sortedFiles.length / ITEMS_PER_PAGE)

    const handleNextPage = () => {
        if (currentPage < totalPages) {
            setCurrentPage((prevPage) => prevPage + 1)
        }
    }

    const handlePrevPage = () => {
        if (currentPage > 1) {
            setCurrentPage((prevPage) => prevPage - 1)
        }
    }

    const handleFirstPage = () => {
        setCurrentPage(1)
    }

    const handleLastPage = () => {
        setCurrentPage(totalPages)
    }

    const handleOpenFile = (file: ILogFile) => {
        dispatch.logs.setActiveFile({ tab, file })
    }

    return (
        <>
            <div
                className={twJoin(
                    'bw-sm:rounded-lg bw-relative bw-overflow-x-auto',
                )}
            >
                <table
                    className={twJoin(
                        'bw-rtl:text-right bw-dark:text-gray-400 bw-w-full bw-text-left',
                    )}
                    cellSpacing={0}
                >
                    <thead
                        className={twJoin(
                            'bw-bg-gray-100 bw-text-xs bw-uppercase bw-text-gray-700',
                        )}
                    >
                        <tr>
                            <Th>{__('Name', 'reepay-checkout-gateway')}</Th>
                            <Th>
                                {__('Date created', 'reepay-checkout-gateway')}
                            </Th>
                            <Th>
                                {__('Date modified', 'reepay-checkout-gateway')}
                            </Th>
                            <Th>
                                {__('File size', 'reepay-checkout-gateway')}
                            </Th>
                        </tr>
                    </thead>
                    <tbody>
                        {currentFiles.map((file, index) => (
                            <File
                                key={file.name + file.created}
                                file={file}
                                fileIndex={index}
                                onOpen={() => handleOpenFile(file)}
                            />
                        ))}
                    </tbody>
                </table>
                <div
                    className={twJoin(
                        'bw-mt-4 bw-flex bw-items-center bw-justify-end bw-gap-1',
                    )}
                >
                    <span className={twJoin('bw-mr-2')}>
                        {tab.files.length}{' '}
                        {__('items', 'reepay-checkout-gateway')}
                    </span>
                    <ButtonNavigation
                        onClick={handleFirstPage}
                        disabled={currentPage === 1}
                        Icon={ChevronDoubleLeftIcon}
                    />
                    <ButtonNavigation
                        onClick={handlePrevPage}
                        disabled={currentPage === 1}
                        Icon={ChevronLeftIcon}
                    />
                    <span className={twJoin('bw-mx-2')}>
                        {currentPage} of {totalPages}
                    </span>
                    <ButtonNavigation
                        onClick={handleNextPage}
                        disabled={currentPage === totalPages}
                        Icon={ChevronRightIcon}
                    />
                    <ButtonNavigation
                        onClick={handleLastPage}
                        disabled={currentPage === totalPages}
                        Icon={ChevronDoubleRightIcon}
                    />
                </div>
            </div>

            <div className={twJoin('')}></div>
        </>
    )
}

export default FilesList
