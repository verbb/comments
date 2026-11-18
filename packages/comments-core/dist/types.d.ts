/**
 * The data API's JSON, as `comments/api/*` and the GraphQL `comments*` queries send it.
 *
 * `scripts/generate-schema.mjs` turns every type exported here into `schema/data.json`, which
 * the server's tests validate each response against. Keep this file to plain JSON shapes (no
 * generics or functions), and describe a field here rather than in the schema, which is generated.
 */
/** The version of the JSON format, sent as `apiVersion` and the `X-Comments-Api-Version` header. */
export type ApiVersion = 1;
export type SortOrder = 'newest' | 'oldest' | 'top';
/** `like` counts upvotes only, `updown` counts both directions, and `none` turns voting off. */
export type InteractionMode = 'none' | 'like' | 'updown';
export type ConversationStatus = 'open' | 'closed';
/**
 * `approved` and `placeholder` are the only states in public data. `pending`, `held` and
 * `reported` arrive only through `viewer`, and `removed` and `spam` only in a `moderate` response,
 * after which the comment leaves the thread. A comment waiting for a moderator is `held` to a
 * moderator and `pending` to its author, so its author learns nothing about why it's waiting.
 */
export type CommentState = 'approved' | 'placeholder' | 'pending' | 'held' | 'reported' | 'removed' | 'spam';
export type ContentFormat = 'plain' | 'rich';
export type VoteValue = 'upvote' | 'downvote';
/** The input a custom field uses on the site. `custom` needs a field override in JavaScript. */
export type FieldInputType = 'text' | 'textarea' | 'number' | 'email' | 'url' | 'date' | 'select' | 'radio' | 'checkboxes' | 'toggle' | 'file' | 'custom';
/** Where a shown custom field appears: after the date, or in a labelled list under the text. */
export type FieldPlacement = 'inline' | 'details';
export type ModerationAction = 'approve' | 'spam' | 'not-spam' | 'remove' | 'keep' | 'restore' | 'hold';
export type FollowOutcome = 'followed' | 'unfollowed' | 'confirming';
export type ErrorData = {
    /** A broad group, such as `validation` or `protection`, for handling a code a client doesn't know. */
    category: string;
    /** A machine-readable code from `COMMENTS_ERROR_CODES`. */
    code: string;
    /** A translated sentence for the visitor. */
    message: string;
    /** Messages keyed by field: `content`, `guest.name`, `guest.email` or `fields.<handle>`. */
    fieldErrors: Record<string, string[]>;
    /** Whether trying again (after a refresh or a wait) can succeed. */
    recoverable: boolean;
    /** Seconds to wait before trying again, for `rate_limited`. */
    retryAfter: number | null;
};
export type ErrorResponse = {
    apiVersion: ApiVersion;
    status: 'error';
    errors: ErrorData[];
};
export type GuestPolicy = {
    allowed: boolean;
    nameAndEmail: 'required' | 'optional' | 'hidden';
    canVote: boolean;
    canReport: boolean;
};
export type ReportReason = {
    value: string;
    label: string;
    description: string | null;
};
export type ConversationData = {
    /** Null until the conversation's first comment. */
    uid: string | null;
    ownerId: number;
    siteId: number;
    status: ConversationStatus;
    /** The notice shown on a closed conversation. */
    closedNotice: string | null;
    /** Approved comments in the whole conversation, replies included. */
    commentCount: number;
    /** Approved top-level comments. */
    rootCount: number;
    sort: SortOrder;
    sorts: SortOrder[];
    interactionMode: InteractionMode;
    /** Null when there's no limit. */
    maxLength: number | null;
    guestPolicy: GuestPolicy;
    /** Whether following by email is offered here. */
    followAvailable: boolean;
    reportReasons: ReportReason[];
};
export type RichEditorData = {
    /** Editor's context handle, such as `comments.reply`. */
    context: string;
    /** Editor's own module, loaded only when someone starts writing. */
    module: string;
    css: string;
};
/** The editor's public definition, the same for everyone. */
export type EditorData = {
    authoring: ContentFormat;
    maxLength: number | null;
    richEditor: RichEditorData | null;
};
/** Editor's options for the person asking, such as their mention and upload endpoints. */
export type EditorOptionsData = {
    context: string;
    options: Record<string, unknown>;
};
export type BadgeData = {
    type: string;
    label: string;
};
/** The public author: no user ID, email or identity ID. */
export type AuthorData = {
    name: string;
    initials: string;
    avatarUrl: string | null;
    avatarTone: number;
    isGuest: boolean;
    badges: BadgeData[];
    /**
     * The signed-in author's net votes across their approved comments, while the site shows author
     * scores. Null for a guest, or when scores are off.
     */
    score: number | null;
};
export type ContentData = {
    format: ContentFormat;
    /** Rich comments only. Sanitised on the server by Editor, and the only raw HTML a renderer inserts. */
    html: string | null;
    text: string;
    /** A GIF shown with the comment, chosen through GIPHY or from Comments 3. */
    gifUrl: string | null;
    /** The GIF's size, when it's known, so the page can make room for it before it loads. */
    gifWidth: number | null;
    gifHeight: number | null;
};
export type DisplayData = {
    /** The relative time in the site's language. */
    timeAgo: string;
    fullDate: string;
};
export type InteractionData = {
    upvotes: number;
    downvotes: number;
};
/** One shown custom field value. Collected-only values never appear in public data. */
export type FieldValueData = {
    handle: string;
    label: string;
    type: FieldInputType;
    placement: FieldPlacement;
    /**
     * The raw value: a string, number, `YYYY-MM-DD` (or an ISO 8601 date and time), an option's
     * value or a list of them, true or false, or for files a list of `FileValueData`. For `url`, the
     * address only when it's `http` or `https`, and null otherwise, with the address in `text`.
     */
    value: unknown;
    /** The formatted value, as text. */
    text: string;
};
/** A file in a shown Assets field. */
export type FileValueData = {
    /** Null for a volume without public URLs. */
    url: string | null;
    filename: string;
    kind: string;
    width?: number;
    height?: number;
};
export type RepliesData = {
    /** Every approved reply, loaded or not. */
    count: number;
    /** A preview, three by default, nested up to four levels in one response. */
    items: CommentData[];
    /** The cursor for the rest, through `replies`. */
    next: string | null;
};
/** A moderator's view of a held or reported comment. Never in public data. */
export type ModerationData = {
    reviewLabel: string | null;
    openReportCount: number;
    actions: ModerationAction[];
};
/** What the person asking may do with one comment. Never in public data. */
export type CommentViewerData = {
    canReply: boolean;
    canEdit: boolean;
    canDelete: boolean;
    canVote: boolean;
    canDownvote: boolean;
    canReport: boolean;
    vote: VoteValue | null;
    /** Whether this person has an open report on the comment. */
    reported: boolean;
    isAuthor: boolean;
    /** For moderators only. */
    moderation: ModerationData | null;
    /** Private data a site adds with `Api::EVENT_DEFINE_VIEWER_DATA`. */
    custom: Record<string, unknown>;
};
/** Why a comment is folded away. */
export type FoldReason = 'reports' | 'downvotes';
export type CommentData = {
    uid: string;
    parentUid: string | null;
    depth: number;
    state: CommentState;
    /** Null on a placeholder. */
    content: ContentData | null;
    /** Null on a placeholder. */
    author: AuthorData | null;
    /** Null on a placeholder. */
    createdAt: string | null;
    editedAt: string | null;
    /** Null on a placeholder. */
    display: DisplayData | null;
    permalink: string | null;
    fields: FieldValueData[];
    /** Null on a placeholder, or when voting is off. */
    interaction: InteractionData | null;
    /**
     * Why the thread folds the comment away, as Comments 3 hid it: `reports` once it has the site's
     * number of open reports, or `downvotes` once it has its number of downvotes. It's still public, and
     * its moderation state is unchanged; a reader can open it. Null when it isn't folded.
     */
    folded: FoldReason | null;
    /** Public data a site adds with `Api::EVENT_DEFINE_COMMENT_DATA`. */
    custom: Record<string, unknown>;
    replies: RepliesData;
    /** Present only in private data: `viewer`'s `extraComments` and write responses. */
    viewer?: CommentViewerData;
    /** Present only for moderators, in private data. */
    moderation?: ModerationData;
};
export type PageData = {
    next: string | null;
    limit: number;
};
/** The `thread` read: public data, the same for every visitor. */
export type ThreadResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    /**
     * A stamp of the public data this was built from. When the `viewer` read's `threadVersion`
     * differs, a cache served an older thread, such as just after the visitor's own comment.
     */
    threadVersion: string;
    conversation: ConversationData;
    editor: EditorData;
    comments: CommentData[];
    page: PageData;
    labels: Record<string, string>;
    locale: string;
};
/** The `replies` read: a page of approved replies to one comment, with `next` for the page after. */
export type RepliesResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    parentUid: string;
    replies: RepliesData;
};
/**
 * The `comment` read, for a link to a comment that isn't loaded: its root comment, with the
 * replies leading down to the target first among their siblings.
 */
