$(function() {

	// Helper for editing a comment, populates a hidden field with selected value, so it can be saved
	$(document).on('click', '.menu.status-settings a', function(e) {
		$('.menu.status-settings a').removeClass('sel');
		$(this).addClass('sel');

		// Update the actual menu item to reflect change
		$('.statusmenubtn').html($(this).html());

		// Update hidden input field so we can use this when saving
		$('input[name="status"]').val($(this).data('status'));
	});

});
