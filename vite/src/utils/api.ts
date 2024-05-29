import { __ } from '@wordpress/i18n'

export const validationApiData = (data: string | object): boolean => {
    if (typeof data === 'string') {
        throw Error(__('Error api response', 'reepay-checkout-gateway'))
    }
    return true
}
