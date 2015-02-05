<?php
/**
 * Code based on Brent Shepherd's Email Subscriptions class
 */
class BillogramEmail {

	private static $woocommerce_email;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {

		add_action( 'woocommerce_email_classes', __CLASS__ . '::addEmails', 10, 1 );

		add_action( 'woocommerce_init', __CLASS__ . '::hookEmails' );
	}

	
	public static function addEmails( $email_classes ) {

		require_once( 'emails/billogramAwaitingApprovalEmail.php' );

		$email_classes['BillogramAwaitingApprovalEmail'] = new BillogramAwaitingApprovalEmail();
		return $email_classes;
	}

	
	public static function hookEmails() {



		$order_email_actions = array(
			'woocommerce_order_status_pending_to_awaiting_approval',
		);

		foreach ( $order_email_actions as $action ) {
			add_action( $action, __CLASS__ . '::sendEmail', 10 );
		}
	}
	
	public static function sendEmail( $order_id ) {
		global $woocommerce;

		$woocommerce->mailer();

		do_action( current_filter() . '_send', $order_id );
	}
}

BillogramEmail::init();
