if (wc && wc.wcBlocksRegistry && React) {
    /**
     * External dependencies
     */
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {__} = wp.i18n;
    const {getSetting} = wc.wcSettings;
    const {decodeEntities} = wp.htmlEntities;

    const PAYMENT_METHOD_NAME = 'reepay_checkout'

    const settings = getSetting('reepay_checkout_data', {});
    const defaultLabel = __(
        'Reepay checkout',
        'reepay-checkout-gateway'
    );
    const label = decodeEntities(settings.title) || defaultLabel;

    /**
     * Content component
     */

    const Content = React.createElement(
        'span',
        null,
        decodeEntities(settings.description || '')
        )

    /**
     * Label component
     *
     * @param {*} props Props from payment API.
     */
    const Label = React.createElement('span', null, label)

    /**
     * Payment method config object.
     */
    const bankTransferPaymentMethod = {
        name: PAYMENT_METHOD_NAME,
        label: Label,
        content: Content,
        edit: Content,
        canMakePayment: (data) => {
            // console.log(data);
            return true;
        },
        ariaLabel: label,
        supports: {
            features: settings?.supports ?? [],
            // registerPaymentMethod: true,
            // showSaveOption: true
        },
    };

    registerPaymentMethod(bankTransferPaymentMethod);
}
