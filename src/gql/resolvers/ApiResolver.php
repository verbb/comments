<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\Comments;
use verbb\comments\helpers\Gql as GqlHelper;

use Craft;
use craft\web\Request as WebRequest;

use GraphQL\Error\Error;

use Throwable;

/**
 * Resolves the data API's GraphQL queries through the same service as `comments/api/*`. A refusal is a
 * GraphQL error whose `extensions` carry the data API's `code`, `category` and field errors.
 */
class ApiResolver
{
    // Static Methods
    // =========================================================================

    public static function resolveThread(mixed $source, array $arguments): array
    {
        return self::_resolve(static function() use ($arguments): array {
            $api = Comments::$plugin->getApi();
            $api->limitPublicRead();
            $owner = $api->resolveOwner($arguments['ownerId'] ?? null, $arguments['ownerUri'] ?? null, $arguments['siteId'] ?? null);

            return $api->thread($owner, $arguments['sort'] ?? null, $arguments['limit'] ?? null, $arguments['cursor'] ?? null);
        });
    }

    public static function resolveReplies(mixed $source, array $arguments): array
    {
        return self::_resolve(static function() use ($arguments): array {
            $api = Comments::$plugin->getApi();
            $api->limitPublicRead();

            return $api->replies($arguments['parentUid'], $arguments['limit'] ?? null, $arguments['cursor'] ?? null);
        });
    }

    public static function resolveComment(mixed $source, array $arguments): array
    {
        return self::_resolve(static function() use ($arguments): array {
            $api = Comments::$plugin->getApi();
            $api->limitPublicRead();

            return $api->comment($arguments['uid'] ?? null, $arguments['id'] ?? null);
        });
    }

    /**
     * Whoever a session or the schema's token signs in. A guest with neither a session nor a guest
     * cookie gets the slim answer, as from `comments/api/viewer`. A public schema needs no token, so it
     * never says what a moderator could see or decide. The CSRF token is only given to a browser with
     * a session, which only an origin Craft's GraphQL settings allow can read.
     */
    public static function resolveViewer(mixed $source, array $arguments): array
    {
        return self::_resolve(static function() use ($arguments): array {
            $api = Comments::$plugin->getApi();
            $api->rateLimit('viewer', $api::VIEWER_READS_PER_MINUTE);
            $owner = $api->resolveOwner($arguments['ownerId'] ?? null, $arguments['ownerUri'] ?? null, $arguments['siteId'] ?? null);
            $user = Comments::$plugin->getService()->getUser();
            $slim = !$user && !$api->hasSession();
            $request = Craft::$app->getRequest();
            $csrf = !$slim && PHP_SAPI !== 'cli' && $request instanceof WebRequest && $api->hasSession()
                ? ['name' => $request->csrfParam, 'value' => $request->getCsrfToken()]
                : null;

            return $api->viewer(
                $owner,
                $arguments['sort'] ?? null,
                $arguments['limit'] ?? null,
                $arguments['cursor'] ?? null,
                $arguments['uids'] ?? [],
                $slim,
                $csrf,
                !GqlHelper::isPublicSchema(),
                $arguments['ids'] ?? [],
            );
        });
    }


    // Private Methods
    // =========================================================================

    private static function _resolve(callable $read): array
    {
        try {
            $data = $read();
        } catch (Throwable $e) {
            $api = Comments::$plugin->getApi();
            $error = $api->errorFor($e);
            $details = $api->errorData($error)['errors'][0];

            if ($error->errorCode === 'server_error') {
                Craft::$app->getErrorHandler()->logException($e);
            }

            throw new Error($error->getMessage(), extensions: [
                'code' => $details['code'],
                'category' => $details['category'],
                'fieldErrors' => $details['fieldErrors'],
                'recoverable' => $details['recoverable'],
                'retryAfter' => $details['retryAfter'],
            ]);
        }

        $apiVersion = $data['apiVersion'];
        unset($data['apiVersion'], $data['status']);

        return [
            'apiVersion' => $apiVersion,
            'data' => $data,
        ];
    }
}
