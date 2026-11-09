<?php
namespace verbb\comments\services;

use verbb\comments\enums\IdentityType;
use verbb\comments\records\Identity as IdentityRecord;

use Craft;
use craft\base\Component;
use craft\elements\User;

use yii\db\Exception as DbException;
use yii\db\IntegrityException;
use yii\web\Cookie;

/**
 * User and guest Identity resolution. Guest continuity uses an opaque cookie secret; DB stores hash.
 */
class Identities extends Component
{
    // Constants
    // =========================================================================

    public const GUEST_COOKIE_NAME = 'comments_guest_token';


    // Properties
    // =========================================================================

    private array $byIdCache = [];

    private array $byUserCache = [];

    /** Given to a guest during this request, whose browser can't have sent it back yet. */
    private ?string $issuedGuestToken = null;


    // Public Methods
    // =========================================================================

    public function getById(int $id): ?IdentityRecord
    {
        if (array_key_exists($id, $this->byIdCache)) {
            return $this->byIdCache[$id];
        }

        $identity = IdentityRecord::findOne($id);
        $this->byIdCache[$id] = $identity;

        return $identity;
    }

    public function findForUser(int $userId): ?IdentityRecord
    {
        if (array_key_exists($userId, $this->byUserCache)) {
            return $this->byUserCache[$userId];
        }

        $identity = IdentityRecord::findOne([
            'type' => IdentityType::User->value,
            'userId' => $userId,
        ]);
        $this->byUserCache[$userId] = $identity;

        if ($identity) {
            $this->byIdCache[(int)$identity->id] = $identity;
        }

        return $identity;
    }

    public function getOrCreateForUser(User|int $user): IdentityRecord
    {
        if (!$user instanceof User) {
            $resolved = Craft::$app->getUsers()->getUserById($user);

            if (!$resolved) {
                throw new \InvalidArgumentException("User {$user} was not found.");
            }

            $user = $resolved;
        }

        $existing = $this->findForUser((int)$user->id);

        if ($existing) {
            return $existing;
        }

        $identity = new IdentityRecord([
            'type' => IdentityType::User->value,
            'userId' => (int)$user->id,
            'name' => $user->fullName ?: $user->username,
            'email' => $user->email ?: null,
        ]);

        try {
            $identity->save(false);
        } catch (IntegrityException) {
            $identity = $this->findForUser((int)$user->id);

            if (!$identity) {
                throw new DbException('Failed to materialize user Identity after unique-index race.');
            }
        }

        $this->byUserCache[(int)$user->id] = $identity;
        $this->byIdCache[(int)$identity->id] = $identity;

        return $identity;
    }

    /**
     * Resolve guest Identity from an opaque continuity token (cookie/session secret).
     * Creates a new Identity + token when none is supplied or the hash is unknown.
     */
    public function resolveOrCreateGuest(
        ?string $guestToken = null,
        ?string $name = null,
        ?string $email = null,
    ): array {
        $guestToken = $guestToken !== null && $guestToken !== '' ? $guestToken : null;

        if ($guestToken !== null) {
            $hash = $this->hashGuestToken($guestToken);
            $identity = IdentityRecord::findOne([
                'type' => IdentityType::Guest->value,
                'guestTokenHash' => $hash,
            ]);

            if ($identity) {
                $dirty = false;

                if ($name !== null && $name !== '' && $identity->name !== $name) {
                    $identity->name = $name;
                    $dirty = true;
                }

                // What was confirmed or opted out of belonged to the old address.
                if ($email !== null && $email !== '' && strcasecmp($email, (string)$identity->email) !== 0) {
                    $identity->email = $email;
                    $identity->verifiedAt = null;
                    $identity->emailOptOutAt = null;
                    $dirty = true;
                }

                if ($dirty) {
                    $identity->save(false);
                }

                $this->byIdCache[(int)$identity->id] = $identity;

                return [
                    'identity' => $identity,
                    'guestToken' => $guestToken,
                    'isNewToken' => false,
                ];
            }
        }

        $guestToken = $this->generateGuestToken();
        $identity = new IdentityRecord([
            'type' => IdentityType::Guest->value,
            'guestTokenHash' => $this->hashGuestToken($guestToken),
            'name' => $name,
            'email' => $email,
        ]);
        $identity->save(false);
        $this->byIdCache[(int)$identity->id] = $identity;

        return [
            'identity' => $identity,
            'guestToken' => $guestToken,
            'isNewToken' => true,
        ];
    }

