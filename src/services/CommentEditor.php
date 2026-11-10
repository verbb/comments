<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\RemovalReason;
use verbb\comments\enums\ReportStatus;
use verbb\comments\models\CommentEditorTab;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\CreateFieldLayoutFormEvent;
use craft\fieldlayoutelements\Html as HtmlElement;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\web\View;

use yii\base\UserException;
use yii\web\ForbiddenHttpException;

/**
 * What a comment adds to Craft's element editor: its extra tabs and sidebar, and applying the posted form.
 */
class CommentEditor extends Component
{
    // Constants
    // =========================================================================

    public const OWNER_TYPES = [
        'entry' => Entry::class,
        'category' => Category::class,
        'asset' => Asset::class,
        'user' => User::class,
    ];


    // Public Methods
    // =========================================================================

    /**
     * Apply the posted editor form. This reads named values rather than mass-assigning, so nothing
     * beyond these fields can be set from the request.
     *
     * Moderation state, mentions and thread moves wait for `finalizeSave()`, so they only happen if
     * the comment passes validation.
     */
    public function applyRequest(Comment $comment): void
    {
        $request = Craft::$app->getRequest();

        // A refresh only needs custom field conditions, and shouldn't create anything along the way.
        if (!$request->getIsPost() || $this->_isFormRefresh()) {
            return;
        }

        $comments = CommentsPlugin::$plugin->getComments();
        $elements = Craft::$app->getElements();
        $user = Craft::$app->getUser();
        $siteId = (int)$comment->siteId;
        $changes = [
            'state' => null,
            'decision' => null,
            'mentionIds' => CommentsPlugin::$plugin->getMentions()->getIdentityIdsForComment((int)$comment->id),
            'reassignFrom' => null,
            'owner' => null,
        ];

        $decision = (string)$request->getBodyParam('decision', '');
        $requestedStatus = (string)$request->getBodyParam('status', $comment->status);

        if ($decision !== '') {
            // The sidebar's Approve, Spam, Remove and Keep buttons: one step that saves the form too.
            if (!in_array($decision, CommentsPlugin::$plugin->getModeration()->availableDecisions($comment, $user->getIdentity()), true)) {
                throw new ForbiddenHttpException('User not authorized to make this decision.');
            }

            $reason = (string)$request->getBodyParam('removalReason', '') ?: null;

            // Checked before saving, so a bad reason doesn't save the edit without the decision.
            if ($decision === Moderation::DECISION_REMOVE && $reason !== null && !RemovalReason::tryFrom($reason)) {
                throw new UserException(Craft::t('comments', 'Choose a reason for removing this comment.'));
            }

            $changes['decision'] = [
                'decision' => $decision,
                'reason' => $reason,
                'note' => (string)$request->getBodyParam('removalNote', ''),
            ];
        } elseif ($requestedStatus !== $comment->status) {
            if (!$user->checkPermission('comments-moderate')) {
                throw new ForbiddenHttpException('User not authorized to change this comment’s status.');
            }

            if ($requestedStatus === Comment::STATUS_REMOVED && !$user->checkPermission('comments-trash')) {
                throw new ForbiddenHttpException('User not authorized to remove this comment.');
            }

            $changes['state'] = match ($requestedStatus) {
                Comment::STATUS_APPROVED => ModerationState::Approved,
                Comment::STATUS_PENDING => ModerationState::Pending,
                Comment::STATUS_SPAM => ModerationState::Spam,
                Comment::STATUS_REMOVED => ModerationState::Removed,
                default => throw new UserException(Craft::t('comments', 'Invalid comment status.')),
            };
        }

        $comments->applyBodyFromRequest($comment);

        // The page no longer offers switching between a member and a guest author, so the comment's own is the default.
        $guestAuthor = $request->getBodyParam('guestAuthor');
        $comment->cpAuthorType = $guestAuthor === null ? ($comment->isGuest() ? 'guest' : 'user') : ($guestAuthor === '1' ? 'guest' : 'user');
        $authorId = $this->_selectedElementId('authorId', $comment->userId);

        if ($comment->cpAuthorType === 'user') {
            $author = $authorId ? User::find()->id($authorId)->status(null)->one() : null;

            if ($author) {
                $comment->userId = (int)$author->id;
                $comment->identityId = (int)CommentsPlugin::$plugin->getIdentities()->getOrCreateForUser($author)->id;
                $comment->setUser($author);
                $comment->name = null;
                $comment->email = null;
            } else {
                $comment->userId = null;
                $comment->setUser(null);
            }
        } else {
            $hadUser = (bool)$comment->userId;
            $name = trim((string)$request->getBodyParam('name', $comment->name));
            $email = trim((string)$request->getBodyParam('email', $comment->email));

            $comment->userId = null;
            $comment->setUser(null);
            $comment->name = $name !== '' ? $name : null;
            $comment->email = $email !== '' ? $email : null;

            // Let the validated save create the guest identity so a failed edit has no side effects.
            if ($hadUser) {
                $comment->identityId = null;
            }
        }

        $ownerType = (string)$request->getBodyParam('ownerType', $comment->getOwnerType());
        $handle = array_search($ownerType, self::OWNER_TYPES, true);

        // Only the types the sidebar has a picker for can be moved. A comment on any other element
        // type, such as a product, keeps its element.
        if ($handle === false) {
            $comment->editorChanges = $changes;

            return;
        }

        $ownerId = $this->_selectedElementId('owner' . ucfirst($handle) . 'Id', $comment->ownerId);

        // A comment whose element has been deleted can still be edited without choosing a new one.
        if (!$ownerId && $comment->ownerId) {
            throw new UserException(Craft::t('comments', 'Select an element.'));
        }

        $owner = $comment->getOwner();

        if ($ownerId && ($ownerId !== (int)$comment->ownerId || !$owner || $owner::class !== $ownerType)) {
            $owner = $elements->getElementById($ownerId, $ownerType, $siteId);

            if (!$owner) {
                throw new UserException(Craft::t('comments', 'The selected element could not be found.'));
            }

            $changes['reassignFrom'] = $comment->conversationId ? (int)$comment->conversationId : null;
            $changes['owner'] = $owner;
            $comment->ownerId = (int)$owner->id;
            $comment->ownerSiteId = $siteId;
            $comment->conversationId = (int)CommentsPlugin::$plugin->getConversations()->getOrCreateForOwner((int)$owner->id, $siteId)->id;
            $comment->setOwner($owner);
        }

        $comment->editorChanges = $changes;
    }

