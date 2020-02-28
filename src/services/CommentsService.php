<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\assetbundles\FrontEndAsset;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\elements\Comment;
use verbb\comments\events\EmailEvent;

use Craft;
use craft\base\Component;
use craft\db\Table;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\FieldEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;

use DateTime;

class CommentsService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SEND_AUTHOR_EMAIL = 'beforeSendAuthorEmail';
    const EVENT_BEFORE_SEND_REPLY_EMAIL = 'beforeSendReplyEmail';
    const EVENT_BEFORE_SEND_MODERATOR_EMAIL = 'beforeSendModeratorEmail';
    const EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL = 'beforeSendModeratorApprovedEmail';

    const CONFIG_FIELDLAYOUT_KEY = 'comments.comments.fieldLayouts';


    // Public Methods
    // =========================================================================

    public function getCommentById(int $id, $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($id, Comment::class, $siteId);
    }

    public function fetch($criteria = null): CommentQuery
    {
        $query = Comment::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    public function render($elementId, $criteria = [])
    {
        $settings = Comments::$plugin->getSettings();
        $view = Craft::$app->getView();

        $templatePath = $this->_getTemplatePath();
        $view->setTemplatesPath($templatePath);

        $query = Comment::find();
        $query->ownerId($elementId);
        $query->level('1');
        $query->orderBy('commentDate desc');

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        $element = Craft::$app->getElements()->getElementById($elementId);
        $id = 'cc-w-' . $elementId;

        // Normalise the action URL
        $actionUrl = trim(UrlHelper::actionUrl(), '/');
        $actionUrl = UrlHelper::rootRelativeUrl($actionUrl);
	    
        $jsVariables = [
            'baseUrl' => $actionUrl,
            'csrfTokenName' => Craft::$app->getConfig()->getGeneral()->csrfTokenName,
            'csrfToken' => Craft::$app->getRequest()->getCsrfToken(),
            'recaptchaEnabled' => (bool)$settings->recaptchaEnabled,
            'recaptchaKey' => $settings->recaptchaKey,
            'translations' => [
                'reply' => Craft::t('comments', 'Reply'),
                'close' => Craft::t('comments', 'Close'),
                'edit' => Craft::t('comments', 'Edit'),
                'save' => Craft::t('comments', 'Save'),
                'delete-confirm' => Craft::t('comments', 'Are you sure you want to delete this comment?'),
            ]
        ];

        // Prepare variables to pass to templates - important to include route params
        $routeParams = Craft::$app->getUrlManager()->getRouteParams();

        $variables = array_merge($routeParams, [
            'id' => $id,
            'element' => $element,
            'commentsQuery' => $query,
            'settings' => $settings,
        ]);

        $jsVariables = array_merge($jsVariables, $variables);

        // Build our complete form
        $formHtml = $view->renderTemplate('comments', $variables);

        $view->registerAssetBundle(FrontEndAsset::class);

        if ($settings->outputDefaultJs) {
            $view->registerJs('window.addEventListener("load", function () { new Comments.Instance(' .
                Json::encode('#' . $id, JSON_UNESCAPED_UNICODE) . ', ' .
                Json::encode($jsVariables, JSON_UNESCAPED_UNICODE) .
            '); });', $view::POS_END);
        }

        $view->setTemplatesPath(Craft::$app->path->getSiteTemplatesPath());

        return Template::raw($formHtml);
    }

    public function renderComment($comment)
    {
        $settings = Comments::$plugin->getSettings();
        $view = Craft::$app->getView();

        // Only approved comments can be rendered
        if ($comment->status != Comment::STATUS_APPROVED) {
            return;
        }

        $templatePath = $this->_getTemplatePath();
        $view->setTemplatesPath($templatePath);

        // Prepare variables to pass to templates - important to include route params
        $routeParams = Craft::$app->getUrlManager()->getRouteParams();

        $variables = array_merge($routeParams, [
            'comment' => $comment,
            'settings' => $settings,
        ]);

        // Build our complete form
        $formHtml = $view->renderTemplate('comment', $variables);

        $view->setTemplatesPath(Craft::$app->path->getSiteTemplatesPath());

        return Template::raw($formHtml);
    }

    // Checks is there are sufficient permissions for commenting on this element
    public function checkPermissions($element)
    {
        // Get the global permissions settings
        $permissions = Comments::$plugin->getSettings()->permissions;

        if (!$element) {
            return false;
        }

        $elementType = get_class($element);

        // Do we even have any settings setup? By default - anything can be commented on
        if ($permissions) {
            if (isset($permissions[$elementType])) {
                // All are set to enabled
                if ($permissions[$elementType] === '*') {
                    return true;
                }

                // None are selected
                if (!is_array($permissions[$elementType])) {
                    return false;
                }

                // Check for various elements
                if ($elementType == 'craft\elements\Entry') {
                    $uid = $element->section->uid;
                } else {
                    $uid = $element->group->uid;
                }

                if (!in_array($uid, $permissions[$elementType])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function checkClosed($element)
    {
        $settings = Comments::$plugin->getSettings();

        // Has this comment been manually closed? Takes precedence
        if (!$this->_checkOwnerFieldEnabled($element)) {
            return true;
        }

        // Has this element's publish date exceeded the set auto-close limit? Does it even have a auto-close limit?
        if ($settings->autoCloseDays) {
            $now = new DateTime('now');
            $interval = $now->diff($element->postDate);

            if ($interval->days > $settings->autoCloseDays) {
                return true;
            }
        }

        return false;
    }

    public function sendAuthorNotificationEmail(Comment $comment)
    {
        $recipient = null;
        $emailSent = null;

        Comments::log('Prepare Author Notifications.');

        // Get our commented-on element
        $element = $comment->getOwner();

        if (!$element) {
            Comments::log('Cannot send element author notification: No element ' . json_encode($element));

            return;
        }

        // Get our recipient
        try {        
            if ($element->getAuthor()) {
                $recipient = $element->getAuthor();
            }
        } catch(\Throwable $e) {
            Comments::log('Not sending element author notification, no author found: ' . $e->getMessage());
        }

        if (!$recipient) {
            Comments::log('Cannot send element author notification: No recipient ' . json_encode($recipient));

            return;
        }

        // If the author and commenter are the same user - don't send
        if ($comment->userId && $recipient->id && $comment->userId === $recipient->id) {
            Comments::log('Cannot send element author notification: Commenter #' . $comment->userId . ' same as author #' . $recipient->id . '.');

            return;
        }

        // If the author and commenter have the same email - don't send
        if ($comment->email === $recipient->email) {
            Comments::log('Cannot send element author notification: Commenter ' . $comment->email . ' has same email as author ' . $recipient->email . '.');

            return;
        }

        try {
            $message = Craft::$app->getMailer()
                ->composeFromKey('comments_author_notification', [
                    'element' => $element,
                    'comment' => $comment,
                ])
                ->setTo($recipient);

            // Fire a 'beforeSendAuthorEmail' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_SEND_AUTHOR_EMAIL)) {
                $this->trigger(self::EVENT_BEFORE_SEND_AUTHOR_EMAIL, new EmailEvent([
                    'mail' => $message,
                    'user' => $recipient,
                    'element' => $element,
                    'comment' => $comment,
                ]));
            }

            $emailSent = $message->send();
        } catch (\Throwable $e) {
            Comments::error('Error sending element author notification: ' . $e->getMessage());
        }

        if ($emailSent) {
            Comments::log('Email sent successfully to element author (' . $recipient->email . ')');
        } else {
            Comments::error('Unable to send email to element author (' . $recipient->email . ')');
        }
    }

    public function sendReplyNotificationEmail(Comment $comment)
    {
        $settings = Comments::$plugin->getSettings();

        $recipient = null;
        $emailSent = null;

        Comments::log('Prepare Reply Notifications.');

        // Get our commented-on element
        $element = $comment->getOwner();

        if (!$element) {
            Comments::log('Cannot send reply notification: No element ' . json_encode($element));

            return;
        }

        // Get the comment we're replying to
        $parentComment = $comment->getParent();

        if (!$parentComment) {
            Comments::log('Cannot send reply notification: No parent comment ' . json_encode($parentComment));

            return;
        }

        // Get our recipient
        $recipient = $parentComment->getAuthor();

        if (!$recipient) {
            Comments::log('Cannot send reply notification: No recipient ' . json_encode($recipient));

            return;
        }

        // If the author and commenter are the same user - don't send
        if ($comment->userId && $recipient->id && $comment->userId === $recipient->id) {
            Comments::log('Cannot send reply notification: Commenter #' . $comment->userId . ' same as author #' . $recipient->id . '.');

            return;
        }

        // If the author and commenter have the same email - don't send
        if ($comment->email === $recipient->email) {
            Comments::log('Cannot send reply notification: Commenter ' . $comment->email . ' has same email as author ' . $recipient->email . '.');

            return;
        }

        try {
            $message = Craft::$app->getMailer()
                ->composeFromKey('comments_reply_notification', [
                    'element' => $element,
                    'comment' => $comment,
                ])
                ->setTo($recipient);

            // Fire a 'beforeSendReplyEmail' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_SEND_REPLY_EMAIL)) {
                $this->trigger(self::EVENT_BEFORE_SEND_REPLY_EMAIL, new EmailEvent([
                    'mail' => $message,
                    'user' => $recipient,
                    'element' => $element,
                    'comment' => $comment,
                ]));
            }

            $emailSent = $message->send();
        } catch (\Throwable $e) {
            Comments::error('Error sending reply notification: ' . $e->getMessage());
        }

        if ($emailSent) {
            Comments::log('Email sent successfully comment author (' . $recipient->email . ')');
        } else {
            Comments::error('Unable to send email to comment author (' . $recipient->email . ')');
        }
    }

    public function sendModeratorNotificationEmail(Comment $comment)
    {
        $settings = Comments::$plugin->getSettings();

        $recipient = null;
        $emailSent = null;

        Comments::log('Prepare Moderator Notifications.');

        // Get our commented-on element
        $element = $comment->getOwner();

        if (!$element) {
            Comments::log('Cannot send moderator notification: No element ' . json_encode($element));

            return;
        }

        // Get our recipients - they're a user group
        if (!$settings->moderatorUserGroup) {
            Comments::log('Cannot send moderator notification: No moderator group set.');

            return;
        }

        $groupId = Db::idByUid(Table::USERGROUPS, $settings->moderatorUserGroup);
        $recipients = User::find()->groupId($groupId)->all();

        foreach ($recipients as $key => $user) {
            try {
                $mail = Craft::$app->getMailer()
                    ->composeFromKey('comments_moderator_notification', [
                        'element' => $element,
                        'comment' => $comment,
                    ])
                    ->setTo($user);

                // Fire a 'beforeSendModeratorEmail' event
                if ($this->hasEventHandlers(self::EVENT_BEFORE_SEND_MODERATOR_EMAIL)) {
                    $this->trigger(self::EVENT_BEFORE_SEND_MODERATOR_EMAIL, new EmailEvent([
                        'mail' => $mail,
                        'user' => $user,
                        'element' => $element,
                        'comment' => $comment,
                    ]));
                }

                $mail->send();

                Comments::log('Email sent successfully comment moderator (' . $user->email . ')');
            } catch (\Throwable $e) {
                Comments::error('Unable to send email to comment moderator (' . $user->email . ') - ' . $e->getMessage());
            }
        }
    }

    public function sendModeratorApprovedNotificationEmail(Comment $comment)
    {
        $recipient = null;
        $emailSent = null;

        Comments::log('Prepare Moderator Approved Notifications.');

        // Get our commented-on element
        $element = $comment->getOwner();

        if (!$element) {
            Comments::log('Cannot send element moderator author notification: No element ' . json_encode($element));

            return;
        }

        // Get our recipient
        try {        
            if ($element->getAuthor()) {
                $recipient = $element->getAuthor();
            }
        } catch(\Throwable $e) {
            Comments::log('Not sending element moderator author notification, no author found: ' . $e->getMessage());
        }

        if (!$recipient) {
            Comments::log('Cannot send element moderator author notification: No recipient ' . json_encode($recipient));

            return;
        }

        // If the author and commenter are the same user - don't send
        if ($comment->userId && $recipient->id && $comment->userId === $recipient->id) {
            Comments::log('Cannot send element moderator author notification: Commenter #' . $comment->userId . ' same as author #' . $recipient->id . '.');

            return;
        }

        // If the author and commenter have the same email - don't send
        if ($comment->email === $recipient->email) {
            Comments::log('Cannot send element moderator author notification: Commenter ' . $comment->email . ' has same email as author ' . $recipient->email . '.');

            return;
        }

        try {
            $message = Craft::$app->getMailer()
                ->composeFromKey('comments_moderator_approved_notification', [
                    'element' => $element,
                    'comment' => $comment,
                ])
                ->setTo($recipient);

            // Fire a 'beforeSendModeratorApprovedEmail' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL)) {
                $this->trigger(self::EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL, new EmailEvent([
                    'mail' => $message,
                    'user' => $recipient,
                    'element' => $element,
                    'comment' => $comment,
                ]));
            }

            $emailSent = $message->send();
        } catch (\Throwable $e) {
            Comments::error('Error sending element moderator author notification: ' . $e->getMessage());
        }

        if ($emailSent) {
            Comments::log('Email sent successfully to element moderator author (' . $recipient->email . ')');
        } else {
            Comments::error('Unable to send email to element moderator author (' . $recipient->email . ')');
        }
    }

    private function _getCommentAncestors($comments, $comment)
    {
        if ($comment->parent) {
            $comments[] = $comment->parent;

            return $this->_getCommentAncestors($comments, $comment->parent);
        }

        return $comments;
    }

    public function sendSubscribeNotificationEmail(Comment $comment)
    {
        $recipients = null;
        // $emailSent = null;

        Comments::log('Prepare Subscribe Notifications.');

        // Get our commented-on element
        $element = $comment->getOwner();

        if (!$element) {
            Comments::log('Cannot send subscribe notification: No element ' . json_encode($element));

            return;
        }

        $subscribedUserIds = [];

        // Get all users subscribed to this element, generally.
        $elementSubscribers = Comments::$plugin->getSubscribe()->getAllSubscribers($element->id, $element->siteId, null);

        foreach ($elementSubscribers as $elementSubscriber) {
            $subscribedUserIds[] = $elementSubscriber->userId;
        }

        // Then also check if we're replying to a comment and get all subscribers subscribing to that comment thread.
        // But - by default, everyone subscribes to replies on their comment, so we want to check against that.
        // Note the checks against ancestors, so we can check against an entire possible thread.
        $commentAncestors = $this->_getCommentAncestors([], $comment);

        foreach ($commentAncestors as $ancestor) {
            // Only allow users to receive notifications
            if (!$ancestor->userId) {
                continue;
            }

            $hasSubscribed = Comments::$plugin->getSubscribe()->hasSubscribed($element->id, $element->siteId, $ancestor->userId, $ancestor->id);

            if ($hasSubscribed) {
                $subscribedUserIds[] = $ancestor->userId;
            }
        }

        // Just in case there are any duplicates
        $subscribedUserIds = array_unique($subscribedUserIds);

        if (!$subscribedUserIds) {
            Comments::log('No users subscribed to this element.');

            return;
        }

        foreach ($subscribedUserIds as $subscribedUserId) {
            try {
                $user = Craft::$app->getElements()->getElementById($subscribedUserId, User::class);

                if (!$user) {
                    Comments::log('Unable to find user with ID: ' . $subscribedUserId);

                    continue;
                }
                
        		// Skip for current user
        		$currentUser = Craft::$app->getUser()->getIdentity();

        		if ($user->id == $currentUser->id) {
        			continue;
        		}
        		
        		// Separate email keys for comment on comment vs comment on entry
        		$emailkey = ( $commentAncestors && count($commentAncestors) > 0 ) ? 'comments_subscriber_notification_comment' : 'comments_subscriber_notification_element';

        		$message = Craft::$app->getMailer()
        		    ->composeFromKey($emailkey, [
            			'element' => $element,
            			'comment' => $comment
        		    ])
        		    ->setTo($user);

                if ($message->send()) {
                    Comments::log('Email sent successfully to subscriber (' . $user->email . ')');
                } else {
                    Comments::error('Unable to send email to subscriber (' . $user->email . ')');
                }
            } catch (\Throwable $e) {
                Comments::error('Error sending reply notification: ' . $e->getMessage());

                continue;
            }
        }
    }

    public function handleChangedFieldLayout(ConfigEvent $event)
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty($config = reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Comment::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Comment::class)->id;
        $layout->type = Comment::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout);
    }

    public function pruneDeletedField(FieldEvent $event)
    {
        /** @var Field $field */
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY);

        // Prune the UID from field layouts.
        if (is_array($layoutData)) {
            foreach ($layoutData as $layoutUid => $layout) {
                if (!empty($layout['tabs'])) {
                    foreach ($layout['tabs'] as $tabUid => $tab) {
                        $projectConfig->remove(self::CONFIG_FIELDLAYOUT_KEY . '.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                    }
                }
            }
        }
    }

    public function handleDeletedFieldLayout(ConfigEvent $event)
    {
        Craft::$app->getFields()->deleteLayoutsByType(Comment::class);
    }

    public function saveFieldLayout()
    {
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost('settings');

        $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];

        Craft::$app->getProjectConfig()->set(self::CONFIG_FIELDLAYOUT_KEY, $configData);
    }


    // Private Methods
    // =========================================================================

    private function _checkOwnerFieldEnabled($element)
    {
        foreach ($element->getFieldValues() as $key => $value) {
            $field = Craft::$app->getFields()->getFieldByHandle($key);

            if (get_class($field) === 'verbb\comments\fields\CommentsField') {
                if (isset($value['commentEnabled']) && !$value['commentEnabled']) {
                    return false;
                }
            }
        }

        return true;
    }

    private function _getTemplatePath()
    {
        $settings = Comments::$plugin->getSettings();

        $templatePath = Craft::getAlias('@verbb/comments/templates/_special');

        if ($settings->templateFolderOverride) {
            $templatePath = Craft::$app->path->getSiteTemplatesPath() . DIRECTORY_SEPARATOR . $settings->templateFolderOverride;
        }

        return $templatePath;
    }

}
