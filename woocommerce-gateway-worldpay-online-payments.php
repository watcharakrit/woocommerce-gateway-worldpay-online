<?php
/**
 * Plugin Name: Worldpay Online Payments
 * Plugin URI: https://woocommerce.com/products/worldpay-online-payments/
 * Description: A plugin for integrating Worldpay Online Payments with WooCommerce..
 * Version: 2.1.5
 * Author: Worldpay, WooCommerce, Andrew Benbow
 * Developer: Andrew Benbow
 * Author URI: http://www.chromeorange.co.uk
 * WC requires at least: 3.4.0
 * WC tested up to: 4.8.0
 * Woo: 3345040:ee95ecddca66ba1374158b6e52e4bd57
 *
 * @package Worldpay Online Payments
 * @category Core
 * @author Worldpay, WooCommerce, Andrew Benbow
 */

/*  Copyright 2018  Andrew Benbow (email : support@chromeorange.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'ee95ecddca66ba1374158b6e52e4bd57', '3345040' );

/**
 * Defines
 */
define( 'WPOLVERSION', 		'2.1.5' );
define( 'WPOLSETTINGS', 	admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_worldpay' ) );
define( 'WPOLSUPPORTURL', 	'http://support.woocommerce.com/' );
define( 'WPOLDOCSURL', 		'https://docs.woocommerce.com/document/worldpay-online-payment-gateway/');
define( 'WPOLPLUGINPATH', 	plugin_dir_path( __FILE__ ) );
define( 'WPOLPLUGINURL', 	plugin_dir_url( __FILE__ ) );
define( 'WPOLSERVICEKEY', 	'T_S_8d9f4d8f-dc28-4a11-a2bc-059e05fbc25f' );
define( 'WPOLCLIENTKEY',  	'T_C_f33d3c75-b67b-4bf2-a0c1-d6e7171d901b' );

// define( 'WP_WORLDPAY_DEBUGGING', TRUE );

if( ! defined( 'WP_WORLDPAY_DEBUGGING' ) ) {
	define( 'WP_WORLDPAY_DEBUGGING', FALSE );
}

load_plugin_textdomain( 'woocommerce-gateway-worldpay', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'languages' );

add_action( 'plugins_loaded', 'init_worldpay_online_gateway', 0 );

function init_worldpay_online_gateway() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    	return;
    }

    /**
     * add_worldpay_online_gateway function.
     *
     * @access public
     * @param mixed $methods
     * @return void
     */
    require_once('libs/worldpay-wordpress-lib.php');
	require_once('Persistence/token.php');
	require_once('Persistence/card-details.php');
	require_once('Forms/payment-form.php');
	require_once('Forms/admin-form.php');
	require_once('Webhooks/webhook-request.php');
	require_once('Constants/worldpay-response-states.php');

	include('classes/class-worldpay-online.php');

    function add_worldpay_online_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Worldpay';
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_worldpay_online_gateway' );

}



