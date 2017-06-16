;(function($) {

	$('#_upcoming').on('change', function() { 
	    // From the other examples
	    if (!this.checked) {
	        $(this).closest('._upcoming_field').siblings('._available_on_field').addClass('wup-hide');
	    } else {
	    	$(this).closest('._upcoming_field').siblings('._available_on_field').removeClass('wup-hide');
	    }
	});

	$('#_available_on').datepicker({ 
		minDate: 1,
		dateFormat: 'yy-mm-dd'
	});

})(jQuery);