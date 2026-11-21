<?php
use craft\base\FieldInterface;
use craft\elements\conditions\users\GroupConditionRule;
use craft\elements\conditions\users\UserCondition;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\Color;
use craft\fields\conditions\TextFieldConditionRule;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Lightswitch;
use craft\fields\Link;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\GqlSchema;
use craft\models\UserGroup;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;
use verbb\comments\elements\conditions\CommentCondition;
use verbb\comments\elements\conditions\OwnerConditionRule;
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\events\RegisterFormFieldTypesEvent;
use verbb\comments\exceptions\ApiException;
use verbb\comments\formfields\UrlType;
use verbb\comments\migrations\m400000_000009_form_fields;
use verbb\comments\migrations\support\V3SettingsReview;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\models\Settings;
use verbb\comments\services\Api;
use verbb\comments\services\FormFields;
use verbb\comments\services\Frontend;
use yii\base\Event;
use yii\base\InvalidArgumentException;

/**
 * Custom fields on the site's comment form (plan section 2.1, stage M's server half): the Comment Form
 * Fields setting and its upgrade, the field type registry, conditions, replies, the fields a comment was
 * posted with, reserved handles, and privacy on every public path, with the threat model's custom field
 * rows in 5.5 named where they're covered. `V4FormFieldsHttpTest` covers uploads through real requests.
 */

function ffApi(): Api
{
    return Comments::$plugin->getApi();
}

function ffFormFields(): FormFields
{
    return Comments::$plugin->getFormFields();
}

function ffAdmin(): User
{
    return Craft::$app->getUsers()->getUserByUsernameOrEmail('admin');
}

function ffAs(?User $user): void
{
    Craft::$app->getUser()->setIdentity($user);
    Comments::$plugin->getIdentities()->clearCaches();
    Comments::$plugin->getConversations()->clearCaches();
}

/**
 * Add `$field` to the Comment layout, with the layout element's own settings, until the test ends.
 */
function ffField(FieldInterface $field, array $element = []): CustomField
{
    $fields = Craft::$app->getFields();
    $field->name = $field->name ?: 'Field';
    $field->handle = $field->handle ?: 'ff' . bin2hex(random_bytes(4));
    expect($fields->saveField($field))->toBeTrue();
    $layout = $fields->getLayoutByType(Comment::class);
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $custom = new CustomField($field, $element);
    $tab->setElements([...$tab->getElements(), $custom]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
    expect($fields->saveLayout($layout, false))->toBeTrue();
    $GLOBALS['ffFields'][] = $field;

    // The saved element, with its UID.
    foreach ($fields->getLayoutByType(Comment::class)->getCustomFieldElements() as $saved) {
        if ($saved->getField()->handle === $field->handle) {
            return $saved;
        }
    }

    throw new RuntimeException('The field wasn’t added to the layout.');
}

/**
 * Set fields' modes by handle: `collect` or `collect-and-show`, with `inline` and `replies` as needed.
 */
function ffModes(array $modes): void
{
    $settings = [];

    foreach (ffFormFields()->layoutElements() as $element) {
        $mode = $modes[$element->getField()->handle] ?? null;

        if ($mode !== null) {
            $parts = explode(':', $mode);
            $settings[$element->uid] = [
                'mode' => $parts[0],
                'placement' => in_array('inline', $parts, true) ? 'inline' : 'details',
                'replies' => in_array('replies', $parts, true),
            ];
        }
    }

    Comments::$plugin->getSettings()->formFields = $settings;
    ffApi()->invalidateCaches();
}

/**
 * Post through the data API as `$user` (or a guest when null), returning the comment.
 */
function ffCreate($entry, string $text, ?User $user, array $fields = [], ?Comment $parent = null): Comment
{
    ffAs($user);
    $body = [
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => ['format' => 'plain', 'text' => $text],
        'fields' => $fields,
    ];

    if ($parent) {
        $body['parentUid'] = $parent->uid;
    }

    if (!$user) {
        $body['guest'] = ['name' => 'Guest Poster', 'email' => 'guest-' . bin2hex(random_bytes(3)) . '@example.test'];
    }

    $result = ffApi()->create($body);

    return Comment::find()->uid($result['comment']['uid'])->status(null)->one();
}

function ffError(callable $callback): ApiException
{
    try {
        $callback();
    } catch (Throwable $e) {
        return ffApi()->errorFor($e);
    }

    throw new RuntimeException('Expected a refusal.');
}

function ffJson(mixed $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function ffGql(array $scope, string $query, bool $public = false): array
{
    $schema = new GqlSchema(['name' => 'Form fields', 'scope' => $scope, 'isPublic' => $public]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);

    try {
        return $gql->executeQuery($schema, $query, debugMode: true);
    } finally {
        $gql->setActiveSchema(null);
        $gql->flushCaches();
    }
}

/** Everything in Craft's file cache, to look for what mustn't be there. */
function ffCacheContents(): string
{
    $cache = Craft::$app->getCache();

    if (!$cache instanceof yii\caching\FileCache) {
        return '';
    }

    $contents = '';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Craft::getAlias($cache->cachePath), FilesystemIterator::SKIP_DOTS)) as $file) {
        $contents .= (string)file_get_contents($file->getPathname());
    }

    return $contents;
}

beforeEach(function() {
    $GLOBALS['ffFields'] = [];
    $GLOBALS['ffLayout'] = Craft::$app->getFields()->getLayoutByType(Comment::class)->getConfig();
    $GLOBALS['ffSettings'] = Comments::$plugin->getSettings()->getAttributes();
    $settings = Comments::$plugin->getSettings();
    $settings->formFields = [];
    $settings->requireModeration = false;
    $settings->enableSpamChecks = false;
    $settings->allowGuest = true;
    $settings->guestRequireEmailName = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    Comments::$plugin->set('formFields', FormFields::class);
    ffApi()->invalidateCaches();
    ffAs(ffAdmin());
});

afterEach(function() {
    Event::off(FormFields::class, FormFields::EVENT_REGISTER_FORM_FIELD_TYPES);
    Event::off(Api::class, Api::EVENT_DEFINE_COMMENT_DATA);
    Comments::$plugin->set('formFields', FormFields::class);
    $fields = Craft::$app->getFields();
    $layout = FieldLayout::createFromConfig($GLOBALS['ffLayout']);
    $current = $fields->getLayoutByType(Comment::class);
    $layout->id = $current->id;
    $layout->uid = $current->uid;
    $layout->type = Comment::class;
    $fields->saveLayout($layout, false);

    foreach ($GLOBALS['ffFields'] as $field) {
        $fields->deleteField($field);
    }

    Comments::$plugin->getSettings()->setAttributes($GLOBALS['ffSettings'], false);
    Comments::$plugin->getSettings()->formFields = [];
    ffAs(ffAdmin());
});

// The setting and its upgrade
// =========================================================================

it('reads Comment Form Fields as Off, Collect or Collect and show, with a placement and replies switch', function() {
    $settings = new Settings();
    $settings->setAttributes(['formFields' => [
        'aaaaaaaa-0000-4000-8000-000000000001' => ['mode' => 'collect-and-show', 'placement' => 'inline', 'replies' => '1'],
        'aaaaaaaa-0000-4000-8000-000000000002' => ['mode' => 'collect', 'placement' => 'sideways', 'replies' => ''],
        'aaaaaaaa-0000-4000-8000-000000000003' => ['mode' => 'off', 'placement' => 'details', 'replies' => '1'],
        'aaaaaaaa-0000-4000-8000-000000000004' => ['mode' => 'publish'],
    ]], false);

    expect($settings->formFields)->toBe([
        'aaaaaaaa-0000-4000-8000-000000000001' => ['mode' => 'collect-and-show', 'placement' => 'inline', 'replies' => true],
        'aaaaaaaa-0000-4000-8000-000000000002' => ['mode' => 'collect', 'placement' => 'details', 'replies' => false],
    ])->and($settings->validate(['formFields']))->toBeTrue();

    // Set by hand, something unknown doesn't validate.
    $settings->formFields = ['aaaaaaaa-0000-4000-8000-000000000001' => ['mode' => 'collect-and-show', 'placement' => 'details', 'replies' => 'yes']];

    expect($settings->validate(['formFields']))->toBeFalse();
});

it('reads the settings page’s ask and show choices as each field’s mode and placement', function() {
    $settings = new Settings();
    $settings->setAttributes(['formFields' => [
        'aaaaaaaa-0000-4000-8000-000000000001' => ['ask' => '1', 'show' => 'inline', 'replies' => '1'],
        'aaaaaaaa-0000-4000-8000-000000000002' => ['ask' => '1', 'show' => 'details', 'replies' => ''],
        'aaaaaaaa-0000-4000-8000-000000000003' => ['ask' => '1', 'show' => 'none', 'replies' => '1'],
        'aaaaaaaa-0000-4000-8000-000000000004' => ['ask' => '', 'show' => 'inline', 'replies' => '1'],
    ]], false);

    expect($settings->formFields)->toBe([
        'aaaaaaaa-0000-4000-8000-000000000001' => ['mode' => 'collect-and-show', 'placement' => 'inline', 'replies' => true],
        'aaaaaaaa-0000-4000-8000-000000000002' => ['mode' => 'collect-and-show', 'placement' => 'details', 'replies' => false],
        'aaaaaaaa-0000-4000-8000-000000000003' => ['mode' => 'collect', 'placement' => 'details', 'replies' => true],
    ]);
});

