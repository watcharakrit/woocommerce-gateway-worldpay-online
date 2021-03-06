    <script type="text/javascript">

        (function ( $ ) {

        var $form   = $( 'form#add_payment_method' );
            ccForm  = $( '#WC_Gateway_Worldpay-cc-form' );

            function worldpayFormHandler() {

                // Check for a token
                if ( 0 === $( 'input#worldpay_token' ).length ) {

                    // Remove any errors and WorldPay fields
                    $( '.woocommerce-error, #WC_Worldpay-card-name, #WC_Worldpay-card-number, #WC_Worldpay-card-expiry-month, #WC_Worldpay-card-expiry-year, #WC_Worldpay-card-cvc', ccForm ).remove();

                    // Set up card name
                    var fName       = $form.find( '#billing_first_name' ).val() || '',
                        lName       = $form.find( '#billing_last_name' ).val() || '';
                    
                    // Make sure it's a complete name
                    if( fName != '' && lName != '' ) {
                        var cardName    = fName + ' ' + lName;
                    }

                    // Get the card number
                    var cardNumber  = $( '#WC_Gateway_Worldpay-card-number' ).val() || '';

                    // Set up expiry date
                    var expiry      = cardExpiryVal( $( '#WC_Gateway_Worldpay-card-expiry' ).val() );

                    // Get the CVC number
                    var cvc         = $( '#WC_Gateway_Worldpay-card-cvc' ).val();

                    // Let's add some hidden fields for WorldPay
                    ccForm.append( '<input type = "hidden" data-worldpay="name" id = "WC_Worldpay-card-name" value = "' + cardName + '" />' );
                    ccForm.append( '<input type = "hidden" data-worldpay="number" id = "WC_Worldpay-card-number" value = "' + cardNumber + '" />' );
                    ccForm.append( '<input type = "hidden" data-worldpay="exp-month" id = "WC_Worldpay-card-expiry-month" value = "' + expiry.month + '" />' );
                    ccForm.append( '<input type = "hidden" data-worldpay="exp-year" id = "WC_Worldpay-card-expiry-year" value = "' + expiry.year + '" />' );
                    ccForm.append( '<input type = "hidden" data-worldpay="cvc" id = "WC_Worldpay-card-cvc" value = "' + cvc + '" />' );

                    return false;

                } else {
                    return true; 
                }


            };

            var worldpayResponseHandler = function (status, response) {

                if ( 0 === $( 'input#worldpay_token' ).length ) {
                    if( 
                        status && 
                        response
                    ) {

                        if ( response.error ) {
                            if( response.error.message ) {
                                $form.unblock();
                                // Display the error if there is one
                                ccForm.prepend( '<ul class="woocommerce-error">' + errors( response.error.message ) + '</ul>' );
                            }
                        } else {
                            // Add the token
                            ccForm.append( '<input type="hidden" id="worldpay_token" name="worldpay_token" value="' + response.token + '"/>' );
                            document.getElementById("add_payment_method").submit();
                        }

                    }

                }

            };

            function errors ( message ) {

                // JSON.stringify( response.error.message )
                var humanMessage = '';
                if ( message ) {
                    message.forEach(function(entry) {
                        humanMessage = humanMessage + '<li>' + entry + '</li>';
                    });
                }

                return humanMessage;

            };

            // Sort out the card expiry date
            function cardExpiryVal( value ) {

                var month, prefix, year, _ref;
                _ref = value.split(/[\s\/]+/, 2), month = _ref[0], year = _ref[1];
                if ((year != null ? year.length : void 0) === 2 && /^\d+$/.test(year)) {
                  prefix = (new Date).getFullYear();
                  prefix = prefix.toString().slice(0, 2);
                  year = prefix + year;
                }
                month = parseInt(month, 10);
                year = parseInt(year, 10);
                return {
                  month: month,
                  year: year
                };
            };

            $( function () {

                $( document.body ).on( 'checkout_error', function () {
                    $( '#worldpay_token' ).remove();
                });

                // Add Card Form 
                $( "form#add_payment_method" ).submit(function() {
                    return worldpayFormHandler();
                });
                                            
                Worldpay.useOwnForm({
                    'clientKey': '<?php echo $this->client_key; ?>',
                    'form': $form,
                    'reusable': true,
                    'callback': worldpayResponseHandler
                });

                /* All Forms */
                $( 'form#add_payment_method' ).on( 'change', '#wc-WC_Gateway_Worldpay-cc-form input', function() {
                    $( '#worldpay_token' ).remove();
                });

            });

        }( jQuery ) );

    </script>