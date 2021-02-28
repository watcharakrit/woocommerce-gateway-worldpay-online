<?php

	class WC_Gateway_Worldpay extends WC_Payment_Gateway_CC {

		protected $client_key;
		protected $store_tokens;
		protected $is_test;
		protected $three_ds;
		protected $authorize_only;
		protected $notifications_enabled;
		protected $service_key;
		protected $api_endpoint;
		protected $js_endpoint;

		private $worldpay_client;

		protected $woocommerce_test_service_key = WPOLSERVICEKEY;
		protected $woocommerce_test_client_key  = WPOLCLIENTKEY;

		/**
		 * [__construct description]
		 */
		public function __construct() {
			$this->id 					= "WC_Gateway_Worldpay";
			$this->has_fields 			= true;
			$this->method_title 		= __( 'Worldpay Online', 'woocommerce-gateway-worldpay' );
			$this->method_description 	= $this->method_description();

			$this->supports 			= array(
		            						'products',
		            						'refunds',
											'subscriptions',
											'subscription_cancellation',
											'subscription_reactivation',
											'subscription_suspension',
											'subscription_amount_changes',
											'subscription_payment_method_change',
											'subscription_payment_method_change_customer',
											'subscription_payment_method_change_admin',
											'subscription_date_changes',
											'multiple_subscriptions',
		            						'pre-orders',
											'tokenization',
										);

			$this->init_form_fields();
			$this->init_settings();

			// Test or Live?
			$this->is_test  = $this->get_option('is_test') != "no";

			$this->init_keys();

			// Check if Worldpay credentials have been entered.
			if( empty($this->client_key) || empty($this->service_key ) ) {
				$this->enabled = false;
			}

			$this->title 				 = $this->get_option( 'title' );
			$this->store_tokens 		 = $this->get_option( 'store_tokens' ) != "no";
			$this->notifications_enabled = $this->get_option( 'notifications_enabled' ) != "no";

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Load necessary scripts.
			if( is_checkout() || is_add_payment_method_page() ) {
				$this->enqueue_checkout_scripts();
			}

			add_action( 'woocommerce_api_wc_gateway_worldpay', array( $this, 'handle_webhook' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page') );
			// add_action( 'woocommerce_receipt_' . $this->id, array($this, 'authorise_3dsecure') );
			// add_action( 'woocommerce_thankyou_' . $this->id,array(&$this, 'thankyou_page') );

            // Support WooCommerce Subscriptions
            if ( class_exists( 'WC_Subscriptions_Order' ) ) {

                add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'woocommerce_process_scheduled_subscription_payment' ), 10, 2 );
                add_filter( 'wcs_renewal_order_meta_query', array( $this, 'remove_renewal_order_meta' ), 10, 3 );

                // display the credit card used for a subscription in the "My Subscriptions" table
                if ( class_exists( 'WC_Payment_Token_CC' ) ) {
                    add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );
                    add_action( 'woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 3 );
                }

            }

            // Capture authorised payments
			add_action ( 'woocommerce_order_action_worldpay_online_process_payment', array( $this, 'process_capture' ) );

			// Support WooCommerce Pre-Orders
            if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
                add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_capture' ) );
            }

            // Show any stored error messages
			add_action( 'woocommerce_before_checkout_form', array( $this, 'show_errors' ), 1 );

		}

		/**
		 * Setup variables from settings
		 */
		private function init_keys() {

			// Test or Live?
			// $this->is_test  = $this->get_option('is_test') != "no";

			// Logging?
			$this->log 		= $this->get_option('log') != "no";

			// 3D Secure?
			$this->three_ds = $this->get_option('threeds_enabled') != "no";

			// Authorize or Capture funds?
			if ($this->get_option('paymentaction') == 'authorization') {
				$this->authorize_only = true;
			} else {
				$this->authorize_only = false;
			}

			// Settlement Curreny
			$this->settlement_currency = $this->get_option('settlement_currency');

			// Client and Service keys
			$test_service_key 	= $this->get_option('test_service_key');
			$test_client_key  	= $this->get_option('test_client_key');

			$test_service 		= isset( $test_service_key ) && $test_service_key != '' ? $this->get_option('test_service_key') : $this->woocommerce_test_service_key;
			$test_client  		= isset( $test_client_key )  && $test_client_key  != '' ? $this->get_option('test_client_key')  : $this->woocommerce_test_client_key;

			$this->service_key 	= $this->is_test ? $test_service : $this->get_option('service_key');
			$this->client_key  	= $this->is_test ? $test_client  : $this->get_option('client_key');
		
			// API
			$this->api_endpoint = $this->get_option('api_endpoint') ? $this->get_option('api_endpoint') : 'https://api.worldpay.com/v1/';
			$this->js_endpoint  = $this->get_option('js_endpoint')  ? $this->get_option('js_endpoint')  : 'https://cdn.worldpay.com/v1/worldpay.js';

			// Which credit card form is used?
			$this->woocommerce_checkout_form  = isset( $this->settings['woocommerce_checkout_form'] ) && $this->settings['woocommerce_checkout_form'] === "yes" ? true : false;

			// Description for checkout 
			$this->description	= $this->settings['description'];

			// Logging
			$this->logging 	= ( $this->is_test || $this->log ) ? true : false;

			// Simulation testing
			$this->test_card_holder_name = isset( $this->settings['test_card_holder_name'] ) ? $this->settings['test_card_holder_name'] : 0;

		}

		public function method_description() {

			$description 	  = '';
			$test_service_key = $this->get_option('test_service_key');

			if( null === $test_service_key || $test_service_key == '' ) {
				$description = sprintf( __( '<div id="wc_gateway_worldpay_form"><p>Need a Worldpay Online account? Sign up <a href="%s" target="_blank">HERE</a></p></div>', 'woocommerce-gateway-worldpay'), 'https://online.worldpay.com/signup/5befac68-6bc1-4a43-8945-2dc05bd99dcf' );
			}

			$description  .= sprintf( __( 'The Worldpay Online payment gateway. Please review <a href="%s" target="_blank">the documentation</a> for an overview of these settings and for troublshooting steps etc.', 'woocommerce-gateway-worldpay' ), "https://docs.woocommerce.com/document/worldpay-online-payment-gateway/" );
			
			return $description;
		}

		/**
		 * [init_form_fields description]
		 * @return [type] [Get admin fields]
		 */
		public function init_form_fields() {
			$this->form_fields = Worldpay_AdminForm::get_admin_form_fields();
		}

		/**
		 * [payment_fields description]
		 * Are we using the WooCommerce form or WorldPay's own form
		 * WorldPay's form does not need an SSL certificate.
		 */
		public function payment_fields() {

			if ( isset($this->woocommerce_checkout_form) && $this->woocommerce_checkout_form ) {

				// Check if we need to display "Save to account" checkbox and saved payment methods
				$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->store_tokens == 'yes';

				echo '<div id="' . $this->id . '-data">';

					if ( $this->description ) {
						echo apply_filters( 'wc_worldpayonline_description', wp_kses_post( $this->description ) );
					}

					// Use our own payment fields
					$this->ownform();

				echo '</div>';

			} else {
				if ( is_add_payment_method_page() ) {
					Worldpay_PaymentForm::render_payment_form();
				} else {
					Worldpay_PaymentForm::render_payment_form( $this->store_tokens, $this->get_stored_card_details() );
				}
			}

		}

		/**
		 * [store_token description]
		 * @return [type] [description]
		 */
		private function store_token( $card_number = NULL, $card_exp_month = NULL, $card_exp_year = NULL, $card_type = NULL ) {
			$currentUser = wp_get_current_user();

			$worldpay_save_card_details = false;
			$worldpay_use_saved_card_details = false;

			if( isset( $_POST['worldpay_save_card_details'] ) && $_POST['worldpay_save_card_details'] ) {
				$worldpay_save_card_details = $_POST['worldpay_save_card_details'];
			}

			if( isset( $_POST['worldpay_use_saved_card_details'] ) && $_POST['worldpay_use_saved_card_details'] ) {
				$worldpay_use_saved_card_details = $_POST['worldpay_use_saved_card_details'];
			}

			if ( $this->store_tokens && $worldpay_save_card_details && !$worldpay_use_saved_card_details ) {
				// Store the token, the WorldPay way
				update_user_meta( $currentUser->ID, 'worldpay_token', $_POST['worldpay_token'] );
				// And the WC way
				$this->save_token( $_POST['worldpay_token'], $card_number, $card_exp_month, $card_exp_year, $card_type );
			}

		}

		/**
		 * [get_stored_card_details description]
		 * @return [type] [description]
		 */
		private function get_stored_card_details() {
			if( ! $this->store_tokens ){
				return NULL;
			}

			$currentUser = wp_get_current_user();

			return Worldpay_CardDetails::get_by_user($currentUser, $this->get_worldpay_client());
		}

		/**
		 * [get_stored_token description]
		 * @return [type] [description]
		 */
		private function get_stored_token() {

			if( ! $this->store_tokens ){
				return null;
			}

			$current_user = wp_get_current_user();
			return Worldpay_Token::get_by_user($current_user);

		}

		/**
		 * [process_payment description]
		 * @param  [type] $order_id [description]
		 * @return [type]           [description]
		 */
		public function process_payment( $order_id ) {

			if (!WC()->session || !WC()->session->has_session()) {
				wc_add_notice( __( 'Payment error: Please login', 'woocommerce-gateway-worldpay' ), 'error' );
				return;
			}
			
			$order = new WC_Order( $order_id );
			$token = '';

			// Set the order type
			$orderType = 'ECOM';

			// Get the token
			if ( isset( $_POST['worldpay_use_saved_card_details'] ) && $_POST['worldpay_use_saved_card_details'] ) {
				$token = $this->get_stored_token();
			} elseif ( isset($_POST['wc-WC_Gateway_Worldpay-payment-token']) && $_POST['wc-WC_Gateway_Worldpay-payment-token'] !== 'new' ) {

				// Get the token from WooCommerce
				$tokens = new WC_Payment_Token_CC();
				$tokens = WC_Payment_Tokens::get( wc_clean( $_POST['wc-WC_Gateway_Worldpay-payment-token'] ) );
				if ( $tokens ) {
					if ( $tokens->get_user_id() == $order->get_customer_id() ) {
						$token = $tokens->get_token();
					}
				}
				// Set the Order type
				$orderType 	= 'RECURRING';

			} elseif( isset($_POST['worldpay_token']) ) {
				$token = wc_clean( $_POST['worldpay_token'] );
			}

			// Make sure we have a $token
			if( $token == '' ) {
				return;
			} else {

				$name =  $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

				// If testing set the name to 3D/No 3DS
				// No 3DS for token and subscription payments
				if ( $this->three_ds && $this->is_test ) {
					if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {
						$name = 'NO 3DS';
					} elseif ( (isset($_POST['wc-WC_Gateway_Worldpay-payment-token']) && $_POST['wc-WC_Gateway_Worldpay-payment-token'] !== 'new') || (isset($_POST['wc-WC_Gateway_Worldpay-new-payment-method']) && $_POST['wc-WC_Gateway_Worldpay-new-payment-method'] ) ) {
						$name = 'NO 3DS';
					} else {
						$name = '3D';				
					}
				}

				// Set name field based on testing simulations
				if ( $this->test_card_holder_name && $this->is_test ) {

					if( $this->test_card_holder_name !== 0 ) {
						$name = $this->test_card_holder_name;
					}

				}

				$billing_address = array(
					"address1"		=> $order->get_billing_address_1(),
					"address2"		=> $order->get_billing_address_2(),
					"address3"		=> '',
					"postalCode"	=> str_replace( ' ', '', $order->get_billing_postcode() ),
					"city"			=> $order->get_billing_city(),
					"state"			=> $order->get_billing_state(),
					"countryCode"	=> $order->get_billing_country()
				);

				try {
					$sessionId = uniqid();

					WC()->session->set( 'wp_sessionid' , $sessionId );

					// Add $sessionId to post meta as a fallback
					update_post_meta ( $order_id, '_wp_sessionid', $sessionId );

					$this->get_worldpay_client()->setSessionId($sessionId);

					$authoriseOnly = $this->get_txtype( $order->get_id(), $order->get_total() );

					$buildorder = array(
						'orderType'			 => $orderType,
						'settlementCurrency' => $this->settlement_currency,
						'token' 			 => $token,
						'orderDescription' 	 => __( 'Order', 'woocommerce-gateway-worldpay' ) . ' ' . str_replace( '#' , '' , $order->get_order_number() ),
						'amount' 			 => $order->get_total() * 100,
						'currencyCode' 		 => $order->get_currency(),
						'name'				 => $name,
						'billingAddress'	 => $billing_address,
						'customerOrderCode'	 => str_replace( '#' , '' , $order->get_order_number() )
					);

					if ( $authoriseOnly ) {
						$buildorder['authoriseOnly'] = $authoriseOnly;
					}

					// Check for Subscriptions, NO 3D Secure!
					if ( function_exists( 'wcs_order_contains_subscription' ) ) {
						if ( wcs_order_contains_subscription( $order ) ) {
							$buildorder['orderType'] 	= 'RECURRING';
						} else {
							$buildorder['is3DSOrder'] = $this->three_ds;
						}
					} else {
						$buildorder['is3DSOrder'] = $this->three_ds;
					}

					// If this is a token payment unset is3DSOrder
					if ( (isset($_POST['wc-WC_Gateway_Worldpay-payment-token']) && $_POST['wc-WC_Gateway_Worldpay-payment-token'] !== 'new' ) || (isset($_POST['wc-WC_Gateway_Worldpay-new-payment-method']) && $_POST['wc-WC_Gateway_Worldpay-new-payment-method']) ) {
						unset( $buildorder['is3DSOrder'] );
					}

				    // Logging
		            if ( $this->logging == true ) {
		            	$this->log_transaction( $buildorder, $this->id, 'New Order $buildorder : ', $start = TRUE );
					}

					$response = $this->get_worldpay_client()->createOrder( $buildorder );

				    // Logging
		            if ( $this->logging == true ) {
		            	$this->log_transaction( $response, $this->id, 'New Order $response : ', $start = FALSE );
		            }

				}

				catch ( Exception $e ) {

					// Logging
		            if ( $this->logging == true ) {
		            	$this->log_transaction( $e, $this->id, 'New Order $response : ', $start = FALSE );
		            }

					wc_add_notice( __( 'Payment error:', 'woocommerce-gateway-worldpay' ) . ' ' . $e->getMessage(), 'error' );

					// Add order note
					$order->add_order_note( __('Payment error:', 'woocommerce-gateway-worldpay') . ' ' . $e->getMessage() );
					return;
				}

				// Store the token the WooCommerce way
				if( isset( $_POST['wc-WC_Gateway_Worldpay-new-payment-method'] ) && $_POST['wc-WC_Gateway_Worldpay-new-payment-method'] ) {
					$this->save_token( $token, $response['paymentResponse']['maskedCardNumber'], $response['paymentResponse']['expiryMonth'], $response['paymentResponse']['expiryYear'], $response['paymentResponse']['cardType'] );
				}

				// If this is a subscription order then add the token to the parent order
				if ( function_exists( 'wcs_order_contains_subscription' ) ) {
					if ( wcs_order_contains_subscription( $order ) ) {
						update_post_meta( $order_id, '_WorldPayOnlineToken' , $token );
					}
				}

				if ( $response['paymentStatus'] === Worldpay_Response_States::SUCCESS || $response['paymentStatus'] === Worldpay_Response_States::AUTHORIZED ) {

					// Logging
		            if ( $this->logging == true ) {
		            	$this->log_transaction( $response, $this->id, 'Sucessfull Order $response : ', $start = FALSE );
		            }

					// Create and add Order Note
		    		$this->order_notes( $order, $response );

					// Pre-Orders support
					if ( class_exists('WC_Pre_Orders') && WC_Pre_Orders_Order::order_contains_pre_order( $order->get_id() ) ) {
						// mark order as pre-ordered / reduce order stock
						WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
					} else {
						$order->payment_complete( $response['orderCode'] );
						// Check if this is an authorized only order
						if ( $this->authorize_only ) {
							$order->update_status( 'authorised', _x( 'Payment authorised, you will need to capture this payment before shipping. Use the "Capture Authorised Payment" option in the "Order Actions" dropdown.<br /><br />', 'woocommerce-gateway-worldpay' ) );
							// Save the order
							// $order->save();
						}
					}

					/**
					 * Empty awaiting payment session
					 */
					unset( WC()->session->order_awaiting_payment );

					// Redirect Customer
					return array(
						'result' 	=> 'success',
						'redirect' 	=> $order->get_checkout_order_received_url()
					);

				} else if ($response['is3DSOrder'] && $response['paymentStatus'] == Worldpay_Response_States::PRE_AUTHORIZED) {

					// Save the token
					$this->store_token( $response['paymentResponse']['maskedCardNumber'], $response['paymentResponse']['expiryMonth'], $response['paymentResponse']['expiryYear'], $response['paymentResponse']['cardType'] );
					
					if (!add_post_meta( $order_id, '_transaction_id', $response['orderCode'], true )) {
						update_post_meta ( $order_id, '_transaction_id', $response['orderCode'] );
					}
					WC()->session->set( 'wp_order' , $response );
					
					// Add $response to post meta as a fallback
					update_post_meta ( $order_id, '_worldpay_response', $response );

					return array(
						'result' => 'success',
						'redirect' =>  $order->get_checkout_payment_url( true )
					);

				} else {
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'], 'error' );
					$order->update_status( 'pending', _x('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'] );
					return;
				}

			} 
		}

		/**
		 * [receipt_page description]
		 * @param  [type] $order_id [description]
		 * @return [type]           [description]
		 */
		public function receipt_page( $order_id ) {

			$order = wc_get_order( $order_id );

			// Unset any errors
			// unset( WC()->session->wp_error );

			$response = WC()->session->get( 'wp_order');

			if( !isset( $response ) ) {
				$response = get_post_meta ( $order_id, '_worldpay_response', TRUE );
			}

			if( $order->needs_payment() ) {

				// Redirect to 3DS check
				if ( $response && $response['is3DSOrder'] && $response['paymentStatus'] == Worldpay_Response_States::PRE_AUTHORIZED && !isset( $_POST['PaRes'] ) ) {
					Worldpay_PaymentForm::three_ds_redirect( $response, $order );
					exit;
				}

				try {

					if ( isset( $response ) ) {

						$orderCode = get_post_meta( $order_id, '_transaction_id', true );

						$sessionId = WC()->session->get( 'wp_sessionid');

						// Use fallback
						if( !isset( $sessionId ) ) {
							$sessionId = get_post_meta ( $order_id, '_wp_sessionid', TRUE );
						}

						$this->get_worldpay_client()->setSessionId( $sessionId );

						// Log authorise3DSOrder
						$request = array(
					            "threeDSResponseCode"   => isset( $_POST['PaRes'] ) ? $_POST['PaRes'] : NULL,
					            "shopperSessionId"      => $sessionId,
					            "shopperAcceptHeader"   => '*/*',
					            "shopperUserAgent"      => $order->get_customer_user_agent(),
					            "shopperIpAddress"      => $order->customer_ip_address
					        );

						$response = $this->get_worldpay_client()->authorise3DSOrder( $orderCode, $request );

						// Logging
			            if ( $this->logging == true ) {
			            	$this->log_transaction( $request, $this->id, 'authorise3DSOrder $request : ', FALSE );
			            	$this->log_transaction( $response, $this->id, 'authorise3DSOrder $response : ', FALSE );
			            }

						if (isset($response['paymentStatus']) && ($response['paymentStatus'] === Worldpay_Response_States::SUCCESS || $response['paymentStatus'] === Worldpay_Response_States::AUTHORIZED)) {

							// Create and add Order Note
			    			$this->order_notes( $order, $response );

							// Pre-Orders support
							if ( class_exists('WC_Pre_Orders') && WC_Pre_Orders_Order::order_contains_pre_order( $order->get_id() ) ) {
								// mark order as pre-ordered / reduce order stock
								WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
							} else {
								$order->payment_complete( $response['orderCode'] );
								// Check if this is an authorized only order
								if ( $this->authorize_only ) {
									$order->update_status( 'authorised', _x( 'Payment authorised, you will need to capture this payment before shipping. Use the "Capture Authorised Payment" option in the "Order Actions" dropdown.<br /><br />', 'woocommerce-gateway-worldpay' ) );
									// Save the order
									$order->save();
								}
							
							}

							/**
							 * Empty awaiting payment session
							 */
							unset( WC()->session->order_awaiting_payment );

							// Logging
				            if ( $this->logging == true ) {
				            	$this->log_transaction( $response, $this->id, 'Thank you page $response : ', FALSE );
				            }

				            // Remove fallback postmeta
				            delete_post_meta ( $order_id, '_wp_sessionid' );
				            delete_post_meta ( $order_id, '_worldpay_response' );

				            wp_redirect( $order->get_checkout_order_received_url() );
				            exit;

						} else {

							$message = __('Payment error: Problem authorising 3DS order.<br />Your card has not been charged, please check your address and card details and try again.', 'woocommerce-gateway-worldpay');
							// wc_add_notice( $message, 'error' );
							update_post_meta( $order_id, '_worldpay_errors', $message );

							$order->add_order_note( __('Payment error: ', 'woocommerce-gateway-worldpay') . '<pre>' . print_r( $response, TRUE ) . '</pre>' );

							// Logging
				            if ( $this->logging == true ) {
				            	$this->log_transaction( $response, $this->id, 'Thank you page $response : ', FALSE );
				            }

				            // Remove fallback postmeta
				            // delete_post_meta ( $order_id, '_wp_sessionid' );
				            // delete_post_meta ( $order_id, '_worldpay_response' );

							// Save the order
							// $order->save();
							wp_redirect( wc_get_checkout_url() );
							exit;
						}

					} else {

						// Remove fallback postmeta
				        delete_post_meta ( $order_id, '_wp_sessionid' );
				        delete_post_meta ( $order_id, '_worldpay_response' );

						// Order failed, redirect to try again
						$message = __('Payment error: There was a problem authorising this transaction.<br />Your card has not been charged, please check your address and card details and try again.', 'woocommerce-gateway-worldpay');
						update_post_meta( $order_id, '_worldpay_errors', $message );

						wp_redirect( wc_get_checkout_url() );
						exit;

					}

				} catch ( WorldpayException $e ) {

					$message = __('Payment error: Problem authorising 3D Secure', 'woocommerce-gateway-worldpay');
					wc_add_notice( $message, 'error' );

					$order->add_order_note( __('Payment error: ', 'woocommerce-gateway-worldpay') . '<pre>' . print_r( $e->getMessage(), TRUE ) . '</pre>' );

					// Logging
		            if ( $this->logging == true ) {
		            	$this->log_transaction( $e->getMessage(), $this->id, 'Thank you page $e->getMessage : ', FALSE );
		            	$this->log_transaction( error_get_last(), $this->id, 'Thank you page Last PHP Error : ', FALSE );
		            }

		            // Remove fallback postmeta
				    delete_post_meta ( $order_id, '_wp_sessionid' );
				    delete_post_meta ( $order_id, '_worldpay_response' );

					// Save the order
					$order->save();
					wp_redirect( wc_get_checkout_url() );
					exit;

				}

			} else {
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}

		}

		/**
		 * [process_refund description]
		 * @param  [type] $order_id [description]
		 * @param  [type] $amount   [description]
		 * @param  string $reason   [description]
		 * @return [type]           [description]
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {

    		include_once( 'refund-class.php' );

    		$refund = new WC_WPOL_Refund( $order_id, $amount, $reason );

    		return $refund->refund();

		}

		/**
		 * [process_capture description]
		 * @param  [type] $order_id [description]
		 * @return [type]           [description]
		 */
		public function process_capture( $order ) {
			
			if( !is_object( $order ) ) {
				$order = wc_get_order( $order );
			}

			if ( ! $order || !$order->get_transaction_id() ) {
				return false;
			}

			/**
			 * Set the amount for capturing
			 */
			$amount = $order->get_total() * 100;
			
			try {

				$response = $this->get_worldpay_client()->captureAuthorisedOrder( $order->get_transaction_id(), $amount );

				// Logging
	            if ( $this->logging == true ) {
	            	$this->log_transaction( $response, $this->id, 'Capture $response : ', $start = true );
	            }

				$order->add_order_note( __('Update from Worldpay : Payment Captured', 'woocommerce-gateway-worldpay') );
				$order->payment_complete( $order->get_transaction_id() );
				$order->set_status( ($order->needs_processing() ? 'processing' : 'completed'), __('Payment completed', 'woocommerce-gateway-worldpay') );
				$order->save();
				return true;

			} catch ( WorldpayException $e ) {
				// Logging
	            if ( $this->logging == true ) {
	            	$this->log_transaction( $e->getMessage(), $this->id, 'Capture Failure $response : ', $start = true );
	            }

	            $order->add_order_note( __( 'Update from Worldpay :<br />Payment capture failed', 'woocommerce-gateway-worldpay') . '<br />' . $e->getMessage() );
				$order->save();

				return new WP_Error("capture-error", __('Capture failed', 'woocommerce-gateway-worldpay'));
			}

		}

		/**
		 * 		ORDER_CREATED
		 * 		FAILED
		 * 		AUTHORIZED
		 * 		SUCCESS
		 * 		CANCELLED
		 * 		EXPIRED	
		 * 		SENT_FOR_REFUND
		 * 		PARTIALLY_REFUNDED
		 * 		REFUNDED
		 * 		SETTLED
		 * 		INFORMATION_REQUESTED
		 * 		INFORMATION_SUPPLIED
		 * 		CHARGED_BACK
		 *
		 * @param  [type] $status [description]
		 * @param  [type] $order  [description]
		 * @return [type]         [description]
		 */
		protected function process_status( $status, $order ) {
			switch ( $status )
			{
				case Worldpay_Response_States::ORDER_CREATED:
					$order->add_order_note( __( 'Update from Worldpay : Order created.', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::AUTHORIZED:
					$order->add_order_note( __( 'Update from Worldpay : Payment authorized', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::CANCELLED:
					$order->update_status('cancelled');
					$order->add_order_note( __( 'Update from Worldpay : Authorization cancelled', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::EXPIRED:
					$order->update_status('cancelled');
					$order->add_order_note( __( 'Update from Worldpay : Authorization expired', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::SUCCESS:
					$order->payment_complete();
					$order->add_order_note( __( 'Update from Worldpay : Payment successful' ));
					$order->set_status( ($order->needs_processing() ? 'processing' : 'completed'), __('Payment completed', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::SETTLED:
					$order->add_order_note( __( 'Update from Worldpay : Payment settled', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::FAILED:
					$order->update_status('failed');
					$order->add_order_note( __( 'Update from Worldpay : Payment failed', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::SENT_FOR_REFUND:
					$order->add_order_note( __( 'Update from Worldpay : Order sent for refund. Worldpay will update the order once the refund has completed.', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::PARTIALLY_REFUNDED:
					$order->add_order_note( __( 'Update from Worldpay : Order partially refunded at Worldpay. Manually refund the correct amount in WooCommerce.', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::REFUNDED:
					if ( 0 == $order->get_total_refunded() ) {
						$order->add_order_note( __( 'Update from Worldpay : Order Refunded', 'woocommerce-gateway-worldpay') );
						$args = array(
							'amount'	 => $order->get_total(),
							'reason'	 => "Order refunded in Worldpay",
							'order_id'   => $order->get_id(),
							'line_items' => array()
						);
						wc_create_refund($args);
					}
					$order->save();
					break;
				case Worldpay_Response_States::INFORMATION_REQUESTED:
					$order->add_order_note( __( 'Update from Worldpay : Payment disputed - information requested.', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::INFORMATION_SUPPLIED:
					$order->add_order_note( __( 'Update from Worldpay : Information received.', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;
				case Worldpay_Response_States::CHARGED_BACK:
					$order->add_order_note( __( 'Update from Worldpay : Order charged back.', 'woocommerce-gateway-worldpay') );
					$order->save();
					break;

			}
		}

		/**
		 * [handle_webhook description]
		 * @return [type] [description]
		 */
		public function handle_webhook() {

			if ( ! $this->notifications_enabled ) {
				return;
			}
			try{
				$webhookRequest = Worldpay_WebhookRequest::from_request();
				if( null == $webhookRequest )	{
					return;
				}

				$order = $this->get_order_from_order_code( $webhookRequest->order_code );
				if ($this->is_test) {
					if ( "TEST" != $webhookRequest->environment ) {
						return;
					}
				} else {
					if ( "LIVE" != $webhookRequest->environment ) {
						return;
					}
				}
				if ( null == $order || null == $order->get_id() ) {
					return;
				}
				$this->process_status( $webhookRequest->paymentStatus, $order );
			}
			catch ( Exception $e ) {
				// Suppress the exception, so the failing webhook is not resent.
			}
			return;
		}

		/**
		 * @param $orderCode
		 * @return WC_Order
		 */
		protected function get_order_from_order_code ( $order_code ) {
			$args = array(
				'meta_query' => array(
					array(
						'key' => '_payment_method',
						'value' => 'WC_Gateway_Worldpay'
					),
					array(
						'key' => '_transaction_id',
						'value' => $order_code
					)
				),
				'post_status' => array_keys( wc_get_order_statuses() ),
				'post_type'   => 'shop_order'
			);
			$posts = get_posts( $args );
			if ( ! is_array($posts) || 1 != count($posts) ) {
				return null;
			}
			return new WC_Order($posts[0]);
		}

		/**
		 * [enqueue_checkout_scripts description]
		 * @return [type] [description]
		 */
		protected function enqueue_checkout_scripts() {
			wp_enqueue_script('worldpay_script', $this->js_endpoint, array('jquery', 'wc-checkout'));

			wp_enqueue_script( 'worldpay_init', plugin_dir_url(__DIR__) . 'scripts/init_worldpay.js', array('jquery', 'wc-checkout', 'worldpay_script'), WPOLVERSION );
			wp_localize_script( 'worldpay_init', 'WorldpayConfig', array('ClientKey' => $this->client_key ) );

		}

		/**
		 * [get_worldpay_client description]
		 * @return [type] [description]
		 */
		protected function get_worldpay_client() {
			if ( ! isset($this->worldpay_client) ) {
				$this->worldpay_client = new WordpressWorldpay( $this->service_key );
				$this->worldpay_client->setEndpoint($this->api_endpoint);
				$this->worldpay_client->setPluginData( 'WooCommerce', WPOLVERSION );
			}
			return $this->worldpay_client;
		}

		/**
         * Wrapper around @see WC_Order::get_order_currency() for versions of WooCommerce prior to 2.1.
         */
        protected function get_order_currency( $order ) {

            if ( method_exists( $order, 'get_order_currency' ) ) {
                $order_currency = $order->get_order_currency();
            } else {
                $order_currency = get_woocommerce_currency();
            }

            return $order_currency;
        }

		/**
		 * Use the txtype from settings unless the order contains a pre-order or the order value is 0
		 *
		 * @param  {[type]} $order_id [description]
		 * @param  {[type]} $amount   [description]
		 * @return {[type]}           [description]
		 */
		function get_txtype( $order_id, $amount ) { 

			if( class_exists( 'WC_Pre_Orders' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) ) {
				return true;
			} elseif( $amount == 0 ) {
				return true;
			} else {
				return $this->authorize_only;
			}

		}

        /**
         * process scheduled subscription payment for Subscriptions 2.0
         */
        function woocommerce_process_scheduled_subscription_payment( $amount_to_charge, $order ) {

            if( !is_object( $order ) ) {
                $order = new WC_Order( $order );
            }

            $order_id = $order->get_id();

            /**
             * Get parent order ID
             */
            $subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
            foreach( $subscriptions as $subscription ) {

                $parent_order      = is_callable( array( $subscription, 'get_parent' ) ) ? $subscription->get_parent() : $subscription->order;

                $parent_order_id   = is_callable( array( $parent_order, 'get_id' ) ) ? $parent_order->get_id() : $parent_order->id;
                $subscription_id   = is_callable( array( $subscription, 'get_id' ) ) ? $subscription->get_id() : $subscription->id;

            }

            $buildOrder = array(
		        'token' 		=> get_post_meta( $parent_order_id, '_WorldPayOnlineToken', true ),
		        'amount' 		=> $amount_to_charge * 100,
		        'currencyCode' 	=> $order->get_currency(),
		        'name' 			=> $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
		        'billingAddress' => array(
					"address1"		=> $order->get_billing_address_1(),
					"address2"		=> $order->get_billing_address_2(),
					"address3"		=> '',
					"postalCode"	=> $order->get_billing_postcode(),
					"city"			=> $order->get_billing_city(),
					"state"			=> $order->get_billing_state(),
					"countryCode"	=> $order->get_billing_country()
		        ),
		        'orderType' 			=> 'RECURRING',
		        'orderDescription' 	 	=> __( 'Order', 'woocommerce-gateway-worldpay' ) . ' ' . str_replace( '#' , '' , $order->get_order_number() ),
		        'customerOrderCode'	 	=> str_replace( '#' , '' , $order->get_order_number() )
		    );

		    // Logging
            if ( $this->logging == true ) {
            	$this->log_transaction( $buildOrder, $this->id, 'Scheduled Subscription Renewal $buildOrder : ', $start = TRUE );
            }

		    $response = $this->get_worldpay_client()->createOrder( $buildOrder );

		    // Logging
            if ( $this->logging == true ) {
            	$this->log_transaction( $response, $this->id, 'Scheduled Subscription Renewal $response : ', $start = FALSE );
            }

		    if ( $response['paymentStatus'] === Worldpay_Response_States::SUCCESS || $response['paymentStatus'] === Worldpay_Response_States::AUTHORIZED ) {

		    	// Create and add Order Note
		    	$this->order_notes( $order, $response );

				// WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
				$order->payment_complete( $response['orderCode'] );

				$subscriptions = wcs_get_subscriptions_for_order( $order );
	            if ( ! empty( $subscriptions ) ) {
	                foreach ( $subscriptions as $subscription ) {
	                    $subscription->payment_complete();
	                }
	                // do_action( 'processed_subscription_payments_for_order', $order );
	            }

			} else {
				$order->add_order_note( __('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'] );

				$subscriptions = wcs_get_subscriptions_for_order( $order );
	            if ( ! empty( $subscriptions ) ) {

	                foreach ( $subscriptions as $subscription ) {
	                    $subscription->payment_failed();
	                }
	                
	            }

				return;
			}

        } // process scheduled subscription payment

        /**
         * Don't transfer WorldPay customer/token meta when creating a parent renewal order.
         *
         * @access public
         * @param array $order_meta_query MySQL query for pulling the metadata
         * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
         * @param int $renewal_order_id Post ID of the order created for renewing the subscription
         * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
         * @return void
         */
        public function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role = NULL ) {
            if ( 'parent' == $new_order_role ) {
                $order_meta_query .= " AND `meta_key` NOT IN ( '_WorldPayOnlineToken' ) ";
            }
            return $order_meta_query;
        }

        /**
         * Render the payment method used for a subscription in the "My Subscriptions" table
         *
         * @param string $payment_method_to_display the default payment method text to display
         * @param array $subscription_details the subscription details
         * @param WC_Order $order the order containing the subscription
         * @return string the subscription payment method
         */
        public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
            // bail for other payment methods
            if ( $this->id != $subscription->payment_method || ! $subscription->customer_user ) {
                return $payment_method_to_display;
            }

            $token     = get_post_meta( $subscription->order->id, '_WorldPayOnlineToken', true );
            $token_id  = $this->get_token_id( $token );

            $tokens = new WC_Payment_Token_CC();
            $tokens = WC_Payment_Tokens::get( $token_id );

            if( $tokens ) {
                $payment_method_to_display = sprintf( __( 'Via %s card ending in %s', 'woocommerce-gateway-worldpay' ), $tokens->get_card_type(), $tokens->get_last4() );
            }

            return $payment_method_to_display;
        }

        /**
         * Update the customer_id for a subscription after using WorldPay to complete a payment to make up for
         * an automatic renewal payment which previously failed.
         *
         * @access public
         * @param WC_Order $original_order The original order in which the subscription was purchased.
         * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
         * @param string $subscription_key A subscription key of the form created by @see WC_Subscriptions_Manager::get_subscription_key()
         * @return void
         */
        public function update_failing_payment_method( $original_order, $renewal_order, $subscription_key ) {
            update_post_meta( $original_order->id, '_WorldPayOnlineToken', get_post_meta( $new_renewal_order->id, '_WorldPayOnlineToken', true ) );
        }

	    /**
		 * [save_token description]
		 * @param  [type] $token        [description]
		 * @param  [type] $card_type    [description]
		 * @param  [type] $last4        [description]
		 * @param  [type] $expiry_month [description]
		 * @param  [type] $expiry_year  [description]
		 * @return [type]               [description]
		 */
		function save_token( $token_value, $card_number, $expiry_month, $expiry_year, $card_type ) {
					
			$token = new WC_Payment_Token_CC();

			$token->set_token( $token_value );
			$token->set_gateway_id( $this->id );
			$token->set_card_type( str_replace( '_',' ', $card_type ) );
			$token->set_last4( substr( $card_number, -4 ) );
			$token->set_expiry_month( $expiry_month );
			$token->set_expiry_year( $expiry_year );
			$token->set_user_id( get_current_user_id() );

			$token->save();

		}

        /**
         * Get the Token ID from the database using the token from WorldPay
         * @param  [type] $token [description]
         * @return [type]        [description]
         */
        function get_token_id( $token ) {
            global $wpdb;

            $id = NULL;

            if ( $token ) {
                $tokens = $wpdb->get_row( $wpdb->prepare(
                    "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s",
                    $token
                ) );
            }

            return $tokens->token_id;
        }

        /**
         * [add_payment_method description]
         */
		public function add_payment_method() {

			$this->log_transaction( $_POST, $this->id, 'add_payment_method : ', $start = TRUE );

			if( is_user_logged_in() ) { 

				$currentUser = wp_get_current_user();

				// $buildOrder = array(
			  //       'token' 		=> wc_clean( $_POST['worldpay_token'] ),
			  //       'amount' 		=> 0,
			  //       'currencyCode' 	=> get_woocommerce_currency(),
			  //       'name' 			=> wc_clean( $_POST['billing_first_name'] ) . ' ' . wc_clean( $_POST['billing_last_name'] ),
			  //       'billingAddress' => array(
				// 		"address1"		=> wc_clean( $_POST['billing_address_1'] ),
				// 		"address2"		=> wc_clean( $_POST['billing_address_2'] ),
				// 		"address3"		=> '',
				// 		"postalCode"	=> wc_clean( $_POST['billing_postcode'] ),
				// 		"city"			=> wc_clean( $_POST['billing_city'] ),
				// 		"state"			=> wc_clean( $_POST['billing_state'] ),
				// 		"countryCode"	=> wc_clean( $_POST['billing_country'] )
			  //       ),
			  //       'orderType' 			=> 'RECURRING',
			  //       'orderDescription' 	 	=> __( 'New card for', 'woocommerce-gateway-worldpay' ) . ' ' . wc_clean( $_POST['billing_first_name'] ) . ' ' . wc_clean( $_POST['billing_last_name'] ),
			  //       'customerOrderCode'	 	=> __( 'New Card', 'woocommerce-gateway-worldpay' )
			  //   );

				// $response = $this->get_worldpay_client()->createOrder( $buildOrder );

				if ( empty( $_POST['worldpay_token'] ) || empty( $_POST['worldpay_response'] ) ) {
					wc_add_notice( __( 'There was a problem adding this card.', 'woocommerce-gateway-worldpay' ), 'error' );
					return;
				}

				$token_value 	= wc_clean( $_POST['worldpay_token'] );
				$response 		= json_decode( str_replace( '\"','"',$_POST['worldpay_response'] ), true );

				// Store the token, the WorldPay way
				update_user_meta( $currentUser->ID, 'worldpay_token', $token_value );

				// Store the token the WC way
				$this->save_token( $token_value, $response['paymentMethod']['maskedCardNumber'], $response['paymentMethod']['expiryMonth'], $response['paymentMethod']['expiryYear'], $response['paymentMethod']['cardType'] );

				return array(
					'result'   => 'success',
					'redirect' => wc_get_endpoint_url( 'payment-methods' ),
				);

			} else {
				wc_add_notice( __( 'There was a problem adding the card. Please make sure you are logged in.', 'woocommerce-gateway-worldpay' ), 'error' );
				return;
			}


		}

		/**
		 * Order Notes
		 *
		 * ToDo : make order notes human readable
		 * ToDo : Check we are including everything
		 */
		private function order_notes( $order, $response ) {

			if( $order && $response ) {

				update_post_meta( $order->get_id(), '_worldpay_online_payment_response', $response );

				$payment_response 	= $response['paymentResponse'];
				$riskscore 			= $response['riskScore'];
				$resultCodes 		= $response['resultCodes'];

				unset( $response['token'] );
				unset( $response['paymentResponse'] );
				unset( $payment_response['billingAddress'] );
				unset( $response['riskScore'] );
				unset( $response['resultCodes'] );

				$response_array = $response;

				$successful_ordernote = '';

				foreach ( $response_array as $key => $value ) {
					$successful_ordernote .= $key . ' : ' . $value . "\r\n";
				}

				$order->add_order_note( __('<h4>Payment completed</h4>', 'woocommerce-gateway-worldpay') . '<br />' . 
						$successful_ordernote . 
						'<br />riskScore : ' . print_r( $riskscore, TRUE ) . 
						'<br />resultCodes : ' . print_r( $resultCodes, TRUE ) 
					);

			}


		}

        /**
         * [log_transaction description]
         * @param  Array   $tolog   contents for log
         * @param  String  $id      payment gateway ID
         * @param  String  $message additional message for log
         * @param  boolean $start   is this the first log entry for this transaction
         */
        public static function log_transaction( $tolog = NULL, $id, $message = NULL, $start = FALSE ) {

            if( !isset( $logger ) ) {
                $logger      = new stdClass();
                $logger->log = new WC_Logger();
            }

            /**
             * If this is the start of the logging for this transaction add the header
             */
            if( $start ) {

                $logger->log->add( $id, __('', 'woocommerce-gateway-worldpay') );
                $logger->log->add( $id, __('=============================================', 'woocommerce-gateway-worldpay') );
                $logger->log->add( $id, __('Worldpay Log', 'woocommerce-gateway-worldpay') );

            }

            $logger->log->add( $id, __('=============================================', 'woocommerce-gateway-worldpay') );
            $logger->log->add( $id, $message );
            $logger->log->add( $id, print_r( $tolog, TRUE ) );
            $logger->log->add( $id, __('=============================================', 'woocommerce-gateway-worldpay') );

        }

		/**
		 * [ownform description]
		 * @return [type] [description]
		 */
		function ownform() {

			wp_enqueue_script( 'wc-credit-card-form' );

?>
			<fieldset id="<?php echo $this->id; ?>-cc-form" data-logged-in="<?php echo is_user_logged_in(); ?>">

				<span id="paymentErrors"></span>
<?php
				// Add billing checkout fields to checkout form
				if( is_add_payment_method_page() ) {
?>
					<div class="woocommerce-billing-fields__field-wrapper">
<?php
					$fields = WC()->checkout()->get_checkout_fields( 'billing' );
					foreach ( $fields as $key => $field ) {
						woocommerce_form_field( $key, $field, WC()->checkout()->get_value( $key ) );
					}
?>
					</div>
<?php
				}			
				parent::payment_fields();
?>
				<div class="clear"></div>
			</fieldset>

<?php
			if( ( is_checkout() && !isset( $_GET['pay_for_order'] ) ) ) {
				include ( WPOLPLUGINPATH . 'scripts/ownform.js' );
			} elseif( is_add_payment_method_page() ) {
				include( WPOLPLUGINPATH . 'scripts/ownform_add_payment_method.js' );
			} else {
				include( WPOLPLUGINPATH . 'scripts/ownform_order_review.js' );
    		}
		}

		/**
		 * [show_errors description]
		 * @param  [type] $checkout [description]
		 * @return [type]           [description]
		 */
		function show_errors( $checkout ) {
			
			// Get the Order ID
			$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
			
			if( ! empty( $order_id ) ) {
				$errors = get_post_meta( $order_id, '_worldpay_errors', TRUE );
				if( ! empty( $errors ) ) {
					wc_print_notice( $errors, 'error' );
				}

				// Make sure to delete the error message immediatley after showing it.
				// 
				// DON'T delete the message if the customer created an account during checkout
				// WooCommerce reloads the checkout after creating the account so the message will disappear :/ 
				$reload_checkout = WC()->session->get( 'reload_checkout' ) ? WC()->session->get( 'reload_checkout' ) : NULL;
				if( is_null($reload_checkout) ) {
					delete_post_meta( $order_id, '_worldpay_errors' );
				}
				
			}
		}

	    /**
	     * get shopper accept header for 3ds
	     * @return string $acceptHeader
	     * */
	    public function getShopperAcceptHeader() {
	       return isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '*/*';
	    }

	} // WC_Gateway_Worldpay

	function add_query_vars_filter( $vars ){
	  $vars[] = "status";
	  return $vars;
	}

	add_filter( 'query_vars', 'add_query_vars_filter' );
