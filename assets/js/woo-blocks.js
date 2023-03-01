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
    const {useSelect, useDispatch} = wp.data;
    const {__} = wp.i18n;
    const {getSetting} = wc.wcSettings;
    const {PAYMENT_STORE_KEY} = wc.wcBlocksData;
    const {decodeEntities} = wp.htmlEntities;

    const settings = getSetting(PAYMENT_METHOD_NAME + '_data', {});

    const label = decodeEntities(settings.title) || __('Reepay checkout', 'reepay-checkout-gateway');

    const Tokens = createElement(() => {
        const tokens = settings.tokens.map((token) => createElement(Token, token))

        tokens.push(createElement(Token, {
            id: 'new',
        }))

        return createElement('ul', {
            className: 'reepay-blocks-list'
        }, ...tokens)
    });

    const Token = (token) => {
        const {paymentMethodData} = useSelect((select) => ({
            paymentMethodData: select(PAYMENT_STORE_KEY).getPaymentMethodData()
        }));

        const {__internalSetPaymentMethodData: setPaymentMethodData} = useDispatch(PAYMENT_STORE_KEY);

        const Img = token.image ? createElement('img', {
            'src': token.image,
            'image_alt': token.label,
        }) : null;

        const name = `wc-${PAYMENT_METHOD_NAME}-payment-token`;

        const Radio = createElement('input', {
            'type': 'radio',
            'name': name,
            'id': `${name}-${token.id}`,
            'value': token.id,
            'onChange': (event) => {
                if( paymentMethodData[name] !== token) {
                    console.log(event.target.value)
                    setPaymentMethodData({
                        ...paymentMethodData,
                        [name]: event.target.value
                    })
                }
            }
        })

        const Label = createElement(
            'label',
            {
                'htmlFor': `wc-${PAYMENT_METHOD_NAME}-payment-token-${token.id}`,
            },
            Img,
            'new' === token.id ?
                __('Use a new payment method', 'reepay-checkout-gateway') :
                `${token.masked} ${token.expiry_month}/${token.expiry_year}`
        )

        return createElement(
            'li',
            {
                key: token.id,
                className: 'reepay-blocks-list-item'
            },
            Radio,
            Label,
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
                console.log('onPaymentProcessing', result);
            } );

            const unsubscribe2 = onCheckoutAfterProcessingWithSuccess( async (result) => {
                console.log('onCheckoutAfterProcessingWithSuccess',result);
                const { processingResponse } = result;
                const { paymentDetails } = processingResponse;

                if (paymentDetails.reepay_id && paymentDetails.accept_url) {
                    wc_reepay.buildModalCheckout(paymentDetails.reepay_id, paymentDetails.accept_url);
                }
            } );

            const unsubscribe3 = onCheckoutAfterProcessingWithError( async (result) => {
                console.log('onCheckoutAfterProcessingWithSuccess',result);
            } );

            // Unsubscribes when this component is unmounted.
            return () => {
                unsubscribe1();
                unsubscribe2();
                unsubscribe3();
            };
        }, [
            emitResponse.responseTypes.SUCCESS,
            emitResponse.responseTypes.ERROR,
            emitResponse.responseTypes.FAIL,
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
