import { WpApiInstance, WpInstance } from '@/api/wp'
import { PREFIX } from '@/const'
import { ILogFile, IDefaultLog } from '@/types/WpLog'
import { validationApiData } from '@/utils/api'
import moment from 'moment'

const namespace = `${PREFIX}/v1`

export const LogApi = {
    async files(): Promise<ILogFile[]> {
        const { data } = await WpApiInstance.post<ILogFile[]>(
            `${namespace}/logs/`,
            {},
        )
        return data
    },
    async logs(logUrl: string): Promise<IDefaultLog[]> {
        const { data } = await WpInstance.get<IDefaultLog[]>(
            `${logUrl}?t=${moment().unix()}`,
        )
        validationApiData(data)
        return data
    },
    async clean(logPath: string): Promise<IDefaultLog[]> {
        const { data } = await WpApiInstance.post<IDefaultLog[]>(
            `${namespace}/logs/clean`,
            {
                logPath,
            },
        )
        validationApiData(data)
        return data
    },
}
