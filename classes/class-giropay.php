<?php
		class WC_Gateway_Worldpay_Giropay extends WC_Gateway_Worldpay_Paypal {
			private $worldpay_client;
			protected $supported_currencies = array('EUR');
			public function __construct() {
				$this->id = "WC_Gateway_Worldpay";
				$this->has_fields = true;
				$this->method_title = __( 'Worldpay Giropay', 'woocommerce-gateway-worldpay' );
				$this->method_description = __( 'The Worldpay Giropay payment gateway, please setup your keys and other settings within the main Worldpay settings.', 'woocommerce-gateway-worldpay' );

				$this->supports = array(
					'products',
					'refunds'
				);
				
				$this->init_keys();

				$this->id = "WC_Gateway_Worldpay_Giropay";

				$this->init_form_fields();
				$this->init_settings();

				if( ! in_array(get_woocommerce_currency(), $this->supported_currencies)
					|| empty($this->client_key)
					|| empty($this->service_key)
				) {
					$this->enabled = false;
				}

				$this->title = $this->get_option( 'title' );
				$this->store_tokens = $this->get_option( 'store_tokens' ) != "no";
				$this->notifications_enabled = $this->get_option( 'notifications_enabled' ) != "no";

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

				if ( is_checkout() ) {
					$this->enqueue_checkout_scripts();
				}

				add_action( 'woocommerce_api_wc_gateway_worldpay', array( $this, 'handle_webhook' ) );
				add_action('woocommerce_thankyou_' . $this->id,array(&$this, 'thankyou_page'));

			}

			public function init_form_fields() {
				$this->form_fields = Worldpay_AdminForm::get_giropay_admin_form_fields();
			}

			public function payment_fields() {
				Worldpay_PaymentForm::render_giropay_form();
			}

		} // EOF WC_Gateway_Worldpay_Giropay