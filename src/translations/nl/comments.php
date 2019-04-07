<?php
return [
    //
    // Email Messages
    //
    'comments_author_notification_heading' => 'Als een reactie is ontvangen:',
    'comments_author_notification_subject' => '"{{element.title}}" heeft een reactie ontvangen op {{siteName}}.',
    'comments_author_notification_body' => "Hi {{user.friendlyName}},\n\n" .
        "Er is een nieuwe reactie geplaatst op de post \"{{ element.title }}\".\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",
    'comments_reply_notification_heading' => 'Als een reactie is ontvangen:',
    'comments_reply_notification_subject' => 'Iemand heeft gereageerd op je reactie op {{siteName}}.',
    'comments_reply_notification_body' => "Hi {{user.friendlyName}},\n\n" .
        "Er is een nieuwe reactie op de post \"{{ element.title }}\" geplaatst.\n\n" .
        "{{element.url}}#comment-{{comment.id}}.",
    //  
    // Email templates
    //
    'Your name' => 'Je naam',
    'Your email' => 'Je email',
    'Add a comment...' => 'Plaats een reactie...',
    'Post comment' => 'Plaatsen'
];
