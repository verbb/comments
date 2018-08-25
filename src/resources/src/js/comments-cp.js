// ==========================================================================

// Comments Plugin for Craft CMS
// Author: Verbb - https://verbb.io/

// ==========================================================================

if (typeof Craft.CommentsCp === typeof undefined) {
    Craft.CommentsCp = {};
}

(function($) {

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
    $(document).on('click', '.elementTypeCheckbox a.check-all', function(e) {
        e.preventDefault();

        var $checkboxes = $('#' + $(this).parents('tr.elementTypeCheckbox').attr('id') + '-nested input[type="checkbox"]');

        if (!$(this).hasClass('checked')) {
            $(this).addClass('checked');
            $(this).html('Uncheck all');
            
            $checkboxes.prop('checked', true);
        } else {
            $(this).removeClass('checked');
            $(this).html('Check all');

            $checkboxes.prop('checked', false);
        }
    });

    // Handle checkboxes initial state
    $('.elementTypeCheckbox a.check-all').trigger('click');

})(jQuery);