it('puts a field placed before the Comment element above the text in the form, and the rest below', function() {
    $rating = ffField(new Number(['name' => 'Rating', 'handle' => 'ffRating']));
    $photo = ffField(new PlainText(['name' => 'Where', 'handle' => 'ffWhere']));
    ffModes(['ffRating' => 'collect', 'ffWhere' => 'collect']);

    // The layout as an editor would arrange it: the rating, then the comment, then the rest.
    $fields = Craft::$app->getFields();
    $layout = $fields->getLayoutByType(Comment::class);
    $tab = $layout->getTabs()[0];
    $others = array_values(array_filter($tab->getElements(), static fn($element) => !$element instanceof verbb\comments\fieldlayoutelements\CommentsField && !in_array($element->uid, [$rating->uid, $photo->uid], true)));
    $rating->width = 50;
    $tab->setElements([$rating, new verbb\comments\fieldlayoutelements\CommentsField(), $photo, ...$others]);
    $layout->setTabs([$tab]);
    expect($fields->saveLayout($layout, false))->toBeTrue();

    $entry = CommentsFixtureFactory::entry('Form field positions');
    $described = array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'formPosition', 'handle');
    $widths = array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'width', 'handle');

    expect(ffFormFields()->formPositions())->toMatchArray([$rating->uid => 'above', $photo->uid => 'below'])
        ->and($described)->toMatchArray(['ffRating' => 'above', 'ffWhere' => 'below'])
        ->and($widths)->toMatchArray(['ffRating' => 50, 'ffWhere' => 100]);

    // Without the Comment element in the layout, nothing can come before it.
    $layout = $fields->getLayoutByType(Comment::class);
    $tab = $layout->getTabs()[0];
    $tab->setElements(array_values(array_filter($tab->getElements(), static fn($element) => !$element instanceof verbb\comments\fieldlayoutelements\CommentsField)));
    $layout->setTabs([$tab]);
    $fields->saveLayout($layout, false);

    expect(ffFormFields()->formPositions())->toMatchArray([$rating->uid => 'below', $photo->uid => 'below']);
});

it('reads Public Fields as Collect on comments and replies, until Comment Form Fields is saved', function() {
    $element = ffField(new PlainText(['name' => 'Order number']));
    $other = ffField(new PlainText(['name' => 'Not listed']));
    $settings = Comments::$plugin->getSettings();
    $settings->setAttributes(['publicFields' => [$element->getField()->handle]], false);

    expect(ffFormFields()->settings())->toBe([
        $element->uid => ['mode' => 'collect', 'placement' => 'details', 'replies' => true],
    ])->and(ffFormFields()->settingFor($other->uid)['mode'])->toBe('off')
        ->and($settings->toArray())->not->toHaveKey('publicFields');

    // Saving the settings page replaces it.
    $settings->setAttributes(['formFields' => [$other->uid => ['mode' => 'collect-and-show', 'placement' => 'details', 'replies' => '']]], false);

    expect(array_keys(ffFormFields()->settings()))->toBe([$other->uid])
        ->and($settings->getLegacyPublicFields())->toBeNull();
});

it('maps Public Fields in project config to Collect, never Collect and show, and says so in the Upgrade Report', function() {
    $element = ffField(new PlainText(['name' => 'Rating']));
    $projectConfig = Craft::$app->getProjectConfig();
    $schemaVersion = $projectConfig->get('plugins.comments.schemaVersion');
    $previous = $projectConfig->get('plugins.comments.settings');
    $db = Craft::$app->getDb();
    $logged = static fn(): ?int => V3UpgradeLog::countFor($db, m400000_000009_form_fields::PUBLIC_FIELDS_NOTICE);
    $before = $logged();

    try {
        $projectConfig->set('plugins.comments.schemaVersion', '4.0.6');
        $projectConfig->set('plugins.comments.settings.publicFields', [$element->getField()->handle]);
        $projectConfig->remove('plugins.comments.settings.formFields');
        Tests\Support\V3Site::writeProjectConfig();

        expect((new m400000_000009_form_fields(['db' => $db]))->safeUp())->toBeTrue();

        expect($projectConfig->get('plugins.comments.settings.formFields'))->toEqual([
            $element->uid => ['mode' => 'collect', 'placement' => 'details', 'replies' => true],
        ])->and($projectConfig->get('plugins.comments.settings.publicFields'))->toBeNull()
            ->and($logged())->toBe(($before ?? 0) + 1)
            ->and(V3UpgradeLog::describe(m400000_000009_form_fields::PUBLIC_FIELDS_NOTICE))->toContain('Answers on Comments');

        // Applying the YAML elsewhere, which already has the new setting, changes nothing.
        $projectConfig->set('plugins.comments.settings.publicFields', [$element->getField()->handle]);
        $projectConfig->set('plugins.comments.schemaVersion', '4.0.7');
        Tests\Support\V3Site::writeProjectConfig();
        (new m400000_000009_form_fields(['db' => $db]))->safeUp();

        expect($projectConfig->get('plugins.comments.settings.publicFields'))->toBe([$element->getField()->handle]);
    } finally {
        $projectConfig->remove('plugins.comments.settings.publicFields');
        $projectConfig->remove('plugins.comments.settings.formFields');
        $projectConfig->set('plugins.comments.settings', $previous);
        $projectConfig->set('plugins.comments.schemaVersion', $schemaVersion);
        Tests\Support\V3Site::writeProjectConfig();

        if ($db->tableExists('{{%comments_v3_log}}')) {
            $before === null
                ? $db->createCommand()->delete('{{%comments_v3_log}}', ['code' => m400000_000009_form_fields::PUBLIC_FIELDS_NOTICE])->execute()
                : $db->createCommand()->update('{{%comments_v3_log}}', ['count' => $before], ['code' => m400000_000009_form_fields::PUBLIC_FIELDS_NOTICE])->execute();
        }
    }
});

it('tells a Comments 3 site that its custom fields are off the form until it chooses them', function() {
    $layoutWith = ffField(new PlainText(['name' => 'Location']));
    $recorded = new V3UpgradeLog(Craft::$app->getDb());
    $before = V3SettingsReview::customFieldCount();

    expect($before)->toBeGreaterThan(0)
        ->and(V3UpgradeLog::describe(V3SettingsReview::FORM_FIELDS_NOTICE))->toContain('The Comment Form');

    // `record()` writes it alongside the other settings notices.
    $reflection = new ReflectionProperty(V3UpgradeLog::class, 'entries');
    V3SettingsReview::record($recorded);

    expect($reflection->getValue($recorded))->toHaveKey(V3SettingsReview::FORM_FIELDS_NOTICE)
        ->and($layoutWith->uid)->not->toBeEmpty();
});

it('keeps the site-wide themeConfig out of project config', function() {
    $settings = new Settings(['themeConfig' => ['primaryButton' => ['attributes' => ['class' => 'btn']]]]);

    expect($settings->themeConfig)->toHaveKey('primaryButton')
        ->and($settings->toArray())->not->toHaveKey('themeConfig');
});

// The registry
// =========================================================================

it('describes each of Craft’s 11 field types through the registry', function() {
    $types = ffFormFields()->getFieldTypes();

    foreach ([PlainText::class, Number::class, Email::class, Link::class, Date::class, Dropdown::class, MultiSelect::class, RadioButtons::class, Checkboxes::class, Lightswitch::class, Assets::class] as $class) {
        expect($types)->toHaveKey($class);
    }

    $inputs = [];

    foreach ([
        'text' => new PlainText(),
        'textarea' => new PlainText(['multiline' => true, 'charLimit' => 200]),
        'number' => new Number(['min' => 1, 'max' => 5]),
        'email' => new Email(),
        'url' => new Link(['types' => ['url']]),
        'date' => new Date(),
        'select' => new Dropdown(['options' => [['label' => 'One', 'value' => 'one']]]),
        'multiselect' => new MultiSelect(['options' => [['label' => 'One', 'value' => 'one']]]),
        'radio' => new RadioButtons(['options' => [['label' => 'One', 'value' => 'one']]]),
        'checkboxes' => new Checkboxes(['options' => [['label' => 'One', 'value' => 'one']]]),
        'toggle' => new Lightswitch(),
        'file' => new Assets(['maxRelations' => 2]),
    ] as $name => $field) {
        $inputs[$name] = ffFormFields()->getTypeFor($field)->describe($field, null);
    }

    expect(array_map(static fn(array $input): string => $input['type'], $inputs))->toBe([
        'text' => 'text', 'textarea' => 'textarea', 'number' => 'number', 'email' => 'email', 'url' => 'url',
        'date' => 'date', 'select' => 'select', 'multiselect' => 'select', 'radio' => 'radio',
        'checkboxes' => 'checkboxes', 'toggle' => 'toggle', 'file' => 'file',
    ])->and($inputs['textarea']['attributes'])->toBe(['maxlength' => 200])
        ->and($inputs['number']['attributes'])->toMatchArray(['min' => 1, 'max' => 5])
        ->and($inputs['multiselect']['multiple'])->toBeTrue()
        ->and($inputs['file'])->toMatchArray(['multiple' => true, 'maxFiles' => 2])
        ->and($inputs['file'])->toHaveKeys(['accept', 'maxSize'])
        ->and($inputs['file']['accept'])->not->toContain('.svg')->not->toContain('.html');
});

