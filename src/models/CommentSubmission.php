<?php
namespace verbb\comments\models;

use verbb\comments\enums\ContentFormat;

use craft\base\Model;

/**
 * Product input for creating a Comment (not a command-bus Command).
 */
class CommentSubmission extends Model
{
    // Properties
    // =========================================================================

    public ?int $conversationId = null;

    public ?int $ownerId = null;

    public ?int $siteId = null;

    public ?int $parentId = null;

    public string $content = '';

    public ContentFormat $contentFormat = ContentFormat::Plain;

    public ?int $identityId = null;

    public ?int $userId = null;

    public ?string $guestToken = null;

    /** A guest asked to be emailed about replies, which starts with confirming their address. */
    public bool $notifyReplies = false;

    public ?string $name = null;

    public ?string $email = null;

    public ?string $url = null;

    /**
     * The GIF from GIPHY to go with the text, already checked by `Giphy::fromInput()`.
     *
     * @var array{url: string, width: ?int, height: ?int}|null
     */
    public ?array $gif = null;

    public ?\verbb\protect\models\ProtectionEvidence $protectionEvidence = null;

    public ?\verbb\protect\models\RequestSignals $protectionRequest = null;

    public bool $headless = true;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['content'], 'string'],
            [['conversationId', 'ownerId', 'siteId', 'parentId', 'identityId', 'userId'], 'integer'],
            [['name', 'email', 'url', 'guestToken'], 'string'],
        ];
    }

    public function hasOwnerTarget(): bool
    {
        return $this->ownerId !== null && $this->siteId !== null;
    }
}
