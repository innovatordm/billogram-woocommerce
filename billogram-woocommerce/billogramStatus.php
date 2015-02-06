<?php 

/**
* Class for adding custom order statuses
*/
class BillogramStatus
{

	private static function getStatuses() {
		$statuses = array(
			'wc-awaiting-approval' => array(
				'label'                     => 'Awaiting Approval',
	        	'public'                    => true,
	        	'exclude_from_search'       => false,
	        	'show_in_admin_all_list'    => true,
	        	'show_in_admin_status_list' => true,
	        	'label_count'				=> _n_noop( 'Awaiting Approval <span class="count">(%s)</span>', 'Awaiting Invoice <span class="count">(%s)</span>' )
	        )
		);
		return $statuses;
	}

	public static function registerAllStatuses()
	{

		foreach (self::getStatuses() as $statusId => $statusContent) {
			register_post_status($statusId, $statusContent);
		}
	}
	// Add to list of WC Order statuses
	public static function addStatusToBillogram( $order_statuses ) {
		// add new order status after processing
		foreach ( self::getStatuses() as $key => $status ) {
			$order_statuses[ $key ] = $status['label'];
		}
		return $order_statuses;
	} 
}

?>