it('takes a third-party field type registered with EVENT_REGISTER_FORM_FIELD_TYPES, and flags types with none', function() {
    Event::on(FormFields::class, FormFields::EVENT_REGISTER_FORM_FIELD_TYPES, function(RegisterFormFieldTypesEvent $event) {
        $event->types[Color::class] = new class () extends FormFieldType {
            public function describe(FieldInterface $field, ?Comment $comment): array
            {
                // `swatches` isn't one of Comments' keys, so it's moved under `meta` beside the type's own.
                return ['type' => 'custom', 'multiple' => false, 'options' => [], 'attributes' => ['pattern' => '#[0-9a-f]{6}'], 'swatches' => 8, 'meta' => ['format' => 'hex']];
            }

            public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
            {
                if (!is_string($submitted) || !preg_match('/^#[0-9a-f]{6}$/i', $submitted)) {
                    throw new InvalidArgumentException('Choose a colour.');
                }

                return $submitted;
            }

            public function display(FieldInterface $field, mixed $value): ?array
            {
                $hex = $value?->getHex();

                return $hex ? ['value' => $hex, 'text' => 'Colour ' . $hex] : null;
            }
        };
    });
    Comments::$plugin->set('formFields', FormFields::class);

    $colour = ffField(new Color(['name' => 'Favourite colour', 'allowCustomColors' => true]));
    $table = ffField(new craft\fields\Entries(['name' => 'Kit list', 'sources' => '*']));
    ffModes([$colour->getField()->handle => 'collect-and-show:inline', $table->getField()->handle => 'collect']);
    $rows = array_column(ffFormFields()->settingsRows(), null, 'uid');

    expect($rows[$colour->uid])->toMatchArray(['supported' => true, 'mode' => 'collect-and-show'])
        ->and($rows[$table->uid])->toMatchArray(['supported' => false, 'mode' => 'off'])
        ->and($rows[$table->uid]['reason'])->toContain('can’t draw Entries fields')->toContain('EVENT_REGISTER_FORM_FIELD_TYPES');

    $entry = CommentsFixtureFactory::entry('Form fields third party');
    $viewer = ffApi()->viewer($entry);

    expect(array_column($viewer['editor']['fields']['comment'], 'handle'))->toBe([$colour->getField()->handle])
        ->and($viewer['editor']['fields']['comment'][0])->toMatchArray(['type' => 'custom', 'public' => true, 'placement' => 'inline', 'attributes' => ['pattern' => '#[0-9a-f]{6}'], 'meta' => ['swatches' => 8, 'format' => 'hex']])
        ->and($viewer['editor']['fields']['comment'][0])->not->toHaveKey('swatches');

    $error = ffError(fn() => ffCreate($entry, 'Bad colour', ffAdmin(), [$colour->getField()->handle => 'red']));

    expect($error->fieldErrors)->toBe(['fields.' . $colour->getField()->handle => ['Choose a colour.']])
        ->and(ffError(fn() => ffCreate($entry, 'Kit', ffAdmin(), [$table->getField()->handle => [1]]))->fieldErrors)
        ->toHaveKey('fields.' . $table->getField()->handle);

    $comment = ffCreate($entry, 'Good colour', ffAdmin(), [$colour->getField()->handle => '#336699']);
    $shown = ffApi()->thread($entry)['comments'][0]['fields'];

    expect((int)$comment->id)->toBeGreaterThan(0)
        ->and($shown)->toBe([[
            'handle' => $colour->getField()->handle,
            'label' => 'Favourite colour',
            'type' => 'custom',
            'placement' => 'inline',
            'value' => '#336699',
            'text' => 'Colour #336699',
        ]]);
});

it('gives a field class the nearest parent class’s description', function() {
    $subclass = new class () extends PlainText {
    };

    expect(ffFormFields()->getTypeFor($subclass))->toBe(ffFormFields()->getFieldTypes()[PlainText::class]);
});

// Reserved handles
// =========================================================================

it('reserves the handles that would collide with the site’s form, the data API and GraphQL', function() {
    foreach (['comment', 'email', 'name', 'protection', 'ownerId', 'parentUid', 'notifyReplies', 'operationId', 'content', 'guest', 'fields', 'editorContent', 'contentDocument', 'contentFormat', 'contentHtml', 'commentsData', 'viewer', 'parentComment', 'parentId', 'siteId'] as $handle) {
        expect(in_array($handle, FormFields::RESERVED_HANDLES, true) || in_array($handle, craft\base\Field::RESERVED_HANDLES, true))->toBeTrue($handle);
    }

    // A layout with one is refused when the settings page saves it.
    $field = new PlainText(['name' => 'Reserved', 'handle' => 'notifyReplies']);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $GLOBALS['ffFields'][] = $field;
    $layout = new FieldLayout(['type' => Comment::class]);
    $tab = new FieldLayoutTab(['layout' => $layout, 'name' => 'Content']);
    $tab->setElements([new CustomField($field)]);
    $layout->setTabs([$tab]);
    $layout->reservedFieldHandles = FormFields::RESERVED_HANDLES;

    expect($layout->validate())->toBeFalse()
        ->and(implode(' ', $layout->getFirstErrors()))->toContain('“notifyReplies” is a reserved word.');

    // One already in the layout, from project config, is never collected.
    $element = ffField($field);
    ffModes(['notifyReplies' => 'collect-and-show']);
    $entry = CommentsFixtureFactory::entry('Form fields reserved');
    $row = array_column(ffFormFields()->settingsRows(), null, 'uid')[$element->uid];

    expect($row['supported'])->toBeFalse()
        ->and($row['reason'])->toContain('“notifyReplies”')
        ->and(ffApi()->viewer($entry)['editor']['fields']['comment'])->toBe([])
        ->and(ffError(fn() => ffCreate($entry, 'Reserved', ffAdmin(), ['notifyReplies' => 'x']))->fieldErrors)->toHaveKey('fields.notifyReplies');
});

// Modes, conditions, replies and the posted-with list
// =========================================================================

it('describes and accepts Collect and Collect and show fields, and refuses Off ones (bypassing field conditions)', function() {
    $collected = ffField(new PlainText(['name' => 'Order number']));
    $shown = ffField(new Number(['name' => 'Rating', 'suffix' => ' stars']));
    $off = ffField(new PlainText(['name' => 'Staff note']), ['required' => true]);
    ffModes([$collected->getField()->handle => 'collect', $shown->getField()->handle => 'collect-and-show:inline']);
    $entry = CommentsFixtureFactory::entry('Form fields modes');
    $member = CommentsFixtureFactory::user('ffModes');
    ffAs($member);
    $described = ffApi()->viewer($entry)['editor']['fields']['comment'];

    expect(array_column($described, 'handle'))->toBe([$collected->getField()->handle, $shown->getField()->handle])
        ->and(array_column($described, 'public'))->toBe([false, true])
        ->and(array_column($described, 'placement'))->toBe([null, 'inline'])
        ->and(array_column($described, 'uid'))->toBe([$collected->uid, $shown->uid]);

    // An Off field is refused, however the request is built, and its being required doesn't stop anyone.
    $error = ffError(fn() => ffCreate($entry, 'With a staff note', $member, [$off->getField()->handle => 'Injected']));

    expect($error->errorCode)->toBe('validation_failed')
        ->and($error->fieldErrors)->toBe(['fields.' . $off->getField()->handle => ['This field can’t be filled in here.']]);

    $comment = ffCreate($entry, 'Rated', $member, [$collected->getField()->handle => 'ORD-1', $shown->getField()->handle => '4']);

    expect($comment->getFieldValue($collected->getField()->handle))->toBe('ORD-1')
        ->and($comment->getFieldValue($shown->getField()->handle))->toEqual(4)
        ->and($comment->getFieldValue($off->getField()->handle))->toBeNull()
        ->and($comment->getPostedFieldUids())->toBe([$collected->uid, $shown->uid]);

    $fields = ffApi()->thread($entry)['comments'][0]['fields'];

    expect($fields)->toBe([[
        'handle' => $shown->getField()->handle,
        'label' => 'Rating',
        'type' => 'number',
        'placement' => 'inline',
        'value' => 4,
        'text' => '4 stars',
    ]]);
});

