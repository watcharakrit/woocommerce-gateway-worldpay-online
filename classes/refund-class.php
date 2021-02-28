<?php
	/**
	 * Refunds for Worldpay Online
	 */
	class WC_WPOL_Refund extends WC_Gateway_Worldpay {

		private $order_id;
		private $amount;
		private $reason;

		public function __construct( $order_id, $amount, $reason ) {

			parent::__construct();

			$this->order_id 	= $order_id;
			$this->amount 		= $amount;
			$this->reason 		= $reason;

		}
	
		function refund() {

			$order = wc_get_order( $this->order_id );

			if ( ! $order || !$order->get_transaction_id() ) {
				return false;
			}

			/**
			 * Set the amount for refunding, if it's the full order amount then set $amount = null
			 */
			if( $this->amount == $order->get_total() ) {
				$amount = null;
			} else {
				$amount = $this->amount * 100;
			}
			
			try {

				$response = $this->get_worldpay_client()->refundOrder( $order->get_transaction_id(), $amount );

				if ( $this->logging == true ) {
	            	$this->log_transaction( $response, $this->id, 'Refund Success $response : ', $start = true );
	            }

				$order->add_order_note( __('Refund successful', 'woocommerce-gateway-worldpay') . '<br />' . 
										__('Refund Amount : ', 'woocommerce-gateway-worldpay') . $this->amount . '<br />' .
										__('Refund Reason : ', 'woocommerce-gateway-worldpay') . $this->reason . '<br />' );

				return true;

			} catch (WorldpayException $e) {
				// Logging
	            if ( $this->logging == true ) {
	            	$this->log_transaction( $e, $this->id, 'Refund Failure $response : ', $start = true );
	            }
				return new WP_Error("refund-error", __('Refund failed, please refund manually', 'woocommerce-gateway-worldpay'));
			}

		}

	} // End class
