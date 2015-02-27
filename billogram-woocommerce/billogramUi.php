<?php
	/**
	* Class to handle ui elements of the plugin
	*/

	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	function billogramUiInit() {
		new BillogramUi;
	}
	// Load on post.php only
	if (is_admin()) {
		add_action('load-post.php', 'billogramUiInit');
		add_action('load-edit.php', 'billogramUiInit');
	}

	class BillogramUi {
		function __construct() {
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'shop_order_columns'), 99);
			add_filter( 'manage_shop_order_posts_custom_column', array( $this, 'render_shop_order_columns'), 99);
			add_action( 'add_meta_boxes', array( $this, 'addOrderStatusMetaBox' ));
			add_action( 'admin_enqueue_scripts', array( $this, 'loadScripts'));
		}

		public function addOrderStatusMetaBox() {
			global $post;

			$gateway = get_post_meta($post->ID, '_payment_method', true);
			$recurringGateway = get_post_meta($post->ID, '_recurring_payment_method', true);

			if($gateway !== 'billogramwc' && $recurringGateway !== 'billogramwc')
				return;

			add_meta_box( 
		        'billogramActions', 
		        __( 'Billogram Invoice Actions', 'billogram-wc' ), 
		        array( $this, 'orderMetaBox' ), 
		        'shop_order', 
		        'side', 
		        'default' 
		    );
		}
		public function orderMetaBox() {
			global $post;
			$order = wc_get_order($post->ID);
			$items = $order->get_items();
			$product = new WC_Product($item['product_id']);
			$_tax = new WC_Tax();
			

			foreach ($items as $item) {
				
				$tax_rates  = $_tax->get_shop_base_rate( $product->tax_class );
				$taxes      = $_tax->calc_tax( $price, $tax_rates, true );
				$tax_amount = $_tax->get_tax_total( $taxes );
				$price      = round( $price - $tax_amount, 2);
				echo "<pre>";
				var_dump((int) $tax_rates[1]['rate']);
				echo "</pre>";
			}
			

			$invoice_status = get_post_meta($post->ID, '_billogram_status', true);
			$required = ($invoice_status === '' || $invoice_status === 'Unattested') ? '' : 'disabled';
			echo __("<h4>Invoice actions</h4>", 'billogram-wc' );
			echo '<input class="button button-primary" id="sendInvoice" value="Skicka faktura" type="submit"' . $required . '>';	
			if(class_exists( 'WC_Subscriptions_Order' ) ) {
				echo __("<h4>subscription actions</h4>", 'billogram-wc' );
				if (WC_Subscriptions_Order::order_contains_subscription( $post->ID ) ) {
					echo '<input class="button button-primary" id="createRenewal" value="Skapa förnyelseorder" type="submit">';	
				} else {
					echo __("<p class='howto'><strong>You must use the original sbuscription order, in order to create a renewal order.</strong></p>", 'billogram-wc' );
					echo '<input class="button button-primary" id="createRenewal" value="Skapa förnyelseorder" type="submit" disabled>';
				}
				echo __("<p class='howto'><strong>NOTE!</strong> Only use this if you want to create a new renewal order!</p>", 'billogram-wc' );
			}
		}

		public function loadScripts($hook) {
			global $post;
			if( ( empty($post) ) || 'post.php' !== $hook && $post->post_type !== 'shop_order')
				return;	
			wp_enqueue_script( 'billogram-ajax', plugins_url( '/js/billogramAjax.js', __FILE__ ), array('jquery') );
			wp_enqueue_style( 'billo-fontawesome', plugins_url('css/font-awesome/css/font-awesome.min.css', __FILE__));
			wp_enqueue_style( 'billogram-statusStyle', plugins_url('css/style.css', __FILE__), array('billo-fontawesome'));
			// in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
			wp_localize_script( 'billogram-ajax', 'billogramData',
	            array( 
	            	'ajaxUrl' => admin_url( 'admin-ajax.php' ), 
	            	'orderId' => $post->ID, 
	            	'nonce' => wp_create_nonce( "innovBilloNonce" ) 
	            ) 
            );
		}

		public function shop_order_columns( $columns ) {
	        $columns['invoice_status']    = __( 'Invoice status', 'billogram-wc' );  
	        return $columns;
	    }

	    public static function render_shop_order_columns( $column ) {

	   
	        global $post, $woocommerce, $the_order;
	        
	        if ( empty( $the_order ) || $the_order->id != $post->ID ) {
				$the_order = wc_get_order( $post->ID );
			}
	        
	        
			switch ( $column ) {
				
				case 'invoice_status':
	                $invoice_status = get_post_meta($the_order->id, '_billogram_status', true);
	                $gateway = get_post_meta($the_order->id, '_payment_method', true);
	                $recurringGateway = get_post_meta($post->ID, '_recurring_payment_method', true);
	            
	                if($gateway === 'billogramwc' || $recurringGateway === 'billogramwc'){
	                   
	                    switch ($invoice_status) {
	                    	case '':
	                    	case 'Unattested':
	                    		echo __( 'Not sent', 'billogram-wc' );
	                    	break;

	                    	case 'Sending':
	                    		echo __( 'Sent', 'billogram-wc' );
	                    	break;

	                    	case 'Paid':
	                    		echo __( 'Paid', 'billogram-wc' );
	                    	break;

	                    	case 'PartlyPaid':
	                    		echo __( 'Partially paid', 'billogram-wc' );
	                    	break;

	                    	case 'Overdue':
	                    		echo __( 'Expired', 'billogram-wc' );
	                    	break;

	                    	default:
	                    		echo __( 'Unknown status', 'billogram-wc' );
	                    	break;
	                    }
	                }else{
	                    echo "&mdash;";
	                }
	            break;
	            
	        }
	    }
	}
?>