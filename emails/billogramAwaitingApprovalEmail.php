<?php

/*
* Thanks to Max Rice for the code!
* http://www.skyverge.com/blog/how-to-add-a-custom-woocommerce-email/
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class BillogramAwaitingApprovalEmail extends WC_Email {
	/**
	 * Set email defaults
	 *
	 * @since 0.1
	 */
	public function __construct() {
	 
	    // set ID, this simply needs to be a unique name
	    $this->id = 'billogram_order_confirmation';
	 
	    // this is the title in WooCommerce Email settings
	    $this->title = __('Billogram Order received', 'billogram-wc' );
	 
	    // this is the description in WooCommerce email settings
	    $this->description = __('Send a notification to the user that their order has been received', 'billogram-wc' );
	 
	    // these are the default heading and subject lines that can be overridden using the settings
	    $this->heading = __('Billogram Order received', 'billogram-wc' );
	    $this->subject = __('Billogram Order received', 'billogram-wc' );
	 
	    // these define the locations of the templates that this email should use, we'll just use the new order template since this email is similar
	    $this->template_html  = 'emails/customer-processing-invoice.php';
	    $this->template_plain = 'emails/plain/customer-processing-invoice.php';
	 	$this->template_base  = plugin_dir_path(__FILE__) . 'templates/';

	 	add_action( 'woocommerce_order_status_pending_to_awaiting_approval_send',  array( $this, 'trigger' ), 10, 1 );
	    
	    WC_Email::__construct();
	    // this sets the recipient to the settings defined below in init_form_fields()
	    $this->recipient = $this->get_option( 'recipient' );
	 	
	    // if none was entered, just use the WP admin email as a fallback
	    if ( ! $this->recipient )
	        $this->recipient = get_option( 'admin_email' );
		// Call parent constructor to load any other defaults not explicity defined here
		
	}

	/**
	 * Determine if the email should actually be sent and setup email merge variables
	 *
	 * @since 0.1
	 * @param int $order_id
	 */
	public function trigger( $order_id ) {

 		error_log("BillogramEmail Send mail for order: " . $order_id);
	    // bail if no order ID is present
	    if ( ! $order_id )
	        return;
	 
	    // setup order object
	    $this->object = new WC_Order( $order_id );
	    $this->recipient = $this->object->billing_email;
	 
	    // replace variables in the subject/headings
	    $this->find[] = '{order_date}';
	    $this->replace[] = date_i18n( woocommerce_date_format(), strtotime( $this->object->order_date ) );
	 
	    $this->find[] = '{order_number}';
	    $this->replace[] = $this->object->get_order_number();
	 
	    if ( ! $this->is_enabled() || ! $this->get_recipient() )
	        return;
	 
	    // woohoo, send the email!
	    $return = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		error_log($this->get_recipient());
	}

	/**
	 * get_content_html function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		woocommerce_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading()
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}
 
 
	/**
	 * get_content_plain function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		woocommerce_get_template(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading()
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}
	/**
	 * Initialize Settings Form Fields
	 *
	 * @since 0.1
	 */
	public function init_form_fields() {
	 
	    $this->form_fields = array(
	        'enabled'    => array(
	            'title'   => __('Enable/Disable', 'billogram-wc' ),
	            'type'    => 'checkbox',
	            'label'   => __('Enable this email notification', 'billogram-wc' ),
	            'default' => 'yes'
	        ),
	        'subject'    => array(
	            'title'       => __('Subject', 'billogram-wc' ),
	            'type'        => 'text',
	            'description' => sprintf( __('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'billogram-wc' ), $this->subject ),
	            'placeholder' => '',
	            'default'     => __('Order received', 'billogram-wc' )
	        ),
	        'heading'    => array(
	            'title'       => __('Email Heading', 'billogram-wc' ),
	            'type'        => 'text',
	            'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'billogram-wc' ), $this->heading ),
	            'placeholder' => '',
	            'default'     => __('Order received', 'billogram-wc' )
	        ),
	        'email_type' => array(
	            'title'       => __('Email type', 'billogram-wc' ),
	            'type'        => 'select',
	            'description' => __('Choose which format of email to send.', 'billogram-wc' ),
	            'default'     => 'html',
	            'class'       => 'email_type',
	            'options'     => array(
	                'plain'     => 'Plain text',
	                'html'      => 'HTML', 'woocommerce',
	                'multipart' => 'Multipart', 'woocommerce',
	            )
	        )
	    );
	}
}

?>