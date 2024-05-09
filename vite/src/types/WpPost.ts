export enum WpPostTypeEnum {
    ShopOrder = 'shop_order',
    User = 'user',
}

export const isWpPostType = (value: string): value is WpPostTypeEnum => {
    return Object.values(WpPostTypeEnum).includes(value as WpPostTypeEnum)
}
