import { WpApiInstance } from '@/api/wp'
import { IWpDebug } from '@/types/WpDebug'
import { PREFIX } from '@/const'

const namespace = `${PREFIX}/v1`

export const DebugApi = {
    async run(code: string): Promise<IWpDebug> {
        const { data } = await WpApiInstance.post<IWpDebug>(
            `${namespace}/debug/`,
            {
                code,
            },
        )
        return data
    },
}