    /**
     * Everything `applyRequest()` held back, now that the comment has been saved.
     */
    public function finalizeSave(Comment $comment): void
    {
        $changes = $comment->editorChanges;
        $comment->editorChanges = null;

        if ($changes === null) {
            return;
        }

        $comments = CommentsPlugin::$plugin->getComments();
        $mentionIds = $comments->takePendingMentionIdentityIds();

        if ($mentionIds !== null) {
            $comments->finalizeCommentMentions($comment, $changes['mentionIds'], $mentionIds);
        }

        $comments->takePendingAssetIds();
        $assetReferences = $comments->takePendingAssetReferences();

        if ($assetReferences !== null) {
            CommentsPlugin::$plugin->getCommentAssets()->syncEditedReferences((int)$comment->id, $assetReferences, (int)Craft::$app->getUser()->getId());
        }

        if ($changes['owner']) {
            CommentsPlugin::$plugin->getConversations()->reassignThread(
                $comment,
                $changes['owner'],
                (int)$comment->siteId,
                $changes['reassignFrom'],
            );
        }

        if ($changes['decision']) {
            CommentsPlugin::$plugin->getModeration()->decide(
                $comment,
                $changes['decision']['decision'],
                Craft::$app->getUser()->getIdentity(),
                $changes['decision']['reason'],
                $changes['decision']['note'],
            );
        } elseif ($changes['state']) {
            CommentsPlugin::$plugin->getModeration()->setState($comment, $changes['state'], Craft::$app->getUser()->getIdentity());
        }
    }

