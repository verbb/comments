<?php
namespace verbb\comments\elements\actions;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

use Throwable;

/**
 * One moderator decision applied to the selected comments. Comments it doesn't apply to, such as
 * approving one that's already approved, are left alone.
 */
abstract class ModerateComments extends ElementAction
{
    // Public Methods
    // =========================================================================

    abstract public function decision(): string;

    public function performAction(ElementQueryInterface $query): bool
    {
        $moderation = Comments::$plugin->getModeration();
        $actor = Craft::$app->getUser()->getIdentity();
        $done = 0;
        $failed = 0;

        foreach ($query->all() as $comment) {
            if (!$comment instanceof Comment || !in_array($this->decision(), $moderation->availableDecisions($comment, $actor), true)) {
                continue;
            }

            try {
                $moderation->decide($comment, $this->decision(), $actor);
                $done++;
            } catch (Throwable) {
                $failed++;
            }
        }

        if (!$done) {
            $this->setMessage($failed
                ? Craft::t('comments', 'Couldn’t update the selected comments.')
                : Craft::t('comments', 'None of the selected comments needed this.'));

            return $failed === 0;
        }

        $this->setMessage($failed
            ? Craft::t('comments', '{done} updated, {failed} couldn’t be.', ['done' => $done, 'failed' => $failed])
            : ($done === 1 ? $moderation->decisionMessage($this->decision()) : Craft::t('comments', '{count} comments updated.', ['count' => $done])));

        return true;
    }
}
