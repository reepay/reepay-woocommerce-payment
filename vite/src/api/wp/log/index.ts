import { WpApiInstance, WpInstance } from '@/api/wp'
import { PREFIX } from '@/const'
import { ILogFile, ILog } from '@/types/WpLog'
import { validationApiData } from '@/utils/api'

const namespace = `${PREFIX}/v1`

export const LogApi = {
    async files(): Promise<ILogFile[]> {
        const { data } = await WpApiInstance.post<ILogFile[]>(
            `${namespace}/logs/`,
            {},
        )
        return data
    },
    async logs(logUrl: string): Promise<ILog[]> {
        const { data } = await WpInstance.get<ILog[]>(logUrl)
        validationApiData(data)
        return data
    },
}