    public function addTabs(CreateFieldLayoutFormEvent $event): void
    {
        /** @var Comment $comment */
        $comment = $event->element;

        if (!$comment->id) {
            return;
        }

        // Craft refreshes the form as fields change, only using the tab menu. Rendering these again
        // would just re-run their scripts.
        $isRefresh = $this->_isFormRefresh();

        $this->_addContentContext($event, $comment, $isRefresh);

        if (!Craft::$app->getUser()->checkPermission('comments-moderate')) {
            return;
        }

        // Craft's form refresh only keeps track of tabs with a UID, and brings the first of those back
        // into view if none of them is selected.
        $tab = new CommentEditorTab(['handle' => 'activity', 'name' => $this->panelLabel($comment), 'uid' => 'comment-activity']);
        $tab->setLayout($event->sender);
        $tab->setElements([new HtmlElement(Html::tag('div', $isRefresh ? '' : $this->panelHtml($comment)))]);
        $event->tabs[] = $tab;
    }

    public function panelLabel(Comment $comment): string
    {
        return Craft::t('comments', 'Activity');
    }

    /**
     * The Activity tab: a box for moderators' notes, then everything that's happened, newest first, so a
     * new note sits by the box.
     */
    public function panelHtml(Comment $comment): string
    {
        return Html::tag('div', Craft::$app->getView()->renderTemplate('comments/comments/_editor-activity', [
            'comment' => $comment,
            'timeline' => array_reverse($this->historyTimeline($comment)),
            'protection' => CommentsPlugin::$plugin->getProtection()->describe($comment->protection),
        ], View::TEMPLATE_MODE_CP), [
            'data' => ['comments-panel' => 'activity', 'comment-id' => $comment->id],
        ]);
    }

