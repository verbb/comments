<?php

return [
  //
  // Time ago
  //
  '1 year ago' => 'vor 1 Jahr',
  '{num} years ago' => 'vor {num} Jahren',
  '1 month ago' => 'vor 1 Monat',
  '{num} months ago' => 'vor {num} Monaten',
  '1 day ago' => 'vor 1 Tag',
  '{num} days ago' => 'vor {num} Tagen',
  '1 hour ago' => 'vor 1 Stunde',
  '{num} hours ago' => 'vor {num} Stunden',
  '1 minute ago' => 'vor 1 Minute',
  '{num} minutes ago' => 'vor {num} Minuten',
  '1 second ago' => 'vor 1 Sekunde',
  '{num} seconds ago' => 'vor {num} Sekunden',

  //
  // Email Messages
  //
  'comments_author_notification_heading' => 'Wenn ein Kommentar geschrieben wurde:',
  'comments_author_notification_subject' => '"{{element.title}}" hat einen Kommentar auf {{siteName}} erhalten.',
  'comments_author_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_reply_notification_heading' => 'Wenn eine Antwort auf einen Kommentar geschrieben wurde:',
  'comments_reply_notification_subject' => 'Jemand hat auf deinen Kommentar auf {{siteName}} reagiert.',
  'comments_reply_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A new reply to your comment on the post \"{{ element.title }}\" has been made.\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_subscriber_notification_element_heading' => 'Wenn ein Kommentar zu einem abonnierten Element geschrieben wird:',
  'comments_subscriber_notification_element_subject' => 'Ein neuer Kommentar zu {{ element.title }}',
  'comments_subscriber_notification_element_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_subscriber_notification_comment_heading' => 'Wenn jemand auf einen anderen Kommentar reagiert:',
  'comments_subscriber_notification_comment_subject' => 'Ein neuer Kommentar zu {{ element.title }}',
  'comments_subscriber_notification_comment_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A new reply on the post \"{{ element.title }}\" has been made.\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_moderator_notification_heading' => 'Wenn ein neuer Kommentar geschrieben wurde, der moderiert werden muss:',
  'comments_moderator_notification_subject' => 'Ein Kommentar auf {{ siteName }} benötigt Moderation',
  'comments_moderator_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A new comment on the post \"{{ element.title }}\" has been made and requires moderation.\n\n" .
    "{{comment.cpEditUrl}}.",

  'comments_moderator_edit_notification_heading' => 'Wenn ein Kommentar durch Moderation freigeschaltet wurde:',
  'comments_moderator_edit_notification_subject' => 'Ihr Kommentar bei {{ siteName }} wurde freigeschaltet',
  'comments_moderator_edit_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "A comment on the post \"{{ element.title }}\" was edited and requires moderation.\n\n" .
    "{{comment.cpEditUrl}}.",

  'comments_moderator_approved_notification_heading' => 'When a comment has been approved via moderation:',
  'comments_moderator_approved_notification_subject' => 'Your comment has been approved on {{ siteName }}',
  'comments_moderator_approved_notification_body' => "Hallo {{user.friendlyName}},\n\n" .
    "Your comment has been approved on the post \"{{ element.title }}\".\n\n" .
    "{{element.url}}#comment-{{comment.id}}.",

  'comments_admin_notification_heading' => 'When a comment is received by an admin:',
  'comments_admin_notification_subject' => '"{{element.title}}" has received a comment on {{siteName}}.',
  'comments_admin_notification_body' => "Hallo,\n\n" .
    "A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
    "{{comment.cpEditUrl}}.",

  'comments_flag_notification_heading' => 'When a comment has received a flag:',
  'comments_flag_notification_subject' => '"{{element.title}}" has received a comment flag on {{siteName}}.',
  'comments_flag_notification_body' => "Hallo,\n\n" .
    "A comment has been flagged on the post \"{{ element.title }}\".\n\n" .
    "{{comment.cpEditUrl}}.",

  'Add a comment...' => 'Kommentar hinzufügen…',
  'Administrators' => 'Administrators',
  'All comments' => 'Alle Kommentare',
  'All {elements}' => 'Alle {elements}',
  'Approved' => 'Genehmigt',
  'Are you sure you want to delete the selected comments?' => 'Möchten Sie die ausgewählten Kommentare wirklich löschen?',
  'Are you sure you want to delete this comment?' => 'Sind Sie sicher, dass Sie diesen Kommentar löschen wollen?',
  'Close' => 'Schließen',
  'Comment' => 'Kommentar',
  'Comment blocked due to security policy.' => 'Kommentar aufgrund von Sicherheitsrichtlinien blockiert.',
  'Comment deleted.' => 'Kommentar gelöscht.',
  'Comment hidden due to low rating' => 'Kommentar wurde auf Grund niedriger Bewertungen versteckt',
  'Comment marked as inappropriate' => 'Kommentar wurde als unangebracht markiert',
  'Comment must be shorter than {limit} characters.' => 'Der Kommentar muss kürzer sein als {limit} Zeichen.',
  'Comment must not be blank.' => 'Der Kommentar darf nicht leer sein.',
  'Comment not allowed.' => 'Comment not allowed.',
  'Comment Options' => 'Kommentar Optionen',
  'Comments' => 'Kommentare',
  'Comments are disabled for this element.' => 'Kommentare sind für dieses Element deaktiviert.',
  'Comment saved successfully.' => 'Kommentar erfolgreich gespeichert.',
  'Comments deleted.' => 'Kommentare gelöscht.',
  'Couldn’t save comment.' => 'Couldn’t save comment.',
  'Create comments' => 'Create comments',
  'Created Date' => 'Erstellungsdatum',
  'CSS/JS Resources' => 'CSS/JS-Ressourcen',
  'Date' => 'Datum',
  'Delete' => 'Löschen',
  'Delete comments' => 'Kommentare löschen',
  'Edit' => 'Bearbeiten',
  'Edit other users’ comments' => 'Edit other users’ comments',
  'Element' => 'Element',
  'Email' => 'E-Mail',
  'Email is required.' => 'E-Mail ist erforderlich.',
  'Flag' => 'Melden',
  'Flagged' => 'Flagged',
  'Flagging is not allowed.' => 'Flagging is not allowed.',
  'For more fine-grained control over specific elements, create a Comment Options fieldtype, and add to your elements custom fields.' => 'Um die Anzeige von Kommentaren für einzelnen Elemente zu kontrollieren, erstellen ein Feld mit dem Typ „Kommentaroptionen“ und fügen Sie das dem Element hinzu.',
  'Form validation failed. Marked as spam.' => 'Fehler bei der Formularüberprüfung. Als Spam markiert.',
  'Guest' => 'Anonym',
  'If disabling any of these options, you‘ll need to supply your own CSS or JS in order for the form and comments functionality to look and work correctly.' => 'Wenn Sie eine dieser Optionen deaktivieren, müssen Sie Ihr eigenes CSS oder JS angeben, damit die Formular- und Kommentarfunktionalität richtig aussieht und funktioniert.',
  'IP Address' => 'IP-Adresse',
  'Moderators' => 'Moderatoren',
  'Must be logged in to comment.' => 'Eine Anmeldung ist erfolgreich, um einen Kommentar abzugeben zu können.',
  'Must be logged in to flag comments.' => 'Must be logged in to flag comments.',
  'Must be logged in to vote.' => 'Must be logged in to vote.',
  'Name' => 'Name',
  'Name is required.' => 'Ihr Name ist erforderlich.',
  'No comments.' => 'No comments.',
  'No comment with the ID “{id}”' => 'Kein Kommentar mit der ID „{id}“',
  'No replies for this comment' => 'Keine Antworten für diesen Kommentar',
  'Pending' => 'Ausstehend',
  'Per-comment subscriptions are not allowed.' => 'Per-comment subscriptions are not allowed.',
  'Post comment' => 'Kommentar veröffentlichen',
  'Replies' => 'Antworten',
  'Reply' => 'Antworten',
  'Save' => 'Speichern',
  'Save comments' => 'Save comments',
  'Select which element collections should have commenting enabled on by default.' => 'Wählen Sie aus für welche Elements Kommentare standardmäßig aktiviert sein sollen.',
  'Sending comment notification.' => 'Sending comment notification.',
  'Spam' => 'Spam',
  'Status' => 'Status',
  'Structure Info' => 'Structure Info',
  'Subscribe' => 'Abonnieren',
  'Subscribed to discussion.' => 'Subscribed to discussion.',
  'Subscribe to get email updates for this discussion' => 'Abonniere E-Mail-Benachrichtigungen für Neuigkeiten in dieser Diskussion',
  'Subscribing' => 'Abonnieren',
  'The default templates are what is used when calling `craft.comments.render()`, and automatically outputs CSS and JS code in your templates. If you want full control over your templates, use [Custom Templates]({link}).' => 'Die Standard-Templates werden beim Aufrufen von `craft.comments.render()` verwendet und geben automatisch CSS- und JS-Code in Ihren Vorlagen aus. Wenn Sie die volle Kontrolle über die Templates haben möchten, verwenden Sie [Benutzerdefinierte Templates]({link}).',
  'These values should not be altered manually. If for some reason they are empty, you can run the following commend to re-generate these values: <code>php craft comments/base/resave-structure</code>' => 'These values should not be altered manually. If for some reason they are empty, you can run the following commend to re-generate these values: <code>php craft comments/base/resave-structure</code>',
  'Trashed' => 'Gelöscht',
  'Trash other users’ comments' => 'Trash other users’ comments',
  'Unable to delete comment.' => 'Kommentar kann nicht gelöscht werden.',
  'Unable to modify another user’s comment.' => 'Unable to modify another user’s comment.',
  'Unable to update subscribe status.' => 'Der Abonnementsstatus kann nicht aktualisiert werden.',
  'Unsubscribed from discussion.' => 'Unsubscribed from discussion.',
  'Updated Date' => 'Aktualisierungsdatum',
  'URL' => 'URL',
  'User' => 'Benutzer',
  'User Agent' => 'User-Agent',
  'View comments' => 'View comments',
  'Votes' => 'Votes',
  'Voting is not allowed.' => 'Voting is not allowed.',
  'Warning' => 'Warnung',
  'You can only vote on a comment once.' => 'Sie können nur einmal über einen Kommentar abstimmen.',
  'You may only delete your own comments.' => 'You may only delete your own comments.',
  'You must be logged in to change your settings.' => 'Sie müssen angemeldet sein, um Ihre Einstellungen ändern zu können.',
  'Your comment has been posted and is under review.' => 'Ihr Kommentar wurde gespeichert und wird derzeit geprüft.',
  'Your email' => 'Ihre E-Mail-Adresse',
  'Your name' => 'Ihr Name',
  '[Deleted element]' => '[Gelöschtes Element]',
  '[Deleted User]' => '[Deleted User]',
];