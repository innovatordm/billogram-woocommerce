<?php

/**
* 
*/
require_once('billogramApi.php');

function billogramAjaxInit() {
	new BillogramAjax;
}
add_action('admin_init', 'billogramAjaxInit');

class BillogramAjax {
	
	private $bill,
			$api;
	
	function __construct() {
		$this->bill = new BillogramWC;

		$this->api = new BillogramApiWrapper($this->bill->apiUser, $this->bill->apiPassword, $this->bill->apiUrl);
		add_action('wp_ajax_send_invoice', array($this, 'sendInvoice'));
		add_action('wp_ajax_create_renewal', array($this, 'createRenewal'));
	}

	public function sendInvoice() {
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
						$order->update_status( 'failed', __( 'Misslyckades med att skapa fakturan', 'woocommerce' ) );
						echo "Något gick fel, fakturan har inte skickats!";
						wp_die();
					}
				}
				$this->api->send();
				echo "Fakturan skickad";
				wp_die();
			}
		} catch (Exception $e) {
			echo "Något gick fel, fakturan har inte skickats!";
		}
		wp_die();
	}

	public function createRenewal() {
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
						echo "Order skapad!";
					} else {
						echo "Ordern innehåller ingen prenumeration!";	
					}
				} else {
					echo "Woocommerce Subscriptions hittades inte!";
				}
				wp_die();
			}
		} catch (Exception $e) {
			echo "Något gick fel, ordern har inte skapats!";
		}
		wp_die();
	}
}

?>