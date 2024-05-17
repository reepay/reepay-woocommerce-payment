import { ICreateWpMetaField, IWpMetaField } from '@/types/WpMetaField'
import { createModel } from '@rematch/core'
import { RootModel } from '.'
import { MetaFieldsApi } from '@/api/wp/metaFields'
import { WpPostTypeEnum } from '@/types/WpPost'

type MetaFieldState = {
    metaFields: IWpMetaField[]
}

export const metaFieldModel = createModel<RootModel>()({
    state: {
        metaFields: [],
    } as MetaFieldState,
    reducers: {
        setMetaFieldsInState(state, metaFields: IWpMetaField[]) {
            state.metaFields = metaFields
        },
        deleteMetaFieldInState(state, metaField: IWpMetaField) {
            state.metaFields = state.metaFields.filter(
                (field) => field.id !== metaField.id,
            )
        },
        updateMetaFieldInState(state, metaField: IWpMetaField) {
            state.metaFields = state.metaFields.map((existingField) => {
                if (existingField.id === metaField.id) {
                    return metaField
                }
                return existingField
            })
        },
    },
    effects: (dispatch) => ({
        async fetchMetaFields({
            entityId,
            postType,
        }: {
            entityId: number
            postType: WpPostTypeEnum
        }) {
            const metaFields = await MetaFieldsApi.get(entityId, postType)
            dispatch.metaField.setMetaFieldsInState(metaFields)
        },
        async deleteMetaField({
            field,
            entityId,
            postType,
        }: {
            field: IWpMetaField
            entityId: number
            postType: WpPostTypeEnum
        }) {
            await MetaFieldsApi.delete(entityId, postType, field.id)
            dispatch.metaField.deleteMetaFieldInState(field)
        },

        async addMetaField(
            {
                field,
                entityId,
                postType,
            }: {
                field: ICreateWpMetaField
                entityId: number
                postType: WpPostTypeEnum
            },
            rootState,
        ) {
            const newField = await MetaFieldsApi.add(entityId, postType, field)

            dispatch.metaField.setMetaFieldsInState([
                ...rootState.metaField.metaFields,
                newField,
            ])
        },

        async updateMetaField({
            field,
            entityId,
            postType,
        }: {
            field: IWpMetaField
            entityId: number
            postType: WpPostTypeEnum
        }) {
            const updateField = await MetaFieldsApi.update(
                entityId,
                postType,
                field,
            )

            dispatch.metaField.updateMetaFieldInState(updateField)
        },
    }),
})