export type CommentResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    targetUid: string;
    /** The element the comment's thread belongs to. */
    ownerId?: number;
    siteId?: number;
    comment: CommentData;
};
export type FollowData = {
    available: boolean;
    following: boolean;
    needsEmail: boolean;
};
export type ViewerData = {
    isGuest: boolean;
    /** False in the slim answer for a browser Comments doesn't recognise. */
    hasSession: boolean;
    name: string | null;
    initials: string;
    avatarUrl: string | null;
    avatarTone: number;
    /** The badges this person's comments carry here, in the same shape as an author's. */
    badges: BadgeData[];
    canComment: boolean;
    canVote: boolean;
    canReport: boolean;
    isModerator: boolean;
    blocked: boolean;
    loginUrl: string | null;
    follow: FollowData;
};
export type GuestEditorData = {
    show: boolean;
    required: boolean;
    name: string | null;
    email: string | null;
    offerReplyEmails: boolean;
};
export type FieldOptionData = {
    label: string;
    value: string;
};
/** A custom field this person may fill in, after conditions and the replies rule. */
export type FormFieldData = {
    handle: string;
    /** The field layout element's UID. */
    uid: string;
    label: string;
    instructions: string | null;
    required: boolean;
    type: FieldInputType;
    multiple: boolean;
    options: FieldOptionData[];
    attributes: Record<string, string | number | boolean>;
    /** Whether the value is shown publicly on the comment, which matters to the person filling it in. */
    public: boolean;
    /** Where a shown field's value appears, or null for a field that's only collected. */
    placement: FieldPlacement | null;
    /**
     * Where the field goes in the form: `above` the text, for a field placed before the Comment
     * element in the Comment field layout, such as a rating, or `below` it. Missing means below.
     */
    formPosition?: 'above' | 'below';
    /** The width set in the Comment field layout, as a percentage of the form: 25, 50, 75 or 100. Missing means 100. */
    width?: 25 | 50 | 75 | 100;
    /** Assets fields only: the extensions taken, with their dots, such as `.jpg`. */
    accept?: string[];
    /** Assets fields only: null for no limit. */
    maxFiles?: number | null;
    /** Assets fields only: the largest file, in bytes. */
    maxSize?: number | null;
    /** Anything else the field type's description gives, for the site's own field override. */
    meta?: Record<string, unknown>;
};
export type FormFieldsData = {
    comment: FormFieldData[];
    reply: FormFieldData[];
};
/**
 * One challenge in a preparation. This and the two types after it are Protect's own
 * (`ChallengeManifest`, `BrowserManifest` and `Evidence` in `@verbb/protect`), written out so
 * core's types need neither Protect's nor the DOM's; a test keeps them the same.
 */