    /**
     * Everything that's happened to the comment, oldest first: posting, moderation, reports and moderators'
     * notes. Each item
     * has a tone and icon for its kind, a first line (who did what), and second-line details: the element
     * posted on, chips for a reason and a report's state, and a reporter's or moderator's note.
     */
    public function historyTimeline(Comment $comment): array
    {
        $moderation = CommentsPlugin::$plugin->getModeration();
        $reports = CommentsPlugin::$plugin->getReports();
        $owner = $comment->getOwner();
        $ownerUrl = $owner?->getCpEditUrl();
        $items = [[
            'date' => $comment->commentDate,
            'actor' => $comment->getAuthorName(),
            'actorUrl' => $comment->identityId ? UrlHelper::cpUrl('comments/commenters/' . $comment->identityId) : null,
            'text' => Craft::t('comments', 'posted this'),
            'tone' => 'blue',
            'icon' => 'comment',
            'context' => $owner
                ? Craft::t('comments', 'on {element}', [
                    'element' => $ownerUrl ? Html::a(Html::encode($owner->getUiLabel()), $ownerUrl) : Html::encode($owner->getUiLabel()),
                ])
                : null,
            'chips' => [],
            'note' => null,
        ]];

        foreach (array_reverse($moderation->getDetailPanel((int)$comment->id, 'history')['items']) as $action) {
            [$tone, $icon] = match ($action['action']) {
                Moderation::ACTION_APPROVE, Moderation::ACTION_RESTORE => ['teal', 'check'],
                Moderation::ACTION_REMOVE => ['red', 'trash'],
                Moderation::ACTION_MARK_SPAM => ['violet', 'shield'],
                Moderation::ACTION_MARK_NOT_SPAM => ['teal', 'arrow-rotate-left'],
                Moderation::ACTION_HOLD => ['amber', 'clock'],
                Moderation::ACTION_AUTHOR_EDIT, Moderation::ACTION_MODERATOR_EDIT => ['blue', 'pen'],
                default => ['gray', 'clock'],
            };

            $items[] = [
                'date' => $action['dateCreated'],
                'actor' => $action['actorName'] ?? ($action['action'] === Moderation::ACTION_AUTHOR_EDIT ? $comment->getAuthorName() : Craft::t('comments', 'Comments')),
                // Staff link to their user account; an author's own edit, to their commenter page.
                'actorUrl' => $action['actorUrl'] ?? ($action['action'] === Moderation::ACTION_AUTHOR_EDIT && $comment->identityId
                    ? UrlHelper::cpUrl('comments/commenters/' . $comment->identityId)
                    : null),
                // Edits read as the person's own action, since the actor is named before it.
                'text' => in_array($action['action'], [Moderation::ACTION_AUTHOR_EDIT, Moderation::ACTION_MODERATOR_EDIT], true)
                    ? Craft::t('comments', 'edited it')
                    : mb_strtolower($action['actionLabel']),
                'tone' => $tone,
                'icon' => $icon,
                'context' => null,
                'chips' => $action['reasonLabel'] ? [['label' => $action['reasonLabel'], 'tone' => $tone]] : [],
                'note' => null,
            ];
        }

        foreach (array_reverse($moderation->getDetailPanel((int)$comment->id, 'reports')['items']) as $report) {
            $items[] = [
                'date' => $report['dateCreated'],
                'actor' => $report['reporterName'],
                'actorUrl' => $report['identityId'] ? UrlHelper::cpUrl('comments/commenters/' . $report['identityId']) : null,
                'text' => $report['reporterName'] ? Craft::t('comments', 'reported this') : Craft::t('comments', 'Reported'),
                'tone' => 'red',
                'icon' => 'flag',
                'context' => null,
                'chips' => [
                    ['label' => $reports->reasonLabel($report['reason']), 'tone' => 'red'],
                    match ($report['status']) {
                        ReportStatus::Resolved->value => ['label' => Craft::t('comments', 'Resolved'), 'tone' => 'gray'],
                        ReportStatus::Dismissed->value => ['label' => Craft::t('comments', 'Dismissed'), 'tone' => 'gray'],
                        default => ['label' => Craft::t('comments', 'Open'), 'tone' => 'open'],
                    },
                ],
                'note' => $report['details'] ?: null,
            ];
        }

        foreach ($moderation->getDetailPanel((int)$comment->id, 'notes')['items'] as $note) {
            $author = !empty($note['userId']) ? Craft::$app->getUsers()->getUserById((int)$note['userId']) : null;

            $items[] = [
                'date' => $note['dateCreated'],
                'actor' => $note['authorName'] ?: Craft::t('comments', 'Unknown user'),
                'actorUrl' => $author?->getCpEditUrl(),
                'text' => Craft::t('comments', 'left a note'),
                'tone' => 'note',
                'icon' => 'note-sticky',
                'context' => null,
                'chips' => [],
                'note' => $note['note'],
            ];
        }

        usort($items, static fn(array $a, array $b): int => DateTimeHelper::toDateTime($a['date'])?->getTimestamp() <=> DateTimeHelper::toDateTime($b['date'])?->getTimestamp());

        return $items;
    }

