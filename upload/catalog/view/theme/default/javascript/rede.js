(function ($) {
    $(document).ready(
        function () {
            $('.formulario').submit(function (e) {
                var active_payment_method = $('input.payment_method:checked').val();
                var form = $('.formulario');

                e.preventDefault();

                form.attr("disabled", "disabled");
                form.toggleClass('disabled');
                $('#submit-spinner').show();

                $.ajax({
                    type: "POST",
                    url: form.attr('action'),
                    dataType: 'json',
                    data: {
                        payment_method: active_payment_method,
                        card_number: $('#' + active_payment_method + '_card_number').val(),
                        holder_name: $('#' + active_payment_method + '_card_holder_name').val(),
                        card_expiration_year: $('#' + active_payment_method + '_card_expiration_year').val(),
                        card_expiration_month: $('#' + active_payment_method + '_card_expiration_month').val(),
                        card_cvv: $('#' + active_payment_method + '_card_cvv').val(),
                        card_installments: $('#' + active_payment_method + '_card_installments').val()
                    },
                    success: function (data) {
                        form.removeAttr("disabled");
                        form.toggleClass('disabled');

                        $('#submit-spinner').hide();

                        if (data.error !== undefined) {
                            alert(data.error);
                        } else if (data.redirect !== undefined) {
                            window.location.href = data.redirect;
                        } else {
                            alert(data);
                        }
                    }
                });
            });

            const show_form = (active) => {
                let active_payment_method = active;
                let inactive_payment_method = active_payment_method === 'credit' ? 'debit' : 'credit';

                $('.payment_method_title .issuers').attr('src', 'catalog/view/theme/default/image/rede_' + active + '.jpg');
                $('.payment_form.' + active_payment_method).show()
                $('.payment_form.' + inactive_payment_method).hide();
            };

            $('input.payment_method').change(function () {
                show_form($(this).val());
            })

            show_form('credit');
        });
})(jQuery);
