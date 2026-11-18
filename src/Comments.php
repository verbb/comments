<?php
namespace verbb\comments;

use verbb\comments\base\PluginTrait;
use verbb\comments\elements\Comment;
use verbb\comments\elements\db\CommentQuery as CommentElementQuery;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldLayoutElement;
use verbb\comments\fields\CommentsField;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\mutations\Comment as CommentMutations;
use verbb\comments\gql\queries\ApiQuery;
use verbb\comments\gql\queries\CommentQuery;
use verbb\comments\gql\types\ApiDataType;
use verbb\comments\helpers\Gql as CommentsGql;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\helpers\ProjectConfigData;
use verbb\comments\integrations\CommentFeedMeElement;
use verbb\comments\models\Settings;
use verbb\comments\services\Comments as CommentsService;
use verbb\comments\services\MemberTokens;
use verbb\comments\variables\CommentsVariable;
use verbb\comments\widgets\Comments as CommentsWidget;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\events\CancelableEvent;
use craft\events\CreateFieldLayoutFormEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineElementHtmlEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\ElementEvent;
use craft\events\ExecuteGqlQueryEvent;
use craft\events\PluginEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterGqlMutationsEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\SearchEvent;
use craft\events\UserEvent;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\Plugins;
use craft\services\ProjectConfig;
use craft\services\Search;
use craft\services\SystemMessages;
use craft\services\UserPermissions;
use craft\services\Users;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use yii\base\Event;

use GraphQL\Error\UserError;
use Throwable;

use craft\feedme\events\RegisterFeedMeElementsEvent;
use craft\feedme\services\Elements as FeedMeElements;

