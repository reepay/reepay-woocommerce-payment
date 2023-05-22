if (window.wc
    && window.wc.wcBlocksRegistry
    && window.React
    && window.wc_reepay) {
    const PAYMENT_METHOD_NAME = (new URL(document.currentScript.src)).searchParams.get('name');

    if (!PAYMENT_METHOD_NAME) {
        throw 'Script ' + document.currentScript.src + ' - name is undefined';
    }

    /**
     * External dependencies
     */
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {createElement, useEffect, useState, Fragment} = React;
    const {useSelect, useDispatch} = wp.data;
    const {__} = wp.i18n;
    const {getSetting} = wc.wcSettings;
    const {PAYMENT_STORE_KEY} = wc.wcBlocksData;
    const {decodeEntities} = wp.htmlEntities;

    const settings = getSetting(PAYMENT_METHOD_NAME + '_data', {});

    if (settings.cssPath && !document.getElementById('wc-reepay-blocks')) {
        document.head.insertAdjacentHTML('beforeend', `<link rel="stylesheet" href="${settings.cssPath}">`);
    }

    const label = decodeEntities(settings.title) || __('Reepay checkout', 'reepay-checkout-gateway');

    if (settings.tokens) {
        settings.tokens.push({
            id: 'new'
        })
    }

    const Tokens = createElement(() => {
        const [activeToken, setActiveToken] = useState(0)

        const tokens = settings.tokens.map((token) => createElement(Token, {
            token,
            activeToken,
            setActiveToken
        }))

        return createElement(
            'ul',
            {
                className: 'reepay-blocks-list'
            }
            ,
            ...tokens,
            'new' === activeToken ? createElement(
                'li',
                null,
                createElement(TokenSaving)
            ) : null)
    });

    const Token = ({token, activeToken, setActiveToken}) => {
        const {paymentMethodData} = useSelect((select) => ({
            paymentMethodData: select(PAYMENT_STORE_KEY).getPaymentMethodData()
        }));

        const {__internalSetPaymentMethodData: setPaymentMethodData} = useDispatch(PAYMENT_STORE_KEY);

        const name = `wc-${PAYMENT_METHOD_NAME}-payment-token`;

        const Img = token.image ? createElement('img', {
            'src': token.image,
            'image_alt': token.label,
        }) : null;

        const Radio = createElement('input', {
            'type': 'radio',
            'name': name,
            'id': `${name}-${token.id}`,
            'value': token.id,
            'checked': activeToken === token.id,
            'onChange': () => {
                if (paymentMethodData[name] !== token.id) {
                    setPaymentMethodData({
                        ...paymentMethodData,
                        [name]: '' + token.id
                    })

                    setActiveToken(token.id)
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
            Label
        )
    }

    const TokenSaving = () => {
        const [checked, toggleChecked] = useState(settings.always_save_token || false)

        const {paymentMethodData} = useSelect((select) => ({
            paymentMethodData: select(PAYMENT_STORE_KEY).getPaymentMethodData()
        }));

        const {__internalSetPaymentMethodData: setPaymentMethodData} = useDispatch(PAYMENT_STORE_KEY);

        const name = `wc-${PAYMENT_METHOD_NAME}-new-payment-method`;

        const Checkbox = createElement('input', {
            'type': 'checkbox',
            'name': name,
            'id': name,
            'value': true,
            'checked': checked,
            'disabled': settings.always_save_token || false,
            'onChange': !settings.always_save_token ? (e) => {
                if (e.target.checked) {
                    setPaymentMethodData({
                        ...paymentMethodData,
                        [name]: 'true'
                    })
                } else {
                    const temp = {...paymentMethodData}
                    delete temp[name]
                    setPaymentMethodData(temp)
                }

                toggleChecked(!checked)
            } : undefined
        })

        const Label = createElement(
            'label',
            {
                htmlFor: name
            },
            __('Save to account', 'reepay-checkout-gateway')
        )

        return createElement(
            Fragment,
            {},
            Checkbox,
            Label
        )
    }

    /**
     * Content component
     */
    const Content = createElement((props) => {
        const {eventRegistration, emitResponse} = props;
        const {
            onPaymentProcessing,
            onCheckoutAfterProcessingWithSuccess,
            onCheckoutAfterProcessingWithError
        } = eventRegistration;

        useEffect(() => {
            const unsubscribe1 = onPaymentProcessing(async (result) => {
                console.log('onPaymentProcessing', result);
            });

            const unsubscribe2 = onCheckoutAfterProcessingWithSuccess(async (result) => {
                console.log('onCheckoutAfterProcessingWithSuccess', result);
                const {processingResponse} = result;
                const {paymentDetails} = processingResponse;

                if (paymentDetails.reepay_id && paymentDetails.accept_url) {
                    wc_reepay.buildModalCheckout(paymentDetails.reepay_id, paymentDetails.accept_url);
                }
            });

            const unsubscribe3 = onCheckoutAfterProcessingWithError(async (result) => {
                console.log('onCheckoutAfterProcessingWithSuccess', result);
            });

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
        ]);

        if (settings.tokens) {
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
            features: Object.values(settings.supports ?? {}),
            // showSavedCards: settings.supports.includes('cards'),
            // showSaveOption: settings.supports.includes('cards')
        },
    });
}
