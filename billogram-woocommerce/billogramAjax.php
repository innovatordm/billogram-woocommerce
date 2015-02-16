<?php

	/**
	* Class to handle all ajax calls to the plugin
	*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once('billogramApi.php');

function billogramAjaxInit() {
	new BillogramAjax;
}
add_action('admin_init', 'billogramAjaxInit');

class BillogramAjax {
	
	private $bill,
			$api;
	
	function __construct() {
		add_action('wp_ajax_send_invoice', array($this, 'sendInvoice'));
		add_action('wp_ajax_create_renewal', array($this, 'createRenewal'));
	}

	public function setup()
	{
		$this->bill = new BillogramWC;
		$this->api = new BillogramApiWrapper($this->bill->apiUser, $this->bill->apiPassword, $this->bill->apiUrl);
	}

	public function sendInvoice() {
		$this->setup();
		// Check nonce, if invalid die
		check_ajax_referer( "innovBilloNonce", "BillSec", true );

		$post = get_post(intval($_POST['orderId']));
		$gateway = get_post_meta($post->ID, '_payment_method', true);
		$recurringGateway = get_post_meta($post->ID, '_recurring_payment_method', true);
		try {
			if( $gateway === 'billogramwc' || $recurringGateway === 'billogramwc') {
				$invoiceId = get_post_meta($post->ID, '_billogram_id', true);
				if($invoiceId !== '') {
					$this->api->getInvoice($invoiceId);
					//echo $this->api->getInvoiceCustomerValue('email');
					
				} else {
					try {
						$this->bill->createInvoiceOrder($post->ID);
						$invoiceId = get_post_meta($post->ID, '_billogram_id', true);
						$this->api->getInvoice($invoiceId);
					} catch (Exception $e) {
						$order = wc_get_order($post->ID);
						$order->update_status( 'failed', __( 'Failed to create the invoice', 'billogram-wc' ) );
						echo __("Something went wrong, the invoice was not sent!", 'billogram-wc' );
						wp_die();
					}
				}
				$this->api->send();
				echo __("Invoice sent", 'billogram-wc' );
				wp_die();
			}
		} catch (Exception $e) {
			echo __("Something went wrong, the invoice was not sent!", 'billogram-wc' );
		}
		wp_die();
	}

	public function createRenewal() {
		$this->setup();
		// Check nonce, if invalid die
		check_ajax_referer( "innovBilloNonce", "BillSec", true );

		$post = get_post(intval($_POST['orderId']));
		$gateway = get_post_meta($post->ID, '_payment_method', true);

		try {
			if( $gateway === 'billogramwc') {
				$invoiceId = get_post_meta($post->ID, '_billogram_id', true);
				if(class_exists( 'WC_Subscriptions_Order' ) ) {
					if (WC_Subscriptions_Order::order_contains_subscription( $post->ID ) ) {
						//WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $post->ID, $product_id = '');
						WC_Subscriptions_Manager::process_subscription_payments_on_order( $post->ID, $product_id = '');
						echo __("Order created!", 'billogram-wc' );
					} else {
						echo __("This order does not contain any subscriptions!", 'billogram-wc' );	
					}
				} else {
					echo __("Woocommerce Subscriptions was not found!", 'billogram-wc' );
				}
				wp_die();
			}
		} catch (Exception $e) {
			echo __("Something went wrong, the order was not created!", 'billogram-wc' );
		}
		wp_die();
	}
}

?>