class Comments extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;


    // Static Methods
    // =========================================================================

    /**
     * The element IDs a `relatedTo` or `notRelatedTo` criteria names, however it's written: IDs, elements,
     * lists with `and` or `or`, or `element`, `sourceElement` and `targetElement` keys.
     */
    private static function _relationIds(mixed $criteria): array
    {
        if ($criteria instanceof ElementInterface) {
            return $criteria->id ? [(int)$criteria->id] : [];
        }

        if (is_int($criteria) || (is_string($criteria) && ctype_digit($criteria))) {
            return [(int)$criteria];
        }

        if (is_string($criteria) && str_contains($criteria, ',')) {
            return self::_relationIds(explode(',', $criteria));
        }

        if (!is_iterable($criteria)) {
            return [];
        }

        $ids = [];

        foreach ($criteria as $key => $value) {
            if (is_string($key) && !in_array($key, ['element', 'sourceElement', 'targetElement'], true)) {
                continue;
            }

            array_push($ids, ...self::_relationIds($value));
        }

        return $ids;
    }


    // Properties
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    public string $schemaVersion = '4.0.11';
    public string $minVersionRequired = '1.9.2';

    // How many GraphQL requests are running, for the guard on relations to comments.
    private int $_gqlRequests = 0;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_bootstrapEditor();

        // Comments 3's Twig functions and console commands, when `compatibilityMode` is on.
        $this->getCompatibility()->bootstrap();

        $this->_registerEmailMessages();
        $this->_registerVariables();
        $this->_registerFieldTypes();
        $this->_registerElementTypes();
        $this->_registerGraphQl();
        $this->_registerEventHandlers();
        $this->_registerProjectConfigEventHandlers();
        $this->_registerFeedMeSupport();
        $this->_registerSiteRoutes();

        // Registered unconditionally: Craft hides the permission UI on editions without user
        // groups, and unregistered permissions get stripped when group/user permissions save.
        $this->_registerPermissions();

        $this->_registerFieldLayoutListener();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerWidgets();
            $this->_registerIndexChips();
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->_registerResaveCommand();
        }

        // The Spam settings come from this installation's database, once the migration that made it has run.
        if ($this->isInstalled && !Craft::$app->getPlugins()->isPluginUpdatePending($this)) {
            $this->getSpamSettings()->hydrate($this->getSettings());
        }
    }

    public function getPluginName(): string
    {
        return Craft::t('comments', $this->getSettings()->pluginName);
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('comments/settings'));
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();

        $nav['label'] = $this->getPluginName();

        $user = Craft::$app->getUser();
        $isAdmin = $user->getIsAdmin();

        // Each item mirrors the permission its controller enforces, so the nav can never
        // link a user to a screen that will 403 on them.
        if ($isAdmin || $user->checkPermission('comments-view')) {
            // `comments` is the same screen as the top-level nav item, so it comes first.
            $nav['subnav']['comments'] = [
                'label' => Craft::t('comments', 'Comments'),
                'url' => 'comments',
            ];

            // What waits for a moderator: held comments plus reported ones still on the site.
            if ($isAdmin || $user->checkPermission('comments-moderate')) {
                $counts = $this->getModeration()->getQueueCounts();
                $waiting = $counts['pending'] + $counts['reported'];

                if ($waiting) {
                    $nav['badgeCount'] = $waiting;
                }
            }
        }

        if ($isAdmin || $user->checkPermission('comments-moderate')) {
            $nav['subnav']['commenters'] = [
                'label' => Craft::t('comments', 'Commenters'),
                'url' => 'comments/commenters',
            ];
        }

        // The Spam settings are kept in the database, so admins can change them on any environment, and
        // so can anyone with **Manage spam settings**.
        if ($this->getSpamSettings()->canManage()) {
            $nav['subnav']['spamProtection'] = [
                'label' => Craft::t('comments', 'Spam Protection'),
                'url' => 'comments/spam-protection',
            ];
        }

        // Settings write to project config, so they need admin plus `allowAdminChanges`.
        if ($isAdmin && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('comments', 'Settings'),
                'url' => 'comments/settings',
            ];
        }

        // No reachable screen means no section.
        if (!$nav['subnav']) {
            return null;
        }

        // A single item is just the section itself; the subnav would be noise.
        if (count($nav['subnav']) === 1) {
            $nav['url'] = reset($nav['subnav'])['url'];
            unset($nav['subnav']);
        }

        return $nav;
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _bootstrapEditor(): void
    {
        \verbb\editor\Editor::bootstrap();
        $editor = \verbb\editor\Editor::getInstance();
        EditorContexts::register($editor->getEditorConfigs());
    }

    private function _registerIndexChips(): void
    {
        // Craft's element label measures its text on one line and adds a tooltip of the whole text when it overflows.
        // A comment's label in an index is two lines, the author over the excerpt, so every row got one. The label
        // skips that when it's `disabled`, which has no other effect on it.
        Event::on(Cp::class, Cp::EVENT_DEFINE_ELEMENT_CHIP_HTML, function(DefineElementHtmlEvent $event) {
            if ($event->element instanceof Comment && in_array($event->context, ['index', 'embedded-index'], true)) {
                $event->html = preg_replace('/<craft-element-label(?=[\s>])/', '<craft-element-label disabled', $event->html, 1);
            }
        });
    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                // The Comments index and editor are Craft's own.
                'comments/edit/<elementId:\d+>' => 'elements/edit',
                'comments/<elementId:\d+>' => 'elements/edit',
                'comments/<elementId:\d+>/<siteHandle:{handle}>' => 'elements/edit',
                'comments/commenters' => 'comments/commenters/index',
                'comments/commenters/<identityId:\d+>' => 'comments/commenters/view',
                'comments/spam-protection' => 'comments/spam-protection/index',
                'comments/settings' => 'comments/settings/index',
                'comments/settings/<pane:{slug}>' => 'comments/settings/index',
            ]);
        });
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'comments/manage' => 'comments/subscriptions/manage',
            ]);
        });
    }

    private function _registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            // `comments-view` gates the CP section itself, so the capabilities that only make
            // sense once you can see comments are nested beneath it.
            $event->permissions[] = [
                'heading' => Craft::t('comments', 'Comments'),
                'permissions' => [
                    'comments-view' => [
                        'label' => Craft::t('comments', 'View comments'),
                        'nested' => [
                            'comments-moderate' => ['label' => Craft::t('comments', 'Moderate comments')],
                            'comments-edit' => ['label' => Craft::t('comments', 'Edit other users’ comments')],
                            'comments-trash' => ['label' => Craft::t('comments', 'Trash other users’ comments')],
                            'comments-delete' => ['label' => Craft::t('comments', 'Delete comments')],
                        ],
                    ],
                    // The Spam settings are kept in the database, so this works where admin changes are off.
                    'comments-manageSpamSettings' => [
                        'label' => Craft::t('comments', 'Manage spam settings'),
                    ],
                ],
            ];
        });
    }

    private function _registerEmailMessages(): void
    {
        Event::on(SystemMessages::class, SystemMessages::EVENT_REGISTER_MESSAGES, function(RegisterEmailMessagesEvent $event) {

            $event->messages[] = [
                'key' => 'comments_author_notification',
                'heading' => Craft::t('comments', 'comments_author_notification_heading'),
                'subject' => Craft::t('comments', 'comments_author_notification_subject'),
                'body' => Craft::t('comments', 'comments_author_notification_body'),
            ];

            $event->messages[] = [
                'key' => 'comments_reply_notification',
                'heading' => Craft::t('comments', 'comments_reply_notification_heading'),
                'subject' => Craft::t('comments', 'comments_reply_notification_subject'),
                'body' => Craft::t('comments', 'comments_reply_notification_body'),
            ];

            $event->messages[] = [
                'key' => 'comments_mention_notification',
                'heading' => Craft::t('comments', 'comments_mention_notification_heading'),
                'subject' => Craft::t('comments', 'comments_mention_notification_subject'),
                'body' => Craft::t('comments', 'comments_mention_notification_body'),
            ];

            $event->messages[] = [
                'key' => 'comments_subscriber_notification_element',
                'heading' => Craft::t('comments', 'comments_subscriber_notification_element_heading'),
                'subject' => Craft::t('comments', 'comments_subscriber_notification_element_subject'),
                'body' => Craft::t('comments', 'comments_subscriber_notification_element_body'),
            ];

            $event->messages[] = [
                'key' => 'comments_subscriber_notification_comment',
                'heading' => Craft::t('comments', 'comments_subscriber_notification_comment_heading'),
                'subject' => Craft::t('comments', 'comments_subscriber_notification_comment_subject'),
                'body' => Craft::t('comments', 'comments_subscriber_notification_comment_body'),
            ];

            $event->messages[] = [
                'key' => 'comments_moderator_approved_notification',
                'heading' => Craft::t('comments', 'comments_moderator_approved_notification_heading'),
                'subject' => Craft::t('comments', 'comments_moderator_approved_notification_subject'),
                'body' => Craft::t('comments', 'comments_moderator_approved_notification_body'),
            ];

            $event->messages[] = [
                'key' => 'comments_guest_confirmation',
                'heading' => Craft::t('comments', 'comments_guest_confirmation_heading'),
                'subject' => Craft::t('comments', 'comments_guest_confirmation_subject'),
                'body' => Craft::t('comments', 'comments_guest_confirmation_body'),
            ];

            // Comments 3's keys, so customised wording carries over.
            foreach (['comments_moderator_notification', 'comments_moderator_edit_notification', 'comments_admin_notification', 'comments_flag_notification'] as $key) {
                $event->messages[] = [
                    'key' => $key,
                    'heading' => Craft::t('comments', $key . '_heading'),
                    'subject' => Craft::t('comments', $key . '_subject'),
                    'body' => Craft::t('comments', $key . '_body'),
                ];
            }

        });
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('comments', CommentsVariable::class);
        });
    }

    private function _registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CommentsField::class;
        });
    }

    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Comment::class;
        });
    }

    private function _registerEventHandlers(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(Plugins::class, Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS, function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    Comments::$plugin->getComments()->saveFieldLayout();
                    EditorContexts::refresh();
                }
            });
        }

        Event::on(FieldLayout::class, FieldLayout::EVENT_CREATE_FORM, function(CreateFieldLayoutFormEvent $event) {
            if ($event->element instanceof Comment) {
                Comments::$plugin->getCommentEditor()->addTabs($event);
            }
        });

        // D1: CommentQuery keeps a match on an address out of the results for anyone who can't see
        // addresses. Leave it out of the ranking too, so an address can't lift a comment up the list.
        // Custom fields are left out of the ranking in the same way for a GraphQL schema that can't read them all.
        Event::on(Search::class, Search::EVENT_BEFORE_SCORE_RESULTS, function(SearchEvent $event) {
            if ($event->elementQuery->elementType !== Comment::class || $event->results === null) {
                return;
            }

            $hideAddresses = !CommentsHelper::canViewAddresses();
            $privateFieldIds = [];

            if ($event->elementQuery instanceof CommentElementQuery && !$event->elementQuery->withPrivateFields) {
                foreach (Craft::$app->getFields()->getLayoutByType(Comment::class)->getCustomFields() as $field) {
                    $privateFieldIds[] = (int)$field->id;
                }
            }

            if (!$hideAddresses && !$privateFieldIds) {
                return;
            }

            $event->results = array_values(array_filter(
                $event->results,
                static fn(array $row): bool => !($hideAddresses && ($row['attribute'] ?? null) === Comment::SEARCH_ATTRIBUTE_EMAIL)
                    && !in_array((int)($row['fieldId'] ?? 0), $privateFieldIds, true),
            ));
        });

        // Craft clears its element caches as it saves, which can be before the save's transaction commits,
        // so a read in between would cache what was there before. The data API's are cleared again
        // once the change is committed, for comments and for the users whose names and photos they show.
        $invalidateApi = static function(ElementEvent $event): void {
            if ($event->element instanceof Comment || $event->element instanceof User) {
                Comments::$plugin->getApi()->invalidateCaches();
            }
        };

        foreach ([Elements::EVENT_AFTER_SAVE_ELEMENT, Elements::EVENT_AFTER_DELETE_ELEMENT, Elements::EVENT_AFTER_RESTORE_ELEMENT] as $eventName) {
            Event::on(Elements::class, $eventName, $invalidateApi);
        }

        // A deleted user's Gravatar copy goes with them, rather than waiting for its next check (SD-53).
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, function(ElementEvent $event) {
            if ($event->element instanceof User) {
                Comments::$plugin->getAvatars()->deleteForUser((int)$event->element->id);
            }
        });

        // Fires once trashed elements are purged, so replies left without a parent are put back right away.
        Event::on(Gc::class, Gc::EVENT_RUN, function() {
            // Garbage collection can run while the upgrade from Comments 3 is still pending.
            if (Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'parentId')) {
                Comments::$plugin->getConversations()->repairDetachedReplies();
            }

            if (Craft::$app->getDb()->tableExists(MemberTokens::TABLE)) {
                Comments::$plugin->getMemberTokens()->deleteExpired();
            }

            // Craft only removes temporary upload folders once they're empty, so uploads for custom
            // fields that were never posted are removed here. A failure mustn't stop the rest.
            try {
                Comments::$plugin->getFormFields()->deleteExpiredUploads();
            } catch (Throwable $e) {
                Comments::error('Unable to delete expired custom field uploads: ' . $e->getMessage());
            }

            // **Delete Spam After**. A batch per run, so a backlog clears over a few runs.
            try {
                Comments::$plugin->getModeration()->deleteExpiredSpam();
            } catch (Throwable $e) {
                Comments::error('Unable to delete expired spam: ' . $e->getMessage());
            }
        });

        // A suspended member is signed out of other sites at once. Each request also checks that its
        // member is still active and hasn't changed their password since the token was minted.
        Event::on(Users::class, Users::EVENT_AFTER_SUSPEND_USER, function(UserEvent $event) {
            if (Craft::$app->getDb()->tableExists(MemberTokens::TABLE)) {
                Comments::$plugin->getMemberTokens()->revokeForUser((int)$event->user->id);
            }
        });
    }

    private function _registerGraphQl(): void
    {
        // A GraphQL schema that can't moderate mustn't learn what a comment's relation fields hold from
        // the other side either, such as entries, assets or users related to a comment, or not related
        // to one. Any element query made while such a schema's request runs is refused when its
        // relations name a comment.
        Event::on(Gql::class, Gql::EVENT_BEFORE_EXECUTE_GQL_QUERY, function() {
            $this->_gqlRequests++;
        });

        Event::on(Gql::class, Gql::EVENT_AFTER_EXECUTE_GQL_QUERY, function() {
            $this->_gqlRequests = max(0, $this->_gqlRequests - 1);
        });

        Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE, function(CancelableEvent $event) {
            /** @var ElementQuery $query */
            $query = $event->sender;

            if ($this->_gqlRequests === 0 || ($query->relatedTo === null && $query->notRelatedTo === null) || $this->getFormFields()->canReadEveryField()) {
                return;
            }

            $ids = [...self::_relationIds($query->relatedTo), ...self::_relationIds($query->notRelatedTo)];

            if ($ids && (new Query())->from(Table::ELEMENTS)->where(['id' => $ids, 'type' => Comment::class])->exists()) {
                throw new UserError(Craft::t('comments', 'Elements can’t be related to comments here.'));
            }
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = CommentInterface::class;
            $event->types[] = ApiDataType::class;
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event) {
            foreach ([...CommentQuery::getQueries(), ...ApiQuery::getQueries()] as $key => $value) {
                $event->queries[$key] = $value;
            }
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_MUTATIONS, function(RegisterGqlMutationsEvent $event) {
            $event->mutations = array_merge(
                $event->mutations,
                CommentMutations::getMutations()
            );
        });

        // A `viewer` field answers for whoever's asking, which Craft's result cache doesn't vary by,
        // and votes don't clear it. A query asking for one is neither served from the cache nor saved
        // to it, so one person's permissions or votes never reach another. The data API's queries and
        // `commentsData` skip it too: `commentsViewer` and `commentsData` are personal, and the public
        // queries have their own cache, which votes do clear.
        Event::on(Gql::class, Gql::EVENT_BEFORE_EXECUTE_GQL_QUERY, function(ExecuteGqlQueryEvent $event) {
            $generalConfig = Craft::$app->getConfig()->getGeneral();

            // Craft's GraphQL cache moves on with project config, but not with `config/comments.php`, so a
            // change there to which custom fields are shown clears it, before anything cached is used.
            $hash = $this->getFormFields()->settingsHash();
            $cache = Craft::$app->getCache();

            if ($cache->get('comments:form-fields:hash') !== $hash) {
                Craft::$app->getGql()->invalidateCaches();
                $this->getApi()->invalidateCaches();
                $cache->set('comments:form-fields:hash', $hash);
            }

            if (!$generalConfig->enableGraphqlCaching || !preg_match('/\b(viewer|commentsThread|commentsReplies|commentsComment|commentsViewer|commentsData)\b/', (string)$event->query)) {
                return;
            }

            $generalConfig->enableGraphqlCaching = false;

            $restore = static function() use (&$restore, $generalConfig): void {
                $generalConfig->enableGraphqlCaching = true;
                Event::off(Gql::class, Gql::EVENT_AFTER_EXECUTE_GQL_QUERY, $restore);
            };

            Event::on(Gql::class, Gql::EVENT_AFTER_EXECUTE_GQL_QUERY, $restore);
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $label = Craft::t('comments', 'Comments');

            $event->queries[$label] = [
                'comments:read' => [
                    'label' => Craft::t('comments', 'View comments'),
                    'nested' => [
                        CommentsGql::EMAILS_SCOPE => ['label' => Craft::t('comments', 'View commenters’ email addresses (private schemas only)')],
                    ],
                ],
            ];

            $event->mutations[$label] = [
                'comments:edit' => ['label' => Craft::t('comments', 'Create comments')],
                'comments:save' => ['label' => Craft::t('comments', 'Save comments')],
                'comments:delete' => ['label' => Craft::t('comments', 'Delete comments')],
                'comments:moderate' => ['label' => Craft::t('comments', 'Moderate comments')],
            ];
        });
    }

    private function _registerProjectConfigEventHandlers(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();
        $service = $this->getComments();

        $projectConfigService->onAdd(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleChangedFieldLayout'])
            ->onUpdate(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleChangedFieldLayout'])
            ->onRemove(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleDeletedFieldLayout']);

        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD, [$service, 'pruneDeletedField']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['comments'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    private function _registerFieldLayoutListener(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, function(DefineFieldLayoutFieldsEvent $event) {
            $fieldLayout = $event->sender;

            if ($fieldLayout->type == Comment::class) {
                $event->fields[] = CommentsFieldLayoutElement::class;
            }
        });
    }

    private function _registerFeedMeSupport(): void
    {
        if (class_exists(FeedMeElements::class)) {
            Event::on(FeedMeElements::class, FeedMeElements::EVENT_REGISTER_FEED_ME_ELEMENTS, function(RegisterFeedMeElementsEvent $event) {
                $event->elements[] = CommentFeedMeElement::class;
            });
        }
    }

    private function _registerWidgets(): void
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CommentsWidget::class;
        });
    }

    private function _registerResaveCommand(): void
    {
        if (!Craft::$app instanceof ConsoleApplication) {
            return;
        }

        Event::on(ResaveController::class, ConsoleController::EVENT_DEFINE_ACTIONS, function(DefineConsoleActionsEvent $event) {
            $event->actions['comments-comments'] = [
                'action' => function(): int {
                    $controller = Craft::$app->controller;

                    return $controller->resaveElements(Comment::class);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Comments comments.',
            ];
        });
    }
}