    /**
     * What a moderator needs to know first: why it's waiting, why it was marked as spam, what it was
     * reported for, or why it was removed.
     */
    public function reviewNotice(Comment $comment): ?array
    {
        $moderation = CommentsPlugin::$plugin->getModeration();
        $state = $moderation->currentState($comment);
        $reason = HeldReason::tryFrom((string)$comment->heldReason);
        $protection = $reason === HeldReason::Protection ? CommentsPlugin::$plugin->getProtection()->describe($comment->protection) : [];

        if ($state === ModerationState::Pending) {
            return [
                'tone' => 'waiting',
                'title' => Craft::t('comments', 'Waiting for review: {reason}', ['reason' => mb_strtolower(($reason ?? HeldReason::PreModeration)->label())]),
                'text' => ($reason ?? HeldReason::PreModeration)->explanation(),
                'lines' => [],
                'protection' => $protection,
            ];
        }

        if ($state === ModerationState::Spam) {
            return [
                'tone' => 'spam',
                'title' => Craft::t('comments', 'Marked as spam'),
                'text' => match ($reason) {
                    HeldReason::Protection => Craft::t('comments', 'Spam protection marked this comment. Not spam sends it to Needs review, and tells the provider it was wrong.'),
                    HeldReason::Keyword => Craft::t('comments', 'This comment contains one of the Spam Keywords. Not spam sends it to Needs review.'),
                    default => Craft::t('comments', 'Hidden from the site. Not spam sends it to Needs review.'),
                },
                'lines' => [],
                'protection' => $protection,
            ];
        }

        if ($state === ModerationState::Removed) {
            $removal = null;

            foreach ($moderation->getDetailPanel((int)$comment->id, 'history')['items'] as $action) {
                if ($action['action'] === Moderation::ACTION_REMOVE) {
                    $removal = $action;

                    break;
                }
            }

            return [
                'tone' => 'removed',
                'title' => Craft::t('comments', 'Removed'),
                'text' => $removal
                    ? Craft::t('comments', 'Removed by {name}{reason}. Replies stay on the site under “Comment removed”.', [
                        'name' => $removal['actorName'] ?? Craft::t('comments', 'a moderator'),
                        'reason' => $removal['reasonLabel'] ? ' · ' . $removal['reasonLabel'] : '',
                    ])
                    : Craft::t('comments', 'Hidden from the site. Replies stay under “Comment removed”.'),
                'lines' => [],
                'protection' => [],
            ];
        }

        $reports = CommentsPlugin::$plugin->getReports();
        $open = $reports->getOpenForComment((int)$comment->id);

        if (!$open) {
            return null;
        }

        return [
            'tone' => 'reported',
            'title' => count($open) === 1
                ? Craft::t('comments', 'Reported once')
                : Craft::t('comments', 'Reported {count} times', ['count' => count($open)]),
            'text' => Craft::t('comments', 'It’s still on the site. Keep it to dismiss the reports, or remove it.'),
            'lines' => array_map(static fn($report): string => trim($reports->reasonLabel($report->reason) . ($report->details ? ': ' . $report->details : '')), $open),
            'protection' => [],
        ];
    }


    // Private Methods
    // =========================================================================

    /**
     * Why the comment is waiting, what it replies to, and a guest's name and address, around the
     * comment's own fields on the first tab.
     */
    private function _addContentContext(CreateFieldLayoutFormEvent $event, Comment $comment, bool $isRefresh): void
    {
        if ($isRefresh || !$event->tabs) {
            return;
        }

        $view = Craft::$app->getView();
        $user = Craft::$app->getUser();
        $before = $view->renderTemplate('comments/comments/_editor-context', [
            'comment' => $comment,
            'review' => $user->checkPermission('comments-moderate') ? $this->reviewNotice($comment) : null,
            'parent' => $comment->getParentComment(),
        ], View::TEMPLATE_MODE_CP);
        $after = $comment->isGuest()
            ? $view->renderTemplate('comments/comments/_editor-guest', ['comment' => $comment], View::TEMPLATE_MODE_CP)
            : '';

        $tab = clone $event->tabs[0];
        $elements = $tab->getElements();

        if (trim($before) !== '') {
            array_unshift($elements, new HtmlElement($before));
        }

        if (trim($after) !== '') {
            $elements[] = new HtmlElement($after);
        }

        $tab->setElements($elements);
        $event->tabs[0] = $tab;
    }

    private function _isFormRefresh(): bool
    {
        return Craft::$app->requestedRoute === 'elements/update-field-layout';
    }

    private function _selectedElementId(string $name, ?int $fallback = null): ?int
    {
        $value = Craft::$app->getRequest()->getBodyParam($name, $fallback);

        if (is_array($value)) {
            $value = reset($value);
        }

        $value = (int)$value;

        return $value > 0 ? $value : null;
    }
}
