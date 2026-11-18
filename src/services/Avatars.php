<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\migrations\m400000_000012_gifs_avatars;
use verbb\comments\queue\jobs\FetchAvatars;
use verbb\comments\records\Identity as IdentityRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use DateInterval;
use DateTime;
use finfo;
use Throwable;

/**
 * Gravatar images for commenters without a photo, kept and served by the site (SD-53).
 *
 * Only an address known to be the commenter's is looked up: a signed-in user's, or a guest's once
 * they've confirmed it. A queue job fetches the image from Gravatar, never a page render, and keeps a
 * copy against the commenter's identity. The thread is given the site's own URL for it, so visitors'
 * browsers never contact Gravatar and the address's hash never reaches the page, where it could be
 * checked against a guessed address or used to follow someone between sites.
 */
class Avatars extends Component
{
    // Constants
    // =========================================================================

    public const TABLE = m400000_000012_gifs_avatars::AVATARS_TABLE;

    // Twice the 64 pixels threads draw avatars at, for high-density screens.
    public const SIZE = 128;

    // How long a copy, or knowing someone has no Gravatar, lasts before it's checked again.
    public const REFRESH_AFTER = 'P7D';

    // Gravatar's images at this size are a few kilobytes. Anything larger isn't kept.
    public const IMAGE_MAX_BYTES = 60000;

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const GRAVATAR_URL = 'https://gravatar.com/avatar/';

    // How long a render waits before asking for the same commenter's image again, while a job is queued.
    private const QUEUED_FOR = 3600;


    // Properties
    // =========================================================================

    /** Extra Guzzle options for reaching Gravatar, such as a test's mock handler. */
    public array $httpOptions = [];

    /** Each identity's copy, or false for none, for this request. */
    private array $_rows = [];


    // Public Methods
    // =========================================================================

