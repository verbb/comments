<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\services\Subscriptions;

use craft\web\Controller;
use craft\web\View;

use yii\web\Response;

/**
 * Where the links in notification emails land. The signed token is the authority, so this works without
 * an account or the guest's cookie, and mail clients can post a one-click unsubscribe with no session.
 *
 * Only a POST changes anything, because mail scanners follow every link they see.
 */
class SubscriptionsController extends Controller
{
    // Properties
    // =========================================================================

    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = ['manage'];


    // Public Methods
    // =========================================================================

    public function actionManage(): Response
    {
        $headers = $this->response->getHeaders();
        $headers->set('Cache-Control', 'private, no-store');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Robots-Tag', 'noindex');
        $headers->set('X-Frame-Options', 'DENY');

        $subscriptions = Comments::$plugin->getSubscriptions();
        $token = (string)$this->request->getParam('emailToken', '');
        $management = $subscriptions->readManagementToken($token);
        $applied = null;

        if ($management && $this->request->getIsPost()) {
            $applied = $subscriptions->applyManagementToken($management, $this->request->getBodyParam('scope') === Subscriptions::PURPOSE_ALL);
        }

        $html = $this->getView()->renderTemplate('comments/frontend/manage', [
            'token' => $token,
            'management' => $management,
            'applied' => $applied,
            'elementTitle' => $management ? $subscriptions->managementElementTitle($management) : null,
            'purposes' => [
                'all' => Subscriptions::PURPOSE_ALL,
                'conversation' => Subscriptions::PURPOSE_CONVERSATION,
                'confirm' => Subscriptions::PURPOSE_CONFIRM,
            ],
        ], View::TEMPLATE_MODE_CP);

        $this->response->format = Response::FORMAT_HTML;
        $this->response->data = $html;

        if (!$management) {
            $this->response->setStatusCode(404);
        }

        return $this->response;
    }
}