it('requires a required field only of someone it’s offered to', function() {
    $required = ffField(new PlainText(['name' => 'Location']), ['required' => true]);
    ffModes([$required->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields required');
    $member = CommentsFixtureFactory::user('ffRequired');

    $error = ffError(fn() => ffCreate($entry, 'No location', $member));

    expect($error->errorCode)->toBe('validation_failed')
        ->and($error->fieldErrors)->toHaveKey('fields.' . $required->getField()->handle);

    // A reply isn't offered it, so it isn't required there.
    $root = ffCreate($entry, 'Has a location', $member, [$required->getField()->handle => 'Hobart']);
    $reply = ffCreate($entry, 'A reply', $member, [], $root);

    expect((int)$reply->parentId)->toBe((int)$root->id);
});

it('applies a user condition when describing and when validating, holding guests to it (bypassing field conditions)', function() {
    $group = new UserGroup(['name' => 'Members ' . bin2hex(random_bytes(3)), 'handle' => 'ffMembers' . bin2hex(random_bytes(3))]);
    expect(Craft::$app->getUserGroups()->saveGroup($group))->toBeTrue();

    try {
        $membersOnly = ffField(new PlainText(['name' => 'Membership number']), [
            'required' => true,
            'userCondition' => [
                'class' => UserCondition::class,
                'conditionRules' => [['class' => GroupConditionRule::class, 'operator' => 'in', 'values' => [$group->uid]]],
            ],
        ]);
        $everyone = ffField(new PlainText(['name' => 'Location']));
        ffModes([$membersOnly->getField()->handle => 'collect', $everyone->getField()->handle => 'collect']);
        $entry = CommentsFixtureFactory::entry('Form fields user condition');
        $member = CommentsFixtureFactory::user('ffGroupMember');
        Craft::$app->getUsers()->assignUserToGroups((int)$member->id, [(int)$group->id]);
        $member = Craft::$app->getUsers()->getUserById((int)$member->id);
        $other = CommentsFixtureFactory::user('ffNotMember');
        $handles = static fn(?User $user): array => (function() use ($user, $entry): array {
            ffAs($user);

            return array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'handle');
        })();

        expect($handles($member))->toBe([$membersOnly->getField()->handle, $everyone->getField()->handle])
            ->and($handles($other))->toBe([$everyone->getField()->handle])
            ->and($handles(null))->toBe([$everyone->getField()->handle]);

        // Hidden from them, so refused if they send it, and not required of them.
        foreach (['member outside the group' => $other, 'guest' => null] as $label => $user) {
            $error = ffError(fn() => ffCreate($entry, 'Sneaking in', $user, [$membersOnly->getField()->handle => 'M-1']));

            expect($error->fieldErrors, $label)->toBe(['fields.' . $membersOnly->getField()->handle => ['This field can’t be filled in here.']]);
            expect(ffCreate($entry, 'Without it', $user)->id, $label)->not->toBeNull();
        }

        expect(ffCreate($entry, 'Member', $member, [$membersOnly->getField()->handle => 'M-2'])->getFieldValue($membersOnly->getField()->handle))->toBe('M-2');
    } finally {
        Craft::$app->getUserGroups()->deleteGroupById((int)$group->id);
    }
});

it('applies an element condition against the comment as it would be created, and flags conditions on entered values', function() {
    $entry = CommentsFixtureFactory::entry('Form fields element condition');
    $elsewhere = CommentsFixtureFactory::entry('Form fields other owner');
    $onEntry = ffField(new PlainText(['name' => 'Only here']), [
        'elementCondition' => [
            'class' => CommentCondition::class,
            'elementType' => Comment::class,
            'conditionRules' => [['class' => OwnerConditionRule::class, 'elementId' => $entry->id]],
        ],
    ]);
    $dependent = ffField(new PlainText(['name' => 'Follow-up']), [
        'elementCondition' => [
            'class' => CommentCondition::class,
            'elementType' => Comment::class,
            'conditionRules' => [['class' => TextFieldConditionRule::class, 'fieldUid' => $onEntry->getField()->uid, 'operator' => '=', 'value' => 'yes']],
        ],
    ]);
    ffModes([$onEntry->getField()->handle => 'collect', $dependent->getField()->handle => 'collect']);
    $member = CommentsFixtureFactory::user('ffElementCondition');
    ffAs($member);

    expect(array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'handle'))->toBe([$onEntry->getField()->handle])
        ->and(array_column(ffApi()->viewer($elsewhere)['editor']['fields']['comment'], 'handle'))->toBe([])
        ->and(ffError(fn() => ffCreate($elsewhere, 'Elsewhere', $member, [$onEntry->getField()->handle => 'x']))->fieldErrors)->toHaveKey('fields.' . $onEntry->getField()->handle)
        // Evaluated as the form opens, with nothing entered, whatever is sent with it.
        ->and(ffError(fn() => ffCreate($entry, 'Follow-up', $member, [$onEntry->getField()->handle => 'yes', $dependent->getField()->handle => 'x']))->fieldErrors)->toHaveKey('fields.' . $dependent->getField()->handle);

    $rows = array_column(ffFormFields()->settingsRows(), null, 'uid');

    expect($rows[$dependent->uid]['notes'])->toHaveCount(1)
        ->and($rows[$dependent->uid]['notes'][0])->toContain('checks conditions once')
        ->and($rows[$onEntry->uid]['notes'])->toBe([]);
});

it('offers a field on replies only with On replies too', function() {
    $rating = ffField(new Number(['name' => 'Rating']));
    $location = ffField(new PlainText(['name' => 'Location']));
    ffModes([$rating->getField()->handle => 'collect-and-show', $location->getField()->handle => 'collect:replies']);
    $entry = CommentsFixtureFactory::entry('Form fields replies');
    $member = CommentsFixtureFactory::user('ffReplies');
    ffAs($member);
    $fields = ffApi()->viewer($entry)['editor']['fields'];

    expect(array_column($fields['comment'], 'handle'))->toBe([$rating->getField()->handle, $location->getField()->handle])
        ->and(array_column($fields['reply'], 'handle'))->toBe([$location->getField()->handle]);

    $root = ffCreate($entry, 'Top level', $member, [$rating->getField()->handle => 5]);
    $error = ffError(fn() => ffCreate($entry, 'Reply with a rating', $member, [$rating->getField()->handle => 1], $root));
    $reply = ffCreate($entry, 'Reply with a location', $member, [$location->getField()->handle => 'Nelson'], $root);

    expect($error->fieldErrors)->toBe(['fields.' . $rating->getField()->handle => ['This field can’t be filled in here.']])
        ->and($reply->getPostedFieldUids())->toBe([$location->uid]);
});

