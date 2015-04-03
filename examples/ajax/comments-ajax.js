$( document ).ready(function() {
	$(document).on('submit', '.comment-form', function(e) {
	  e.preventDefault();

	  var data = $(this).serialize();

		$.ajax({
			method: 'POST',
			url: data.action,
			data: data,
			headers: {'Content-Type': 'application/x-www-form-urlencoded'}
		})
		.success(function(data) {
			console.log('success', data);
		})
		.error(function(data) {
			console.log('error', data);
		});
	});

	// toggle to show/hide comments
	$(document).on('click', 'a.comment-toggle', function(e) {
		e.preventDefault();

		$(this).parents('.comment-single:first').find('.comments-list:first').slideToggle();
	});

	// toggle to show/hide reply form
	$(document).on('click', 'a.comment-reply', function(e) {
		e.preventDefault();

		$element = $(this).parents('.comment-single:first');
		$element.find('form:first').slideToggle();
	});

    // Handle voting
    $(document).on('click', 'a.comment-vote-down, a.comment-vote-up', function(e) {
        e.preventDefault();

        $.ajax({
            url: $(this).attr('href'),
        })
        .success(function(data) {
            console.log('success', data);

            // update label
            if (data.success) {
                $(e.target).parent().parent().find('.count').html(data.votes + ' votes');
            }
        })
        .error(function(data) {
            console.log('error', data);
        });
    });

    // Handle flagging
    $(document).on('click', 'a.comment-flag', function(e) {
        e.preventDefault();

        $.ajax({
            url: $(this).attr('href'),
        })
        .success(function(data) {
            console.log('success', data);

            // update label
            $(e.target).parent().parent().find('.comment-flag').replaceWith('<span class="static-label comment-flag"><span class="glyphicon glyphicon-flag"></span>Flagged as inappropriate</span>');
        })
        .error(function(data) {
            console.log('error', data);
        });
    });

    // Handle deleting
    $(document).on('click', 'a.comment-delete', function(e) {
        e.preventDefault();

        $.ajax({
            url: $(this).attr('href'),
        })
        .success(function(data) {
            console.log('success', data);
        })
        .error(function(data) {
            console.log('error', data);
        });
    });

    // Handle editing
    $(document).on('click', 'a.comment-edit', function(e) {
        e.preventDefault();

        var comment_text = $.trim($(this).parents('.comment-text').find('.comment-content').text());
        var id = $(this).parents('.comment-single:first').data('id');
        var csrf = $('input[name="CRAFT_CSRF_TOKEN"]:first').val();

        var html = '';
        html += '<form class="edit-comment" role="form" method="post">';
        html += '<input type="hidden" name="action" value="comments/edit">';
        html += '<input type="hidden" name="commentId" value="'+id+'">';
        html += '<input type="hidden" name="CRAFT_CSRF_TOKEN" value="'+csrf+'">';
        html += '<textarea class="form-control" name="fields[comment]" rows="5">'+comment_text+'</textarea>';
        html += '<button class="btn btn-default">Save</button>';
        html += '</form>';

        $(this).parents('.comment-text').find('.comment-content').html(html);
    });

    $(document).on('submit', '.edit-comment', function(e) {
        e.preventDefault();

        var data = $(this).serialize();

        $.ajax({
            method: 'POST',
            url: data.action,
            data: data,
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        })
        .success(function(data) {
            console.log('success', data);

            // remove the form
            var comment_text = $('form.edit-comment textarea[name="fields[comment]"]').val().replace(/\n/g, '<br />');

            $('form.edit-comment').parent().html('<p>'+comment_text+'</p>');
        })
        .error(function(data) {
            console.log('error', data);
        });
    });




});
