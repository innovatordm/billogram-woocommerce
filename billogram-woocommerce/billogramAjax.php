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
		try {
			$postId = get_post_meta(intval($_POST['orderId']), '_billogram_id', true);
			$this->api->getInvoice($postId);
			echo $this->api->getInvoiceValue('id');
		} catch (Exception $e) {
			echo "Something went wrong.";
		}
		die;
	}
}

?>