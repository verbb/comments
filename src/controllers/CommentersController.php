<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ReviewSetting;
use verbb\comments\services\Commenters;
use verbb\comments\services\Frontend;

use Craft;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\web\View;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use Throwable;

class CommentersController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requirePermission('comments-moderate');

        return true;
    }

    public function actionIndex(): Response
    {
        $commenters = Comments::$plugin->getCommenters();
        $sources = $commenters->getSources();
        $source = (string)$this->request->getQueryParam('source', Commenters::SOURCE_ALL);

        return $this->renderTemplate('comments/commenters/_index', [
            'sources' => $sources,
            'source' => isset($sources[$source]) ? $source : Commenters::SOURCE_ALL,
            'pageSize' => Commenters::PAGE_SIZE,
            'reviewSettings' => array_map(static fn(ReviewSetting $setting): array => [
                'value' => $setting->value,
                'label' => $setting->label(),
                'instructions' => $setting->instructions(),
            ], ReviewSetting::cases()),
        ]);
    }

    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();

        return $this->asJson(Comments::$plugin->getCommenters()->getTableData(
            (string)$this->request->getParam('source', Commenters::SOURCE_ALL),
            $this->request->getParam('search'),
            (int)$this->request->getParam('page', 1),
            $this->request->getParam('sort'),
            (string)$this->request->getParam('direction', 'desc'),
        ));
    }

    public function actionView(int $identityId): Response
    {
        $detail = Comments::$plugin->getCommenters()->getDetail($identityId);

        if (!$detail) {
            throw new NotFoundHttpException('Commenter not found.');
        }

        $photo = $detail['user']?->getPhoto();
        $screen = $this->asCpScreen()
            ->title($detail['name'])
            ->selectedSubnavItem('commenters')
            ->addCrumb(Craft::t('comments', Comments::$plugin->getPluginName()), 'comments')
            ->addCrumb(Craft::t('comments', 'Commenters'), 'comments/commenters')
            ->contentTemplate('comments/commenters/_view', $detail + [
                'commentsIndexHtml' => $this->_commentsIndexHtml($identityId),
                'initials' => Commenters::initials($detail['name']),
                'avatarTone' => Frontend::avatarTone($detail['name']),
                'photoUrl' => $photo ? Craft::$app->getAssets()->getThumbUrl($photo, 128) : null,
            ])
            ->metaSidebarTemplate('comments/commenters/_sidebar', $detail + [
                'reviewSettings' => ReviewSetting::cases(),
                'redirectUrl' => "comments/commenters/{$identityId}",
            ]);

        if ($detail['user']) {
            $screen->additionalButtonsHtml(Html::a(Craft::t('comments', 'User account'), $detail['user']->getCpEditUrl(), ['class' => 'btn', 'data-icon' => 'user']));
        }

        return $screen;
    }

    /**
     * Set the review setting of one commenter, from their page, or of several, from the list.
     */
    public function actionSetReviewSetting(): ?Response
    {
        $this->requirePostRequest();

        $setting = ReviewSetting::tryFrom((string)$this->request->getRequiredBodyParam('reviewSetting'));

        if (!$setting) {
            throw new BadRequestHttpException('Invalid review setting.');
        }

        $identityIds = array_values(array_unique(array_filter(array_map('intval', (array)$this->request->getBodyParam('identityIds', [$this->request->getBodyParam('identityId')])))));

        if (!$identityIds) {
            throw new BadRequestHttpException('Choose at least one commenter.');
        }

        $note = trim((string)$this->request->getBodyParam('note')) ?: null;
        $actor = Craft::$app->getUser()->getIdentity();
        $moderation = Comments::$plugin->getModeration();

        try {
            foreach ($identityIds as $identityId) {
                if ($moderation->getReviewSetting($identityId) !== $setting) {
                    $moderation->setReviewSetting($identityId, $setting, $actor, $note);
                } elseif ($note !== null) {
                    // Saving the same setting with a note keeps the note rather than dropping it.
                    $moderation->addCommenterNote($identityId, $note, $actor);
                }
            }
        } catch (Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        $message = count($identityIds) === 1
            ? Craft::t('comments', 'Review setting saved.')
            : Craft::t('comments', 'Review setting saved for {count} commenters.', ['count' => count($identityIds)]);

        return $this->asSuccess($message, redirect: count($identityIds) === 1 ? UrlHelper::cpUrl("comments/commenters/{$identityIds[0]}") : null);
    }

    /**
     * A note about a commenter, from their Activity tab, which comes back redrawn with the note in it.
     */
    public function actionAddNote(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $identityId = (int)$this->request->getRequiredBodyParam('identityId');
        $commenters = Comments::$plugin->getCommenters();

        if (!Comments::$plugin->getIdentities()->getById($identityId)) {
            throw new NotFoundHttpException('Commenter not found.');
        }

        try {
            Comments::$plugin->getModeration()->addCommenterNote(
                $identityId,
                (string)$this->request->getRequiredBodyParam('note'),
                Craft::$app->getUser()->getIdentity(),
            );
        } catch (Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess(Craft::t('comments', 'Note added.'), [
            'html' => $this->getView()->renderTemplate('comments/commenters/_activity', [
                'id' => $identityId,
                'history' => $commenters->getHistory($identityId),
            ], View::TEMPLATE_MODE_CP),
        ]);
    }

    public function actionRevoke(): ?Response
    {
        $this->requirePostRequest();

        $identityId = (int)$this->request->getRequiredBodyParam('identityId');

        try {
            Comments::$plugin->getModeration()->revokeRestriction(
                (int)$this->request->getRequiredBodyParam('restrictionId'),
                Craft::$app->getUser()->getIdentity(),
            );
        } catch (Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess(Craft::t('comments', 'Restriction removed.'), redirect: UrlHelper::cpUrl("comments/commenters/{$identityId}"));
    }


    // Private Methods
    // =========================================================================

    private function _commentsIndexHtml(int $identityId): string
    {
        return Cp::elementIndexHtml(Comment::class, [
            'context' => 'embedded-index',
            'defaultSort' => ['commentDate', 'desc'],
            'defaultTableColumns' => [['reason'], ['ownerId'], ['commentDate']],
            'id' => "commenter-comments-index-{$identityId}",
            'jsSettings' => [
                'criteria' => [
                    'identityId' => $identityId,
                    'status' => null,
                ],
                'storageKey' => "comments.commenter.{$identityId}",
            ],
            'showSiteMenu' => 'auto',
            'showStatusMenu' => true,
            'sources' => false,
        ]);
    }
}
