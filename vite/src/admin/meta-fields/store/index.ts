import { init, RematchDispatch, RematchRootState } from '@rematch/core'
import immerPlugin from '@rematch/immer'
import loadingPlugin, { ExtraModelsFromLoading } from '@rematch/loading'
import { models, RootModel } from '@/admin/meta-fields/store/models'
import updatedPlugin, { ExtraModelsFromUpdated } from '@rematch/updated'

type FullModel = ExtraModelsFromLoading<RootModel, { type: 'full' }> &
    ExtraModelsFromUpdated<RootModel>

export const store = init<RootModel, FullModel>({
    models,
    plugins: [immerPlugin(), loadingPlugin({ type: 'full' }), updatedPlugin()],
})
export type Store = typeof store
export type Dispatch = RematchDispatch<RootModel>
export type RootState = RematchRootState<RootModel, FullModel>
