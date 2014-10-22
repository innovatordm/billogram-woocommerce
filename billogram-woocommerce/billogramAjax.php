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
		/*
		echo $this->bill->apiUrl;
		die; */

		$this->api = new BillogramApiWrapper($this->bill->apiUser, $this->bill->apiPassword, $this->bill->apiUrl);
		add_action('wp_ajax_send_invoice', array($this, 'sendInvoice'));
	}

	public function sendInvoice() {
			$post = get_post(intval($_POST['orderId']));
			$gateway = get_post_meta($post->ID, '_payment_method', true);
		try {
			if($post->post_status === 'wc-on-hold' && $gateway === 'billogramwc') {
				$invoiceId = get_post_meta($post->ID, '_billogram_id', true);
				$this->api->getInvoice($invoiceId);
				echo $this->api->send();
			}
		} catch (Exception $e) {
			echo "Something went wrong. " . $e;
		}
		die;
	}
}

?>