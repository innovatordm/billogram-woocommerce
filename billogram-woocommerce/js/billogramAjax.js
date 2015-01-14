jQuery(document).ready(function($) {
	var data = {
		'action': 'send_invoice',
		'orderId': billogramData.orderId
	};
	$('#sendInvoice').click(function(e) {
		e.preventDefault();
		$.post(ajaxurl, data, function(response) {
			alert('Status: ' + response);
		})
	});
});