it('edits exactly the fields a comment was posted with, minus any turned Off, with its collected values', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    $rating = ffField(new Dropdown(['name' => 'Rating', 'options' => [['label' => 'Good', 'value' => 'good'], ['label' => 'Bad', 'value' => 'bad']]]));
    ffModes([$order->getField()->handle => 'collect', $rating->getField()->handle => 'collect-and-show']);
    $entry = CommentsFixtureFactory::entry('Form fields edits');
    $author = CommentsFixtureFactory::user('ffEditor');
    $comment = ffCreate($entry, 'Posted', $author, [$order->getField()->handle => 'ORD-SECRET-77', $rating->getField()->handle => 'good']);

    // A field added later doesn't change what the comment's edit form asks for.
    $later = ffField(new PlainText(['name' => 'Added later']));
    ffModes([$order->getField()->handle => 'collect', $rating->getField()->handle => 'collect-and-show', $later->getField()->handle => 'collect']);
    ffAs($author);
    $edit = ffApi()->edit($comment->uid);

    expect((array)$edit['fields'])->toBe([$order->getField()->handle => 'ORD-SECRET-77', $rating->getField()->handle => 'good'])
        ->and(array_column($edit['editor']['fields'], 'handle'))->toBe([$order->getField()->handle, $rating->getField()->handle])
        ->and(ffError(fn() => ffApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Edited'], 'fields' => [$later->getField()->handle => 'x']]))->fieldErrors)
        ->toHaveKey('fields.' . $later->getField()->handle);

    // Turned Off since, it's no longer asked for or accepted, and its value is left alone.
    ffModes([$rating->getField()->handle => 'collect-and-show', $later->getField()->handle => 'collect']);
    $edit = ffApi()->edit($comment->uid);

    expect((array)$edit['fields'])->toBe([$rating->getField()->handle => 'good'])
        ->and(ffError(fn() => ffApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Edited'], 'fields' => [$order->getField()->handle => 'changed']]))->fieldErrors)
        ->toHaveKey('fields.' . $order->getField()->handle);

    ffApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Edited'], 'fields' => [$rating->getField()->handle => 'bad']]);
    $saved = Comment::find()->id($comment->id)->status(null)->one();

    expect($saved->getFieldValue($rating->getField()->handle)->value)->toBe('bad')
        ->and($saved->getFieldValue($order->getField()->handle))->toBe('ORD-SECRET-77');

    // Nobody but its author gets the edit read.
    ffAs(CommentsFixtureFactory::user('ffNotAuthor'));

    expect(ffError(fn() => ffApi()->edit($comment->uid))->errorCode)->toBe('forbidden');
});

it('gives an older comment without a posted-with list the fields that apply to its author now, and records them', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    ffModes([$order->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields older comment');
    $author = CommentsFixtureFactory::user('ffOlder');
    $comment = ffCreate($entry, 'Old', $author);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['postedFields' => null], ['id' => $comment->id])->execute();
    ffAs($author);

    expect(array_column(ffApi()->edit($comment->uid)['editor']['fields'], 'handle'))->toBe([$order->getField()->handle]);

    ffApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Old, edited'], 'fields' => [$order->getField()->handle => 'ORD-9']]);

    expect(Comment::find()->id($comment->id)->status(null)->one()->getPostedFieldUids())->toBe([$order->uid]);
});

// Displays
// =========================================================================

it('shows each built-in type as text, links only web addresses, and puts files and options as data', function() {
    $fields = [
        'link' => ffField(new Link(['name' => 'Website', 'types' => ['url']])),
        'agree' => ffField(new Lightswitch(['name' => 'Agree', 'onLabel' => 'Signed up', 'offLabel' => 'Not yet'])),
        'tags' => ffField(new Checkboxes(['name' => 'Tags', 'options' => [['label' => 'One <b>', 'value' => 'one'], ['label' => 'Two', 'value' => 'two']]])),
        'when' => ffField(new Date(['name' => 'Visited'])),
        'pace' => ffField(new RadioButtons(['name' => 'Pace', 'options' => [['label' => 'Fast', 'value' => 'fast'], ['label' => 'Slow', 'value' => 'slow']]])),
    ];
    ffModes(array_map(static fn(CustomField $element): string => 'collect-and-show', array_combine(array_map(static fn(CustomField $element): string => $element->getField()->handle, $fields), $fields)));
    $entry = CommentsFixtureFactory::entry('Form fields displays');
    ffCreate($entry, 'All of them', ffAdmin(), [
        $fields['link']->getField()->handle => 'https://example.test/run?x=1',
        $fields['agree']->getField()->handle => 'false',
        $fields['tags']->getField()->handle => ['one', 'two'],
        $fields['when']->getField()->handle => '2026-11-09',
        $fields['pace']->getField()->handle => 'slow',
    ]);
    $shown = array_column(ffApi()->thread($entry)['comments'][0]['fields'], null, 'handle');

    expect($shown[$fields['link']->getField()->handle])->toMatchArray(['type' => 'url', 'value' => 'https://example.test/run?x=1', 'text' => 'https://example.test/run?x=1'])
        ->and($shown[$fields['agree']->getField()->handle])->toMatchArray(['type' => 'toggle', 'value' => false, 'text' => 'Not yet'])
        ->and($shown[$fields['tags']->getField()->handle])->toMatchArray(['type' => 'checkboxes', 'value' => ['one', 'two'], 'text' => 'One <b>, Two'])
        ->and($shown[$fields['when']->getField()->handle])->toMatchArray(['type' => 'date', 'value' => '2026-11-09'])
        ->and($shown[$fields['pace']->getField()->handle])->toMatchArray(['type' => 'radio', 'value' => 'slow', 'text' => 'Slow']);

    // Anything but `http` and `https` is shown as text, never as a link.
    $url = ffFormFields()->getTypeFor($fields['link']->getField());

    foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:x', '//evil.example.test', 'mailto:a@example.test'] as $address) {
        expect($url->display($fields['link']->getField(), $address))->toMatchArray(['value' => null, 'text' => $address]);
    }

    expect(UrlType::isWebAddress('HTTPS://example.test'))->toBeTrue()
        ->and(UrlType::isWebAddress('https:///nohost'))->toBeFalse();
});

it('refuses values a type can’t read', function() {
    $number = ffField(new Number(['name' => 'Rating']));
    $choice = ffField(new Dropdown(['name' => 'Choice', 'options' => [['label' => 'A', 'value' => 'a']]]));
    $agree = ffField(new Lightswitch(['name' => 'Agree']));
    $when = ffField(new Date(['name' => 'When']));
    $files = ffField(new Assets(['name' => 'Photos']));
    ffModes(array_fill_keys(array_map(static fn(CustomField $element): string => $element->getField()->handle, [$number, $choice, $agree, $when, $files]), 'collect'));
    $entry = CommentsFixtureFactory::entry('Form fields bad values');

    foreach ([
        [$number, 'lots', 'Enter a number.'],
        [$choice, 'z', 'Choose one of the options.'],
        [$agree, 'maybe', 'Send true or false.'],
        [$when, 'next tuesday', 'Enter a date.'],
        [$files, ['data' => 'data:image/png;base64,AAAA', 'filename' => 'x.png'], 'Upload files first, then send the IDs you were given.'],
    ] as [$element, $value, $message]) {
        expect(ffError(fn() => ffCreate($entry, 'Bad', ffAdmin(), [$element->getField()->handle => $value]))->fieldErrors)
            ->toBe(['fields.' . $element->getField()->handle => [$message]]);
    }

    expect(ffError(fn() => ffCreate($entry, 'Not keyed', ffAdmin(), ['just', 'values']))->errorCode)->toBe('validation_failed');
});

// Privacy (gate 3): collected values on every public path
// =========================================================================

it('keeps collected values out of the thread, replies, comment, structured data and the data cache (collected values leaking)', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    $off = ffField(new PlainText(['name' => 'Staff note']));
    $location = ffField(new PlainText(['name' => 'Location']));
    ffModes([$order->getField()->handle => 'collect:replies', $location->getField()->handle => 'collect-and-show:inline:replies']);
    $entry = CommentsFixtureFactory::entry('Form fields public paths');
    $root = ffCreate($entry, 'Root', ffAdmin(), [$order->getField()->handle => 'ORDER-SECRET-4471', $location->getField()->handle => 'Rotorua']);
    $reply = ffCreate($entry, 'Reply', null, [$order->getField()->handle => 'ORDER-SECRET-4472'], $root);
    $root->setFieldValue($off->getField()->handle, 'STAFF-SECRET-1');
    Craft::$app->getElements()->saveElement($root, false);
    ffApi()->invalidateCaches();
    ffAs(null);

    $public = ffJson([
        ffApi()->thread($entry),
        ffApi()->replies($root->uid),
        ffApi()->comment($reply->uid),
        ffApi()->structuredData($entry, ['includeAuthors' => true]),
    ]);

    expect($public)->toContain('Rotorua')
        ->not->toContain('ORDER-SECRET')
        ->not->toContain('STAFF-SECRET')
        ->and(ffCacheContents())->not->toContain('ORDER-SECRET')->not->toContain('STAFF-SECRET');

    // A guest's `viewer`, and a member's, describe the form but carry no values.
    expect(ffJson(ffApi()->viewer($entry)))->not->toContain('ORDER-SECRET');
    ffAs(CommentsFixtureFactory::user('ffViewerReader'));
    expect(ffJson(ffApi()->viewer($entry, uids: [$root->uid, $reply->uid])))->not->toContain('ORDER-SECRET')->not->toContain('STAFF-SECRET');

    // Its author's own `edit` is the one place on the site it's given back.
    ffAs(ffAdmin());
    expect(ffJson(ffApi()->edit($root->uid)))->toContain('ORDER-SECRET-4471')->not->toContain('STAFF-SECRET');
});

it('gives Twig only the values a comment shows, through comment.shownFields', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    $location = ffField(new PlainText(['name' => 'Location']));
    ffModes([$order->getField()->handle => 'collect:replies', $location->getField()->handle => 'collect-and-show:inline:replies']);
    $entry = CommentsFixtureFactory::entry('Form fields shown in Twig');
    $comment = ffCreate($entry, 'Root', ffAdmin(), [$order->getField()->handle => 'ORDER-TWIG-1', $location->getField()->handle => 'Napier']);
    $comment = Comment::find()->id($comment->id)->status(null)->one();

    $shown = $comment->getShownFields();
    $twig = Craft::$app->getView()->renderString('{% for field in comment.shownFields %}{{ field.label }}: {{ field.text }}|{% endfor %}', ['comment' => $comment]);

    expect(array_column($shown, 'handle'))->toBe([$location->getField()->handle])
        ->and($shown[0])->toHaveKeys(['handle', 'label', 'type', 'placement', 'value', 'text'])
        ->and($twig)->toContain('Napier')->not->toContain('ORDER-TWIG')
        // Read by its handle, a field gives its value whatever its mode, which is why the docs use shownFields.
        ->and((string)$comment->getFieldValue($order->getField()->handle))->toBe('ORDER-TWIG-1');
});

it('keeps collected values out of what a moderator gets on the site (SD-42)', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    ffModes([$order->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields moderator');
    $author = CommentsFixtureFactory::user('ffModeratedAuthor');
    Comments::$plugin->getSettings()->requireModeration = true;
    $held = ffCreate($entry, 'Held with an order', $author, [$order->getField()->handle => 'ORDER-HELD-9']);
    Comments::$plugin->getSettings()->requireModeration = false;
    $moderator = CommentsFixtureFactory::user('ffModerator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-moderate', 'comments-trash']);
    ffAs(Craft::$app->getUsers()->getUserById((int)$moderator->id));

    $viewer = ffApi()->viewer($entry);

    expect(array_column($viewer['extraComments'], 'uid'))->toContain($held->uid)
        ->and(ffJson($viewer))->not->toContain('ORDER-HELD-9')
        ->and(ffJson(ffApi()->commentData($held)))->not->toContain('ORDER-HELD-9');
});

it('keeps collected values out of notification emails', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    ffModes([$order->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields emails');
    $comment = ffCreate($entry, 'Emailed', ffAdmin(), [$order->getField()->handle => 'ORDER-EMAIL-5']);
    $templates = Comments::$plugin->getTemplates();
    $variables = ['element' => $entry, 'comment' => $comment, 'user' => ffAdmin()];

    // Comments' own messages don't include custom fields, and a site's own message can't reach one,
    // because the email templates are sandboxed to a comment's own details.
    $body = '';

    foreach (['comments_author_notification_body', 'comments_reply_notification_body', 'comments_mention_notification_body', 'comments_moderator_notification_body', 'comments_admin_notification_body'] as $key) {
        $body .= $templates->renderSandboxedString(Craft::t('comments', $key), $variables);
    }

    try {
        $custom = $templates->renderSandboxedString('{{ comment.' . $order->getField()->handle . ' }}', $variables);
    } catch (Throwable) {
        $custom = '';
    }

    expect($body)->not->toContain('ORDER-EMAIL-5')
        ->and($custom)->not->toContain('ORDER-EMAIL-5');
});

it('warns in Dev Mode when a handler puts a collected value into public data (collected values leaking)', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    ffModes([$order->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields event data');
    ffCreate($entry, 'With an order', ffAdmin(), [$order->getField()->handle => 'ORDER-EVENT-3']);
    $handle = $order->getField()->handle;
    Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event) use ($handle) {
        foreach ($event->comments as $uid => $comment) {
            $event->custom[$uid] = ['order' => $comment->getFieldValue($handle)];
        }
    });
    $logger = Craft::getLogger();
    $before = count($logger->messages);
    ffApi()->invalidateCaches();
    ffApi()->thread($entry);
    $warnings = array_filter(array_slice($logger->messages, $before), static fn(array $message): bool => str_contains((string)$message[0], 'private details to the public data'));

    expect($warnings)->not->toBe([]);
});

