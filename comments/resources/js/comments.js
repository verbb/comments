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

	// For Permissions panel, handle checkboxes
	$(document).on('change', '.allElementCheckbox', function(e) {
		var $group = $(this).parent().parent();

		if ($(this).is(':checked')) {
			$group.find('input[type="checkbox"]').prop('checked', true);
		} else {
			$group.find('input[type="checkbox"]').prop('checked', false);
		}
	});






});
