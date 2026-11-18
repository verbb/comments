<?php
namespace verbb\comments\models;

use craft\base\Model;

/**
 * One comment as anyone can see it, prepared once on the server for the data API and GraphQL to
 * serialise. It holds nothing about who's asking.
 */
class FrontendCommentView extends Model
{
    // Properties
    // =========================================================================

    public int $id;
    public string $uid;
    public int $depth;
    public string $state;
    public string $content;
    public string $contentFormat;
    /** A rich comment's HTML, already through Editor's sanitiser. */
    public ?string $contentHtml = null;
    public ?string $authorName = null;
    public ?string $authorAvatarUrl = null;
    /** Shown on a colour when there's no avatar image. */
    public string $authorInitials = '';
    /** One of eight colours, picked from the name, so a person keeps theirs. */
    public int $avatarTone = 0;
    /** The long date in the site's language. */
    public ?string $fullDate = null;
    public ?string $commentDate = null;
    public ?string $editedAt = null;
    public array $badges = [];
    public ?string $gifUrl = null;
    public ?int $gifWidth = null;
    public ?int $gifHeight = null;

    /** Stands in for a hidden comment so the visible replies beneath it stay connected. */
    public bool $placeholder = false;
}
