jQuery(document).ready(function ($) {
    $(document).on('click', '#reepay_capture', function (e) {
        e.preventDefault();
        var nonce = $(this).data('nonce');
        var order_id = $(this).data('order-id');
        var self = $(this);

        $.ajax({
            url: Reepay_Admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reepay_capture',
                nonce: nonce,
                order_id: order_id
            },
            beforeSend: function () {
                self.data('text', self.html());
                self.html(Reepay_Admin.text_wait);
                self.prop('disabled', true);
            },
            success: function (response) {
                self.html(self.data('text'));
                self.prop('disabled', false);
                if (!response.success) {
                    return false;
                }

                window.location.href = location.href;
            }
        });
    });

    $(document).on('click', '#reepay_cancel', function (e) {
        e.preventDefault();
        var nonce = $(this).data('nonce');
        var order_id = $(this).data('order-id');
        var self = $(this);
        $.ajax({
            url: Reepay_Admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reepay_cancel',
                nonce: nonce,
                order_id: order_id
            },
            beforeSend: function () {
                self.data('text', self.html());
                self.html(Reepay_Admin.text_wait);
                self.prop('disabled', true);
            },
            success: function (response) {
                self.html(self.data('text'));
                self.prop('disabled', false);
                /*
                if (!response.success) {
                    alert(response.data);
                    return false;
                }
                */
                window.location.href = location.href;
            }
        });
    });

    $(document).on('click', '#reepay_refund', function (e) {
        e.preventDefault();
        var nonce = $(this).data('nonce');
        var order_id = $(this).data('order-id');
        var amount = $(this).data('amount');
        var self = $(this);
        $.ajax({
            url: Reepay_Admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reepay_refund',
                nonce: nonce,
                order_id: order_id,
                amount: amount
            },
            beforeSend: function () {
                self.data('text', self.html());
                self.html(Reepay_Admin.text_wait);
                self.prop('disabled', true);
            },
            success: function (response) {
                self.html(self.data('text'));
                self.prop('disabled', false);
                if (!response.success) {
                    alert(response.data);
                    return false;
                }

                window.location.href = location.href;
            },
            error: function (response) {
                alert(response);
            }
        });
    });

    $(document).on('click', '#reepay_capture_partly', function (e) {
        e.preventDefault();
        var nonce = $(this).data('nonce');
        var order_id = $(this).data('order-id');
        var amount = $("#reepay-capture_partly_amount-field").val();
        var self = $(this);

        $.ajax({
            url: Reepay_Admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reepay_capture_partly',
                nonce: nonce,
                order_id: order_id,
                amount: amount
            },
            beforeSend: function () {
                self.data('text', self.html());
                self.html(Reepay_Admin.text_wait);
                self.prop('disabled', true);
            },
            success: function (response) {
                self.html(self.data('text'));
                self.prop('disabled', false);
                if (!response.success) {
                    alert(response.data);
                    return false;
                }
                window.location.href = location.href;
            },
            error: function (response) {
                alert("error response: " + JSON.stringify(response));
            }
        });
    });

    $(document).on('click', '#reepay_refund_partly', function (e) {
        console.log('refund_partually');
        e.preventDefault();
        var nonce = $(this).data('nonce');
        var order_id = $(this).data('order-id');
        var amount = $("#reepay-refund_partly_amount-field").val();
        var self = $(this);

        $.ajax({
            url: Reepay_Admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reepay_refund_partly',
                nonce: nonce,
                order_id: order_id,
                amount: amount
            },
            beforeSend: function () {
                self.data('text', self.html());
                self.html(Reepay_Admin.text_wait);
                self.prop('disabled', true);
            },
            success: function (response) {
                self.html(self.data('text'));
                self.prop('disabled', false);
                if (!response.success) {
                    alert(response.data);
                    return false;
                }
                window.location.href = location.href;
            },
            error: function (response) {
                alert("error response: " + JSON.stringify(response));
            }
        });
    });

    $(document).on('click', '#woocommerce-order-actions .button', function (e) {
        e.preventDefault();

        var order_id = $("#reepay_order_id").data('order-id');
        var amount = $('#reepay_order_total').data('order-total');
        var authorized = $('#reepay_order_total_authorized').val();
        var settled = $('#reepay_order_total_settled').val();
        var formatted_amount = $("#reepay_order_total").val();
        if (amount > 0 && settled < authorized && $("#order_status option:selected").val() == 'wc-completed') {
            const settle = window.confirm('You are about to change the order status. Do you want to capture the remaining amount of ' + formatted_amount + ' at the same time? Click OK to continue with settle. Click Cancel to continue without settle.');

            $.ajax({
                url: Reepay_Admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'reepay_set_complete_settle_transient',
                    nonce: Reepay_Admin.nonce,
                    order_id: order_id,
                    settle_order: Number(settle)
                },
                beforeSend: function () {
                },
                success: function () {
                },
                error: function (response) {
                    alert("error response: " + JSON.stringify(response));
                },
                complete: function () {
                    $('#post').submit();
                }
            });

        } else {
            $('#post').submit();
        }
    });

    $('#reepay-capture_partly_amount-field, #reepay-refund_partly_amount-field').inputmask({
        alias: "currency",
        groupSeparator: ''
    });
});