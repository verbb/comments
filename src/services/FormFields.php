<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\base\FormFieldType;
use verbb\comments\base\FormFieldTypeInterface;
use verbb\comments\elements\Comment;
use verbb\comments\events\RegisterFormFieldTypesEvent;
use verbb\comments\exceptions\ApiException;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\fieldlayoutelements\CommentsField as CommentBodyElement;
use verbb\comments\formfields\AssetsType;
use verbb\comments\formfields\DateType;
use verbb\comments\formfields\EmailType;
use verbb\comments\formfields\LightswitchType;
use verbb\comments\formfields\NumberType;
use verbb\comments\formfields\OptionsType;
use verbb\comments\formfields\TextType;
use verbb\comments\formfields\UrlType;
use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\helpers\UploadPolicy;
use verbb\comments\records\Identity as IdentityRecord;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\base\conditions\ConditionInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\conditions\FieldConditionRuleInterface;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Lightswitch;
use craft\fields\Link;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fs\Temp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\VolumeFolder;

use yii\base\InvalidArgumentException;
use yii\web\UploadedFile;

use Throwable;

use verbb\editor\helpers\UploadSafety;
use verbb\protect\models\RequestSignals;

/**
 * Custom fields on the site's comment form: which are collected and which are also shown, for whom, how
 * each kind of field is filled in and shown, and the files uploaded for them.
 *
 * Each field in the Comment field layout is **Off** (the default), **Collect** or **Collect and show**,
 * stored against the layout element's UID in the **Comment Form Fields** setting. A field is offered to
 * the person writing only when it isn't Off, its type has a front-end description, its handle isn't one
 * Comments reserves, it's a top-level comment or the field is on for replies too, and the field layout's
 * conditions show it to them. The same check describes the form and validates what's sent, with the same
 * inputs, so a hand-built request can't fill in a field the form wouldn't show.
 *
 * Only Collect and show values ever reach public data. A comment records which fields applied when it was
 * posted (`postedFields`), and its author's edit form asks for exactly those, minus any since turned Off.
 */
class FormFields extends Component
{
    // Static Methods
    // =========================================================================

    /**
     * Whatever a type gives for a shown value, every address in it is an `http` or `https` one or null:
     * the value itself for a `url` field, and any `url` or `href` inside it, such as a file's. The
     * renderer checks again before linking anything.
     */
    private static function _webAddressesOnly(mixed $value, bool $isAddress): mixed
    {
        if ($isAddress) {
            return is_string($value) && UrlType::isWebAddress($value) ? $value : null;
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = in_array($key, ['url', 'href'], true)
                ? (is_string($item) && UrlType::isWebAddress($item) ? $item : null)
                : self::_webAddressesOnly($item, false);
        }

        return $value;
    }


    // Constants
    // =========================================================================

    /**
     * Register front-end descriptions for field types, keyed by field class, with
     * `RegisterFormFieldTypesEvent`. It isn't Craft's `Fields::EVENT_REGISTER_FIELD_TYPES`, which adds
     * field classes rather than describing them.
     */
    public const EVENT_REGISTER_FORM_FIELD_TYPES = 'registerFormFieldTypes';

    public const MODE_OFF = 'off';
    public const MODE_COLLECT = 'collect';
    public const MODE_COLLECT_AND_SHOW = 'collect-and-show';

    public const PLACEMENT_DETAILS = 'details';
    public const PLACEMENT_INLINE = 'inline';

    /**
     * Keys of a field type's description that Comments puts on the field's JSON as they are. Anything else
     * a description returns goes under `meta`, so Comments can add keys later without clashing with a
     * plugin's.
     */
    public const DESCRIPTION_KEYS = ['type', 'multiple', 'options', 'attributes', 'accept', 'maxFiles', 'maxSize'];

    /** Inline values are cut to this many characters, after the date. */
    public const INLINE_MAX_LENGTH = 40;

    /**
     * Handles a custom field on the Comment layout can't use, because they're the names of arguments to
     * Comments' GraphQL mutations, keys in the data API's requests and the site's form, or properties of
     * a comment that a field of the same name would hide. Craft's own reserved handles, such as `id`,
     * `uid`, `siteId` and `status`, apply as well.
     */
    public const RESERVED_HANDLES = [
        // Arguments to `createComment`, `saveComment` and Comments' other mutations
        'action', 'comment', 'commentId', 'content', 'contentDocument', 'contentFormat', 'details', 'downvote',
        'editorContent', 'email', 'following', 'name', 'note', 'notifyReplies', 'operationId', 'ownerId', 'parentId',
        'parentUid', 'protection', 'reason', 'upvote', 'vote', 'withdraw',
        // Keys in the data API's requests and the site's form
        'cursor', 'fields', 'guest', 'limit', 'ownerUri', 'sort',
        // Fields of a comment in GraphQL
        'commentsData', 'contentHtml', 'viewer',
        // A comment's own properties
        'author', 'authorEmail', 'authorName', 'authorRemovedAt', 'bodyContent', 'commentDate', 'conversationId',
        'depth', 'downvotes', 'editedAt', 'flags', 'gifUrl', 'heldReason', 'identityId', 'ipAddress',
        'moderationState', 'ownerSiteId', 'parentComment', 'postedFields', 'rawComment', 'replies', 'rootId', 'url', 'user',
        'userAgent', 'userId', 'upvotes', 'votes',
    ];