export type ProtectionChallengeData = {
    provider: string;
    siteKey: string;
    action: string;
};
export type ProtectionManifestData = {
    version: 1;
    instanceId: string;
    preparation: string;
    /** Unix seconds, on the server's clock. */
    issuedAt: number;
    expiresAt: number;
    notBefore?: number;
    honeypot: string | null;
    javascript: boolean;
    challenges: ProtectionChallengeData[];
};
/** What a form sends back for a preparation, from Protect's `prepare()`. */
export type ProtectionEvidenceData = {
    version: 1;
    preparation: string;
    honeypot: Record<string, string>;
    javascript: string;
    challenges: Record<string, string>;
};
/** A Protect preparation for one form, single-use. */
export type ProtectionData = {
    version: 1;
    manifest: ProtectionManifestData;
    /** Unix seconds, on the server's clock. */
    expiresAt: number;
};
/** The editor's private half, for the person asking. */
export type ViewerEditorData = {
    draftKey: string;
    guest: GuestEditorData;
    fields: FormFieldsData;
    /** Null for a guest who hasn't started writing, or when the site doesn't use protection. */
    protection: ProtectionData | null;
    richEditor: EditorOptionsData | null;
    /** GIF search through GIPHY, or null when it's off or this person can't comment. */
    gifs: GifsData | null;
};
/**
 * What the browser needs to search GIPHY itself, as GIPHY's terms require. The key is the site's
 * GIPHY key, which GIPHY treats as public for a website; `rating` and `limit` go to GIPHY with each
 * search.
 */
