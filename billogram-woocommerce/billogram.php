<?php
/*
Plugin Name: WooCommerce Billogram Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Billogram payment gateway for woocommerce
Version: 0.1
Author: Innovator Digital Markets AB
Author URI: http://innovator.se/

	Copyright: Â© 2014-2014 Innovator Digital Markets AB.
	License: All rights reserved
	License URI: http://www.innovator.se
*/
use billogramApi as Billogram;

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
	 	function __construct() {
		 	$this->id                   = 'billogramwc';
			$this->has_fields           = true;
			$this->liveurl              = 'https://www.paypal.com/cgi-bin/webscr';
			$this->testurl              = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
			$this->method_title         = __( 'Billogram', 'woocommerce' );
			$this->method_description   = __( 'Pay by invoice, using Billogram.', 'woocommerce' );
			
			// Plugin settings defines
			$this->init_form_fields();
			$this->init_settings(); // Init settings for usage

			/* Define user set settings here */
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );

			// Actions
	    	add_action( 'woocommerce_thankyou_billograminvoice', array( $this, 'thankyou_page' ) );
			// Save settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		    
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


		/*
		* Mandatory WC functions
		*/

		/* User settings */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Billogram', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Billogram', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Pay via invoice; invoices man!', 'woocommerce' )
				),
				'testmode' => array(
					'title'       => __( 'Billogram sandbox', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Billogram sandbox', 'woocommerce' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Billogram sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>.', 'woocommerce' ), 'https://developer.paypal.com/' ),
				),
				'debug' => array(
					'title'       => __( 'Debug Log', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log Billogram events, such as IPN requests, inside <code>%s</code>', 'woocommerce' ), wc_get_log_file_path( 'paypal' ) )
				),
				'advanced' => array(
					'title'       => __( 'Advanced options', 'woocommerce' ),
					'type'        => 'title',
					'description' => '',
				),
				'paymentaction' => array(
					'title'       => __( 'Payment Action', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose whether you wish to allow invoicces immediately or authorize them manually.', 'woocommerce' ),
					'default'     => 'sale',
					'desc_tip'    => true,
					'options'     => array(
						'allow'          => __( 'Allow', 'woocommerce' ),
						'authorize' => __( 'Authorize', 'woocommerce' )
					)
				),
				'api_details' => array(
					'title'       => __( 'API Credentials', 'woocommerce' ),
					'type'        => 'title',
					'description' => sprintf( __( 'Enter your Billogram API credentials. Learn how to access your Billogram API Credentials %shere%s.', 'woocommerce' ), '<a href="https://developer.paypal.com/webapps/developer/docs/classic/api/apiCredentials/#creating-classic-api-credentials">', '</a>' ),
				),
				'api_username' => array(
					'title'       => __( 'API Username', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Get your API credentials from Billogram.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'Optional', 'woocommerce' )
				),
				'api_password' => array(
					'title'       => __( 'API Password', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Get your API credentials from Billogram.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'Optional', 'woocommerce' )
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