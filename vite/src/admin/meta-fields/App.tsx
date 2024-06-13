import React from 'react'
import { Provider, useDispatch } from 'react-redux'
import { Dispatch, store } from '@/admin/meta-fields/store'
import List from '@/admin/meta-fields/components/List'
import { WpPostTypeEnum } from '@/types/WpPost'
import MetaBox from '@/admin/meta-fields/components/MetaBox'
import useOnceEffect from '@/hooks/useOnceEffect'
import { __ } from '@wordpress/i18n'

type AppProps = {
    entityId: number
    postType: WpPostTypeEnum
}

const App: React.FC<AppProps> = ({ entityId, postType }) => {
    const listComponent = (
        <List
            entityId={entityId}
            postType={postType}
        />
    )

    const StoreInit: React.FC = () => {
        const dispatch = useDispatch<Dispatch>()

        useOnceEffect(() => {
            dispatch.metaField.fetchMetaFields({
                entityId: entityId,
                postType: postType,
            })
        })

        return <></>
    }

    return (
        <>
            <Provider store={store}>
                <StoreInit />
                {postType === WpPostTypeEnum.User ?
                    <MetaBox
                        title={__(
                            'Debug: User meta fields',
                            'reepay-checkout-gateway',
                        )}
                    >
                        {listComponent}
                    </MetaBox>
                :   listComponent}
            </Provider>
        </>
    )
}

export default App