it('gives GraphQL on a schema that can’t moderate only the fields a comment shows, through Craft’s own field resolvers too', function() {
    // Searchable, so a search could otherwise find a comment by its values.
    $order = ffField(new PlainText(['name' => 'Order number', 'searchable' => true]));
    $location = ffField(new PlainText(['name' => 'Location', 'searchable' => true]));
    $off = ffField(new PlainText(['name' => 'Staff note', 'searchable' => true]));
    $agree = ffField(new Lightswitch(['name' => 'Agree']));
    $related = ffField(new craft\fields\Entries(['name' => 'Related order', 'sources' => '*']));
    ffModes([$order->getField()->handle => 'collect', $location->getField()->handle => 'collect-and-show', $agree->getField()->handle => 'collect', $related->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields GraphQL');
    $relatedEntry = CommentsFixtureFactory::entry('Form fields related order');
    $comment = ffCreate($entry, 'Through GraphQL', ffAdmin(), [$order->getField()->handle => 'ORDER-GQL-1', $location->getField()->handle => 'Hobart', $agree->getField()->handle => true]);
    $comment->setFieldValue($off->getField()->handle, 'STAFF-GQL-1');
    $comment->setFieldValue($related->getField()->handle, [$relatedEntry->id]);
    Craft::$app->getElements()->saveElement($comment, false);
    // Craft indexes for search in a queue job, which doesn't run here.
    Craft::$app->getSearch()->indexElementAttributes($comment);
    [$o, $l, $s, $a, $p] = array_map(static fn(CustomField $element): string => $element->getField()->handle, [$order, $location, $off, $agree, $related]);
    $query = "{ comments(ownerId: {$entry->id}) { id ... on Comment { $o $l $s $a $p { id } } } }";
    $sections = 'sections.' . $relatedEntry->getSection()->uid . ':read';

    $public = ffGql(['comments:read', $sections], $query, true);
    $private = ffGql(['comments:read', $sections], $query);
    $moderating = ffGql(['comments:read', 'comments:moderate', $sections], $query);

    expect($public['errors'] ?? [])->toBe([])
        ->and($public['data']['comments'][0])->toMatchArray([$o => null, $l => 'Hobart', $s => null, $a => null, $p => null])
        ->and($private['data']['comments'][0])->toMatchArray([$o => null, $l => 'Hobart', $s => null])
        ->and($moderating['errors'] ?? [])->toBe([])
        ->and($moderating['data']['comments'][0])->toMatchArray([$o => 'ORDER-GQL-1', $l => 'Hobart', $s => 'STAFF-GQL-1', $a => true])
        // Once another site exists, Craft can give a related element once per site.
        ->and(array_values(array_unique(array_column($moderating['data']['comments'][0][$p] ?? [], 'id'))))->toBe([(string)$relatedEntry->id]);

    // Nor can it order, search or filter by one, for anyone who can't see everything.
    ffAs(null);
    $ordered = ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, orderBy: \"$o\") { id } }", true);
    $searched = ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, search: \"ORDER-GQL-1\") { id } }", true);
    $searchedModerating = ffGql(['comments:read', 'comments:moderate'], "{ comments(ownerId: {$entry->id}, search: \"ORDER-GQL-1\") { id } }");
    $searchedShown = ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, search: \"Hobart\") { id } }", true);
    $relatedTo = ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, relatedTo: [{$relatedEntry->id}]) { id } }", true);

    expect($ordered['errors'][0]['message'] ?? null)->toBe('Comments can’t be ordered by that.')
        ->and($searched['data']['comments'] ?? null)->toBe([])
        ->and(array_column($searchedModerating['data']['comments'] ?? [], 'id'))->toBe([(string)$comment->id])
        // A shown field can hold values entered while it was collected, so no custom field is searched.
        ->and($searchedShown['data']['comments'] ?? null)->toBe([])
        ->and($relatedTo['errors'][0]['message'] ?? null)->toBe('Comments can’t be filtered by related elements here.');

    // The data API's `commentsComment` gives the same `fields` as REST.
    $read = ffGql(['comments:read'], "{ commentsComment(uid: \"{$comment->uid}\") { data } }", true);

    expect(ffJson($read))->not->toContain('ORDER-GQL-1')->toContain('Hobart');
});

it('takes Collect and show fields through GraphQL mutations under the same rules, and refuses Off ones', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    $rating = ffField(new Number(['name' => 'Rating']));
    $off = ffField(new PlainText(['name' => 'Staff note']));
    ffModes([$order->getField()->handle => 'collect', $rating->getField()->handle => 'collect-and-show']);
    $entry = CommentsFixtureFactory::entry('Form fields mutations');
    [$o, $r, $s] = array_map(static fn(CustomField $element): string => $element->getField()->handle, [$order, $rating, $off]);
    $scope = ['comments:read', 'comments:edit', 'comments:save'];

    $refused = ffGql($scope, "mutation { createComment(ownerId: {$entry->id}, comment: \"Off\", $s: \"x\") { id } }");
    $created = ffGql($scope, "mutation { createComment(ownerId: {$entry->id}, comment: \"Rated\", $o: \"ORD-GQL\", $r: 3) { id } }");

    expect($refused['errors'][0]['message'] ?? '')->toContain($s)
        ->and($created['errors'] ?? [])->toBe([]);

    $comment = Comment::find()->id((int)$created['data']['createComment']['id'])->status(null)->one();

    expect($comment->getFieldValue($o))->toBe('ORD-GQL')
        ->and($comment->getFieldValue($r))->toEqual(3)
        ->and($comment->getPostedFieldUids())->toBe([$order->uid, $rating->uid]);

    $saved = ffGql($scope, "mutation { saveComment(id: {$comment->id}, comment: \"Rerated\", $r: 5) { id } }");

    expect($saved['errors'] ?? [])->toBe([])
        ->and(Comment::find()->id($comment->id)->status(null)->one()->getFieldValue($r))->toEqual(5);
});

// The follow-ups from stage E
// =========================================================================

