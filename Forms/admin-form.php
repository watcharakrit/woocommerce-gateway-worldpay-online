<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worldpay_AdminForm
{
	public static function get_admin_form_fields()
	{
		return array(
			'enabled' 		=> array(
				'title' 		=> __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable Worldpay', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'yes'
			),
			'is_test' 		=> array(
				'title' 		=> __( 'Testing', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Use test settings', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'yes'
			),
			'log' 		=> array(
				'title' 		=> __( 'Logging', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Turn on logging. Always on for test transactions', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'yes'
			),
			'woocommerce_checkout_form' => array(
				'title' 		=> __( 'Checkout Form', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Use the WooCommerce Checkout form?', 'woocommerce-gateway-worldpay' ),
				'description' 	=> __( 'Choose whether you wish to the WorldPay checkout form or the WooCommerce checkout form - if you choose the WooCommerce form you will need an SSL certificate.', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'yes',
				'desc_tip'    	=> true,
			),
			'paymentaction' => array(
				'title'       	=> __( 'Payment Action', 'woocommerce-gateway-worldpay' ),
				'type'        	=> 'select',
				'class'       	=> 'wc-enhanced-select',
				'description' 	=> __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce-gateway-worldpay' ),
				'default'     	=> 'sale',
				'desc_tip'    	=> true,
				'options'     	=> array(
					'sale'          => __( 'Capture', 'woocommerce-gateway-worldpay' ),
					'authorization' => __( 'Authorize', 'woocommerce-gateway-worldpay' )
				)
			),
			'threeds_enabled' => array(
				'title' 		=> __( '3DS Enabled', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable 3Ds', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'no',
				'description'	=> __( 'Enable 3D Secure for cards that support it?', 'woocommerce-gateway-worldpay' ),
				'desc_tip'    	=> true
			),
			'title' 		=> array(
				'title' 		=> __( 'Title', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the title which your customer sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'default' 		=> __( 'Worldpay', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
			),
			'description' 	=> array(
				'title' 		=> __( 'Customer Message', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'textarea',
				'default' 		=> 'Pay with Worldpay',
				'description' 	=> __( 'This controls the description which your customer sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
			),
			'store_tokens' => array(
				'title' 		=> __( 'Tokens / Card-on-file Payment', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable tokens / card-on-file payment', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'no',
				'description' 	=> __( 'Enable Card on file / Tokens for faster checking out.', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
			),
			'notifications_enabled' => array(
				'title' 		=> __( 'Webhooks', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable webhooks', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'no',
                'description' 	=> "Webhook URL: " . site_url() . "/?s=word&wc-api=WC_Gateway_Worldpay"
			),
			'settlement_currency' => array(
                'title' 		=> __( 'Settlement Currency', 'woocommerce-gateway-worldpay' ),
                'type' 			=> 'select',
                'default' 		=> 'GBP',
                'options' 		=> get_woocommerce_currencies()
            ),
            'service_key' => array(
                'title' 		=> __( 'Live Service Key', 'woocommerce-gateway-worldpay' ),
                'type' 			=> 'text',
                'default' 		=> '',
				'description' 	=> __( 'Enter your Live Service Key if you have one. Provided by WorldPay.', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
            ),
			'client_key' => array(
				'title' 		=> __( 'Live Client Key', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'text',
				'default' 		=> '',
				'description' 	=> __( 'Enter your Live Client Key if you have one. Provided by WorldPay.', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
			),
            'test_service_key' => array(
                'title' 		=> __( 'Test Service Key', 'woocommerce-gateway-worldpay' ),
                'type' 			=> 'text',
                'default' 		=> '',
				'description' 	=> __( 'Enter your Test Service Key if you have one. Provided by WorldPay. You can leave this field blank to place test orders using the WooCommerce key.', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
				'placeholder'	=> 'T_S_373e0d7f-ee88-47c6-b549-7adfa0808346'
            ),
			'test_client_key' => array(
				'title' 		=> __( 'Test Client Key', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'text',
				'default' 		=> '',
				'description' 	=> __( 'Enter your Test Client Key if you have one. Provided by WorldPay. You can leave this field blank to place test orders using the WooCommerce key', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
				'placeholder'	=> 'T_C_9e2688a8-0b84-4dc2-a8ac-e090c0851d09'
			),
			'test_card_holder_name' => array(
				'title' 		=> __( 'Test transactions simulations', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'select',
				'default' 		=> '0',
				'description' 	=> __( 'Simulate succcessful, failed and error responses during test transactions. This option will only have an effect in test orders.', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
				'options'     	=> array(
					'0'          		=> __( 'Use checkout form values (default)', 'woocommerce-gateway-worldpay' ),
					'SUCCESS' 			=> __( 'Simulation of a successful payment', 'woocommerce-gateway-worldpay' ),
					'FAILED' 			=> __( 'Simulation of a unsuccessful payment', 'woocommerce-gateway-worldpay' ),
					'ERROR' 			=> __( 'Simulation of an error', 'woocommerce-gateway-worldpay' )
				)
			),
            
			
		);
	}

	public static function get_paypal_admin_form_fields()
	{
		return array(
			'enabled' => array(
				'title' 		=> __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable Worldpay PayPal', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'yes'
			),
			'title' => array(
				'title' 		=> __( 'Title', 'woocommerce-gateway-worldpay' ),
				'type'			=> 'text',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'default' 		=> __( 'Worldpay PayPal', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
			)
		);
	}

	public static function get_giropay_admin_form_fields()
	{
		return array(
			'enabled' => array(
				'title' 		=> __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable Worldpay Giropay', 'woocommerce-gateway-worldpay' ),
				'default' 		=> 'yes'
			),
			'title' => array(
				'title' 		=> __( 'Title', 'woocommerce-gateway-worldpay' ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'default' 		=> __( 'Worldpay Giropay', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  	=> true,
			)
		);
	}
}
