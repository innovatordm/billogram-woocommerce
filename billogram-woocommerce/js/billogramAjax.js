jQuery(document).ready(function($) {
	var data = {
		'action': 'send_invoice',
		'orderId': billogramData.orderId // We pass php values differently!
	};
	$('#sendInvoice').click(function(e) {
		// We can also pass the url value separately from ajaxurl for front end AJAX implementations
		e.preventDefault();
		$.post(ajaxurl, data, function(response) {
			alert('Got this from the server: ' + response);
		})
	});
});