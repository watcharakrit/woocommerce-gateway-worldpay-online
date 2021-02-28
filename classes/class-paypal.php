<?php
		class WC_Gateway_Worldpay_Paypal extends WC_Gateway_Worldpay {
			private $worldpay_client;
			public function __construct() {
				$this->id = "WC_Gateway_Worldpay";
				$this->has_fields = true;
				$this->method_title = __( 'Worldpay PayPal', 'woocommerce-gateway-worldpay' );
				$this->method_description = __( 'The Worldpay PayPal payment gateway, please setup your keys and other settings within the main Worldpay settings.', 'woocommerce-gateway-worldpay' );

				$this->supports = array(
					'products',
					'refunds'
				);

				
				$this->init_keys();

				$this->id = "WC_Gateway_Worldpay_Paypal";

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
			//	add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
				add_action('woocommerce_thankyou_' . $this->id,array(&$this, 'thankyou_page'));

			}
			public function init_form_fields() {
				$this->form_fields = Worldpay_AdminForm::get_paypal_admin_form_fields();
			}
			
			public function payment_fields() {
				Worldpay_PaymentForm::render_paypal_form();
			}

			public function process_payment( $order_id ) {
				if (!WC()->session || !WC()->session->has_session()) {
					wc_add_notice( __( 'Payment error: Please login', 'woocommerce-gateway-worldpay' ), 'error' );
					return;
				}

				$order = new WC_Order( $order_id );
				$token = $_POST['worldpay_token'];

				$name =  $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

				$billing_address = array(
					"address1"=> $order->get_billing_address_1(),
					"address2"=> $order->get_billing_address_2(),
					"address3"=> '',
					"postalCode"=> $order->get_billing_postcode(),
					"city"=> $order->get_billing_city(),
					"state"=> $order->get_billing_state(),
					"countryCode"=> $order->get_billing_country()
				);

				try {
					$response = $this->get_worldpay_client()->createApmOrder(array(
						'settlementCurrency' => $this->settlement_currency,
						'token' => $token,
						'orderDescription' => "Order: " . $order_id,
						'amount' => $order->get_total() * 100,
						'currencyCode' => get_woocommerce_currency(),
						'name' => $name,
						'billingAddress' => $billing_address,
						'customerOrderCode' => $order_id,
						'successUrl' =>  add_query_arg( 'status', 'success', $order->get_checkout_order_received_url()) . '&',
						'pendingUrl' =>  add_query_arg( 'status', 'pending', $order->get_checkout_order_received_url()). '&',
						'failureUrl' =>   add_query_arg( 'status', 'failure', $order->get_checkout_order_received_url()). '&',
						'cancelUrl' =>  add_query_arg( 'wp_cancel', '1', $order->get_checkout_payment_url()). '&'
					));
				}
				catch ( Exception $e )
				{
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . ' ' . $e->getMessage(), 'error' );
					return;
				}

				if ($response['paymentStatus'] == Worldpay_Response_States::PRE_AUTHORIZED) {
					if (!add_post_meta( $order_id, '_transaction_id', $response['orderCode'], true )) {
						update_post_meta ( $order_id, '_transaction_id', $response['orderCode'] );
					}
					WC()->session->set( 'wp_order' , $response );
					return array(
						'result' => 'success',
						'redirect' =>  $response['redirectURL']
					);
				} else {
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'], 'error' );
					return;
				}
			}

			public function thankyou_page($order_id) {
				$response = WC()->session->get( 'wp_order');
				if ($response) {
					WC()->session->set( 'wp_order', false);
					$order = new WC_Order($order_id);
					
					$status = get_query_var('status', '');
	
					if ($status == 'failure') {
						WC()->session->set( 'wp_error',  __('Payment error: Payment failed, please try again', 'woocommerce-gateway-worldpay'));
						WC()->session->save_data();
						wp_redirect( $order->get_checkout_payment_url());
						exit;
					}
					try {
						$wpOrder = $this->get_worldpay_client()->getOrder($response['orderCode']);
						if (isset($wpOrder['paymentStatus']) && ($wpOrder['paymentStatus'] === Worldpay_Response_States::SUCCESS ||  $wpOrder['paymentStatus'] === Worldpay_Response_States::AUTHORIZED)) {
							$order->payment_complete($response['orderCode']);
							$order->reduce_order_stock();
						}
					} catch (WorldpayException $e) {
						WC()->session->set( 'wp_error',  __('Payment error: ' . $e->getMessage(), 'woocommerce-gateway-worldpay'));
						wp_redirect( $order->get_checkout_payment_url());
						exit;
					}
				}
			}
		} // WC_Gateway_Worldpay_Paypal