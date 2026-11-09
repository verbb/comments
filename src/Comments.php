<?php
namespace verbb\comments;

use verbb\comments\base\PluginTrait;
use verbb\comments\elements\Comment;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldLayoutElement;
use verbb\comments\fields\CommentsField;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\mutations\Comment as CommentMutations;
use verbb\comments\gql\queries\CommentQuery;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\helpers\ProjectConfigData;
use verbb\comments\integrations\CommentFeedMeElement;
use verbb\comments\models\Settings;
use verbb\comments\services\Comments as CommentsService;
use verbb\comments\variables\CommentsVariable;
use verbb\comments\widgets\Comments as CommentsWidget;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\db\Table;
use craft\events\CreateFieldLayoutFormEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
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
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use yii\base\Event;

use craft\feedme\events\RegisterFeedMeElementsEvent;
use craft\feedme\services\Elements as FeedMeElements;

class Comments extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;


    // Properties
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    public string $schemaVersion = '4.0.4';
    public string $minVersionRequired = '1.9.2';


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_bootstrapEditor();

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
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->_registerResaveCommand();
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
                'comments/settings' => 'comments/settings/index',
                'comments/settings/<pane:{slug}>' => 'comments/settings/index',
            ]);
        });
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            // Query-param actions align with the browser apiBase helper (e.g. conversation?ownerId=).
            $event->rules = array_merge($event->rules, [
                'comments/api/v1/conversation' => 'comments/frontend-api/conversation',
                'comments/api/v1/roots' => 'comments/frontend-api/roots',
                'comments/api/v1/replies' => 'comments/frontend-api/replies',
                'comments/api/v1/viewer-controls' => 'comments/frontend-api/viewer-controls',
                'comments/api/v1/protection' => 'comments/frontend-api/protection',
                'comments/api/v1/composer' => 'comments/frontend-api/composer',
                'comments/api/v1/create' => 'comments/frontend-api/create',
                'comments/api/v1/remove' => 'comments/frontend-api/remove',
                'comments/api/v1/interaction' => 'comments/frontend-api/interaction',
                'comments/api/v1/moderate' => 'comments/frontend-api/moderate',
                'comments/api/v1/report' => 'comments/frontend-api/report',
                'comments/api/v1/subscription' => 'comments/frontend-api/subscription',
                'comments/api/v1/thread' => 'comments/frontend-api/thread',
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
                ],
            ];
        });
    }

    private function _registerEmailMessages(): void
    {
        Event::on(SystemMessages::class, SystemMessages::EVENT_REGISTER_MESSAGES, function(RegisterEmailMessagesEvent $event) {

            $event->messages[] = [
                'key' => 'comments_reply_notification',
                'heading' => Craft::t('comments', 'comments_reply_notification_heading'),
                'subject' => Craft::t('comments', 'comments_reply_notification_subject'),
                'body' => Craft::t('comments', 'comments_reply_notification_body'),
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
        Event::on(Search::class, Search::EVENT_BEFORE_SCORE_RESULTS, function(SearchEvent $event) {
            if ($event->elementQuery->elementType !== Comment::class || $event->results === null || CommentsHelper::canViewAddresses()) {
                return;
            }

            $event->results = array_values(array_filter(
                $event->results,
                static fn(array $row): bool => ($row['attribute'] ?? null) !== Comment::SEARCH_ATTRIBUTE_EMAIL,
            ));
        });

        // Fires once trashed elements are purged, so replies left without a parent are put back right away.
        Event::on(Gc::class, Gc::EVENT_RUN, function() {
            // Garbage collection can run while the upgrade from Comments 3 is still pending.
            if (Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'parentId')) {
                Comments::$plugin->getConversations()->repairDetachedReplies();
            }
        });
    }

    private function _registerGraphQl(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = CommentInterface::class;
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event) {
            $queries = CommentQuery::getQueries();

            foreach ($queries as $key => $value) {
                $event->queries[$key] = $value;
            }
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_MUTATIONS, function(RegisterGqlMutationsEvent $event) {
            $event->mutations = array_merge(
                $event->mutations,
                CommentMutations::getMutations()
            );
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $label = Craft::t('comments', 'Comments');

            $event->queries[$label] = [
                'comments:read' => ['label' => Craft::t('comments', 'View comments')],
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
