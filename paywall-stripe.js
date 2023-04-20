const checkout_form = jQuery( 'form.checkout' );
var selected_payment_method = "";
//Jquery plugin events and code.
jQuery(function($) {
    $(document.body)
    .on( 'updated_checkout', function() {
        paywall_using_gateway();

        $('input[name="payment_method"]').change(function(){
            paywall_using_gateway();
        });
    });

    // Create a token or display an error when the form is submitted.
    $(document.body).on('click', '#place_order', async (event) => {
        event.preventDefault();
        if(selected_payment_method === 'stripe_card_element'){
            const {token, error} = await stripe.createToken(card);
            if (error) {
                // Inform the customer that there was an error.
                const errorElement = document.getElementById('card-errors');
                errorElement.textContent = error.message;
            } else {
                // Send the token to your server.
                stripeTokenHandler(token);
            }
        }else{
            checkout_form.submit();
        }
    });
});
//End

const paywall_using_gateway = function(){
    selected_payment_method = jQuery("input[name='payment_method']:checked").val();
    if(selected_payment_method === 'stripe_card_element'){
        if(jQuery('#paywall_stripe_token').length === 0)
        jQuery('#place_order').after('<input type="hidden" name="paywall_stripe_token" id="paywall_stripe_token" value="">');
        if(jQuery('#paywall_last4').length === 0)
        jQuery('#place_order').after('<input type="hidden" name="paywall_last4" id="paywall_last4" value="">');
        jQuery('#place_order').attr('type', "button");
    }else{
        jQuery('#paywall_stripe_token').remove();
        jQuery('#paywall_last4').remove();
        jQuery('#place_order').attr('type', "submit");
    }
}

const stripeTokenHandler = (token) => {
    // Insert the token ID into the form
    jQuery('#paywall_stripe_token').val(token.id);
    // insert the last4
    jQuery('#paywall_last4').val(token.card.last4);

    // Submit the form
    checkout_form.submit();
}