    /** Uploads for Assets fields that haven't been posted with a comment yet. */
    public const UPLOADS_TABLE = '{{%comments_field_uploads}}';

    /** How long a member's upload waits for its comment before it's removed. */
    public const UPLOAD_LIFETIME = 86400;

    /** How long a guest's upload waits for its comment, as long as their guest session lasts. */
    public const GUEST_UPLOAD_LIFETIME = 7200;

    /** How many uploads one person can have waiting at once, across every field. */
    public const MAX_PENDING_UPLOADS = 20;

    /**
     * How many uploads, and how many bytes, guests from one address can have waiting at once, whichever
     * guest identity each is from, since every new cookie or session token is a new identity.
     */
    public const MAX_GUEST_UPLOADS_PER_ADDRESS = 20;
    public const MAX_GUEST_BYTES_PER_ADDRESS = 50 * 1024 * 1024;

    // The name of a guest's own temporary upload folder starts with this.
    private const GUEST_FOLDER_PREFIX = 'comments_guest_';


    // Properties
    // =========================================================================

    private ?array $_types = null;


    // Public Methods
    // =========================================================================

    /**
     * Every registered description, keyed by field class: Comments' own for Craft's 11 field types, then
     * anything a plugin or module registers, which can replace them.
     */
    public function getFieldTypes(): array
    {
        if ($this->_types !== null) {
            return $this->_types;
        }

        $options = new OptionsType();
        $event = new RegisterFormFieldTypesEvent([
            'types' => [
                PlainText::class => new TextType(),
                Number::class => new NumberType(),
                Email::class => new EmailType(),
                Link::class => new UrlType(),
                Date::class => new DateType(),
                Dropdown::class => $options,
                MultiSelect::class => $options,
                RadioButtons::class => $options,
                Checkboxes::class => $options,
                Lightswitch::class => new LightswitchType(),
                Assets::class => new AssetsType(),
            ],
        ]);

        if ($this->hasEventHandlers(self::EVENT_REGISTER_FORM_FIELD_TYPES)) {
            $this->trigger(self::EVENT_REGISTER_FORM_FIELD_TYPES, $event);
        }

        $types = [];

        foreach ($event->types as $fieldClass => $type) {
            if (is_string($type) && is_subclass_of($type, FormFieldTypeInterface::class)) {
                $type = Craft::createObject($type);
            }

            if (is_string($fieldClass) && $type instanceof FormFieldTypeInterface) {
                $types[$fieldClass] = $type;
            }
        }

        return $this->_types = $types;
    }

    /**
     * The description for a field: its own class's, or the nearest parent class's.
     */
    public function getTypeFor(FieldInterface $field): ?FormFieldTypeInterface
    {
        $types = $this->getFieldTypes();

        for ($class = get_class($field); $class !== false; $class = get_parent_class($class)) {
            if (isset($types[$class])) {
                return $types[$class];
            }
        }

        return null;
    }

    /**
     * Why a field can't be collected on the site, or null when it can.
     */
    public function unsupportedReason(FieldInterface $field): ?string
    {
        if (in_array($field->handle, self::RESERVED_HANDLES, true)) {
            return Craft::t('comments', 'Comments uses “{handle}” itself, so a field with this handle can’t be collected on the site. Change the field’s handle to use it.', [
                'handle' => $field->handle,
            ]);
        }

        $type = $this->getTypeFor($field);

        if (!$type) {
            return Craft::t('comments', 'Comments can’t draw {type} fields on the site. A plugin can add them with `FormFields::EVENT_REGISTER_FORM_FIELD_TYPES`.', [
                'type' => $field::displayName(),
            ]);
        }

        return $type instanceof FormFieldType ? $type->unsupportedReason($field) : null;
    }

    /**
     * The custom fields in the Comment field layout, in layout order.
     */
    public function layoutElements(): array
    {
        return Craft::$app->getFields()->getLayoutByType(Comment::class)->getCustomFieldElements();
    }

    /**
     * Where each field sits in the comment form, by layout element UID: `above` the text for a field
     * placed before the Comment element in the Comment field layout, such as a rating, or `below` it.
     * A layout without the Comment element puts every field below.
     */
    public function formPositions(): array
    {
        $positions = [];
        $seen = false;

        foreach (Craft::$app->getFields()->getLayoutByType(Comment::class)->getTabs() as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($element instanceof CommentBodyElement) {
                    $seen = true;
                } elseif ($element instanceof CustomField) {
                    $positions[(string)$element->uid] = $seen ? 'below' : 'above';
                }
            }
        }

