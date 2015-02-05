<?php
/*
Plugin Name: WooCommerce Billogram Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Billogram payment gateway for woocommerce
Version: 0.1
Author: Innovator Digital Markets AB
Author URI: http://innovator.se/

	Copyright: Â© 2014-2015 Innovator Digital Markets AB.
	License: All rights reserved
	License URI: http://www.innovator.se
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


add_action('plugins_loaded', 'BillogramWCInit', 0);
load_plugin_textdomain('billogram-wc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

function BillogramWCInit() {
	
	$requires = array(
	'billogramApi.php',
	'billogramUi.php',
	'billogramAjax.php',
	'billogramStatus.php',
	'billogramEmail.php'
	);

	foreach ($requires as $require) {
		require_once($require);
	}

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Localisation
	 */
    
	/**
 	 * Gateway class
 	 */
	class BillogramWC extends WC_Payment_Gateway {
	
		/* 
		*
		* Construcy class for plugin defines
		*
		*/
	 	public function __construct() {
	 		
		 	$this->id                   = 'billogramwc';
			$this->has_fields           = true;
			$this->liveurl              = 'https://billogram.com/api/v2';
			$this->testurl              = 'https://sandbox.billogram.com/api/v2';
			$this->method_title         = __( 'Billogram', 'billogram-wc' );
			$this->method_description   = __( 'Pay by invoice.', 'billogram-wc' );
			// Declare support for subscriptions
			$this->supports = array( 
				'subscriptions', 
				'products', 
				'subscription_cancellation', 
               	'subscription_suspension', 
               	'subscription_reactivation',
               	'subscription_date_changes',
               	'subscription_payment_method_change',
               	'subscription_amount_changes'
            );
			//error_log('Billogram class');
			// Plugin settings defines
			$this->init_form_fields();
			$this->init_settings(); // Init settings for usage

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			$this->testMode		= $this->get_option( 'testmode' );
			$this->apiUrl		= ($this->testMode === 'yes') ? $this->testurl : $this->liveurl;
			$this->apiUser 		= $this->get_option( 'api_username' );
			$this->apiPassword 	= $this->get_option( 'api_password' );
			// Actions
	    	add_action( 'woocommerce_thankyou_billograminvoice', array( $this, 'thankyou_page' ) );
			// Save settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 	// Payment listener/API hook
			add_action( 'woocommerce_api_billogramwc', array( $this, 'billogramCallbacks' ) );   
			// Subscription actions/filters 
			add_action( 'scheduled_subscription_payment_billogramwc', array( $this, 'processSubscription' ), 10, 3 );   
			add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'processSubscriptionRenewal' ), 10, 4 );
		}
		/**
		* Output for the order received page.
		*/
		public function thankyou_page() {
			if ( $this->instructions )
	      		echo wpautop( wptexturize( $this->instructions ) );
		}

	    /**
	    * Process the payment and return the result
	    *
	    * @param int $order_id
	    * @return array
	    */
		public function process_payment( $order_id ) {
			// Mark as on-hold (we're awaiting the manual invoice)
			$order = wc_get_order( $order_id );

			try {
				$this->createInvoiceOrder($order_id);
			} catch (Exception $e) {
				error_log(print_r($e, true));
				$order->update_status( 'wc-awaiting-approval', __( '<strong>Failed to create invoice at Billogram.</strong>', 'billogram-wc' ) );
				// Return thankyou redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);
			}
			do_action('woocommerce_order_status_pending_to_awaiting_approval', $order_id);
			// Set to on-hold for invoice approval
			$order->update_status( 'wc-awaiting-approval', __( 'Waiting for order approval.', 'billogram-wc' ) );
			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
		// Create order invoice
		public function createInvoiceOrder($order_id)
		{
			$order = wc_get_order( $order_id );
			/*
			var_dump(get_post_meta($order->id));
			var_dump($order->get_shipping_method());
			die; 
			*/
			$bill = new BillogramApiWrapper(
				$this->apiUser,
				$this->apiPassword,
				$this->apiUrl
			);

			// Create customer, if it doesn't exist
			if(!$bill->customerExists($order->billing_email)) {
				$bill->setOptions(array(
					'name' => $order->billing_first_name . ' ' . $order->billing_last_name,
	                'company_type' => 'individual',
	                'org_no' => '',
	                'address' => array(
	                    'street_address' => $order->billing_address_1,
	                    'zipcode' => $order->billing_postcode,
	                    'city' => $order->billing_city,
	                    //'country' => $order->billing_country
	                ),
	                'contact' => array(
	                    'email' => $order->billing_email,
	                    'phone' => $order->billing_phone
	                )
				), 'customer');
				$bill->createCustomer();
			}
			
			// Get customer id from created customer or fetch from billogram if customer aldready exists
			$customer_no = (null !== $bill->getCustomerField('customer_no')) ? 
				$bill->getCustomerField('customer_no') : 
				$bill->getFirstCustomerByField('contact:email', $order->billing_email)->customer_no;
			
			// Add items to invoice
			$items = $order->get_items();
			foreach ($items as $item) {
				$bill->addItem(
					$item['qty'],
					($item['line_total'] / $item['qty']),
					(int) (($item['line_tax'] / $item['line_total'])*100), // Tax
					$item['name']
				);
			}
			// Add shipping cost
			$bill->addItem(
				1,
				$order->order_shipping,
				25,
				$order->get_shipping_method()
			);
			
			$current = date("Y-m-d");
			$due = date("Y-m-d", strtotime("+14 day"));
			// Key to sign callback
			$key = md5(uniqid("", true) . $order->id);
			// Generated callback url
			$callbackUrl = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'BillogramWC', home_url( '/' ) ) );
			// Save sign key to order info
			update_post_meta($order->id, '_billogram_sign_key', $key);

			$bill->setOptions(array(
				'invoice_date' => $current,
				'due_date' => $due,
				'customer' => array(
                    'customer_no' => $customer_no, // Must be defined!
                ),
                'callbacks' => array(
                	'sign_key' => $key, // Key to sign callback
                	'custom' => $order->id, // Associated order id
                	'url' => $callbackUrl
                ),
			), 'invoice');
			$bill->createInvoice();
			// Save invoice id to order
			update_post_meta($order->id, '_billogram_id', $bill->getInvoiceValue('id'));
			update_post_meta($order->id, '_billogram_status', 'Unattested');
			// Update invoice with woocommerce order details
			$thing = $bill->updateInvoiceCusomerDetails(
				$order->billing_first_name . ' ' . $order->billing_last_name,
				array(
                    'street_address' => $order->shipping_address_1, // Street address
                    'zipcode' => $order->shipping_postcode, // Zip
                    'city' => $order->shipping_city // City
                )	
			);
		}
		// Subscription related functions
		public function processSubscription($amount_to_charge, $order, $product_id = '') {
			$order->payment_complete();
			//$renewed = get_post_meta($order->id, '_billogram_order_renewed', true);
			//error_log($renewed . ' for order ' . $order->id);
			//if($renewed === '') {
				//update_post_meta($order->id, '_billogram_order_renewed', 'true');
				WC_Subscriptions_Manager::process_subscription_payments_on_order( $order, $product_id);
				error_log("Process subscription " . $amount_to_charge . " " . $order->id . " " . $product_id );
			//}
		}
		// Don't copy over the original orders invoice data
		public function processSubscriptionRenewal( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
			$order = wc_get_order($renewal_order_id);

			$order_meta_query .= " AND `meta_key` NOT IN ('_billogram_id', '_billogram_status', '_billogram_sign_key', '_billogram_order_renewed' )";
			
			//$order->update_status( 'on-hold', __( 'Väntar på att fakturan ska bli skickad', 'billogram-wc' ) );
			
			return $order_meta_query;
		}
		// Callback function for billogram
		public function billogramCallbacks() {
			$entityBody = json_decode(file_get_contents('php://input'));
			
			if(is_nan($entityBody->custom))
				wp_die( "Invalid request", "Billogram WC", array( 'response' => 200 ) );
			
			$check = md5($entityBody->callback_id . get_post_meta($entityBody->custom, '_billogram_sign_key', true));
			if($check === $entityBody->signature) {
				// error_log(print_r($entityBody, true));

				$order = wc_get_order( $entityBody->custom );

				switch ($entityBody->event->type) {
					
					case 'BillogramSent':
						if(class_exists( 'WC_Subscriptions_Order' ) ) {
							if (WC_Subscriptions_Order::order_contains_subscription( $order->id ) ) {
								$order->update_status( 'processing', __( 'Invoice sent, waiting for payment.<br>', 'billogram-wc' ) );
							} else {
								$order->update_status( 'pending', __( 'Invoice sent, waiting for payment.<br>', 'billogram-wc' ) );
							}
						} else {
							$order->update_status( 'pending', __( 'Invoice sent, waiting for payment.<br>', 'billogram-wc' ) );
						}						
						update_post_meta($order->id, '_billogram_status', $entityBody->billogram->state);
					break;
					
					case 'Payment':
						// Was payment manually entered by seller?
						if($entityBody->event->data->manual) { // Yes
							$order->add_order_note( 
								sprintf(
									__( 'Manual payment for invoice, registered by seller.<br>Amount: %d kr.<br>%d kr remains to be paid.', 'billogram-wc' ), 
									$entityBody->event->data->amount, 
									$entityBody->event->data->remaining_sum
								), 
							0);
						} else { // No
							$order->add_order_note( 
								sprintf(
									__( 'Payment from customer received.<br>Amount: %d kr. <br>%d kr remains to be paid.', 'billogram-wc' ), 
									$entityBody->event->data->amount, 
									$entityBody->event->data->remaining_sum
								), 
							0);
						}
						update_post_meta($order->id, '_billogram_status', $entityBody->billogram->state);
					break;

					case 'Overdue':
						if(class_exists( 'WC_Subscriptions_Order' ) ) {
							if (WC_Subscriptions_Order::order_contains_subscription( $order->id ) ) {
								$order->update_status( 'on-hold', __( 'Invoice has expired.', 'billogram-wc' ) );
								WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id = '' );
							} else {
								// Completed payment with invoice id
								$order->update_status( 'on-hold', __( 'Invoice has expired.', 'billogram-wc' ) );
							}
						} else {
							// Completed payment with invoice id
							$order->update_status( 'on-hold', __( 'Invoice has expired.', 'billogram-wc' ) );
						}
						update_post_meta($order->id, '_billogram_status', $entityBody->billogram->state);
					break;

					case 'BillogramEnded':
						//WC_Subscriptions_Manager::process_subscription_payments_on_order( $entityBody->billogram->id, $product_id = '' );
						$order->add_order_note(__( 'The invoice has been paid in whole.', 'billogram-wc' ));
						if(class_exists( 'WC_Subscriptions_Order' ) ) {
							if (WC_Subscriptions_Order::order_contains_subscription( $order->id ) || count( WC_Subscriptions_Manager::get_subscription( WC_Subscriptions_Manager::get_subscription_key( $order->id, $product_id = '' )) ) > 0) {
								$order->update_status( 'completed', __( 'Subscription has been paid.', 'billogram-wc' ) );
								//WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
							} else {
								// Completed payment with invoice id
								$order->payment_complete($entityBody->billogram->id);
							}
						} else {
							// Completed payment with invoice id
							$order->payment_complete($entityBody->billogram->id);
						}
						update_post_meta($order->id, '_billogram_status', $entityBody->billogram->state);
					break;
					
					default:
						//error_log(print_r( $entityBody, true));
					break;
				}
				wp_die( "Success", "Billogram WC", array( 'response' => 200 ) );
			} else wp_die( "Invalid request", "Billogram WC", array( 'response' => 200 ) );
		}

		/*
		* Mandatory WC functions
		*/

		/* User settings */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Activate/Deactivate', 'billogram-wc' ),
					'type'    => 'checkbox',
					'label'   => __( 'Activate Billogram', 'billogram-wc' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'billogram-wc' ),
					'type'        => 'text',
					'description' => __( 'Write the title you want to be displayed at checkout.', 'billogram-wc' ),
					'default'     => __( 'Billogram', 'billogram-wc' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'billogram-wc' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Write the description you want to be showed at checkout.', 'billogram-wc' ),
					'default'     => __( 'Betala via faktura', 'billogram-wc' )
				),
				'testmode' => array(
					'title'       => __( 'Billogram sandbox', 'billogram-wc' ),
					'type'        => 'checkbox',
					'label'       => __( 'Activate Billogram test environment', 'billogram-wc' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Test billogram in the sanbox environment, you must register a Sanbox account with Billogram in order to use this feature. More information can be found <a href="%s">here</a>.', 'billogram-wc' ), 'https://billogram.com/api' ),
				), /*
				'debug' => array(
					'title'       => __( 'Debug Log', 'billogram-wc' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'billogram-wc' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log Billogram events, such as IPN requests, inside <code>%s</code>', 'billogram-wc' ), wc_get_log_file_path( 'billogram' ) )
				), */
				'advanced' => array(
					'title'       => __( 'Invoice settings', 'billogram-wc' ),
					'type'        => 'title',
					'description' => '',
				),
				'paymentaction' => array(
					'title'       => __( 'Approval settings', 'billogram-wc' ),
					'disabled'	  => true,
					'type'        => 'select',
					'description' => __( 'Choose whether you want to approve invoices automatically or manually on orders.', 'billogram-wc' ),
					'default'     => 'authorize',
					'desc_tip'    => true,
					'options'     => array(
						'allow'          => __( 'Send invoices automatically', 'billogram-wc' ),
						'authorize' => __( 'Send invoices manually', 'billogram-wc' )
					)
				),
				'api_details' => array(
					'title'       => __( 'API settings', 'billogram-wc' ),
					'type'        => 'title',
					'description' => sprintf( __( 'To find your API credentials; login to Billogram and navigate to the account settings, then scroll down to the bottom of the page.  %sLink to Billogram settings page%s.', 'billogram-wc' ), 
						'<a href="https://billogram.com/settings">', '</a>' ),
				),
				'api_username' => array(
					'title'       => __( 'API Username', 'billogram-wc' ),
					'type'        => 'text',
					'description' => __( 'Your API username for Billogram API.', 'billogram-wc' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'This field must not be empty!', 'billogram-wc' )
				),
				'api_password' => array(
					'title'       => __( 'API Password', 'billogram-wc' ),
					'type'        => 'text',
					'description' => __( 'Your API password for Billogram API.', 'billogram-wc' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'This field must not be empty!', 'billogram-wc' )
				)
			);
		}
	}
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function addBillogramGateway($methods) {
		$methods[] = 'BillogramWC';
		return $methods;
	}
	function initBillogramEmail( $email_classes ) {
		require_once('billogramEmail.php');
    	// add the email class to the list of email classes that WooCommerce loads
    	$email_classes['BillogramEmail'] = new BillogramEmail();
    	return $email_classes;
	}
	
	// Add custom order statuses
	add_action( 'init', 'BillogramStatus::registerAllStatuses' );
	add_filter( 'wc_order_statuses', 'BillogramStatus::addStatusToBillogram' );
	add_filter('woocommerce_payment_gateways', 'addBillogramGateway' );
} 