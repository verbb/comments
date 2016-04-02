<?php

return array (
    'comments_author_notification_heading' => 'When a comment is received:',
    'comments_author_notification_subject' => '"{{element.title}}" has received a comment on {{siteName}}.',
    'comments_author_notification_body' => "Hey {{user.friendlyName}},\n\n" .
        "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",

    'comments_reply_notification_heading' => 'When a comment is received:',
    'comments_reply_notification_subject' => 'Someone has replied to your comment on {{siteName}}.',
    'comments_reply_notification_body' => "Hey {{user.friendlyName}},\n\n" .
        "A new reply to your comment on the post \"{{ element.title }}\" has been made.\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",
);
