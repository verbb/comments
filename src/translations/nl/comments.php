<?php

return [
  //
  // Time ago
  //
  '1 year ago' => '1 jaar geleden',
  '{num} years ago' => '{num} jaar geleden',
  '1 month ago' => '1 maand geleden',
  '{num} months ago' => '{num} maanden geleden',
  '1 day ago' => '1 dag geleden',
  '{num} days ago' => '{num} dagen geleden',
  '1 hour ago' => '1 uur geleden',
  '{num} hours ago' => '{num} uur geleden',
  '1 minute ago' => '1 minuut geleden',
  '{num} minutes ago' => '{num} minuten geleden',
  '1 second ago' => '1 seconde geleden',
  '{num} seconds ago' => '{num} seconden geleden',

  //
  // Email Messages
  //
  'comments_author_notification_heading' => 'Als een reactie is ontvangen:',
  'comments_author_notification_subject' => '"{{element.title}}" heeft een reactie ontvangen op {{siteName}}.',
  'comments_author_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Er is een reactie op de post \"{{ element.title }}\" geplaatst.\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_reply_notification_heading' => 'Als iemand reageert op een andere reactie:',
  'comments_reply_notification_subject' => 'Iemand heeft op je gereageerd op {{siteName}}.',
  'comments_reply_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Iemand heeft gereageerd op je onder de post \"{{ element.title }}\".\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_subscriber_notification_element_heading' => 'Als er een reactie op een element/entry geplaatst is:',
  'comments_subscriber_notification_element_subject' => 'Er is een nieuwe reactie geplaatst op {{ element.title }}',
  'comments_subscriber_notification_element_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Er is een reactie geplaatst op \"{{ element.title }}\".\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_subscriber_notification_comment_heading' => 'Wanneer iemand reageert op een reactie waarop deze geabonneerd is:',
  'comments_subscriber_notification_comment_subject' => 'Er is een nieuwe reactie geplaatst op {{ element.title }}',
  'comments_subscriber_notification_comment_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Er is een nieuwe reactie op de post \"{{ element.title }}\" gemaakt.\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_moderator_notification_comment_heading' => 'Als een reactie is geplaatst, maar goedgekeurd moet worden:',
  'comments_moderator_notification_comment_subject' => 'Een nieuwe reactie moet gekeurd worden op {{ siteName }}',
  'comments_moderator_notification_comment_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Een nieuwe reactie op de post \"{{ element.title }}\" moet gekeurd worden.\n\n" .
    "{{comment.cpEditUrl}}.",

  'comments_moderator_edit_notification_heading' => 'When a comment has been edited, and awaits moderation:',
  'comments_moderator_edit_notification_subject' => 'Someone has edited their comment and requires moderation on {{siteName}}.',
  'comments_moderator_edit_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A comment on the post \"{{ element.title }}\" was edited and requires moderation.\n\n" .
    "{{comment.cpEditUrl}}.",

  'comments_moderator_approved_notification_comment_heading' => 'Als een reactie is goedgekeurd:',
  'comments_moderator_approved_notification_comment_subject' => 'Je reactie is goedgekeurd op {{ siteName }}',
  'comments_moderator_approved_notification_comment_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Je reactie is goedgekeurd onder \"{{ element.title }}\".\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_admin_notification_heading' => 'Als een reactie is ontvangen:',
  'comments_admin_notification_subject' => '"{{element.title}}" heeft een reactie ontvangen op {{siteName}}.',
  'comments_admin_notification_body' => "Hallo,\n\n" .
    "Er is een nieuwe reactie geplaatst onder \"{{ element.title }}\".\n\n" .
    "{{comment.cpEditUrl}}.",

  'comments_flag_notification_heading' => 'Als een reactie is geflagged:',
  'comments_flag_notification_subject' => '"{{element.title}}" heeft een flag ontvangen op {{siteName}}.',
  'comments_flag_notification_body' => "Hallo,\n\n" .
    "Er is een reactie geflagged op \"{{ element.title }}\".\n\n" .
    "{{comment.cpEditUrl}}.",

  'Add a comment...' => 'Plaats een reactie...',
  'Administrators' => 'Administrators',
  'All comments' => 'Alle reacties',
  'All {elements}' => 'Alle {elements}',
  'Approved' => 'Approved',
  'Are you sure you want to delete the selected comments?' => 'Weet je zeker dat je de geselecteerde reacties wilt verwijderen?',
  'Are you sure you want to delete this comment?' => 'Are you sure you want to delete this comment?',
  'Close' => 'Sluiten',
  'Comment' => 'Reactie',
  'Comment blocked due to security policy.' => 'Reactie geblokkeerd i.v.m. veiligheidsredenen.',
  'Comment deleted.' => 'Comment deleted.',
  'Comment hidden due to low rating' => 'Reactie verborgen vanwege lage beoordelingen.',
  'Comment marked as inappropriate' => 'Reactie aangemerkt als ongepast',
  'Comment must be shorter than {limit} characters.' => 'Reactie mag niet langer zijn dan {limit} tekens.',
  'Comment must not be blank.' => 'Reactie mag niet leeg zijn.',
  'Comment not allowed.' => 'Comment not allowed.',
  'Comment Options' => 'Comment Options',
  'Comments' => 'Comments',
  'Comments are disabled for this element.' => 'Reacties zijn uitgeschakeld.',
  'Comment saved successfully.' => 'Comment saved successfully.',
  'Comments deleted.' => 'Reacties verwijderd.',
  'Couldn’t save comment.' => 'Couldn’t save comment.',
  'Create comments' => 'Create comments',
  'Created Date' => 'Created Date',
  'CSS/JS Resources' => 'CSS/JS Resources',
  'Date' => 'Datum',
  'Delete' => 'Verwijderen',
  'Delete comments' => 'Delete comments',
  'Edit' => 'Bewerken',
  'Edit other users’ comments' => 'Edit other users’ comments',
  'Element' => 'Element',
  'Email' => 'Email',
  'Email is required.' => 'Email is vereist.',
  'Flag' => 'Melden',
  'Flagged' => 'Flagged',
  'Flagging is not allowed.' => 'Flagging is not allowed.',
  'For more fine-grained control over specific elements, create a Comment Options fieldtype, and add to your elements custom fields.' => 'For more fine-grained control over specific elements, create a Comment Options fieldtype, and add to your elements custom fields.',
  'Form validation failed. Marked as spam.' => 'Je reactie is gedetecteerd als spam en dus niet verstuurd.',
  'Guest' => 'Anoniem',
  'If disabling any of these options, you‘ll need to supply your own CSS or JS in order for the form and comments functionality to look and work correctly.' => 'If disabling any of these options, you‘ll need to supply your own CSS or JS in order for the form and comments functionality to look and work correctly.',
  'IP Address' => 'IP Address',
  'Moderators' => 'Moderators',
  'Must be logged in to comment.' => 'Inloggen is verplicht.',
  'Must be logged in to flag comments.' => 'Must be logged in to flag comments.',
  'Must be logged in to vote.' => 'Must be logged in to vote.',
  'Name' => 'Name',
  'Name is required.' => 'Naam is vereist.',
  'No comments.' => 'No comments.',
  'No comment with the ID “{id}”' => 'No comment with the ID “{id}”',
  'No replies for this comment' => 'No replies for this comment',
  'Pending' => 'In afwachting',
  'Per-comment subscriptions are not allowed.' => 'Per-comment subscriptions are not allowed.',
  'Post comment' => 'Plaatsen',
  'Replies' => 'Replies',
  'Reply' => 'Reageren',
  'Save' => 'Opslaan',
  'Save comments' => 'Save comments',
  'Select which element collections should have commenting enabled on by default.' => 'Select which element collections should have commenting enabled on by default.',
  'Sending comment notification.' => 'Sending comment notification.',
  'Spam' => 'Spam',
  'Status' => 'Status',
  'Structure Info' => 'Structure Info',
  'Subscribe' => 'Subscribe',
  'Subscribed to discussion.' => 'Subscribed to discussion.',
  'Subscribe to get email updates for this discussion' => 'Subscribe to get email updates for this discussion',
  'Subscribing' => 'Subscribing',
  'The default templates are what is used when calling `craft.comments.render()`, and automatically outputs CSS and JS code in your templates. If you want full control over your templates, use [Custom Templates]({link}).' => 'The default templates are what is used when calling `craft.comments.render()`, and automatically outputs CSS and JS code in your templates. If you want full control over your templates, use [Custom Templates]({link}).',
  'These values should not be altered manually. If for some reason they are empty, you can run the following commend to re-generate these values: <code>php craft comments/base/resave-structure</code>' => 'These values should not be altered manually. If for some reason they are empty, you can run the following commend to re-generate these values: <code>php craft comments/base/resave-structure</code>',
  'Trashed' => 'Verwijderd',
  'Trash other users’ comments' => 'Trash other users’ comments',
  'Unable to delete comment.' => 'Unable to delete comment.',
  'Unable to modify another user’s comment.' => 'Je kan andermans reactie niet bewerken.',
  'Unable to update subscribe status.' => 'Unable to update subscribe status.',
  'Unsubscribed from discussion.' => 'Unsubscribed from discussion.',
  'Updated Date' => 'Updated Date',
  'URL' => 'URL',
  'User' => 'User',
  'User Agent' => 'User Agent',
  'View comments' => 'View comments',
  'Votes' => 'Votes',
  'Voting is not allowed.' => 'Voting is not allowed.',
  'Warning' => 'Warning',
  'You can only vote on a comment once.' => 'Je kan maar 1x stemmen.',
  'You may only delete your own comments.' => 'You may only delete your own comments.',
  'You must be logged in to change your settings.' => 'Je moet ingelogd zijn om de instellingen te veranderen.',
  'Your comment has been posted and is under review.' => 'Je reactie is verzonden en zal gekeurd worden.',
  'Your email' => 'Je email',
  'Your name' => 'Je naam',
  '[Deleted element]' => '[verwijderd element]',
  '[Deleted User]' => '[Deleted User]',
];