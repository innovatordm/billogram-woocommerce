jQuery(document).ready(function($) {
	var sendInvoiceData = {
		'action': 'send_invoice',
		'orderId': billogramData.orderId,
		'BillSec': billogramData.nonce
	};
	var createRenewalData = {
		'action': 'create_renewal',
		'orderId': billogramData.orderId,
		'BillSec': billogramData.nonce
	};
	$('#sendInvoice').click(function(e) {
		e.preventDefault();
		$.post(ajaxurl, sendInvoiceData, function(response) {
			alert('Status: ' + response);
		})
	});
	$('#createRenewal').click(function(e) {
		e.preventDefault();
		$.post(ajaxurl, createRenewalData, function(response) {
			alert('Status: ' + response);
		})
	});
});