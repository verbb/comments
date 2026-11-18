<?php
namespace verbb\comments\models;

use verbb\comments\enums\RequestProfile;

use craft\base\Model;
use craft\elements\User;

/**
 * How the data API request being handled identifies the person making it, once its profile and origin
 * have been checked. Requests that use a header token (a member token or a guest session token) are
 * identified by it alone: Craft's session and Comments' guest cookie are never read for them.
 */
class RequestSignIn extends Model
{
    // Properties
    // =========================================================================

    public RequestProfile $profile = RequestProfile::SameOriginBrowser;

    // The request's `Origin`, in canonical form, when it sent one.
    public ?string $origin = null;

    // The **Allowed Origins** entry the request came from, for the cross-origin profiles.
    public ?AllowedOrigin $allowedOrigin = null;

    // The member a member token signed in, for `cross-origin-member`.
    public ?User $user = null;
    public ?int $memberTokenId = null;

    // The secret behind a `cross-origin-public` guest's session token, which their guest identity is
    // stored against (hashed), as a guest cookie's is in tier 1.
    public ?string $guestSecret = null;

    // Set when the response should carry a fresh guest session token.
    public bool $sendGuestSession = false;


    // Public Methods
    // =========================================================================

    public function usesHeaderToken(): bool
    {
        return $this->profile->usesHeaderToken();
    }

    /**
     * Whether this person may moderate through this request. Only a member token is limited, by its
     * origin's `moderation` setting; Craft's session carries Craft's own permissions.
     */
    public function allowsModeration(): bool
    {
        return $this->profile !== RequestProfile::CrossOriginMember || ($this->allowedOrigin?->moderation ?? false);
    }
}