    /**
     * The guest behind this request's cookie, without creating one.
     */
    public function findGuestForRequest(): ?IdentityRecord
    {
        $token = $this->guestTokenFromRequest();

        if ($token === null) {
            return null;
        }

        return IdentityRecord::findOne([
            'type' => IdentityType::Guest->value,
            'guestTokenHash' => $this->hashGuestToken($token),
        ]);
    }

    public function getByUid(string $uid): ?IdentityRecord
    {
        return IdentityRecord::findOne(['uid' => $uid]);
    }

    /**
     * Whether someone can be sent participant email. Anyone can type an address into a guest form, so
     * a guest's has to be confirmed first.
     */
    public function acceptsEmail(?IdentityRecord $identity): bool
    {
        if (!$identity || $identity->emailOptOutAt) {
            return false;
        }

        return $identity->userId || $identity->verifiedAt;
    }

    /**
     * Historical guest Identity for v3 migration — no continuity token.
     */
    public function createHistoricalGuest(?string $name, ?string $email): IdentityRecord
    {
        $identity = new IdentityRecord([
            'type' => IdentityType::Guest->value,
            'guestTokenHash' => null,
            'name' => $name !== null && $name !== '' ? $name : null,
            'email' => $email !== null && $email !== '' ? $email : null,
        ]);
        $identity->save(false);
        $this->byIdCache[(int)$identity->id] = $identity;

        return $identity;
    }

    public function hashGuestToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateGuestToken(): string
    {
        return Craft::$app->getSecurity()->generateRandomString(64);
    }

    public function resolveForCurrentRequest(?string $name = null, ?string $email = null): IdentityRecord
    {
        $user = Craft::$app->getUser()->getIdentity();

        if ($user) {
            return $this->getOrCreateForUser($user);
        }

        $resolved = $this->resolveOrCreateGuest(
            $this->guestTokenFromRequest(),
            $name,
            $email,
        );

        if ($resolved['isNewToken'] && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->issueGuestCookie($resolved['guestToken']);
        }

        return $resolved['identity'];
    }

    /**
     * Read guest continuity token from the request cookie when present.
     */
    public function guestTokenFromRequest(): ?string
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return null;
        }

        if ($this->issuedGuestToken !== null) {
            return $this->issuedGuestToken;
        }

        $token = $request->getCookies()->getValue(self::GUEST_COOKIE_NAME);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Persist guest continuity cookie (HttpOnly). Clearing the cookie loses ownership continuity.
     */
    public function issueGuestCookie(string $guestToken): void
    {
        $this->issuedGuestToken = $guestToken;

        $response = Craft::$app->getResponse();
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        $cookie = Craft::createObject(Craft::cookieConfig([
            'class' => Cookie::class,
            'name' => self::GUEST_COOKIE_NAME,
            'value' => $guestToken,
            'expire' => time() + (86400 * 400),
            'path' => '/',
            'httpOnly' => true,
            'sameSite' => $generalConfig->sameSiteCookieValue ?? Cookie::SAME_SITE_LAX,
        ]));

        $response->getCookies()->add($cookie);
    }

    public function getByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $missing = [];
        $result = [];

        foreach ($ids as $id) {
            if (array_key_exists($id, $this->byIdCache) && $this->byIdCache[$id]) {
                $result[$id] = $this->byIdCache[$id];
            } else {
                $missing[] = $id;
            }
        }

        if ($missing !== []) {
            /** @var IdentityRecord[] $rows */
            $rows = IdentityRecord::find()->where(['id' => $missing])->all();

            foreach ($rows as $row) {
                $this->byIdCache[(int)$row->id] = $row;
                $result[(int)$row->id] = $row;
            }
        }

        return $result;
    }

    /** Clear request-local caches (tests / long-running workers). */
    public function clearCaches(): void
    {
        $this->byIdCache = [];
        $this->byUserCache = [];
        $this->issuedGuestToken = null;
    }
}
