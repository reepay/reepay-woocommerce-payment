import { Models } from '@rematch/core'
import { metaFieldModel } from '@/admin/meta-fields/store/models/metaFieldModel'

export interface RootModel extends Models<RootModel> {
    metaField: typeof metaFieldModel
}

export const models: RootModel = { metaField: metaFieldModel }
