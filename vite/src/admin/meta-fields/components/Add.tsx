import { __ } from '@wordpress/i18n'
import React, { useState } from 'react'
import { z } from 'zod'
import { ICreateWpMetaField } from '@/types/WpMetaField'
import { SubmitHandler, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import Th from '@/admin/meta-fields/components/Th'
import IconBillwerk from '@/admin/meta-fields/components/IconBillwerk'
import Textarea from '@/admin/meta-fields/components/Textarea'
import clsx from 'clsx'
import Input from '@/admin/meta-fields/components/Input'

type AddProps = {
    onAdd: (field: ICreateWpMetaField) => Promise<void>
}

const addSchema = z.object({
    key: z.string().min(1),
    value: z.string().min(1),
})

type AddSchemaType = z.infer<typeof addSchema>

const Add: React.FC<AddProps> = ({ onAdd }) => {
    const [openForm, setOpenForm] = useState<boolean>(false)
    const [isBeingAdded, setIsBeingAdded] = useState<boolean>(false)

    const {
        reset,
        watch,
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<AddSchemaType>({ resolver: zodResolver(addSchema) })

    const onSubmit: SubmitHandler<AddSchemaType> = async (data) => {
        setIsBeingAdded(true)
        try {
            await onAdd(data)
            reset()
        } catch (error) {
            console.error(error)
        } finally {
            setIsBeingAdded(false)
        }
    }

    return (
        <>
            <div
                className={clsx(
                    'bw-mt-4 bw-cursor-pointer bw-select-none bw-rounded bw-bg-blue-300 bw-bg-opacity-30 bw-p-2 bw-text-center bw-font-semibold bw-text-sky-700',
                )}
                onClick={() => setOpenForm(!openForm)}
                aria-hidden={true}
            >
                {openForm ? '-' : '+'} {__('Add new meta field')}
            </div>
            {openForm && (
                <>
                    <table
                        className={clsx(
                            'bw-mb-3 bw-mt-4 bw-w-full bw-rounded-md bw-border bw-border-solid bw-border-neutral-200 bw-bg-gray-200',
                        )}
                        cellSpacing={0}
                    >
                        <thead>
                            <tr>
                                <Th>{__('Key', 'reepay-checkout-gateway')}</Th>
                                <Th>
                                    {__('Value', 'reepay-checkout-gateway')}
                                </Th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    className={clsx(
                                        'bw-flex bw-flex-col bw-gap-2 bw-p-2 bw-align-top',
                                    )}
                                >
                                    <div
                                        className={clsx(
                                            'bw-flex bw-items-center bw-gap-1',
                                        )}
                                    >
                                        <Input
                                            register={register}
                                            name={'key'}
                                            errors={errors}
                                        />
                                        {window.BILLWERK_SETTINGS.metaFieldKeys.includes(
                                            watch('key'),
                                        ) && <IconBillwerk />}
                                    </div>
                                </td>
                                <td className={clsx('bw-p-2 bw-align-top')}>
                                    <Textarea
                                        register={register}
                                        name={'value'}
                                        errors={errors}
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button
                        type={'submit'}
                        className={'button'}
                        disabled={isBeingAdded}
                        onClick={handleSubmit(onSubmit)}
                    >
                        {__('Add meta field', 'reepay-checkout-gateway')}
                    </button>
                </>
            )}
        </>
    )
}

export default Add