export type GifsData = {
    apiKey: string;
    rating: 'g' | 'pg' | 'pg-13' | 'r';
    limit: number;
    /** The site's language, for GIPHY's `lang`. */
    language: string;
};
/** A comment's GIF: its address as GIPHY gave it, and its size when known. */
export type GifData = {
    url: string;
    width: number | null;
    height: number | null;
};
export type ModerationSummaryData = {
    heldCount: number;
    reportedCount: number;
    reviewUrl: string | null;
};
/** Craft's CSRF parameter, sent back on writes as the `X-CSRF-Token` header. */
export type CsrfData = {
    name: string;
    value: string;
};
/** The `viewer` read: everything personal for the window a `thread` or `replies` read loaded. */
export type ViewerResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    /** The current public data's stamp, to compare with the drawn thread's `threadVersion`. */
    threadVersion: string;
    viewer: ViewerData;
    editor: ViewerEditorData;
    /** Permissions keyed by comment UID. */
    comments: Record<string, CommentViewerData>;
    /** Comments the public data leaves out but this person may see: their own pending ones, and held or reported ones for moderators. */
    extraComments: CommentData[];
    moderation: ModerationSummaryData | null;
    /** Null in the slim answer for a guest with no session. */
    csrf: CsrfData | null;
    /** Private data a site adds with `Api::EVENT_DEFINE_VIEWER_DATA`. */
    custom: Record<string, unknown>;
};
/**
 * The `session` read: who's signed in and the CSRF token, starting a guest's session. A tier 3
 * guest's token arrives in the `X-Comments-Session` header. `csrf` is null for tier 3, whose
 * requests carry no cookies.
 */
export type SessionResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    viewer: ViewerData;
    csrf: CsrfData | null;
};
/**
 * The `protection` read, for someone about to write: a fresh preparation, or null when the site
 * doesn't use protection here, and the CSRF token, since this is where a guest's session starts.
 */
export type ProtectionResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    protection: ProtectionData | null;
    csrf: CsrfData;
};
/** The `structured-data` read: the schema.org JSON-LD object for the comments. */
export type StructuredDataResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    structuredData: Record<string, unknown>;
};
export type PlainContentInput = {
    format: 'plain';
    /** Can be empty when the comment is only a GIF. */
    text: string;
    gif?: GifInput | null;
};
export type RichContentInput = {
    format: 'rich';
    /** Editor's document JSON. */
    document: Record<string, unknown>;
    gif?: GifInput | null;
};
/**
 * A GIF to post with a comment, by the address GIPHY gave it. On an update, leaving `gif` out keeps
 * the comment's GIF, and `null` removes it.
 */
