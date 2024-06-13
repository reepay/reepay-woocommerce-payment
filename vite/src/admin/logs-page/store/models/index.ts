import { Models } from '@rematch/core'
import { logsModel } from '@/admin/logs-page/store/models/LogsModel'

export interface RootModel extends Models<RootModel> {
    logs: typeof logsModel
}

export const models: RootModel = { logs: logsModel }
