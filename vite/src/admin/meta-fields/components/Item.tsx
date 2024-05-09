import React, { useState } from 'react'
import { IWpMetaField } from '@/types/WpMetaField'
import { __ } from '@wordpress/i18n'
import { z } from 'zod'
import { SubmitHandler, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import IconBillwerk from '@/admin/meta-fields/components/IconBillwerk'
import Input from '@/admin/meta-fields/components/Input'
import Textarea from '@/admin/meta-fields/components/Textarea'
import clsx from 'clsx'

type ItemProps = {
    field: IWpMetaField
    onRemove: (field: IWpMetaField) => Promise<void>
    onUpdate: (field: IWpMetaField) => Promise<void>
}

const updateSchema = z.object({
    key: z.string().min(1),
    value: z.string().min(1),
})

type UpdateSchemaType = z.infer<typeof updateSchema>

const Item: React.FC<ItemProps> = ({ field, onRemove, onUpdate }) => {
    const [isBeingRemoved, setIsBeingRemoved] = useState<boolean>(false)
    const [isBeingUpdated, setIsBeingUpdated] = useState<boolean>(false)

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<UpdateSchemaType>({ resolver: zodResolver(updateSchema) })

    const handleRemove = async (field: IWpMetaField) => {
        setIsBeingRemoved(true)
        try {
            await onRemove(field)
        } catch (error) {
            console.error(error)
        } finally {
            setIsBeingRemoved(false)
        }
    }

    const handleUpdate: SubmitHandler<UpdateSchemaType> = async (data) => {
        setIsBeingUpdated(true)
        try {
            await onUpdate({ ...field, key: data.key, value: data.value })
        } catch (error) {
            console.error(error)
        } finally {
            setIsBeingUpdated(false)
        }
    }

    return (
        <tr>
            <td
                className={clsx(
                    'bw-flex bw-flex-col bw-gap-2 bw-px-2 bw-pt-2 bw-align-top',
                )}
            >
                <div className={clsx('bw-flex bw-items-center bw-gap-1')}>
                    <Input
                        defaultValue={field.key}
                        register={register}
                        name={'key'}
                        errors={errors}
                    />
                    {window.BILLWERK_SETTINGS.metaFieldKeys.includes(
                        field.key,
                    ) && <IconBillwerk />}
                </div>

                <div className={clsx('bw-flex bw-justify-end bw-gap-1')}>
                    <button
                        className={clsx('button button-small')}
                        type={'button'}
                        disabled={isBeingUpdated}
                        onClick={handleSubmit(handleUpdate)}
                    >
                        {__('Update', 'reepay-checkout-gateway')}
                    </button>
                    <button
                        className={clsx('button button-small')}
                        type={'button'}
                        disabled={isBeingRemoved}
                        onClick={() => handleRemove(field)}
                    >
                        {__('Delete', 'reepay-checkout-gateway')}
                    </button>
                </div>
            </td>
            <td className={clsx('bw-p-2 bw-align-top')}>
                <Textarea
                    defaultValue={field.value}
                    register={register}
                    name={'value'}
                    errors={errors}
                />
            </td>
        </tr>
    )
}

export default Item
