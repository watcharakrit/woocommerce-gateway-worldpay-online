<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worldpay_PaymentForm
{
	public static function render_payment_form( $storeTokens = false, $cardDetails = null ) {

		self::payment_errors_section();

		if ( null != $cardDetails ) {
			// self::existing_details_script();
			add_action( 'wp_footer', array( __CLASS__, 'existing_details_script' ), 0 );
			self::existing_details_fields( $cardDetails );
		} else {
			// self::no_existing_details_script();
			add_action( 'wp_footer', array( __CLASS__, 'no_existing_details_script' ), 0 );
		}

		self::common_fields($cardDetails);
		self::store_tokens_options( $storeTokens, $cardDetails );
	}

	public static function payment_errors_section() {
		?>
			<div id="worldpay-payment-errors"></div>
		<?php
	}

	public static function existing_details_fields( Worldpay_CardDetails $card_details ) {
		?>
			<p class="form-row">
				<label for="worldpay_saved_card">
					<strong>
						Saved card details:
					</strong>
				</label>
				<input id="worldpay_saved_card" type="text" class="input-text" disabled autocomplete="off" value="<?php echo $card_details->masked_card_number ?>"/>

				<input id="worldpay_token" type="hidden" data-worldpay="token" value="<?php echo $card_details->token ?>"/>
			</p>

			<p class="form-row">
				<label for="worldpay_saved_card">
					<strong>
						CVC:
					</strong>
				</label>
				<input style="width:150px" id="worldpay_cvc" type="text" data-worldpay="cvc" value=""/>
			</p>

			<p class="form-row">
				<label for="worldpay_use_saved_card_details">
					Use saved card details?
				</label>
				<input id="worldpay_use_saved_card_details" type="checkbox" name="worldpay_use_saved_card_details" checked/>
			</p>
			
			<p class="form-row worldpay_new_card_fields form-row-wide" <?=$card_details? 'style="display: none;"': ''?>>
				<strong>
					New card details:
				</strong>
			</p>
		<?php
	}

	public static function common_fields($cardDetails) {
		?>
			<div class="worldpay_new_card_fields" id="worldpay-templateform" <?=$cardDetails? 'style="display: none;"': ''?>>Loading..</div>
		<?php
	}

	public static function month_select() {
		?>
			<select id="worldpay_expiration_month" data-worldpay="exp-month">
				<option value="">Month</option>
				<?php for($i = 1; $i <=12; $i++) { ?>
					<?php $formatted = sprintf("%02d",$i); ?>
					<option value="<?php echo $formatted ?>"><?php echo $formatted ?> - <?= date("F", mktime(0, 0, 0, $i, 10)) ?></option>
				<?php } ?>
			</select>
		<?php
	}

	public static function year_select() {
		?>
			<select id="worldpay_expiration_year" data-worldpay="exp-year">
				<option value="">Year</option>
				<?php
				$year = date("Y");
				for($i = $year; $i <= $year + 13; $i++) { ?>
					<option value="<?php echo $i ?>"><?php echo $i ?></option>
				<?php } ?>
			</select>
		<?php
	}

	public static function store_tokens_options( $storeTokens, $cardDetails ) {
		if( $storeTokens ) {
		?>
		<p class="form-row form-row-wide" <?=$cardDetails? 'style="display: none;"': ''?>>
			<label for="worldpay_save_card_details">
				<?php echo __('Save card details?') ?>
			</label>
			<input id="worldpay_save_card_details" type="checkbox" name="worldpay_save_card_details" checked/>
		</p>
	<?php
		} else {
		?>
		<p class="form-row form-row-wide" style="visibility: hidden;">
			<input id="worldpay_save_card_details" type="hidden" name="worldpay_save_card_details" value=""/>
		</p>
	<?php
		}
	}

	public static function no_existing_details_script() {
		?>
			<script type="text/javascript">
				jQuery(function($){
					$(document).ready(function(){
						WorldpayCheckout.setupNewCardForm();
					});
				});
			</script>
		<?php
	}

	public static function existing_details_script() {
		?>
			<script type="text/javascript">
				jQuery(function($){
					$(document).ready(function(){
						$('#order_review').on('click', '#worldpay_use_saved_card_details', function() {
							$('.worldpay_new_card_fields').toggle();
							$('#worldpay_save_card_details').parent().toggle();
						})
						WorldpayCheckout.setupNewCardForm();
					});
				});
			</script>
		<?php
	}

	public static function three_ds_redirect($response, $order) {
		?>
		<form id="submitForm" method="post" action="<?php echo $response['redirectURL'] ?>">
			<input type="hidden" name="PaReq" value="<?php echo $response['oneTime3DsToken']; ?>"/>
			<input type="hidden" id="termUrl" name="TermUrl" value="<?php echo $order->get_checkout_payment_url( true ); ?>"/>
			<script>
				document.getElementById('submitForm').submit();
			</script>
		</form>
		<?php
	}

	public static function render_paypal_form() {
		?>
			<?php echo __('You will be redirected to PayPal to complete your transaction.') ?>
			<script type="text/javascript">
				WorldpayCheckout.setupPayPalForm();
			</script>
		<?php
	}

	public static function render_giropay_form() {
		?>
			<?php echo __('You will be redirected to Giropay to complete your transaction.') ?>

			<p class="form-row form-row-first">
				<label for="worldpay_swift_code">
					<strong>
						Swift code:
					</strong>
				</label>
				<input id="worldpay_swift_code" type="text" class="input-text" autocomplete="off" value=""/>
			</p>
			<script type="text/javascript">
				WorldpayCheckout.setupGiropayForm();
			</script>
		<?php
	}
}
