import { ClassNameValue, extendTailwindMerge } from 'tailwind-merge'

const twMerge = extendTailwindMerge({ prefix: 'bw-' })

export const twm = (...classLists: ClassNameValue[]): string => {
    return twMerge(classLists)
}
