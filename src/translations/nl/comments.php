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

	'comments_subscriber_notification_element_heading' => 'When a comment is made on a subscribed element:',
	'comments_subscriber_notification_element_subject' => 'Iemand heeft een reactie geplaatst op {{ element.title }}',
	'comments_subscriber_notification_element_body' => "Hi {{user.friendlyName}},\n\n" .
	"A new comment on the post \"{{ element.title }}\" has been made.\n\n" .
	"{{element.url}}#comment-{{comment.id}}.",

	'comments_subscriber_notification_comment_heading' => 'When a comment is made on another comment:',
	'comments_subscriber_notification_comment_subject' => 'Iemand heeft op je gereageerd op {{ siteName }}',
	'comments_subscriber_notification_comment_body' => "Hi {{user.friendlyName}},\n\n" .
	"Somebody replied on your comment on the post \"{{ element.title }}\".\n\n" .
	"{{element.url}}#comment-{{comment.id}}.",
	
	//  
	// Email templates
	//

	'Your name' => 'Je naam',
	'Your email' => 'Je email',
	'Add a comment...' => 'Plaats een reactie...',
	'Post comment' => 'Plaatsen',

	//
	// validation
	//

	'Form validation failed. Marked as spam.' => 'Je reactie is gedetecteerd als spam en dus niet verstuurd.',
	'Comment blocked due to security policy.' => 'Reactie geblokkeerd i.v.m. veiligheidsredenen.',
	'Comment must be shorter than 1000 characters.' => 'Reactie mag niet langer zijn dan 1000 karakters',
	'Must be logged in to comment.' => 'Inloggen verplicht.',
	'Name is required.' => 'Naam is vereist.',
	'Email is required.' => 'Email is vereist.',
	'Unable to modify another user’s comment.' => 'Je kan andermans reactie niet bewerken.',
	'Comment must not be blank.' => 'Reactie mag niet leeg zijn.',
	'Comments are disabled for this element.' => 'Reacties zijn uitgeschakeld.'
];