        // Without the Comment element in the layout, nothing can come before it.
        return $seen ? $positions : array_map(static fn(): string => 'below', $positions);
    }

    /**
     * Each field's setting, keyed by layout element UID: its `mode`, its `placement` when shown,
     * and whether it's on `replies` too. Fields that aren't listed are Off.
     *
     * **Public Fields**, the development builds' list of handles, is read as Collect on comments and
     * replies, which is what it did, whether it's still in project config or set in `config/comments.php`.
     */
    public function settings(): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $legacy = $settings->getLegacyPublicFields();

        if ($settings->formFields !== [] || $legacy === null) {
            return $settings->formFields;
        }

        $translated = [];

        foreach ($this->layoutElements() as $element) {
            if (in_array($element->getField()->handle, $legacy, true)) {
                $translated[$element->uid] = ['mode' => self::MODE_COLLECT, 'placement' => self::PLACEMENT_DETAILS, 'replies' => true];
            }
        }

        return $translated;
    }

    /**
     * A fingerprint of the settings that decide what's public, for caches keyed without them, such as
     * Craft's GraphQL cache when `config/comments.php` changes them.
     */
    public function settingsHash(): string
    {
        return md5(Json::encode([$this->settings(), CommentsPlugin::$plugin->getSettings()->allowGuestFieldUploads]));
    }

    /**
     * One field's setting, Off when it has none.
     */
    public function settingFor(string $uid): array
    {
        return $this->settings()[$uid] ?? ['mode' => self::MODE_OFF, 'placement' => self::PLACEMENT_DETAILS, 'replies' => false];
    }

    /**
     * What the settings page lists for each field in the layout: its setting, whether it can be collected
     * and why not, and anything worth knowing about choosing it.
     */
    public function settingsRows(): array
    {
        $rows = [];

        foreach ($this->layoutElements() as $element) {
            $field = $element->getField();
            $reason = $this->unsupportedReason($field);
            $setting = $this->settingFor($element->uid);
            $notes = [];

            // Collected files are private in the data API, but not where they're stored.
            if ($field instanceof Assets && $reason === null && $setting['mode'] !== self::MODE_COLLECT_AND_SHOW && ($warning = $this->_storageWarning($field))) {
                $notes[] = $warning;
            }

            if ($this->_dependsOnEnteredValues($element)) {
                $notes[] = Craft::t('comments', 'Its condition depends on what’s entered on the form. The site’s form checks conditions once, as it opens, so this field shows or hides as if nothing had been entered yet.');
            }

            $rows[] = [
                'uid' => $element->uid,
                'handle' => $field->handle,
                'name' => $field->name,
                'label' => $element->label() ?? $field->name,
                'type' => $field::displayName(),
                'supported' => $reason === null,
                'reason' => $reason,
                'mode' => $reason === null ? $setting['mode'] : self::MODE_OFF,
                'placement' => $setting['placement'],
                'replies' => $setting['replies'],
                'isEmail' => $field instanceof Email,
                'notes' => $notes,
                // How the site's form would describe it, for the settings page's previews.
                'description' => $reason === null ? ($this->describe([$element])[0] ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * A comment as it would be created, before anything submitted is applied, which conditions are
     * evaluated against when the form is described and when it's sent.
     */
    public function prospect(int $ownerId, int $siteId, ?Comment $parent, ?User $user): Comment
    {
        return new Comment([
            'ownerId' => $ownerId,
            'ownerSiteId' => $siteId,
            'siteId' => $siteId,
            'parentId' => $parent ? (int)$parent->id : null,
            'rootId' => $parent ? (int)($parent->rootId ?: $parent->id) : null,
            'depth' => $parent ? (int)$parent->depth + 1 : 0,
            'conversationId' => $parent?->conversationId,
            'userId' => $user ? (int)$user->id : null,
        ]);
    }

    /**
     * The fields `$user` can fill in on a new comment, keyed by layout element UID, in layout order. A
     * reply is a comment with a `parentId`, or `$isReply` describes a reply to any comment.
     */
    public function collectable(Comment $comment, ?User $user, ?bool $isReply = null): array
    {
        $isReply ??= (bool)$comment->parentId;
        $elements = [];

        foreach ($this->layoutElements() as $element) {
            $setting = $this->settingFor($element->uid);

            if ($setting['mode'] === self::MODE_OFF || ($isReply && !$setting['replies'])) {
                continue;
            }

            if ($this->unsupportedReason($element->getField()) !== null || !$this->_showsFor($element, $comment, $user)) {
                continue;
            }

            // Guests are only offered file uploads when **Guests Can Upload Files** is on.
            if (!$user && $element->getField() instanceof Assets && !CommentsPlugin::$plugin->getSettings()->allowGuestFieldUploads) {
                continue;
            }

            $elements[$element->uid] = $element;
        }

        return $elements;
    }

    /**
     * The fields a comment's author can change on the site: the ones it was posted with, minus any since
     * turned Off or that can no longer be drawn. Conditions aren't evaluated again, so an edit asks for
     * what the comment was posted with. A comment from before Comments recorded that is given what applies
     * to its author now.
     */
    public function editable(Comment $comment, ?User $user): array
    {
        $posted = $comment->getPostedFieldUids();

        if ($posted === null) {
            return $this->collectable($comment, $user);
        }

        $elements = [];

        foreach ($this->layoutElements() as $element) {
            if (!in_array($element->uid, $posted, true) || $this->settingFor($element->uid)['mode'] === self::MODE_OFF) {
                continue;
            }

            if ($this->unsupportedReason($element->getField()) === null) {
                $elements[$element->uid] = $element;
            }
        }

        return $elements;
    }

    /**
     * How the form draws these fields, in layout order. This is private data, because conditions make
     * it depend on who's asking.
     */
    public function describe(array $elements, ?Comment $comment = null): array
    {
        $descriptions = [];
        $positions = $elements !== [] ? $this->formPositions() : [];

        foreach ($elements as $element) {
            $field = $element->getField();
            $type = $this->getTypeFor($field);
            $setting = $this->settingFor($element->uid);

            if (!$type) {
                continue;
            }

            $description = $type->describe($field, $comment);
            $public = $setting['mode'] === self::MODE_COLLECT_AND_SHOW;
            $meta = array_merge(
                array_diff_key($description, array_flip([...self::DESCRIPTION_KEYS, 'meta'])),
                is_array($description['meta'] ?? null) ? $description['meta'] : [],
            );

            $descriptions[] = [
                'handle' => $field->handle,
                'uid' => (string)$element->uid,
                'label' => Craft::t('site', (string)($element->label() ?? $field->name)),
                'instructions' => ($element->instructions ?: $field->instructions) ?: null,
                'required' => (bool)$element->required,
                'type' => (string)($description['type'] ?? 'custom'),
                'multiple' => (bool)($description['multiple'] ?? false),
                'options' => array_values((array)($description['options'] ?? [])),
                'attributes' => (array)($description['attributes'] ?? []),
                'public' => $public,
                'placement' => $public ? $setting['placement'] : null,
                'formPosition' => $positions[(string)$element->uid] ?? 'below',
                // The width set in the field layout, 25, 50, 75 or 100 percent of the form.
                'width' => in_array((int)$element->width, [25, 50, 75], true) ? (int)$element->width : 100,
                ...array_intersect_key($description, array_flip(['accept', 'maxFiles', 'maxSize'])),
                ...($meta !== [] ? ['meta' => $meta] : []),
            ];
        }

        return $descriptions;
    }

    /**
     * Apply what a data API or GraphQL request sent for custom fields, keyed by handle, to a comment that
     * `$elements` says may be filled in. A field outside them is refused, as is a value its type can't
     * read. The comment records the fields that applied to it, whatever was sent, unless it already has.
     */
    public function apply(Comment $comment, mixed $values, array $elements): void
    {
        if ($values !== null && $values !== [] && (!is_array($values) || array_is_list($values))) {
            throw new ApiException(Craft::t('comments', 'This field can’t be filled in here.'), 'validation_failed', 422);
        }

        $values = is_array($values) ? $values : [];
        $byHandle = [];

        foreach ($elements as $element) {
            $byHandle[$element->getField()->handle] = $element;
        }

        $errors = [];

        foreach (array_keys($values) as $handle) {
            if (!isset($byHandle[$handle])) {
                $errors['fields.' . $handle] = [Craft::t('comments', 'This field can’t be filled in here.')];
            }
        }

        if ($errors) {
            throw new ApiException(reset($errors)[0], 'validation_failed', 422, $errors);
        }

        $normalized = [];
        $tooManyFiles = false;

        foreach ($values as $handle => $value) {
            $field = $byHandle[$handle]->getField();

            try {
                $normalized[$handle] = $this->getTypeFor($field)->normalizeInput($field, $value);
            } catch (InvalidArgumentException $e) {
                $errors['fields.' . $handle] = [$e->getMessage()];

                continue;
            }

            if ($field instanceof Assets && $field->maxRelations && is_array($normalized[$handle]) && count($normalized[$handle]) > $field->maxRelations) {
                $errors['fields.' . $handle] = [Craft::t('comments', 'Choose no more than {count, plural, =1{one file} other{# files}}.', ['count' => (int)$field->maxRelations])];
                $tooManyFiles = true;
            }
        }

        if ($errors) {
            throw new ApiException(reset($errors)[0], $tooManyFiles && count($errors) === 1 ? 'too_many_files' : 'validation_failed', 422, $errors);
        }

        $comment->setFormFieldElements(array_keys($elements));
        $this->recordPostedModes($comment, array_keys($elements));

        $comment->applyFormFieldValues(array_keys($byHandle), function() use ($comment, $normalized): void {
            foreach ($normalized as $handle => $value) {
                $comment->setFieldValue((string)$handle, $value);
            }
        });
    }

    /**
     * Record that these fields, by layout element UID, were filled in on the comment now, each in the
     * mode it's in now, alongside the fields it already had. Posting, its author's edits and saves in the
     * control panel record them, and only a value recorded while its field was shown is ever public, so
     * switching a field to Collect and show never publishes what was entered while it was private.
     */
    public function recordPostedModes(Comment $comment, array $uids): void
    {
        $modes = $comment->getPostedFieldModes() ?? [];

        foreach ($uids as $uid) {
            $mode = $this->settingFor((string)$uid)['mode'];

            if ($mode !== self::MODE_OFF) {
                $modes[(string)$uid] = $mode;
            }
        }

        $comment->setPostedFieldModes($modes);
    }

    /**
     * Each of these fields' values on a comment as its form takes them back, keyed by handle, for its
     * author's edit form. This includes values that are only collected.
     */
    public function inputValues(Comment $comment, array $elements): array
    {
        $values = [];

        foreach ($elements as $element) {
            $field = $element->getField();
            $type = $this->getTypeFor($field);

            if ($type) {
                $values[$field->handle] = $type->inputValue($field, $comment->getFieldValue($field->handle));
            }
        }

        return $values;
    }

    /**
     * The custom field values a comment shows publicly, in layout order: each Collect and show field that
     * applied to it, with its handle, label, input type, where it's drawn, a raw `value` and a formatted
     * `text`. Values that are only collected are never included.
     */
    public function shownFields(Comment $comment): array
    {
        $fields = [];

        foreach ($this->_shownElements($comment) as $element) {
            $field = $element->getField();
            $type = $this->getTypeFor($field);

            // A third party's type that fails shows nothing for its field, rather than failing the read.
            try {
                $display = $type->display($field, $comment->getFieldValue($field->handle));
                $input = (string)($type->describe($field, $comment)['type'] ?? 'custom');
            } catch (Throwable $e) {
                Craft::warning('Couldn’t show the “' . $field->handle . '” field of comment ' . $comment->id . ': ' . $e->getMessage(), __METHOD__);

                continue;
            }

            if ($display === null || !array_key_exists('value', $display) || !is_string($display['text'] ?? null)) {
                continue;
            }

            $fields[] = [
                'handle' => $field->handle,
                'label' => Craft::t('site', (string)($element->label() ?? $field->name)),
                'type' => $input,
                'placement' => $this->settingFor($element->uid)['placement'],
                'value' => self::_webAddressesOnly($display['value'], $input === 'url'),
                'text' => $display['text'],
            ];
        }

        return $fields;
    }

    /**
     * Whether `$handle` is shown publicly on `$comment`, for GraphQL on a schema that can't moderate.
     */
    public function isShownOn(Comment $comment, string $handle): bool
    {
        foreach ($this->_shownElements($comment) as $element) {
            if ($element->getField()->handle === $handle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the GraphQL request being answered may read every custom field: a schema that isn't public
     * and can moderate comments. Any other gets only the fields each comment shows publicly.
     */
    public function canReadEveryField(): bool
    {
        if (GqlHelper::isPublicSchema() || !GqlHelper::canSchema('comments', 'moderate')) {
            return false;
        }

        // As for `flags`: someone the request is for has to be able to moderate too. A schema's token
        // without a user is the site's own server.
        $user = CommentsPlugin::$plugin->getService()->getUser();

        return !$user instanceof User || CommentsPlugin::$plugin->getModeration()->canModerateComment($user);
    }

    /**
     * The values a comment has for fields that aren't shown publicly, as text, so Dev Mode can say when a
     * handler has put one into public data.
     */
    public function privateValues(Comment $comment): array
    {
        $shown = array_map(static fn(CustomField $element): string => (string)$element->uid, $this->_shownElements($comment));
        $values = [];

        foreach ($this->layoutElements() as $element) {
            if (in_array($element->uid, $shown, true)) {
                continue;
            }

            $field = $element->getField();
            $type = $this->getTypeFor($field);

            try {
                $display = $type?->display($field, $comment->getFieldValue($field->handle));
            } catch (Throwable) {
                $display = null;
            }

            $text = is_array($display) ? ($display['text'] ?? null) : null;

            // Short values such as "Yes" or a number would match anything.
            if (is_string($text) && mb_strlen($text) >= 4) {
                $values[] = $text;
            }
        }

        return $values;
    }

    /**
     * Store a file uploaded for an Assets field in the uploader's temporary folder, after Comments'
     * upload rules and the field's own kinds and limits. It moves to the field's upload location only
     * when a comment is saved with it, and is removed if none is, after a day for a member and two hours
     * for a guest. `$elements` are the fields this person can fill in on the comment it's for.
     *
     * `$identity` gives the uploader's identity, and is only called once the upload has passed every
     * check, so a refused upload never starts a guest's identity.
     */
    public function upload(?UploadedFile $file, string $handle, array $elements, ?User $user, callable $identity): array
    {
        $element = null;

        foreach ($elements as $candidate) {
            if ($candidate->getField()->handle === $handle) {
                $element = $candidate;
            }
        }

        if (!$element) {
            throw new ApiException(Craft::t('comments', 'This field can’t be filled in here.'), 'validation_failed', 422, [
                'fields.' . $handle => [Craft::t('comments', 'This field can’t be filled in here.')],
            ]);
        }

        $field = $element->getField();

        if (!$field instanceof Assets || !$this->getTypeFor($field) instanceof AssetsType) {
            throw new ApiException(Craft::t('comments', 'This field doesn’t take files.'), 'invalid_request', 400);
        }

        if (!$file) {
            throw new ApiException(Craft::t('comments', 'No file was uploaded.'), 'validation_failed', 422, [
                'file' => [Craft::t('comments', 'No file was uploaded.')],
            ]);
        }

        // Files wait on Craft's temporary upload filesystem until their comment is posted, unchecked by a
        // moderator. If it has public URLs, anyone with an address could open one before then.
        if (UploadSafety::temporaryUploadsArePublic()) {
            Craft::warning('Comments refused a field upload because Craft’s temporary upload filesystem (the `tempAssetUploadFs` general config setting) has public URLs. Use a filesystem without public URLs for temporary uploads.', __METHOD__);

            throw new ApiException(Craft::t('comments', 'Uploads are turned off on this site, because files waiting to be saved would be public.'), 'invalid_request', 400);
        }

        // What's run out goes first, so it doesn't count against anyone.
        $this->deleteExpiredUploads();

        $identities = CommentsPlugin::$plugin->getIdentities();
        $existing = $user ? $identities->findForUser((int)$user->id) : $identities->findGuestForRequest();

        if ($existing && $this->_pendingUploads((int)$existing->id) >= self::MAX_PENDING_UPLOADS) {
            throw new UploadRefusedException(Craft::t('comments', 'You have too many files waiting to be posted. Post your comment, or try again later.'), 'too_many_files');
        }

        UploadPolicy::check($file, AssetsType::allowedExtensions($field), AssetsType::maxSize());
        $address = $user ? null : $this->_guestAddressKey();
        $size = (int)filesize((string)$file->tempName);

        if ($address !== null) {
            $this->_reserveGuestUpload($address, $size);
        }

        $identity = $identity();

        $folder = $user ? Craft::$app->getAssets()->getUserTemporaryUploadFolder($user) : $this->_guestFolder($identity);
        $filename = AssetsHelper::prepareAssetName((string)$file->name);

        $asset = new Asset();
        $asset->tempFilePath = $file->tempName;
        $asset->setFilename($filename);
        $asset->setMimeType((string)$file->type);
        $asset->newFolderId = $folder->id;
        $asset->setVolumeId($folder->volumeId);
        $asset->uploaderId = $user ? (int)$user->id : null;
        $asset->title = AssetsHelper::filename2Title(pathinfo($filename, PATHINFO_FILENAME));
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new UploadRefusedException(Craft::t('comments', 'The file couldn’t be uploaded. Please try again.'));
        }

        Db::insert(self::UPLOADS_TABLE, [
            'assetId' => (int)$asset->id,
            'fieldUid' => (string)$element->uid,
            'identityId' => (int)$identity->id,
        ]);

        $file = [
            'id' => (int)$asset->id,
            'filename' => $asset->getFilename(),
            'kind' => $asset->kind,
            'size' => (int)$asset->size,
        ];

        if ($asset->kind === Asset::KIND_IMAGE && $asset->getWidth() && $asset->getHeight()) {
            $file['width'] = (int)$asset->getWidth();
            $file['height'] = (int)$asset->getHeight();
        }

        return ['field' => $handle, 'file' => $file];
    }

    /**
     * The IDs of files `$identity` uploaded for the field at layout element `$uid` that are still waiting
     * to be posted, which a comment may be saved with.
     */
    public function pendingUploadIds(string $uid, ?IdentityRecord $identity): array
    {
        if (!$identity || !Craft::$app->getDb()->tableExists(self::UPLOADS_TABLE)) {
            return [];
        }

        return array_map('intval', (new Query())
            ->select(['uploads.assetId'])
            ->from(['uploads' => self::UPLOADS_TABLE])
            ->innerJoin(['assets' => Table::ASSETS], '[[assets.id]] = [[uploads.assetId]]')
            ->innerJoin(['folders' => Table::VOLUMEFOLDERS], '[[folders.id]] = [[assets.folderId]]')
            ->where([
                'uploads.fieldUid' => $uid,
                'uploads.identityId' => (int)$identity->id,
                'folders.volumeId' => null,
            ])
            ->column());
    }

    /**
     * Garbage collection: remove uploads that waited a day without a comment, and forget the ones that
     * were posted. Craft itself only removes temporary folders once they're empty.
     */
    public function deleteExpiredUploads(): int
    {
        $db = Craft::$app->getDb();

        if (!$db->tableExists(self::UPLOADS_TABLE)) {
            return 0;
        }

        $memberCutoff = Db::prepareDateForDb(DateTimeHelper::now()->modify('-' . self::UPLOAD_LIFETIME . ' seconds'));
        $guestCutoff = Db::prepareDateForDb(DateTimeHelper::now()->modify('-' . self::GUEST_UPLOAD_LIFETIME . ' seconds'));
        $deleted = 0;

        // Posted files moved out of the temporary folders, so there's nothing left to track.
        $posted = (new Query())
            ->select(['uploads.id'])
            ->from(['uploads' => self::UPLOADS_TABLE])
            ->leftJoin(['assets' => Table::ASSETS], '[[assets.id]] = [[uploads.assetId]]')
            ->leftJoin(['folders' => Table::VOLUMEFOLDERS], '[[folders.id]] = [[assets.folderId]]')
            ->where(['or', ['assets.id' => null], ['not', ['folders.volumeId' => null]]])
            ->column();

        if ($posted) {
            Db::delete(self::UPLOADS_TABLE, ['id' => $posted]);
        }

        // A guest's last for as long as their guest session, and a member's for a day.
        $expired = (new Query())
            ->select(['uploads.assetId', 'uploads.identityId', 'identities.userId'])
            ->from(['uploads' => self::UPLOADS_TABLE])
            ->leftJoin(['identities' => '{{%comments_identities}}'], '[[identities.id]] = [[uploads.identityId]]')
            ->where(['or',
                ['and', ['identities.userId' => null], ['<', 'uploads.dateCreated', $guestCutoff]],
                ['<', 'uploads.dateCreated', $memberCutoff],
            ])
            ->all();
        $guestIdentityIds = array_values(array_unique(array_map('intval', array_column(array_filter($expired, static fn(array $row): bool => $row['userId'] === null), 'identityId'))));

        foreach (array_chunk(array_map('intval', array_column($expired, 'assetId')), 100) as $ids) {
            foreach (Craft::$app->getAssets()->createTempAssetQuery()->id($ids)->status(null)->all() as $asset) {
                if (Craft::$app->getElements()->deleteElement($asset, true)) {
                    $deleted++;
                }
            }

            Db::delete(self::UPLOADS_TABLE, ['assetId' => $ids]);
        }

        $this->_deleteUnusedGuestIdentities($guestIdentityIds);

        return $deleted;
    }


    // Private Methods
    // =========================================================================

    /**
     * Whether the field layout's conditions show `$element` to `$user` on `$comment`, through Craft's
     * `showInForm()`. Craft only applies a user condition when someone is signed in, because the control
     * panel always has someone, so a guest is held to one here: a field with a user condition is never
     * shown to a guest.
     */
    private function _showsFor(CustomField $element, Comment $comment, ?User $user): bool
    {
        $userCondition = $element->getUserCondition();

        if ($userCondition && $userCondition->getConditionRules() !== [] && (!$user || !$userCondition->matchElement($user))) {
            return false;
        }

        return $element->showInForm($comment);
    }

    /**
     * A warning when files collected by `$field` can be reached anyway: its upload location's volume has
     * public URLs, so anyone with a file's address can open it, or a public GraphQL schema can list that
     * volume's assets.
     */
    private function _storageWarning(Assets $field): ?string
    {
        $source = $field->restrictLocation ? $field->restrictedLocationSource : $field->defaultUploadLocationSource;
        $volume = is_string($source) && str_starts_with($source, 'volume:')
            ? Craft::$app->getVolumes()->getVolumeByUid(substr($source, 7))
            : null;

        if (!$volume) {
            return null;
        }

        try {
            $hasUrls = $volume->getFs()->hasUrls;
        } catch (Throwable) {
            $hasUrls = false;
        }

        $publicSchema = false;

        foreach (Craft::$app->getGql()->getSchemas() as $schema) {
            if ($schema->isPublic && $schema->has('volumes.' . $volume->uid . ':read')) {
                $publicSchema = true;
            }
        }

        return match (true) {
            $hasUrls && $publicSchema => Craft::t('comments', 'Files collected here go to the “{volume}” volume, which has public URLs and is readable through the public GraphQL schema, so they aren’t private where they’re stored. Use a volume without public URLs.', ['volume' => $volume->name]),
            $hasUrls => Craft::t('comments', 'Files collected here go to the “{volume}” volume, which has public URLs, so anyone with a file’s address can open it. Use a volume without public URLs to keep them private.', ['volume' => $volume->name]),
            $publicSchema => Craft::t('comments', 'Files collected here go to the “{volume}” volume, which the public GraphQL schema can read, so anyone can list them. Take that volume out of the public schema to keep them private.', ['volume' => $volume->name]),
            default => null,
        };
    }

    /**
     * Whether a field's element condition looks at something entered on the same form, which the site's
     * form can't follow without asking the server on every change.
     */
    private function _dependsOnEnteredValues(CustomField $element): bool
    {
        $condition = $element->getElementCondition();

        if (!$condition instanceof ConditionInterface) {
            return false;
        }

        foreach ($condition->getConditionRules() as $rule) {
            if ($rule instanceof FieldConditionRuleInterface
                || $rule instanceof \verbb\comments\elements\conditions\CommentConditionRule
                || $rule instanceof \verbb\comments\elements\conditions\NameConditionRule
                || $rule instanceof \verbb\comments\elements\conditions\EmailConditionRule
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Collect and show fields a comment shows: those that were shown when it was posted, or last
     * edited, and still are. A comment without that record, from before Comments kept it, shows none until
     * it's next edited or saved. Conditions depend on who's asking, so they never decide what's public.
     */
    private function _shownElements(Comment $comment): array
    {
        $posted = $comment->getPostedFieldModes();
        $elements = [];

        if (!$posted) {
            return [];
        }

        foreach ($this->layoutElements() as $element) {
            if (($posted[$element->uid] ?? null) !== self::MODE_COLLECT_AND_SHOW || $this->settingFor($element->uid)['mode'] !== self::MODE_COLLECT_AND_SHOW) {
                continue;
            }

            if ($this->unsupportedReason($element->getField()) === null) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private function _pendingUploads(int $identityId): int
    {
        return (int)(new Query())
            ->from(['uploads' => self::UPLOADS_TABLE])
            ->innerJoin(['assets' => Table::ASSETS], '[[assets.id]] = [[uploads.assetId]]')
            ->innerJoin(['folders' => Table::VOLUMEFOLDERS], '[[folders.id]] = [[assets.folderId]]')
            ->where(['uploads.identityId' => $identityId, 'folders.volumeId' => null])
            ->count();
    }

    /**
     * The guest identities among these that nothing uses any more: no comment, vote, report, follow,
     * mention, restriction, moderator's note or upload. A guest's first upload starts their identity, so one whose uploads
     * were never posted would otherwise be left behind.
     */
    private function _deleteUnusedGuestIdentities(array $identityIds): void
    {
        if ($identityIds === []) {
            return;
        }

        $db = Craft::$app->getDb();
        $used = [];

        foreach (['{{%comments_comments}}', '{{%comments_interactions}}', '{{%comments_reports}}', '{{%comments_subscriptions}}', '{{%comments_mentions}}', '{{%comments_restrictions}}', '{{%comments_commenternotes}}', self::UPLOADS_TABLE] as $table) {
            if ($db->tableExists($table)) {
                array_push($used, ...array_map('intval', (new Query())->select(['identityId'])->distinct()->from($table)->where(['identityId' => $identityIds])->column()));
            }
        }

        $unused = array_values(array_diff($identityIds, $used));

        if ($unused === []) {
            return;
        }

        try {
            Db::delete('{{%comments_identities}}', ['id' => $unused, 'type' => 'guest', 'userId' => null, 'name' => null, 'email' => null]);
        } catch (Throwable $e) {
            // Something else still points at one, so it stays.
            Craft::warning('Couldn’t remove unused guest identities: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * The address guests' uploads are counted against, with an IPv6 address counted by its /64, as the
     * data API's rate limits are, or null when it isn't known.
     */
    private function _guestAddressKey(): ?string
    {
        $address = RequestSignals::fromRequest(Craft::$app->getRequest())->ipAddress;

        if (!is_string($address) || $address === '') {
            return null;
        }

        if (str_contains($address, ':') && ($packed = @inet_pton($address)) !== false) {
            $address = bin2hex(substr($packed, 0, 8));
        }

        return 'comments:field-uploads:' . hash('sha256', $address);
    }

    /**
     * Count a guest's upload against their address, across every guest identity, refusing it when the
     * address already has as many files or bytes waiting as guests may have. Entries last as long as a
     * guest's upload does.
     */
    private function _reserveGuestUpload(string $key, int $size): void
    {
        $cache = Craft::$app->getCache();
        $mutex = Craft::$app->getMutex();
        $locked = $mutex->acquire($key, 5);

        try {
            $now = DateTimeHelper::currentTimeStamp();
            $entries = array_values(array_filter(
                is_array($waiting = $cache->get($key)) ? $waiting : [],
                static fn(mixed $entry): bool => is_array($entry) && ($entry[0] ?? 0) > $now - self::GUEST_UPLOAD_LIFETIME,
            ));
            $bytes = array_sum(array_column($entries, 1));

            if (count($entries) >= self::MAX_GUEST_UPLOADS_PER_ADDRESS || $bytes + $size > self::MAX_GUEST_BYTES_PER_ADDRESS) {
                $retryAfter = $entries ? max(1, (int)$entries[0][0] + self::GUEST_UPLOAD_LIFETIME - $now) : self::GUEST_UPLOAD_LIFETIME;

                throw new ApiException(Craft::t('comments', 'Too many files have been uploaded from here. Please try again later.'), 'rate_limited', 429, retryAfter: $retryAfter);
            }

            $entries[] = [$now, $size];
            $cache->set($key, $entries, self::GUEST_UPLOAD_LIFETIME);
        } finally {
            if ($locked) {
                $mutex->release($key);
            }
        }
    }

    /**
     * A guest's own temporary upload folder, beside Craft's per-user ones. Craft names a guest's after
     * their session, which a guest on another site doesn't have, so it's named after their identity.
     */
    private function _guestFolder(IdentityRecord $identity): VolumeFolder
    {
        $assets = Craft::$app->getAssets();
        $folderName = self::GUEST_FOLDER_PREFIX . substr(hash_hmac('sha256', (string)$identity->uid, Craft::$app->getConfig()->getGeneral()->securityKey), 0, 32);
        $top = $assets->findFolder(['volumeId' => ':empty:', 'parentId' => ':empty:']);

        if (!$top) {
            $top = new VolumeFolder();
            $top->name = Craft::t('app', 'Temporary Uploads');
            $assets->storeFolderRecord($top);
        }

        $folder = $assets->findFolder(['name' => $folderName, 'parentId' => $top->id]);

        if (!$folder) {
            $folder = new VolumeFolder();
            $folder->parentId = $top->id;
            $folder->name = $folderName;
            $folder->path = $folderName . '/';
            $assets->storeFolderRecord($folder);
        }

        $fs = $assets->getTempAssetUploadFs();

        if ($fs instanceof Temp) {
            FileHelper::createDirectory(Craft::$app->getPath()->getTempAssetUploadsPath() . DIRECTORY_SEPARATOR . $folderName);
        } elseif (!$fs->directoryExists($folderName)) {
            $fs->createDirectory($folderName);
        }

        return $folder;
    }
}