if ( ! class_exists( 'WC_WPOL' ) ) {
	class WC_WPOL {

		private static $instance;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

        public function __construct() {

        	add_action( 'plugins_loaded', array( $this, 'init' ) );
        	add_action( 'admin_init', array( $this, 'admin_init' ) );

        }

    	public function init() {

            // Get settings
            $this->woocommerce_worldpay_online_settings = get_option( 'woocommerce_WC_Gateway_Worldpay_settings' );

            // Client and Service keys
			$test_service_key 	= $this->woocommerce_worldpay_online_settings['test_service_key'];
			$test_client_key  	= $this->woocommerce_worldpay_online_settings['test_client_key'];

			$test_service 		= isset( $test_service_key ) && $test_service_key != '' ? $test_service_key : WPOLSERVICEKEY;
			$test_client  		= isset( $test_client_key )  && $test_client_key  != '' ? $test_client_key  : WPOLCLIENTKEY;

			$this->service_key 	= !isset( $this->woocommerce_worldpay_online_settings['is_test'] ) || $this->woocommerce_worldpay_online_settings['is_test'] == 'yes' ? $test_service : $this->woocommerce_worldpay_online_settings['service_key'];
			$this->client_key  	= !isset( $this->woocommerce_worldpay_online_settings['is_test'] ) || $this->woocommerce_worldpay_online_settings['is_test'] == 'yes' ? $test_client  : $this->woocommerce_worldpay_online_settings['client_key'];

			// Delete Token from Worldpay, if it exists there
            add_action( 'woocommerce_payment_token_deleted', array( $this, 'woocommerce_payment_token_deleted' ), 10, 2 );

            // Add 'Authorised' order status
            add_action( 'init', array( $this, 'worldpay_online_register_order_status' ) );

			// Set wc-authorized in WooCommerce order statuses.
        	add_filter( 'wc_order_statuses', array( $this, 'worldpay_online_order_statuses' ) );
			
            // Remove Save Card Option
            // apply_filters( 'woocommerce_payment_gateway_save_new_payment_method_option_html', $html, $this );
            if ( $this->woocommerce_worldpay_online_settings['store_tokens'] != 'yes' ) {
            	add_filter( 'woocommerce_payment_gateway_save_new_payment_method_option_html', array( $this, 'remove_save_card' ), 10, 2 );
            }

            /**
             * Testing
             * Turn off WooCommerce postcode validation to allow for AVS checking
             * https://beta.developer.worldpay.com/docs/access-worldpay/reference/testing/payments-test-values
             */
            if( $this->woocommerce_worldpay_online_settings['is_test'] == 'yes' ) {
                add_filter( 'woocommerce_validate_postcode', array( $this, 'worldpay_online_validate_postcode'), 10, 3 );
            }

		}

        /**
         * [worldpay_online_disable_validate_postcode description]
         * Force the postcode to be valid regardless of value
         * @return true
         */
        function worldpay_online_validate_postcode( $valid, $postcode, $country ) {
            return true;
        }

		function remove_save_card( $html, $gateway ) {
			return null;
		}

		public function admin_init() {
			// Plugin Links
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this,'plugin_links' ) );

            // Enqueue Admin Scripts and CSS
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 99 );

            // Add 'Capture Authorised Payment' to WooCommerce Order Actions
        	add_filter( 'woocommerce_order_actions', array( $this, 'worldpay_online_woocommerce_order_actions' ) );
			
		}

        /**
         * Plugin page links
         */
        function plugin_links( $links ) {

            $plugin_links = array(
                '<a href="' . WPOLSETTINGS . '">' . __( 'Settings', 'woocommerce-gateway-worldpay' ) . '</a>',
                '<a href="' . WPOLSUPPORTURL . '">' . __( 'Support', 'woocommerce-gateway-worldpay' ) . '</a>',
                '<a href="' . WPOLDOCSURL . '">' . __( 'Docs', 'woocommerce-gateway-worldpay' ) . '</a>',
            );

            return array_merge( $plugin_links, $links );
        }

        /**
         * Load admin CSS
         * @param  [type] $hook [description]
         * @return void
         */
        function admin_scripts( $hook ) {
        	wp_enqueue_style( 'WC_Gateway_Worldpay-admin-fa', "//netdna.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.css" , array(), WPOLVERSION );
            wp_enqueue_style( 'worldpay-online-admin-wp', plugin_dir_url(__FILE__) . 'woocommerce/css/admin-css.css' , array(), WPOLVERSION );
        }

        /**
         * [sagepayments_woocommerce_order_actions description]
         * Add Capture option to the Order Actions dropdown.
         */
        function worldpay_online_woocommerce_order_actions( $orderactions ) {
            global $post;
            $id     = $post->ID;
            $order  = new WC_Order( $id );

            if ( $order->get_status() === 'authorised' ) {
                $orderactions['worldpay_online_process_payment'] = 'Capture Authorised Payment';
            }

            return $orderactions;
        }

        
        /**
         * New order status for WooCommerce 2.2 or later
         *
         * @return void
         */
        function worldpay_online_register_order_status() {
            register_post_status( 'wc-authorised', array(
                'label'                     => _x( 'Authorised Payments', 'Order status', 'woocommerce-gateway-worldpay' ),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'Authorised, not captured <span class="count">(%s)</span>', 'Authorised, not captured <span class="count">(%s)</span>', 'woocommerce-gateway-worldpay' )
            ) );
        }

        
        /**
         * Set wc-authorized in WooCommerce order statuses.
         *
         * @param  array $order_statuses
         * @return array
         */
        function worldpay_online_order_statuses( $order_statuses ) {
            $order_statuses['wc-authorised'] = _x( 'Authorised', 'Order status', 'woocommerce-gateway-worldpay' );

            return $order_statuses;
        }

        /**
		 * Delete token from Worldpay
		 */
		public function woocommerce_payment_token_deleted( $token_id, $token ) {

			if ( 'WC_Gateway_Worldpay' === $token->get_gateway_id() ) {

				// Make sure we delte the token from the user
				delete_user_meta( get_current_user_id(), 'worldpay_token', $token->get_token() );
				
				// Set URL
				$url = 'https://api.worldpay.com/v1/tokens/' . $token->get_token();

			    /**
			     * Build Header
			     */
			    $header = array(
			                "Authorization"	=> $this->service_key,
			                "content-type"	=> "application/json",
			            );
				/**
				 * Delete the token from Worldpay
				 */
				$contents = array(
							'method' 		=> 'DELETE',
							'timeout' 		=> 45,
							'redirection' 	=> 5,
							'httpversion' 	=> '1.0',
							'blocking' 		=> true,
							'headers' 		=> $header,
							'body' 			=> '',
							'cookies' 		=> array()
						);

				$response = wp_remote_post( $url, $contents );

			}

		}

	}

	$GLOBALS['wc_wpol'] = WC_WPOL::get_instance();
}
