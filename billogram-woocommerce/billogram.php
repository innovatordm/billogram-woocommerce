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
$requires = array(
	'billogramApi.php',
	'billogramUi.php',
	'billogramAjax.php'
);

foreach ($requires as $require) {
	require_once($require);
}


add_action('plugins_loaded', 'BillogramWCInit', 0);

function BillogramWCInit() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Localisation
	 */
	load_plugin_textdomain('billogram-wc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
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
			$this->method_title         = __( 'Billogram', 'woocommerce' );
			$this->method_description   = __( 'Ta betalt med faktura, via Billogram.', 'woocommerce' );
			
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
		}
		/**
		* Output for the order received page.
		*/
		public function thankyou_page() {
			if ( $this->instructions )
	      		echo wpautop( wptexturize( $this->instructions ) );
		}
		/**
	    * Add content to the WC emails.
	    *
	    * @access public
	    * @param WC_Order $order
	    * @param bool $sent_to_admin
	    * @param bool $plain_text
	    */
	  
	    /*
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
	        if ( $this->instructions && ! $sent_to_admin && 'manualinvoice' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	    */

	    /**
	    * Process the payment and return the result
	    *
	    * @param int $order_id
	    * @return array
	    */
		public function process_payment( $order_id ) {
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
			$customer_no = (null !== $bill->getCustomerField('customer_no')) ? $bill->getCustomerField('customer_no') : $bill->getFirstCustomerByField('contact:email', $order->billing_email)->customer_no;
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
			// Update invoice with woocommerce address
			$thing = $bill->updateInvoiceAddress(
				$order->shipping_address_1, // Street address
				$order->shipping_postcode, // Zip
				$order->shipping_city // City
			);
			// Mark as on-hold (we're awaiting the manualinvoice)
			$order->update_status( 'on-hold', __( 'Awaiting invoice approval', 'woocommerce' ) );

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
						$order->update_status( 'pending', __( 'Faktura har skickats, inväntar betalning.<br>', 'woocommerce' ) );
						update_post_meta($order->id, '_billogram_status', $entityBody->billogram->state);
					break;
					
					case 'Payment':
						// Was payment manually entered by seller?
						if($entityBody->event->data->manual) { // Yes
							$order->add_order_note( 
								sprintf(
									__( 'Manuell betalning från kund, angiven av säljare.<br>Belopp: %d kr.<br>%d kr kvarstår.', 'woocommerce' ), 
									$entityBody->event->data->amount, 
									$entityBody->event->data->remaining_sum
								), 
							0);
						} else { // No
							$order->add_order_note( 
								sprintf(
									__( 'Betalning från kund.<br>Belopp: %d kr. <br>%d kr kvarstår.', 'woocommerce' ), 
									$entityBody->event->data->amount, 
									$entityBody->event->data->remaining_sum
								), 
							0);
						}
					break;

					case 'BillogramEnded':
						// Completed payment with invoice id
						$order->payment_complete($entityBody->billogram->id);
						$order->add_order_note(__( 'Hela fakturabeloppet har blivit betalt.', 'woocommerce' ));
					break;
					
					default:
						# code...
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
					'title'   => __( 'Aktivera/Avaktivera', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Aktivera Billogram', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Titel', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Skriv den titel du vill visa vid kassan.', 'woocommerce' ),
					'default'     => __( 'Billogram', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Skriv den beskrivning du vill visa vid kassan.', 'woocommerce' ),
					'default'     => __( 'Betala via faktura', 'woocommerce' )
				),
				'testmode' => array(
					'title'       => __( 'Billogram sandbox', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Aktivera Billogram test miljö', 'woocommerce' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Testa billogram i test miljön, du måste registrera ett utvecklarkonto hos billogram för detta. Mer information <a href="%s">här</a>.', 'woocommerce' ), 'https://billogram.com/api' ),
				), /*
				'debug' => array(
					'title'       => __( 'Debug Log', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log Billogram events, such as IPN requests, inside <code>%s</code>', 'woocommerce' ), wc_get_log_file_path( 'billogram' ) )
				), */
				'advanced' => array(
					'title'       => __( 'Faktura inställningar', 'woocommerce' ),
					'type'        => 'title',
					'description' => '',
				),
				'paymentaction' => array(
					'title'       => __( 'Godkänn och skicka fakturor', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Välj om du vill skicka fakturor automatiskt, eller om du vill godkänna dom först.', 'woocommerce' ),
					'default'     => 'authorize',
					'desc_tip'    => true,
					'options'     => array(
						'allow'          => __( 'Godkänn och skicka automatiskt', 'woocommerce' ),
						'authorize' => __( 'Godkänn och skicka manuellt', 'woocommerce' )
					)
				),
				'api_details' => array(
					'title'       => __( 'API iställningar', 'woocommerce' ),
					'type'        => 'title',
					'description' => sprintf( __( 'För att hitta dina API detaljer väljer du som inloggad först inställningar högst upp till höger, scrolla ned till sektionen API och skapa en API-nyckel. %sLänk till billogram inställningar%s.', 'woocommerce' ), 
						'<a href="https://billogram.com/settings">', '</a>' ),
				),
				'api_username' => array(
					'title'       => __( 'API Username', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Ditt API användarnamn från Billogram.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'Du måste fylla i detta fält', 'woocommerce' )
				),
				'api_password' => array(
					'title'       => __( 'API Password', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Ditt API lösenord från Billogram.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'Du måste fylla i detta fält', 'woocommerce' )
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
	
	add_filter('woocommerce_payment_gateways', 'addBillogramGateway' );
} 