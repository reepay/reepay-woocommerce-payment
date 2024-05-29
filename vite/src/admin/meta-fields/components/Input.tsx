import {
    FieldErrors,
    FieldValues,
    Path,
    UseFormRegister,
} from 'react-hook-form'
import clsx from 'clsx'
import { HTMLProps } from 'react'

type InputProps<T extends FieldValues> = {
    name: Path<T>
    register: UseFormRegister<T>
    errors: FieldErrors<T>
} & HTMLProps<HTMLInputElement>

const Input = <T extends FieldValues>({
    name,
    register,
    errors,
    ...props
}: InputProps<T>) => {
    return (
        <input
            type="text"
            className={clsx(
                'bw-w-full bw-rounded-md bw-bg-gray-100 bw-px-2 bw-py-2 bw-text-sm focus:bw-bg-white',
                errors[name] && 'bw-border-red-500 bw-shadow-input-error',
                !errors[name] && 'bw-border-gray-300 focus:bw-shadow-none',
            )}
            {...props}
            {...register(name)}
        ></input>
    )
}

export default Input
