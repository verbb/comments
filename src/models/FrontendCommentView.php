<?php
namespace verbb\comments\models;

use craft\base\Model;

/**
 * Transient public Comment presentation row — not a formal read-model DTO.
 */
class FrontendCommentView extends Model
{
    // Properties
    // =========================================================================

    public int $contractVersion = 1;
    public int $id;
    public string $uid;
    public int $depth;

    /** How far the comment is indented, which stops growing long before its real depth does. */
    public int $visualDepth = 0;
    public string $state;
    public string $content;
    public string $contentFormat;
    /** Rich HTML from server render — safe Markup serialized for Twig. */
    public ?string $contentHtml = null;
    public ?string $authorName = null;
    public ?string $authorAvatarUrl = null;
    /** Shown on a colour when there's no avatar image. */
    public string $authorInitials = '';
    /** One of eight colours, picked from the name, so a person keeps theirs. */
    public int $avatarTone = 0;
    /** Short public field values shown after the time, such as "I race · Rotorua". */
    public array $metaLine = [];
    /** Long date for when a reader taps the relative time. */
    public ?string $fullDate = null;
    /** For moderators: why a comment is held or that it was reported. */
    public ?string $reviewLabel = null;
    public bool $reported = false;
    public ?string $commentDate = null;
    public ?string $timeAgo = null;
    public ?string $editedAt = null;

    public array $badges = [];
    public ?string $permalink = null;
    public ?string $gifUrl = null;

    /** Where actions go without JavaScript. */
    public array $hrefs = [];

    /** A reply or edit form the server has opened here, because JavaScript isn't running. */
    public ?FrontendComposerView $contextualComposer = null;

    /** Stands in for a hidden comment so the visible replies beneath it stay connected. */
    public bool $placeholder = false;

    /** Bounded reply preview. */
    public array $replies = [];

    public int $replyCount = 0;
    public bool $hasMoreReplies = false;

    /** Viewer-visible actions: reply, edit, remove, interact, report */
    public array $actions = [];

    /**
     * Interaction presentation state, or null when the configured mode is `none`.
     */
    public ?array $interaction = null;

    /**
     * Moderation actions the viewer may perform inline (approve, remove, restore, spam, not-spam).
     * Empty for non-moderators.
     */
    public array $moderationActions = [];

    /** Whether this row is shown to the viewer only because they can moderate it. */
    public bool $moderatorOnly = false;
}