    public function isEnabled(): bool
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        return $settings->showAvatar && $settings->enableGravatar;
    }

    /**
     * Loads the copies for these comments' authors at once, and queues a check for any that have none or
     * are due one. A render calls this before asking for each comment's avatar.
     *
     * @param Comment[] $comments
     */
    public function prime(array $comments): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $identityIds = array_values(array_unique(array_filter(array_map(
            static fn(Comment $comment): int => (int)$comment->identityId,
            $comments,
        ))));

        $this->_load($identityIds);
    }

    /**
     * The site's URL for a comment's author's Gravatar, or null when there's no copy (yet).
     */
    public function urlForComment(Comment $comment): ?string
    {
        if (!$this->isEnabled() || !$comment->identityId) {
            return null;
        }

        return $this->urlForIdentity((int)$comment->identityId);
    }

    /**
     * The site's URL for a user's Gravatar, for the composer beside their own comment. A user who has
     * never commented has no identity, and so no Gravatar until they do.
     */
    public function urlForUser(User $user): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $identity = CommentsPlugin::$plugin->getIdentities()->findForUser((int)$user->id);

        return $identity ? $this->urlForIdentity((int)$identity->id) : null;
    }

    public function urlForIdentity(int $identityId): ?string
    {
        $this->_load([$identityId]);
        $row = $this->_rows[$identityId] ?? false;

        if (!$row || !$row['found']) {
            return null;
        }

        // The date it was last fetched changes the URL, so a browser that cached the old image asks again.
        return UrlHelper::siteUrl(Craft::$app->getConfig()->getGeneral()->actionTrigger . '/comments/avatars/image', [
            'id' => $row['uid'],
            'v' => strtotime((string)$row['dateUpdated']) ?: null,
        ]);
    }

    /**
     * The copy behind an image URL, for serving it, or null.
     *
     * @return array{mimeType: string, image: string}|null
     */
    public function imageByUid(string $uid): ?array
    {
        if (!$this->isEnabled() || !StringHelper::isUUID($uid)) {
            return null;
        }

        $row = (new Query())
            ->select(['mimeType', 'image'])
            ->from(self::TABLE)
            ->where(['uid' => $uid, 'found' => true])
            ->one();

        if (!$row || !in_array($row['mimeType'], self::MIME_TYPES, true) || !is_string($row['image'])) {
            return null;
        }

        $image = base64_decode($row['image'], true);

        return $image === false || $image === '' ? null : ['mimeType' => $row['mimeType'], 'image' => $image];
    }

    /**
     * Checks each identity's Gravatar against its current address and keeps a copy, or forgets one it
     * can no longer use. Run by `FetchAvatars`. Returns whether anything a thread shows changed.
     *
     * @param int[] $identityIds
     */
    public function refresh(array $identityIds): bool
    {
        $changed = false;

        foreach (array_unique(array_map('intval', $identityIds)) as $identityId) {
            try {
                $changed = $this->_refreshIdentity($identityId) || $changed;
            } catch (Throwable $e) {
                CommentsPlugin::error('Unable to check a commenter’s Gravatar: ' . $e->getMessage());
            } finally {
                Craft::$app->getCache()->delete($this->_queuedKey($identityId));
            }
        }

        if ($changed) {
            CommentsPlugin::$plugin->getApi()->invalidateCaches();
        }

        return $changed;
    }

    /**
     * Forgets the copies for a user's identity, such as when the user is deleted.
     */
    public function deleteForUser(int $userId): void
    {
        $identity = CommentsPlugin::$plugin->getIdentities()->findForUser($userId);

        if ($identity && Craft::$app->getDb()->tableExists(self::TABLE)) {
            Db::delete(self::TABLE, ['identityId' => (int)$identity->id]);
            unset($this->_rows[(int)$identity->id]);
        }
    }

    /**
     * The address that may be looked up for an identity: a user's own while they're active, or a
     * guest's once they've confirmed it. Null for anyone else.
     */
    public function verifiedEmail(IdentityRecord $identity): ?string
    {
        if ($identity->userId) {
            $user = User::find()->id((int)$identity->userId)->status(null)->one();

            // Pending users haven't confirmed their address, and suspended or locked ones keep their photo only.
            return $user && $user->getStatus() === User::STATUS_ACTIVE && $user->email ? $user->email : null;
        }

        return $identity->verifiedAt && $identity->email ? (string)$identity->email : null;
    }


    // Private Methods
    // =========================================================================

    private function _load(array $identityIds): void
    {
        $missing = array_values(array_filter($identityIds, fn(int $id): bool => $id > 0 && !array_key_exists($id, $this->_rows)));

        if ($missing === [] || !Craft::$app->getDb()->tableExists(self::TABLE)) {
            return;
        }

        $rows = (new Query())
            ->select(['identityId', 'uid', 'found', 'checkedAt', 'dateUpdated'])
            ->from(self::TABLE)
            ->where(['identityId' => $missing])
            ->indexBy('identityId')
            ->all();

        $due = [];
        $staleBefore = (new DateTime())->sub(new DateInterval(self::REFRESH_AFTER));

        foreach ($missing as $identityId) {
            $row = $rows[$identityId] ?? null;
            $this->_rows[$identityId] = $row ? ['uid' => $row['uid'], 'found' => (bool)$row['found'], 'dateUpdated' => $row['dateUpdated']] : false;

            if (!$row || new DateTime((string)$row['checkedAt']) < $staleBefore) {
                $due[] = $identityId;
            }
        }

        $this->_queue($due);
    }

    /**
     * Queues one job for the commenters due a check, skipping any already queued, so a busy thread doesn't
     * queue the same lookups on every read.
     */
    private function _queue(array $identityIds): void
    {
        $cache = Craft::$app->getCache();
        $queue = [];

        foreach ($identityIds as $identityId) {
            if ($cache->add($this->_queuedKey($identityId), true, self::QUEUED_FOR)) {
                $queue[] = $identityId;
            }
        }

        if ($queue !== []) {
            Queue::push(new FetchAvatars(['identityIds' => $queue]));
        }
    }

    private function _queuedKey(int $identityId): string
    {
        return 'comments:avatars:queued:' . $identityId;
    }

    private function _refreshIdentity(int $identityId): bool
    {
        $identity = IdentityRecord::findOne($identityId);
        $existing = (new Query())
            ->select(['id', 'emailHash', 'found'])
            ->from(self::TABLE)
            ->where(['identityId' => $identityId])
            ->one();

        $email = $identity ? $this->verifiedEmail($identity) : null;

        // An address that's no longer theirs, or never was, is never looked up, and any copy is forgotten.
        if ($email === null) {
            if ($existing) {
                Db::delete(self::TABLE, ['id' => $existing['id']]);
            }

            return (bool)($existing['found'] ?? false);
        }

        $hash = hash('sha256', strtolower(trim($email)));
        $fetched = $this->_fetch($hash);

        // Gravatar couldn't be reached. Keep what there is, and try again when it's next due.
        if ($fetched === false) {
            if ($existing && $existing['emailHash'] !== $hash) {
                Db::delete(self::TABLE, ['id' => $existing['id']]);

                return (bool)$existing['found'];
            }

            return false;
        }

        $values = [
            'emailHash' => $hash,
            'found' => $fetched !== null,
            'mimeType' => $fetched['mimeType'] ?? null,
            'image' => isset($fetched['image']) ? base64_encode($fetched['image']) : null,
            'checkedAt' => Db::prepareDateForDb(new DateTime()),
        ];

        if ($existing) {
            Db::update(self::TABLE, $values, ['id' => $existing['id']]);
        } else {
            Db::insert(self::TABLE, ['identityId' => $identityId] + $values);
        }

        unset($this->_rows[$identityId]);

        return $fetched !== null || (bool)($existing['found'] ?? false);
    }

    /**
     * Gravatar's G-rated image for a hash, null when there's none, or false when Gravatar couldn't be
     * reached or sent something that isn't a small image.
     *
     * @return array{mimeType: string, image: string}|null|false
     */
    private function _fetch(string $hash): array|null|false
    {
        try {
            $response = Craft::createGuzzleClient([
                'timeout' => 5,
                'connect_timeout' => 3,
                'http_errors' => false,
                'allow_redirects' => false,
            ] + $this->httpOptions)->get(self::GRAVATAR_URL . $hash, [
                'query' => ['s' => self::SIZE, 'd' => '404', 'r' => 'g'],
            ]);
        } catch (Throwable $e) {
            CommentsPlugin::error('Unable to reach Gravatar: ' . $e->getMessage());

            return false;
        }

        $status = $response->getStatusCode();

        if ($status === 404) {
            return null;
        }

        if ($status !== 200) {
            return false;
        }

        $image = (string)$response->getBody();

        if ($image === '' || strlen($image) > self::IMAGE_MAX_BYTES) {
            return false;
        }

        // Judged by the bytes, not the header, so only an image is ever served from the site.
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($image);

        if (!in_array($mimeType, self::MIME_TYPES, true)) {
            return false;
        }

        return ['mimeType' => $mimeType, 'image' => $image];
    }
}
