if (wc && wc.wcBlocksRegistry && React && wc_reepay) {
    const PAYMENT_METHOD_NAME = (new URL(document.currentScript.src)).searchParams.get('name');

    if (!PAYMENT_METHOD_NAME) {
        throw 'Script ' + document.currentScript.src + ' - name is undefined';
    }

    /**
     * External dependencies
     */
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {createElement, useEffect} = React;
    const {__} = wp.i18n;
    const {getSetting} = wc.wcSettings;
    const {decodeEntities} = wp.htmlEntities;

    const settings = getSetting(PAYMENT_METHOD_NAME + '_data', {});

    const label = decodeEntities(settings.title) || __('Reepay checkout', 'reepay-checkout-gateway');

    const Tokens = createElement((props) => {
        const tokens = settings.tokens.map((token) => createElement(Token, token))

        tokens.push(
            createElement(
                'li',
                {
                    key: 'new',
                    className: 'reepay-blocks-list-item'
                },
                createElement(
                    'input', {
                        'type': 'radio',
                        'name': 'wc-reepay_checkout-payment-token',
                        'value': 'new'
                    }
                ),
                __('Use a new payment method', 'reepay-checkout-gateway')
            )
        )

        return createElement('ul', {
            className: 'reepay-blocks-list'
        }, ...tokens)
    });

    const Token = (token) => {
        const Img = createElement('img', {
            'src': token.image,
            'image_alt': token.label,
        });

        const Radio = createElement('input', {
            'type': 'radio',
            'name': 'wc-reepay_checkout-payment-token',
            'value': token.id
        })

        return createElement(
            'li',
            {
                key: token.id,
                className: 'reepay-blocks-list-item'
            },
            Radio,
            Img,
            `${token.masked} ${token.expiry_month}/${token.expiry_year}`
        )
    }

    /**
     * Content component
     */
    const Content = createElement((props) => {
        const { eventRegistration, emitResponse } = props;
        const { onPaymentProcessing, onCheckoutAfterProcessingWithSuccess,  onCheckoutAfterProcessingWithError} = eventRegistration;

        useEffect( () => {
            const unsubscribe1 = onPaymentProcessing( async (result) => {
            } );

            const unsubscribe2 = onCheckoutAfterProcessingWithSuccess( async (result) => {
                const { processingResponse } = result;
                const { paymentDetails } = processingResponse;

                wc_reepay.buildModalCheckout( paymentDetails.reepay_id, paymentDetails.accept_url );
            } );

            const unsubscribe3 = onCheckoutAfterProcessingWithError( async () => {

            } );

            // Unsubscribes when this component is unmounted.
            return () => {
                unsubscribe1();
                unsubscribe2();
                unsubscribe3();
            };
        }, [
            emitResponse.responseTypes.SUCCESS,
        ] );

        if(settings.tokens) {
            return Tokens;
        } else {
            return decodeEntities(settings.description || "");
        }
    }, null);

    /**
     * Label component
     *
     * @param {*} props Props from payment API.
     */
    const Label = createElement(props => {
        const {PaymentMethodLabel} = props.components;
        return createElement(PaymentMethodLabel, {text: label})
    }, null)

    /**
     * Payment method config.
     */
    registerPaymentMethod({
        name: PAYMENT_METHOD_NAME,
        label: Label,
        content: Content,
        edit: Content,
        savedTokenComponent: null,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports ?? [],
            showSavedCards: settings.supports.includes('cards'),
            showSaveOption: settings.supports.includes('cards')
        },
    });
}