export type GifInput = {
    url: string;
    width?: number | null;
    height?: number | null;
};
export type ContentInput = PlainContentInput | RichContentInput;
export type EditContentData = {
    format: ContentFormat;
    text: string;
    /** Editor's document JSON, for rich comments. */
    document: Record<string, unknown> | null;
    gif: GifData | null;
};
/** The `edit` read, for the comment's author. Includes collected-only field values. */
export type EditResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    comment: {
        uid: string;
        content: EditContentData;
    };
    /**
     * Values keyed by handle, for the fields the comment was posted with, in the shape a submission
     * sends; a file field's is a list of `EditFileData`, whose IDs keep the files when sent back.
     */
    fields: Record<string, unknown>;
    editor: {
        richEditor: EditorOptionsData | null;
        /** Exactly the fields the comment was posted with, minus any since turned Off, to draw instead of `viewer`'s. */
        fields?: FormFieldData[];
        /** GIF search, as on `viewer`'s editor, so an edit can change or add a GIF. */
        gifs: GifsData | null;
    };
};
/** A file an edited comment already has, in an Assets field's value. */
export type EditFileData = {
    id: number;
    url: string | null;
    filename: string;
    kind: string;
    width?: number;
    height?: number;
};
/** A file uploaded for an Assets field, waiting to be posted with a comment. */
export type UploadedFileData = {
    id: number;
    filename: string;
    kind: string;
    size: number;
    width?: number;
    height?: number;
};
/** `upload` for an Assets field. */
export type FieldUploadResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    /** The field's handle. */
    field: string;
    file: UploadedFileData;
};
export type GuestInput = {
    name: string;
    email: string;
    notifyReplies: boolean;
};
export type CreateRequest = {
    operationId: string;
    ownerId: number;
    siteId: number | null;
    parentUid: string | null;
    content: ContentInput;
    guest?: GuestInput;
    /** Values keyed by field handle. */
    fields?: Record<string, unknown>;
    protection?: ProtectionEvidenceData;
};
export type UpdateRequest = {
    operationId: string;
    uid: string;
    content: ContentInput;
    fields?: Record<string, unknown>;
    protection?: ProtectionEvidenceData;
};
export type DeleteRequest = {
    operationId: string;
    uid: string;
};
/** Sets the vote rather than toggling it, so a repeat is harmless. `null` clears it. */
export type VoteRequest = {
    operationId: string;
    uid: string;
    vote: VoteValue | null;
};
export type ReportRequest = {
    operationId: string;
    uid: string;
    reason?: string;
    details?: string | null;
    /** Withdraws this person's open report instead. */
    withdraw?: boolean;
};
export type FollowRequest = {
    operationId: string;
    ownerId: number;
    siteId: number | null;
    following: boolean;
    /** Guests only. */
    email?: string;
};
export type ModerateRequest = {
    operationId: string;
    uid: string;
    action: ModerationAction;
    reason?: string | null;
    note?: string | null;
};
export type CommentCountData = {
    commentCount: number;
};
/**
 * A repeat of a write's `operationId` within ten minutes returns the first result with
 * `replayed: true` instead of acting twice. Every write has an `announcement` for the live
 * region, which is null over GraphQL.
 */
export type CreateResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    /** The new comment, with `viewer`. */
    comment: CommentData;
    moderationState: 'approved' | 'pending';
    /** Null over GraphQL, where the store counts the change itself. */
    conversation: CommentCountData | null;
    /** A sentence for the live region, such as "Comment submitted for moderation." */
    announcement: string | null;
};
export type UpdateResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    comment: CommentData;
    /** An edit can send a comment back for approval. */
    moderationState: 'approved' | 'pending';
    announcement: string | null;
};
export type DeleteResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    uid: string;
    conversation: CommentCountData | null;
    announcement: string | null;
};
/** Part of one comment, for writes that change only that part. */
export type CommentPatchData = {
    uid: string;
    interaction?: InteractionData;
    viewer?: CommentViewerData;
};
export type VoteResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    comment: CommentPatchData;
    announcement: string | null;
};
export type ReportResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    comment: CommentPatchData;
    announcement: string | null;
};
export type FollowResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    viewer: {
        follow: FollowData;
    };
    /** `confirming` when a guest must confirm their address first. */
    outcome: FollowOutcome;
    announcement: string | null;
};
/** What's left of a comment a moderator removed. */
export type RemovedCommentData = {
    uid: string;
    state: 'removed';
    moderation: ModerationData;
};
export type ModerateResponse = {
    apiVersion: ApiVersion;
    status: 'ok';
    replayed: boolean;
    /** The comment with `moderation`. A `removed` or `spam` state means it has left the thread. */
    comment: CommentData | RemovedCommentData;
    announcement: string | null;
};
