<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;

use craft\web\Controller;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Serves the site's copies of commenters' Gravatar images (SD-53), so visitors' browsers load avatars
 * from the site, not from Gravatar. Each image is addressed by a random ID, never by the hash Gravatar
 * knows it by.
 */
class AvatarsController extends Controller
{
    // Properties
    // =========================================================================

    protected array|bool|int $allowAnonymous = ['image'];


    // Public Methods
    // =========================================================================

    public function actionImage(string $id): Response
    {
        $avatar = Comments::$plugin->getAvatars()->imageByUid($id);

        if (!$avatar) {
            throw new NotFoundHttpException('Avatar not found.');
        }

        $headers = $this->response->getHeaders();
        // The URL changes when the image does, so it can be kept for as long as the copy lasts.
        $headers->set('Cache-Control', 'public, max-age=604800');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $headers->set('X-Robots-Tag', 'noindex');

        $this->response->format = Response::FORMAT_RAW;

        return $this->response->sendContentAsFile($avatar['image'], 'avatar', [
            'mimeType' => $avatar['mimeType'],
            'inline' => true,
        ]);
    }
}
