import axios from 'axios'

export const ApiConfig = {
    baseURL: `/wp-json/`,
    headers: {
        'X-WP-Nonce': window.BILLWERK_SETTINGS.nonce,
    },
}

export const DefaultConfig = {
    headers: {
        'X-WP-Nonce': window.BILLWERK_SETTINGS.nonce,
    },
}

export const WpApiInstance = axios.create(ApiConfig)
export const WpInstance = axios.create(DefaultConfig)
