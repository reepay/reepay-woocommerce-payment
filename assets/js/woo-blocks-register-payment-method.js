if (wc && wc.wcBlocksRegistry && React) {
    /**
     * External dependencies
     */
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {__} = wp.i18n;
    const {getSetting} = wc.wcSettings;
    const {decodeEntities} = wp.htmlEntities;

    const PAYMENT_METHOD_NAME = 'reepay'

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
        '',
        null,
        decodeEntities(settings.description || '')
        )

    /**
     * Label component
     *
     * @param {*} props Props from payment API.
     */
    const Label = (props) => {
        const {PaymentMethodLabel} = props.components;
        return React.createElement(PaymentMethodLabel, {text: label}, null)
    };

    /**
     * Payment method config object.
     */
    const bankTransferPaymentMethod = {
        name: PAYMENT_METHOD_NAME,
        label: Label,
        content: Content,
        edit: Content,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings?.supports ?? [],
        },
    };

    registerPaymentMethod(bankTransferPaymentMethod);
}
