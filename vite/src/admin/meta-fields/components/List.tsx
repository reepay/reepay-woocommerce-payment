import { useDispatch, useSelector } from 'react-redux'
import { Dispatch, RootState } from '@/admin/meta-fields/store'
import React from 'react'
import Item from '@/admin/meta-fields/components/Item'
import { __ } from '@wordpress/i18n'
import { getErrorMessage } from '@/utils/error'
import { WpPostTypeEnum } from '@/types/WpPost'
import Add from '@/admin/meta-fields/components/Add'
import Th from '@/admin/meta-fields/components/Th'
import { IWpMetaField } from '@/types/WpMetaField'
import clsx from 'clsx'

type ListProps = {
    entityId: number
    postType: WpPostTypeEnum
}

const List: React.FC<ListProps> = ({ entityId: entityId, postType }) => {
    const dispatch = useDispatch<Dispatch>()
    const metaFieldState = useSelector((state: RootState) => state.metaField)

    const { loading, error } = useSelector(
        (rootState: RootState) =>
            rootState.loading.effects.metaField.fetchMetaFields,
    )

    const sortByBillwerkKeys = (a: IWpMetaField, b: IWpMetaField) => {
        const indexA = window.BILLWERK_SETTINGS.metaFieldKeys.indexOf(a.key)
        const indexB = window.BILLWERK_SETTINGS.metaFieldKeys.indexOf(b.key)

        return indexB - indexA
    }

    const sortedMetaFields =
        metaFieldState ?
            [...metaFieldState.metaFields].sort(sortByBillwerkKeys)
        :   []

    return (
        <>
            {loading && (
                <div className={clsx('bw-w-full bw-text-center')}>
                    {__('Loading...', 'reepay-checkout-gateway')}
                </div>
            )}
            {(error as Error) && <div>{getErrorMessage(error)}</div>}
            {!loading && !error && (
                <table
                    className={clsx(
                        { bord: true },
                        'bw-w-full bw-rounded-md bw-border bw-border-solid bw-border-neutral-200 bw-bg-gray-200',
                    )}
                    cellSpacing={0}
                >
                    <thead>
                        <tr>
                            <Th>{__('Key', 'reepay-checkout-gateway')}</Th>
                            <Th>{__('Value', 'reepay-checkout-gateway')}</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {!loading &&
                            sortedMetaFields.map((field) => (
                                <Item
                                    key={field.id}
                                    field={field}
                                    onRemove={async (field) => {
                                        await dispatch.metaField.deleteMetaField(
                                            {
                                                entityId: entityId,
                                                postType: postType,
                                                field,
                                            },
                                        )
                                    }}
                                    onUpdate={async (field) => {
                                        await dispatch.metaField.updateMetaField(
                                            {
                                                entityId: entityId,
                                                postType: postType,
                                                field,
                                            },
                                        )
                                    }}
                                />
                            ))}
                    </tbody>
                </table>
            )}
            <Add
                onAdd={async (field) => {
                    await dispatch.metaField.addMetaField({
                        entityId: entityId,
                        postType: postType,
                        field,
                    })
                }}
            />
        </>
    )
}

export default List