it('says which thread a linked comment is in', function() {
    $entry = CommentsFixtureFactory::entry('Form fields deep link');
    $comment = ffCreate($entry, 'Linked', ffAdmin());

    $read = ffApi()->comment($comment->uid);
    $gql = ffGql(['comments:read'], "{ commentsComment(id: {$comment->id}) { data } }", true);

    expect($read)->toMatchArray(['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId, 'targetUid' => $comment->uid])
        ->and($gql['data']['commentsComment']['data'] ?? null)->toMatchArray(['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId]);
});

it('merges the site-wide themeConfig first, keeping only what the renderer takes', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->themeConfig = [
        'primaryButton' => ['resetClass' => true, 'attributes' => ['class' => 'btn btn-primary', 'data-track' => 'comments']],
        'input' => ['attributes' => ['class' => 'form-input', 'onclick' => 'alert(1)', 'data-comments-action' => 'x', 'style' => 'color:red']],
        'notAControl' => ['attributes' => ['class' => 'x']],
        'link' => 'not an object',
    ];
    $entry = CommentsFixtureFactory::entry('Form fields theme config');
    Craft::$app->getRequest()->setIsConsoleRequest(false);

    try {
        $html = (string)Comments::$plugin->getComments()->render($entry, [
            'themeConfig' => ['primaryButton' => ['attributes' => ['class' => ['btn', 'btn-sm'], 'title' => 'Post it']], 'avatar' => ['attributes' => ['aria-label' => 'Avatar']]],
        ]);
    } finally {
        Craft::$app->getRequest()->setIsConsoleRequest(true);
    }

    preg_match('/data-comments-config="([^"]*)"/', $html, $matches);
    $config = json_decode(html_entity_decode($matches[1] ?? '{}', ENT_QUOTES), true);

    expect($config['themeConfig'])->toBe([
        'primaryButton' => ['resetClass' => true, 'attributes' => ['class' => ['btn', 'btn-sm'], 'data-track' => 'comments', 'title' => 'Post it']],
        'input' => ['attributes' => ['class' => 'form-input']],
        'avatar' => ['attributes' => ['aria-label' => 'Avatar']],
    ]);
});

it('passes render()’s field overrides to the mount point as custom element names only', function() {
    $entry = CommentsFixtureFactory::entry('Form fields render overrides');
    $config = static function(array $options) use ($entry): array {
        Craft::$app->getRequest()->setIsConsoleRequest(false);

        try {
            $html = (string)Comments::$plugin->getComments()->render($entry, $options);
        } finally {
            Craft::$app->getRequest()->setIsConsoleRequest(true);
        }

        preg_match('/data-comments-config="([^"]*)"/', $html, $matches);

        return json_decode(html_entity_decode($matches[1] ?? '{}', ENT_QUOTES), true);
    };

    $kept = $config(['fields' => [
        'location' => ['input' => 'site-location-input', 'display' => 'site-location'],
        'rating' => ['display' => 'star-rating', 'input' => 'NotAnElement', 'label' => 'x-label'],
        'mood' => ['input' => 'moodpicker'],
        'colour' => ['input' => 'font-face'],
        'script' => ['input' => '<script>alert(1)</script>'],
        'fn' => ['input' => ['not' => 'a name']],
        'bad-handle' => ['input' => 'site-input'],
        'list' => ['site-input'],
        'plain' => 'site-input',
    ]]);

    expect($kept['fields'])->toBe([
        'location' => ['input' => 'site-location-input', 'display' => 'site-location'],
        'rating' => ['display' => 'star-rating'],
    ])
        ->and($config([]))->not->toHaveKey('fields')
        ->and($config(['fields' => ['only' => ['input' => 'nope']]]))->not->toHaveKey('fields')
        ->and($config(['fields' => ['site-input']]))->not->toHaveKey('fields')
        ->and(Frontend::isCustomElementName('my-element'))->toBeTrue()
        ->and(Frontend::isCustomElementName('my.element-2'))->toBeTrue()
        ->and(Frontend::isCustomElementName('My-Element'))->toBeFalse()
        ->and(Frontend::isCustomElementName('-element'))->toBeFalse()
        ->and(Frontend::isCustomElementName('annotation-xml'))->toBeFalse();
});

it('gives the renderer the file tray’s words through Craft’s translations', function() {
    $labels = Comments::$plugin->getApi()->labels();

    expect($labels)->toMatchArray([
        'addFiles' => 'Add files',
        'addFile' => 'Add a file',
        'removeFile' => 'Remove {name}',
        'retryUpload' => 'Try {name} again',
        'uploading' => 'Uploading…',
        'uploaded' => '{name} uploaded.',
        'uploadFailed' => '{name} didn’t upload.',
        'fileRemoved' => '{name} removed.',
        'uploadLimits' => 'Up to {count} files, {size} each.',
        'uploadLimit' => 'Up to {size}.',
        'fileTypeNotAllowed' => '{name} can’t be added here. Choose {types}.',
        'fileTooLarge' => '{name} is larger than {size}.',
        'tooManyFiles' => 'You can add up to {count} files here.',
    ]);

    // Each is a source string in the English translations, so a site can translate it.
    $english = require dirname(__DIR__, 2) . '/src/translations/en/comments.php';

    foreach (['Add files', 'Remove {name}', 'Up to {count} files, {size} each.', 'You can add up to {count} files here.'] as $source) {
        expect($english)->toHaveKey($source);
    }
});

// The security review's findings (gate 9)
// =========================================================================

it('shows a value only if it was entered while its field was shown, so switching a field to show publishes nothing old (M2)', function() {
    $location = ffField(new PlainText(['name' => 'Location']));
    $handle = $location->getField()->handle;
    ffModes([$handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields switched to show');
    $author = CommentsFixtureFactory::user('ffSwitch');
    $before = ffCreate($entry, 'Posted while collected', $author, [$handle => 'COLLECTED-HOBART']);
    $older = ffCreate($entry, 'Posted before the record', $author, [$handle => 'UNRECORDED-NELSON']);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['postedFields' => null], ['id' => $older->id])->execute();

    expect($before->getPostedFieldModes())->toBe([$location->uid => 'collect']);

    ffModes([$handle => 'collect-and-show']);
    ffAs(null);
    $public = ffJson([ffApi()->thread($entry), ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}) { ... on Comment { $handle } } }", true)]);

    expect($public)->not->toContain('COLLECTED-HOBART')->not->toContain('UNRECORDED-NELSON');

    // Its author's edit, under the form that says it's shown, records it as shown.
    ffAs($author);
    ffApi()->update(['uid' => $before->uid, 'content' => ['format' => 'plain', 'text' => 'Edited'], 'fields' => [$handle => 'COLLECTED-HOBART']]);
    ffApi()->invalidateCaches();

    expect(ffJson(ffApi()->thread($entry)))->toContain('COLLECTED-HOBART')->not->toContain('UNRECORDED-NELSON');

    // A save in the control panel, by someone who can use it, records it too.
    $request = Craft::$app->getRequest();
    $request->setIsConsoleRequest(false);
    $request->setIsCpRequest(true);
    ffAs(ffAdmin());

    try {
        $comment = Comment::find()->id($older->id)->status(null)->one();
        expect(Craft::$app->getElements()->saveElement($comment, false))->toBeTrue();
    } finally {
        $request->setIsCpRequest(false);
        $request->setIsConsoleRequest(true);
    }

    ffApi()->invalidateCaches();

    expect(Comment::find()->id($older->id)->status(null)->one()->getPostedFieldModes())->toBe([$location->uid => 'collect-and-show'])
        ->and(ffJson(ffApi()->thread($entry)))->toContain('UNRECORDED-NELSON');
});

it('reads every field only for someone who can moderate, on a schema that can (L2)', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    ffModes([$order->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields moderating schema');
    ffCreate($entry, 'Ordered', ffAdmin(), [$order->getField()->handle => 'ORDER-L2-1']);
    $query = "{ comments(ownerId: {$entry->id}) { ... on Comment { {$order->getField()->handle} } } }";

    ffAs(CommentsFixtureFactory::user('ffNotModerator'));
    $member = ffGql(['comments:read', 'comments:moderate'], $query);
    ffAs(ffAdmin());
    $moderator = ffGql(['comments:read', 'comments:moderate'], $query);

    expect($member['data']['comments'][0])->toBe([$order->getField()->handle => null])
        ->and($moderator['data']['comments'][0][$order->getField()->handle] ?? null)->toBe('ORDER-L2-1');
});

it('orders comments for a schema that can’t moderate only by a fixed list of columns (L1)', function() {
    $entry = CommentsFixtureFactory::entry('Form fields order columns');
    ffCreate($entry, 'Ordered', ffAdmin());
    ffAs(null);

    foreach (['elements_sites.content', 'content', 'FIELD(elements.id, 1)', 'comments_comments.postedFields', 'name desc'] as $orderBy) {
        $result = ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, orderBy: \"$orderBy\") { id } }", true);

        // Craft refuses an expression itself.
        expect($result['errors'][0]['message'] ?? null, $orderBy)->toBe(str_contains($orderBy, '(') ? 'Internal server error' : 'Comments can’t be ordered by that.');
    }

    foreach (['commentDate desc', 'elements.id asc, comments_comments.depth', 'dateCreated'] as $orderBy) {
        expect(ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, orderBy: \"$orderBy\") { id } }", true)['errors'] ?? [], $orderBy)->toBe([]);
    }
});

it('keeps what a comment’s relation fields hold from GraphQL on a schema that can’t moderate, from either side (M1)', function() {
    $related = ffField(new craft\fields\Entries(['name' => 'Order', 'sources' => '*']));
    ffModes([$related->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields reverse relations');
    $order = CommentsFixtureFactory::entry('Form fields private order');
    $comment = ffCreate($entry, 'Related', ffAdmin());
    $comment->setFieldValue($related->getField()->handle, [$order->id]);
    Craft::$app->getElements()->saveElement($comment, false);
    $sections = 'sections.' . $order->getSection()->uid . ':read';
    $refusal = 'Elements can’t be related to comments here.';
    ffAs(null);

    $notRelated = ffGql(['comments:read'], "{ comments(ownerId: {$entry->id}, notRelatedTo: [{$order->id}]) { id } }", true);
    $entries = ffGql(['comments:read', $sections], "{ entries(relatedTo: [{$comment->id}]) { id } }", true);
    $entriesFrom = ffGql(['comments:read', $sections], "{ entries(relatedTo: [\"and\", {$comment->id}]) { id } }", true);
    $entriesNot = ffGql(['comments:read', $sections], "{ entries(notRelatedTo: [{$comment->id}]) { id } }", true);
    $users = ffGql(['comments:read', 'usergroups.everyone:read'], "{ users(relatedTo: [{$comment->id}]) { id } }", true);

    expect($notRelated['errors'][0]['message'] ?? null)->toBe('Comments can’t be filtered by related elements here.')
        ->and($entries['errors'][0]['message'] ?? null)->toBe($refusal)
        ->and($entriesFrom['errors'][0]['message'] ?? null)->toBe($refusal)
        ->and($entriesNot['errors'][0]['message'] ?? null)->toBe($refusal)
        ->and($users['errors'][0]['message'] ?? null)->toBe($refusal);

    // A schema that can moderate, for someone who can, still can.
    ffAs(ffAdmin());
    $moderating = ffGql(['comments:read', 'comments:moderate', $sections], "{ entries(relatedTo: [{$comment->id}]) { id } }");

    expect($moderating['errors'] ?? [])->toBe([])
        ->and(array_values(array_unique(array_column($moderating['data']['entries'], 'id'))))->toBe([(string)$order->id]);
});

it('clears Craft’s GraphQL cache when the fields that are shown change outside project config (L3)', function() {
    $location = ffField(new PlainText(['name' => 'Location']));
    $handle = $location->getField()->handle;
    ffModes([$handle => 'collect-and-show']);
    $entry = CommentsFixtureFactory::entry('Form fields GraphQL cache');
    ffCreate($entry, 'Cached', ffAdmin(), [$handle => 'CACHED-ROTORUA']);
    $query = "{ comments(ownerId: {$entry->id}) { ... on Comment { $handle } } }";
    $general = Craft::$app->getConfig()->getGeneral();
    $caching = $general->enableGraphqlCaching;
    $general->enableGraphqlCaching = true;
    ffAs(null);

    try {
        $first = ffGql(['comments:read'], $query, true);
        // As `config/comments.php` would, without touching project config.
        Comments::$plugin->getSettings()->formFields = [$location->uid => ['mode' => 'collect', 'placement' => 'details', 'replies' => false]];
        $second = ffGql(['comments:read'], $query, true);
    } finally {
        $general->enableGraphqlCaching = $caching;
    }

    expect($first['data']['comments'][0][$handle] ?? null)->toBe('CACHED-ROTORUA')
        ->and($second['data']['comments'][0] ?? null)->toBe([$handle => null]);
});

it('shows only web addresses from any type, and skips a type that fails (L4)', function() {
    Event::on(FormFields::class, FormFields::EVENT_REGISTER_FORM_FIELD_TYPES, function(RegisterFormFieldTypesEvent $event) {
        $event->types[Color::class] = new class () extends FormFieldType {
            public function display(FieldInterface $field, mixed $value): ?array
            {
                return ['value' => [['url' => 'javascript:alert(1)', 'filename' => 'x'], ['url' => 'https://example.test/ok.png', 'href' => 'data:text/html,x']], 'text' => 'Files'];
            }
        };
        $event->types[craft\fields\Time::class] = new class () extends FormFieldType {
            public function display(FieldInterface $field, mixed $value): ?array
            {
                throw new RuntimeException('A broken third-party type.');
            }
        };
    });
    Comments::$plugin->set('formFields', FormFields::class);
    $colour = ffField(new Color(['name' => 'Colour', 'allowCustomColors' => true]));
    $time = ffField(new craft\fields\Time(['name' => 'Time']));
    ffModes([$colour->getField()->handle => 'collect-and-show', $time->getField()->handle => 'collect-and-show']);
    $entry = CommentsFixtureFactory::entry('Form fields hostile types');
    $comment = ffCreate($entry, 'Hostile', ffAdmin());
    $comment->setFieldValue($colour->getField()->handle, '#112233');
    Craft::$app->getElements()->saveElement($comment, false);
    ffApi()->invalidateCaches();

    $fields = ffApi()->thread($entry)['comments'][0]['fields'];

    expect(array_column($fields, 'handle'))->toBe([$colour->getField()->handle])
        ->and($fields[0]['value'])->toBe([['url' => null, 'filename' => 'x'], ['url' => 'https://example.test/ok.png', 'href' => null]]);
});

it('holds the site’s own form to the same fields and replies rule (Twig form)', function() {
    $order = ffField(new PlainText(['name' => 'Order number']));
    $members = ffField(new PlainText(['name' => 'Membership']), [
        'userCondition' => [
            'class' => UserCondition::class,
            'conditionRules' => [['class' => craft\elements\conditions\users\AdminConditionRule::class, 'value' => true]],
        ],
    ]);
    ffModes([$order->getField()->handle => 'collect', $members->getField()->handle => 'collect:replies']);
    $entry = CommentsFixtureFactory::entry('Form fields Twig form');
    $root = ffCreate($entry, 'Root', ffAdmin());
    $request = Craft::$app->getRequest();
    $_SERVER['REQUEST_METHOD'] = 'POST';

    try {
        // A reply isn't offered a field that's not on replies, and a guest isn't offered one with a user
        // condition, so what's sent for them is left out.
        ffAs(null);
        $request->setBodyParams([
            'elementId' => $entry->id,
            'siteId' => $entry->siteId,
            'parentId' => $root->id,
            'fields' => ['comment' => 'Twig reply', 'name' => 'Guest', 'email' => 'twig-guest@example.test', $order->getField()->handle => 'ORD-TWIG', $members->getField()->handle => 'M-TWIG'],
        ]);
        $reply = Comments::$plugin->getComments()->createFromPost()->comment;

        expect($reply->getFieldValue($order->getField()->handle))->toBeNull()
            ->and($reply->getFieldValue($members->getField()->handle))->toBeNull()
            ->and($reply->getPostedFieldUids())->toBe([]);
    } finally {
        $request->setBodyParams([]);
    }
});

it('offers guests Assets fields only with Guests Can Upload Files on (M3)', function() {
    $photos = ffField(new Assets(['name' => 'Photos', 'sources' => '*']));
    $note = ffField(new PlainText(['name' => 'Note']));
    ffModes([$photos->getField()->handle => 'collect', $note->getField()->handle => 'collect']);
    $entry = CommentsFixtureFactory::entry('Form fields guest uploads setting');
    ffAs(null);

    Comments::$plugin->getSettings()->allowGuestFieldUploads = false;
    $off = array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'handle');
    Comments::$plugin->getSettings()->allowGuestFieldUploads = true;
    $on = array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'handle');
    ffAs(CommentsFixtureFactory::user('ffMemberUploads'));
    Comments::$plugin->getSettings()->allowGuestFieldUploads = false;
    $member = array_column(ffApi()->viewer($entry)['editor']['fields']['comment'], 'handle');

    expect($off)->toBe([$note->getField()->handle])
        ->and($on)->toBe([$photos->getField()->handle, $note->getField()->handle])
        ->and($member)->toBe([$photos->getField()->handle, $note->getField()->handle]);
});

it('warns when collected files are stored where anyone can reach them (M4)', function() {
    $handle = 'ffPublicFiles' . bin2hex(random_bytes(3));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => 'https://uploads.example.test/' . $handle . '/']);
    Craft::$app->getFs()->saveFilesystem($filesystem);
    $volume = new craft\models\Volume(['name' => 'Public files', 'handle' => $handle, 'fsHandle' => $handle]);
    Craft::$app->getVolumes()->saveVolume($volume);

    try {
        $photos = ffField(new Assets(['name' => 'Receipts', 'sources' => '*', 'defaultUploadLocationSource' => 'volume:' . $volume->uid]));
        ffModes([$photos->getField()->handle => 'collect']);
        $row = array_column(ffFormFields()->settingsRows(), null, 'uid')[$photos->uid];

        expect(implode(' ', $row['notes']))->toContain('public URLs')->toContain('Public files');
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('refuses field uploads while Craft’s temporary upload filesystem has public URLs', function() {
    $photos = ffField(new Assets(['name' => 'Snapshots', 'restrictFiles' => true, 'allowedKinds' => ['image']]));
    ffModes([$photos->getField()->handle => 'collect']);
    $member = CommentsFixtureFactory::user('ffPublicTemp');
    ffAs($member);
    $handle = 'ffPublicTemp' . bin2hex(random_bytes(3));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => 'https://uploads.example.test/' . $handle . '/']);
    Craft::$app->getFs()->saveFilesystem($filesystem);
    $general = Craft::$app->getConfig()->getGeneral();
    $original = $general->tempAssetUploadFs;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $tempPath = tempnam(sys_get_temp_dir(), 'ff-public-temp-');
    file_put_contents($tempPath, $png);
    $file = new yii\web\UploadedFile(['name' => 'photo.png', 'tempName' => $tempPath, 'type' => 'image/png', 'size' => strlen($png), 'error' => UPLOAD_ERR_OK]);

    try {
        $general->tempAssetUploadFs = $handle;
        $error = ffError(fn() => ffFormFields()->upload($file, $photos->getField()->handle, [$photos], $member, static fn() => null));

        expect($error->errorCode)->toBe('invalid_request')
            ->and($error->getMessage())->toBe('Uploads are turned off on this site, because files waiting to be saved would be public.')
            ->and(is_file($tempPath))->toBeTrue();
    } finally {
        $general->tempAssetUploadFs = $original;
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
        @unlink($tempPath);
        ffAs(null);
    }
});

it('refuses the site’s actions on the control panel’s URL, and trusts only someone using the control panel (H1)', function() {
    $request = Craft::$app->getRequest();
    $member = CommentsFixtureFactory::user('ffCpUrlMember');
    $request->setIsConsoleRequest(false);
    $request->setIsCpRequest(true);

    try {
        ffAs($member);
        $memberIsCp = Comment::isControlPanelUser();
        ffAs(ffAdmin());
        $adminIsCp = Comment::isControlPanelUser();
        ffAs($member);
        $api = new verbb\comments\controllers\ApiController('api', Comments::$plugin);
        $api->runAction('create');
        $apiStatus = $api->response->getStatusCode();
        $apiCode = $api->response->data['errors'][0]['code'] ?? null;
        $refusals = [];

        foreach ([verbb\comments\controllers\CommentsController::class => 'save'] as $class => $action) {
            try {
                (new $class('comments', Comments::$plugin))->runAction($action);
                $refusals[$class] = null;
            } catch (yii\web\BadRequestHttpException $e) {
                $refusals[$class] = $e::class;
            }
        }
    } finally {
        $request->setIsCpRequest(false);
        $request->setIsConsoleRequest(true);
        Craft::$app->getResponse()->clear();
    }

    expect($memberIsCp)->toBeFalse()
        ->and($adminIsCp)->toBeTrue()
        ->and($apiStatus)->toBe(404)
        ->and($apiCode)->toBe('not_found')
        ->and(array_values($refusals))->toBe([yii\web\BadRequestHttpException::class]);
});
