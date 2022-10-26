<?php
return [
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

    //
    // Other
    //

    'Your name' => 'Je naam',
    'Your email' => 'Je email',
    'Add a comment...' => 'Plaats een reactie...',
    'Post comment' => 'Plaatsen',
    'Flag' => 'Melden',
    'Comment marked as inappropriate' => 'Reactie aangemerkt als ongepast',
    'Comment hidden due to low rating' => 'Reactie verborgen vanwege lage beoordelingen.',
    'Reply' => 'Reageren',
    'Edit' => 'Bewerken',
    'Delete' => 'Verwijderen',
    'Close' => 'Sluiten',
    'Save' => 'Opslaan',
    'None' => 'Geen',
    'Enable' => 'Activeren',
    'Disable' => 'Deactiveren',
    'Comment' => 'Reactie',
    '[Deleted element]' => '[verwijderd element]',
    'Guest' => 'Anoniem',
    'Pending' => 'In afwachting',
    'Spam' => 'Spam',
    'Trashed' => 'Verwijderd',
    'Date' => 'Datum',
    'Element' => 'Element',
    'Status' => 'Status',
    'Moderators' => 'Moderators',
    'All comments' => 'Alle reacties',
    'All {elements}' => 'Alle {elements}',
    'You must be logged in to flag a comment.' => 'Je moet ingelogd zijn om een reactie te flaggen.',
    'You must be logged in to vote.' => 'Je moet ingelogd zjn om te stemmen.',
    'You can only vote on a comment once.' => 'Je kan maar 1x stemmen.',
    'You must be logged in to change your settings.' => 'Je moet ingelogd zijn om de instellingen te veranderen.',
    'Are you sure you want to delete the selected comments?' => 'Weet je zeker dat je de geselecteerde reacties wilt verwijderen?',
    'Comments deleted.' => 'Reacties verwijderd.',
    'Form validation failed. Marked as spam.' => 'Je reactie is gedetecteerd als spam en dus niet verstuurd.',
    'Comment blocked due to security policy.' => 'Reactie geblokkeerd i.v.m. veiligheidsredenen.',
    'Comment must be shorter than {limit} characters.' => 'Reactie mag niet langer zijn dan {limit} tekens.',
    'Must be logged in to comment.' => 'Inloggen is verplicht.',
    'Name is required.' => 'Naam is vereist.',
    'Email is required.' => 'Email is vereist.',
    'Unable to modify another user’s comment.' => 'Je kan andermans reactie niet bewerken.',
    'Comment must not be blank.' => 'Reactie mag niet leeg zijn.',
    'Comments are disabled for this element.' => 'Reacties zijn uitgeschakeld.',
    'Your comment has been posted and is under review.' => 'Je reactie is verzonden en zal gekeurd worden.',
    '[Deleted' => '[Verwijderde',
    'User]' => 'Gebruiker]',
    'Unable to modify another users comment.' => 'De reactie van een andere gebruiker kan niet worden gewijzigd.',
    'Could not update status due to a validation error.' => 'Status kon niet bijgewerkt worden door een validatiefout.',
    'Could not update statuses due to validation errors.' => 'Status kon niet bijgewerkt worden door een validatiefout',
    'Status updated, with some failures due to validation errors.' => 'Status bijgewerkt, maar er zijn fouten opgetreden.',
    'Status updated.' => 'Status bijgewerkt.',
    'Statuses updated.' => 'Status bijgewerkt.',
    'Comments Settings' => 'Reactie instellingen',
    'General Settings' => 'Algemene instellingen',
    'Notifications' => 'Notificaties',
    'Voting' => 'Stemmen',
    'Flagging' => 'Melden',
    'Templates' => 'Templates',
    'Default Templates' => 'Standaard Templates',
    'Custom Templates' => 'Custom Templates',
    'Security' => 'Veiligheid',
    'Security & Spam' => 'Veiligheid & Spam',
    'Permissions' => 'Rechten',
    'Enable Author Notifications' => 'Auteurs-notificaties activeren',
];
