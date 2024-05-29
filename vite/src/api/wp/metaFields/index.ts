import { ICreateWpMetaField, IWpMetaField } from '@/types/WpMetaField'
import { validationApiData } from '@/utils/api'
import { PREFIX } from '@/const'
import { WpApiInstance } from '@/api/wp'
import { WpPostTypeEnum } from '@/types/WpPost'

const namespace = `${PREFIX}/v1`

type MetaFieldsGetResponse = IWpMetaField[]

export const MetaFieldsApi = {
    async get(
        postId: number,
        postType: WpPostTypeEnum,
    ): Promise<MetaFieldsGetResponse> {
        const { data } = await WpApiInstance.get<MetaFieldsGetResponse>(
            `${namespace}/meta-fields/${postType}/${postId}`,
        )
        validationApiData(data)
        return data
    },

    async delete(postId: number, postType: WpPostTypeEnum, fieldId: number) {
        const { data } = await WpApiInstance.delete(
            `${namespace}/meta-fields/${postType}/${postId}/${fieldId}`,
        )
        validationApiData(data)
        return data
    },

    async add(
        postId: number,
        postType: WpPostTypeEnum,
        field: ICreateWpMetaField,
    ) {
        const { data } = await WpApiInstance.post(
            `${namespace}/meta-fields/${postType}/${postId}`,
            field,
        )
        validationApiData(data)
        return data
    },

    async update(
        postId: number,
        postType: WpPostTypeEnum,
        field: IWpMetaField,
    ) {
        const { data } = await WpApiInstance.put(
            `${namespace}/meta-fields/${postType}/${postId}/${field.id}`,
            field,
        )
        validationApiData(data)
        return data
    },
}
