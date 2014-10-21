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
	}

	class BillogramUi {
		function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'addOrderStatusMetaBox' ));
			add_action( 'admin_enqueue_scripts', array( $this, 'loadScripts'));
		}

		public function addOrderStatusMetaBox() {
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
			echo '<input class="button button-primary tips" id="sendInvoice" value="Skicka faktura" type="submit">';	
		}

		public function loadScripts($hook) {
			global $post;
			if('post.php' !== $hook && $post->post_type !== 'shop_order')
				return;	
			wp_enqueue_script( 'billogram-ajax', plugins_url( '/js/billogramAjax.js', __FILE__ ), array('jquery') );

			// in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
			wp_localize_script( 'billogram-ajax', 'billogramData',
            array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'orderId' => $post->ID) );
		}
	}
?>