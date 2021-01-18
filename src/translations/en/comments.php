<?php

return [
    //
    // Email Messages
    //
    'comments_author_notification_heading' => 'When a comment is received:',
    'comments_author_notification_subject' => '"{{element.title}}" has received a comment on {{siteName}}.',
    'comments_author_notification_body' => "Hi {{user.friendlyName}},\n\n" .
        "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",

    'comments_reply_notification_heading' => 'When a comment is received:',
    'comments_reply_notification_subject' => 'Someone has replied to your comment on {{siteName}}.',
    'comments_reply_notification_body' => "Hi {{user.friendlyName}},\n\n" .
        "A new reply to your comment on the post \"{{ element.title }}\" has been made.\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",

    'comments_subscriber_notification_element_heading' => 'When a comment is made on a subscribed element:',
    'comments_subscriber_notification_element_subject' => 'A new comment has been made on {{ element.title }}',
    'comments_subscriber_notification_element_body' => "Hi {{user.friendlyName}},\n\n" .
        "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",

    'comments_subscriber_notification_comment_heading' => 'When someone response to another comment:',
    'comments_subscriber_notification_comment_subject' => 'Someone responded to your comment on {{ siteName }}',
    'comments_subscriber_notification_comment_body' => "Hi {{user.friendlyName}},\n\n" .
        "Somebody replied on your comment on the post \"{{ element.title }}\".\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",

    'comments_moderator_notification_comment_heading' => 'When a comment has been made, and awaits moderation:',
    'comments_moderator_notification_comment_subject' => 'A new comment requires moderation on {{ siteName }}',
    'comments_moderator_notification_comment_body' => "Hi {{user.friendlyName}},\n\n" .
        "A new comment on the post \"{{ element.title }}\" has been made and requires moderation.\n\n" .
        "{{comment.cpEditUrl}}.",

    'comments_moderator_approved_notification_comment_heading' => 'When a comment has been approved via moderation:',
    'comments_moderator_approved_notification_comment_subject' => 'Your comment has been approved on {{ siteName }}',
    'comments_moderator_approved_notification_comment_body' => "Hi {{user.friendlyName}},\n\n" .
        "Your comment has been approved on the post \"{{ element.title }}\".\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",
    
    'comments_admin_notification_heading' => 'When a comment is received:',
    'comments_admin_notification_subject' => '"{{element.title}}" has received a comment on {{siteName}}.',
    'comments_admin_notification_body' => "Hi,\n\n" .
        "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
        "{{comment.cpEditUrl}}.",
    
    'comments_flag_notification_heading' => 'When a comment is received:',
    'comments_flag_notification_subject' => '"{{element.title}}" has received a comment flag on {{siteName}}.',
    'comments_flag_notification_body' => "Hi,\n\n" .
        "A comment has been flagged on the post \"{{ element.title }}\".\n\n" .
        "{{comment.cpEditUrl}}.",

    //  
    // Email templates
    //

    'Your name' => 'Your name',
    'Your email' => 'Email',
    'Add a comment...' => 'Write a comment',
    'Post comment' => 'Submit',
    
	//
	// Validation
	//

	'Form validation failed. Marked as spam.' => 'Oops, comment validation failed. Marked as spam.',
	'Comment blocked due to security policy.' => 'Comment blocked due to security policy.',
	'Comment must be shorter than 1000 characters.' => 'Comment must be shorter than 1000 characters.',
	'Must be logged in to comment.' => 'Must be logged in to comment.',
	'Name is required.' => 'Name is required.',
	'Email is required.' => 'Email is required.',
	'Unable to modify another users comment.' => 'Unable to modify another users comment.',
	'Comment must not be blank.' => 'Comment must not be blank.',
	'Comments are disabled for this element.' => 'Comments are disabled for this element.' 
];
