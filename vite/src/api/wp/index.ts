import axios from 'axios'

export const DefaultConfig = {
    baseURL: `/wp-json/`,
    headers: {
        'X-WP-Nonce': window.BILLWERK_SETTINGS.nonce,
    },
}

export const WpApiInstance = axios.create(DefaultConfig)
