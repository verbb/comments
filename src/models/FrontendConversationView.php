<?php
namespace verbb\comments\models;

use craft\base\Model;

/**
 * Transient Conversation shell for canonical frontend Twig partials.
 */
class FrontendConversationView extends Model
{
    // Properties
    // =========================================================================

    public int $contractVersion = 1;
    public string $instanceId;
    public string $conversationUid;
    public int $ownerId;
    public int $siteId;
    public string $sort;
    public int $rootLimit = 20;
    public string $theme;

    /** Root comments for the current page. */
    public array $roots = [];

    public ?string $rootsNext = null;
    public int $rootCount = 0;
    public int $commentCount = 0;
    public ?string $closedNotice = null;
    public bool $allowsTopSort = false;

    /** The list hasn't been rendered, and the browser fetches it when it gets near. */
    public bool $lazy = false;

    /** What's in the page's URL, so moving around without JavaScript doesn't lose it. */
    public array $pageParams = [];
    public string $rootsNextHref = '';
    public string $showCommentsHref = '';
    public string $cancelHref = '';
    public string $removeAction = '';
    public string $reportAction = '';

    public bool $canComment = false;
    public ?string $guestNotice = null;
    public bool $showSubscribe = false;
    public bool $subscribeNeedsEmail = false;
    public bool $isSubscribed = false;

    /** Viewer can moderate this Conversation from the public surface. */
    public bool $isModerator = false;
    /** For the moderator bar: what's hidden from visitors, and where to review it. */
    public int $heldCount = 0;
    public int $reportedCount = 0;
    public ?string $reviewUrl = null;

    /** Configured interaction shape: `none`, `like` or `updown`. */
    public string $interactionMode = 'none';

    public string $saveAction;
    public string $apiSaveAction = '';
    public string $subscribeAction = 'comments/comments/subscribe';
    public string $voteAction = 'comments/comments/vote';
    public string $interactionAction = '';
    public string $moderateAction = '';
    public string $flagAction = 'comments/comments/flag';
    public string $operationId = '';
}
