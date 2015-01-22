<?php
	/**
	* Class to handle ui elements of the plugin
	*/

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

			if($gateway !== 'billogramwc')
				return;

			add_meta_box( 
		        'billogramActions', 
		        __( 'Billogram Faktura åtgärder' ), 
		        array( $this, 'orderMetaBox' ), 
		        'shop_order', 
		        'side', 
		        'default' 
		    );
		}
		public function orderMetaBox() {
			global $post;

			$invoice_status = get_post_meta($post->ID, '_billogram_status', true);
			$required = ($invoice_status === '' || $invoice_status === 'Unattested') ? '' : 'disabled';
			echo '<input class="button button-primary tips" id="sendInvoice" value="Skicka faktura" type="submit"' . $required . '>';	
		}

		public function loadScripts($hook) {
			global $post;
			if('post.php' !== $hook && $post->post_type !== 'shop_order')
				return;	
			wp_enqueue_script( 'billogram-ajax', plugins_url( '/js/billogramAjax.js', __FILE__ ), array('jquery') );

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
	        $columns['invoice_status']    = __( 'Faktura status', 'woocommerce' );  
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
	            
	                if($gateway === 'billogramwc'){
	                   
	                    switch ($invoice_status) {
	                    	case '':
	                    	case 'Unattested':
	                    		echo __( 'Inte skickad', 'woocommerce' );
	                    	break;

	                    	case 'Sending':
	                    		echo __( 'Skickad', 'woocommerce' );
	                    	break;

	                    	case 'Paid':
	                    		echo __( 'Betald', 'woocommerce' );
	                    	break;

	                    	case 'PartlyPaid':
	                    		echo __( 'Delvis betald', 'woocommerce' );
	                    	break;

	                    	case 'Overdue':
	                    		echo __( 'Förfallen', 'woocommerce' );
	                    	break;

	                    	default:
	                    		echo __( 'Okänd status', 'woocommerce' );
	                    	break;
	                    }
	                }else{
	                    echo "&mdash;";
	                }
	            
					/*
	                if ( '0000-00-00 00:00:00' == $post->post_date ) {
						$t_time = $h_time = __( 'Unpublished', 'woocommerce' );
					} else {
						$t_time    = get_the_time( __( 'Y/m/d g:i:s A', 'woocommerce' ), $post );
						$gmt_time  = strtotime( $post->post_date_gmt . ' UTC' );
						$h_time    = get_the_time( __( 'Y/m/d', 'woocommerce' ), $post );
					}
					echo '<abbr title="' . esc_attr( $t_time ) . '">' . esc_html( apply_filters( 'post_date_column_time', $h_time, $post ) ) . '</abbr>';
				     */
	            break;
	            
	        }
	    }
	}
?>