export {}

declare global {
    interface Window {
        BILLWERK_SETTINGS: {
            nonce: string
            metaFieldKeys: string[]
            urlViteAssets: string
        }
    }
}
