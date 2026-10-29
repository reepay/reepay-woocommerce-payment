import { init, Plugin, RematchDispatch, RematchRootState } from '@rematch/core'
import loadingPlugin, { ExtraModelsFromLoading } from '@rematch/loading'
import { produce } from 'immer'
import { models, RootModel } from '@/admin/meta-fields/store/models'
import updatedPlugin, { ExtraModelsFromUpdated } from '@rematch/updated'

type FullModel = ExtraModelsFromLoading<RootModel, { type: 'full' }> &
    ExtraModelsFromUpdated<RootModel>

// @rematch/immer is unmaintained and incompatible with immer's v10+ build
// (it assumes a CJS default export that immer no longer provides), so the
// reducer-wrapping it used to do is reimplemented here against immer directly.
const immerPlugin: Plugin<RootModel, FullModel> = {
    onReducer: (reducer) => (state, action) => {
        if (state === undefined) return reducer(state, action)
        return produce(state, (draft: typeof state) => reducer(draft, action))
    },
}

export const store = init<RootModel, FullModel>({
    models,
    plugins: [immerPlugin, loadingPlugin({ type: 'full' }), updatedPlugin()],
})
export type Store = typeof store
export type Dispatch = RematchDispatch<RootModel>
export type RootState = RematchRootState<RootModel, FullModel>
